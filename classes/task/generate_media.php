<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_aibranchedscenario\task;

use context_module;
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Generates the images and narration for a scenario that already has its text.
 *
 * Generation produces both in one task, but a scenario can also arrive through the
 * import box, drafted in another assistant and pasted in. Those definitions carry an
 * image brief for every node and used to be shown with no pictures at all, because the
 * media loop only ever ran inside the generation task.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_media extends \core\task\adhoc_task {
    /**
     * Generate the media for the working copy.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $cmid = (int)($data->cmid ?? 0);
        if (!$cmid) {
            return;
        }

        try {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'aibranchedscenario');
            $context = context_module::instance($cm->id);
        } catch (\moodle_exception $e) {
            mtrace('Course module ' . $cmid . ' is no longer available; media skipped.');
            return;
        }

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$scenario) {
            return;
        }

        $definition = scenario_manager::get_working_definition($scenario);
        if (!is_array($definition)) {
            mtrace('Activity ' . $scenario->id . ' has no working copy; media skipped.');
            return;
        }

        // A job record, which this task never wrote.
        //
        // The generation route records every run as a job, and the whole reporting chain
        // hangs off that: the wizard polls it, get_job_status turns a short count into
        // "3 of 14 illustrations", and a failure is stored where a site owner can read it.
        // Media on the IMPORT route runs here instead - and here wrote nothing. So a paste
        // route scenario that came back with no pictures and no voice produced no job, no
        // count, no error, and nothing in any log at the site's normal debug level. There
        // was no way to answer "why" except by guessing, which is what happened.
        $job = $DB->insert_record('aibranchedscenario_jobs', (object)[
            'scenarioid'   => (int)$scenario->id,
            'userid'       => (int)$this->get_userid(),
            'status'       => generator::JOB_RUNNING,
            'jobtype'      => 'media',
            'requestjson'  => json_encode(['nodes' => count($definition['nodes'] ?? [])]),
            'modelused'    => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $generator = new generator();
        $media = new media_manager($context);
        $counts = $media->generate_for_definition($generator->get_provider(), $scenario, $definition);
        $failures = $media->failures();

        mtrace(
            'Activity ' . $scenario->id . ' media: '
                . $counts['images'] . ' of ' . $counts['imageswanted'] . ' images, '
                . $counts['narrations'] . ' of ' . $counts['narrationswanted'] . ' narrations.'
                . ($failures ? ' Refused: ' . implode(', ', $failures) . '.' : '')
        );

        // A run that made SOME of what was asked for used to be recorded as a success.
        //
        // Only a run that made nothing at all counted as a failure, so a scenario that
        // wanted forty clips and twenty pictures and produced twelve of them was filed as
        // "ready" - and the teacher was told nothing, because from the plugin's point of
        // view nothing had gone wrong. What they got instead was a scenario where some
        // slides spoke and some did not, some had pictures and some did not, with no
        // pattern to it and nothing anywhere saying why. Every report of "no voiceover on
        // this slide" is that, and it looked like a player fault for days because the only
        // record of it said the media was fine.
        //
        // Made everything: ready. Made nothing: failed. Made some of it: also failed - it
        // is not ready, and calling it ready is the part that hid it.
        $wanted = (int)$counts['imageswanted'] + (int)$counts['narrationswanted'];
        $made = (int)$counts['images'] + (int)$counts['narrations'];
        $short = $wanted - $made;
        $note = $failures ? implode(', ', array_slice($failures, 0, 6)) : null;
        if ($short > 0) {
            // The count goes in the message as well as the reasons, because "insufficient
            // credits" on its own does not say how much of the scenario is missing.
            $note = get_string(
                'media:incomplete',
                'mod_aibranchedscenario',
                (object)[
                    'made'   => $made,
                    'wanted' => $wanted,
                    'why'    => $note ?: get_string('media:nowhy', 'mod_aibranchedscenario'),
                ]
            );
        }
        $DB->update_record('aibranchedscenario_jobs', (object)[
            'id'           => $job,
            'status'       => ($wanted > 0 && $short > 0) ? generator::JOB_ERROR : generator::JOB_READY,
            'resultjson'   => json_encode(['media' => $counts]),
            'errormsg'     => $note,
            'timemodified' => time(),
        ]);

        // Media made after publishing used to be lost, permanently.
        //
        // The two routes into a scenario behave differently and only one of them was
        // safe. Generation runs its media inside the same task, so by the time the
        // teacher sees a result the pictures and the narration already exist and
        // publishing copies them across. Import cannot: it returns the moment the
        // definition is validated and leaves the media to this task, which waits for
        // cron. A teacher who pastes a scenario in and publishes it - which is the
        // obvious thing to do, since the scenario is right there and looks finished -
        // publishes before this has run.
        //
        // publish_media() is the only thing that ever copies the working media into a
        // revision, and it runs at publish time. So this task would finish minutes
        // later, write its work into the working area, and no learner would ever see
        // any of it: a scenario imported from the pasted prompt had no pictures and no
        // voice, and nothing about it looked broken enough to explain why.
        //
        // Whichever of the two finishes last now does the copying, so the order stops
        // mattering. The guard is that the published revision has to be the same
        // definition this media was made for - otherwise a draft that has moved on
        // would put its pictures onto the revision learners are still playing.
        $revision = scenario_manager::get_current_revision($scenario);
        if (!$revision) {
            return;
        }
        if (!self::same_scenes($revision->scenariojson, $definition)) {
            mtrace('Activity ' . $scenario->id . ' has published a different definition; media held.');
            return;
        }
        $media->publish_media((int)$revision->revision);
        mtrace('Activity ' . $scenario->id . ' media copied into revision ' . $revision->revision . '.');
    }

    /**
     * Does the published revision hold the same scenes this media was made for?
     *
     * The test is the node ids, in order, and not the whole definition. Every media file
     * is stored under its node's id at that node's position, so the node list is exactly
     * what decides whether a picture still belongs to the screen it was drawn for.
     *
     * Comparing the definitions in full would be both stricter and less accurate: the
     * working copy is stored as it arrived and the revision is stored after the validator
     * has normalised it, so the two differ on a freshly published scenario that has not
     * been touched at all - which would hold the media every time and fix nothing. It
     * would also hold the media because a teacher corrected a typo, which changes no
     * scene and no picture.
     *
     * @param string $publishedjson The revision's stored definition.
     * @param array $working The definition the media was generated for.
     * @return bool
     */
    protected static function same_scenes(string $publishedjson, array $working): bool {
        $published = json_decode($publishedjson, true);
        if (!is_array($published)) {
            return false;
        }
        $ids = static function ($definition) {
            $out = [];
            foreach ((array)($definition['nodes'] ?? []) as $node) {
                $out[] = (string)($node['id'] ?? '');
            }
            return $out;
        };
        $publishedids = $ids($published);
        return $publishedids !== [] && $publishedids === $ids($working);
    }
}
