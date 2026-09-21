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
use core\task\adhoc_task;
use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Ad-hoc task that generates a scenario, then its optional media.
 *
 * Generation happens outside the web request so a slow provider cannot time out a
 * teacher's browser, and so a failure leaves a recoverable job record behind.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_scenario extends adhoc_task {
    /**
     * Run the generation job.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $jobid = (int)($data->jobid ?? 0);
        $cmid = (int)($data->cmid ?? 0);
        if (!$jobid || !$cmid) {
            mtrace('Missing job or course module id; nothing to do.');
            return;
        }

        $job = $DB->get_record('aibranchedscenario_jobs', ['id' => $jobid], '*', IGNORE_MISSING);
        if (!$job) {
            mtrace('Job ' . $jobid . ' no longer exists.');
            return;
        }
        if ($job->status === generator::JOB_READY) {
            mtrace('Job ' . $jobid . ' already completed.');
            return;
        }

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $job->scenarioid], '*', IGNORE_MISSING);
        if (!$scenario) {
            mtrace('Activity ' . $job->scenarioid . ' no longer exists.');
            return;
        }

        $generator = new generator();
        // One authoring run, one package: the generation and every picture and clip made
        // for what it returns carry the same bundle id.
        $generator->use_bundle(generator::bundle_id($job), generator::package_for_job($job, $scenario));
        try {
            $definition = $generator->run_scenario_job($job, $scenario);
        } catch (generation_exception $e) {
            mtrace('Scenario generation failed: ' . $e->errorcode);
            return;
        }

        $counts = [];
        // Declared before the try: the context lookup can fail, and the publish step below
        // must not be handed an undefined variable when it does.
        $media = null;
        try {
            $context = context_module::instance($cmid);
            $media = new media_manager($context, (int)($job->tier ?? 1));
            $counts = $media->generate_for_definition($generator->get_provider(), $scenario, $definition);
            $used = $media->models_used();
            foreach ($used['models'] as $model => $count) {
                mtrace('Images drawn by ' . $model . ': ' . $count . '.');
            }
            if ($used['fallbacks']) {
                mtrace('Images drawn by a fallback model: ' . $used['fallbacks'] . '.');
            }
        } catch (\moodle_exception $e) {
            mtrace('Course module context unavailable; media skipped.');
        }

        // Media generated for an ALREADY PUBLISHED activity had nowhere to go.
        //
        // This task was written on the assumption that generation always finishes before
        // anyone publishes, so publish_media() at publish time would collect everything.
        // That holds the first time. It does not hold when a teacher regenerates a
        // scenario that is already live: the new pictures and clips land in the working
        // area, publish_media() is never called again, and learners keep seeing the old
        // revision's media - or none - while the wizard reports a complete run. It is the
        // same fault the import route had, reached a different way, and it was missed
        // because the comment explaining why this route was safe was believed rather than
        // tested.
        //
        // The activity is re-read for the same reason it is re-read there: $scenario was
        // fetched before a run that takes minutes, and a record that old cannot be asked
        // what is published now.
        $fresh = $DB->get_record('aibranchedscenario', ['id' => $scenario->id], '*', IGNORE_MISSING);
        $live = $fresh ? scenario_manager::get_current_revision($fresh, (int)($job->tier ?? 1)) : null;
        if ($media && $live && generate_media::same_scenes($live->scenariojson, $definition)) {
            $copied = $media->publish_media((int)$live->revision);
            mtrace(
                'Scenario ' . $scenario->id . ' media copied into revision '
                    . $live->revision . ' (' . $copied . ' files).'
            );
        }

        // Only now is the job finished. Until this point the wizard keeps showing
        // progress rather than offering Publish on a scenario whose pictures are still
        // being drawn.
        $generator->finish_scenario_job($job, $definition, $counts);

        mtrace(
            'Scenario ' . $scenario->id . ' generated: ' . count($definition['nodes']) . ' nodes, '
                . (int)($counts['images'] ?? 0) . '/' . (int)($counts['imageswanted'] ?? 0) . ' images, '
                . (int)($counts['narrations'] ?? 0) . '/'
                . (int)($counts['narrationswanted'] ?? 0) . ' narrations.'
        );
    }
}
