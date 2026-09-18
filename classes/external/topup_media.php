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

namespace mod_aibranchedscenario\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;

/**
 * Reports which pictures a published revision is missing, and makes just those.
 *
 * THE OTHER HALF OF THE RECONCILIATION.
 *
 * media_manager::reconcile_images() has been able to answer "which slots are unfilled"
 * since v1.81.0, and for exactly one release that answer had nowhere to go: nothing called
 * it outside the test harness, and the only way to obtain a missing picture was to
 * regenerate every picture at the full media price. A teacher four pictures short paid for
 * thirty. That is the plugin's oldest recurring fault - a report with no reader - committed
 * in the same week it was written down as the thing to stop doing.
 *
 * Two modes, deliberately separate:
 *
 *   - asked without keys, it REPORTS: what is missing, what is orphaned, what two screens
 *     are sharing. Nothing is generated and nothing is charged;
 *   - asked with keys, it MAKES those and only those, and copies them into the revision
 *     without disturbing the pictures already there.
 *
 * So a teacher sees the shortfall and what it will cost before anything is spent, which is
 * the same order the generation wizard uses.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topup_media extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'keys' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'An image key to generate'),
                'The keys to make. Empty to report the shortfall without making anything.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Report the shortfall, or make the pictures named.
     *
     * @param int $cmid Course module id.
     * @param array $keys Image keys to generate, or empty to report only.
     * @return array
     */
    public static function execute(int $cmid, array $keys = []): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'keys' => $keys,
        ]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');
        $scenario = $resolved['scenario'];

        $revision = scenario_manager::get_current_revision($scenario);
        if (!$revision) {
            throw new \moodle_exception('error:nopublishedrevision', 'mod_aibranchedscenario');
        }
        $definition = json_decode((string)$revision->scenariojson, true);
        if (!is_array($definition)) {
            throw new \moodle_exception('error:nopublishedrevision', 'mod_aibranchedscenario');
        }

        $media = new media_manager($resolved['context']);
        $report = $media->reconcile_images($definition, (int)$revision->revision);

        $missing = [];
        foreach ($report['missing'] as $key => $what) {
            $missing[] = ['key' => (string)$key, 'what' => (string)$what];
        }

        // Report only. Nothing generated, nothing charged - a teacher can look at the
        // shortfall as often as they like.
        if (!$params['keys']) {
            return [
                'missing'  => $missing,
                'orphaned' => array_values($report['orphaned']),
                'shared'   => array_map(static function ($group) {
                    return ['keys' => array_values($group)];
                }, $report['shared']),
                'made'     => 0,
                'wanted'   => 0,
                'note'     => '',
            ];
        }

        // Only keys this revision is actually missing. A caller asking for a key that is
        // already filled would otherwise pay to redraw a frame that was fine, and the
        // request comes from a browser, so it is not the plugin's to trust.
        $wanted = array_values(array_intersect($params['keys'], array_keys($report['missing'])));
        if (!$wanted) {
            return [
                'missing'  => $missing,
                'orphaned' => array_values($report['orphaned']),
                'shared'   => [],
                'made'     => 0,
                'wanted'   => 0,
                'note'     => get_string('topup:nothingmissing', 'mod_aibranchedscenario'),
            ];
        }

        $generator = new generator();
        // Charged as a media run, at the media price already published in schema. A top-up
        // is NOT given a cheaper price of its own here: the prices are fixed product
        // decisions and inventing a fourth one in an external function is not this code's
        // call. The quota check is the same one a full media run passes.
        $generator->check_credits(
            (int)$USER->id,
            schema::media_price(true, false)
        );

        $job = $DB->insert_record('aibranchedscenario_jobs', (object)[
            'scenarioid'   => (int)$scenario->id,
            'userid'       => (int)$USER->id,
            'status'       => generator::JOB_RUNNING,
            'jobtype'      => 'media',
            'requestjson'  => json_encode(['topup' => count($wanted)]),
            'modelused'    => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $counts = $media->generate_missing_images(
            $generator->get_provider(),
            $scenario,
            $definition,
            $wanted
        );
        $copied = $media->publish_image_keys((int)$revision->revision, $wanted);
        $failures = $media->failures();

        // The same honesty rule the media task uses: made everything, ready; made some of
        // it, failed - because it is not ready, and calling it ready is the part that hides
        // it. A frame that was generated but did not reach the revision is a shortfall too,
        // which is why the copy count is what is compared rather than the generate count.
        $short = count($wanted) - $copied;
        $note = $failures ? implode(', ', array_slice($failures, 0, 6)) : '';
        if ($short > 0) {
            $note = get_string('media:incomplete', 'mod_aibranchedscenario', (object)[
                'made'   => $copied,
                'wanted' => count($wanted),
                'why'    => $note !== '' ? $note : get_string('media:nowhy', 'mod_aibranchedscenario'),
            ]);
        }
        $DB->update_record('aibranchedscenario_jobs', (object)[
            'id'           => $job,
            'status'       => $short > 0 ? generator::JOB_ERROR : generator::JOB_READY,
            'resultjson'   => json_encode(['media' => $counts]),
            'errormsg'     => $short > 0 ? 'error:mediaincomplete' : '',
            'timemodified' => time(),
        ]);

        // Read back, so what is returned is what the revision now holds rather than what
        // this run believes it did.
        $after = $media->reconcile_images($definition, (int)$revision->revision);
        $stillmissing = [];
        foreach ($after['missing'] as $key => $what) {
            $stillmissing[] = ['key' => (string)$key, 'what' => (string)$what];
        }

        return [
            'missing'  => $stillmissing,
            'orphaned' => array_values($after['orphaned']),
            'shared'   => array_map(static function ($group) {
                return ['keys' => array_values($group)];
            }, $after['shared']),
            'made'     => $copied,
            'wanted'   => count($wanted),
            'note'     => $note,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'missing' => new external_multiple_structure(
                new external_single_structure([
                    'key'  => new external_value(PARAM_ALPHANUMEXT, 'The image key'),
                    'what' => new external_value(PARAM_TEXT, 'What it should illustrate'),
                ]),
                'Slots the map expects and the revision does not hold',
                VALUE_DEFAULT,
                []
            ),
            'orphaned' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'A stored key the map no longer expects'),
                'Pictures left over from a definition that has since been edited',
                VALUE_DEFAULT,
                []
            ),
            'shared' => new external_multiple_structure(
                new external_single_structure([
                    'keys' => new external_multiple_structure(
                        new external_value(PARAM_ALPHANUMEXT, 'A key'),
                        'The keys sharing one file'
                    ),
                ]),
                'Two or more screens showing the same file',
                VALUE_DEFAULT,
                []
            ),
            'made'   => new external_value(PARAM_INT, 'Pictures that reached the revision'),
            'wanted' => new external_value(PARAM_INT, 'Pictures this run was asked for'),
            'note'   => new external_value(PARAM_TEXT, 'Why a run fell short, or empty'),
        ]);
    }
}
