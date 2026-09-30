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

/**
 * Reports the progress of a queued generation job.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_job_status extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'  => new external_value(PARAM_INT, 'Course module id'),
            'jobid' => new external_value(PARAM_INT, 'Job identifier'),
        ]);
    }

    /**
     * Report the job status.
     *
     * @param int $cmid Course module id.
     * @param int $jobid Job identifier.
     * @return array
     */
    public static function execute(int $cmid, int $jobid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'jobid' => $jobid]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');

        $job = generator::get_job($params['jobid'], (int)$resolved['scenario']->id);
        if (!$job) {
            throw new \moodle_exception('error:unknownjob', 'mod_aibranchedscenario');
        }

        $definition = scenario_manager::get_working_definition($resolved['scenario']);

        // A run where every image failed used to look exactly like a complete one.
        //
        // Two things were still missing from this. It counted illustrations and not
        // narration, so a scenario that came back completely silent - every clip refused -
        // reported nothing at all, which is half of what was actually reported broken. And
        // it gave a number without a reason: "3 of 14" sends the next person guessing,
        // where "3 of 14, insufficientcredits" ends the question.
        $media = '';
        $result = json_decode((string)$job->resultjson, true);
        if (is_array($result) && isset($result['media'])) {
            $counts = (array)$result['media'];
            $made = (int)($counts['images'] ?? 0) + (int)($counts['narrations'] ?? 0);
            $wanted = (int)($counts['imageswanted'] ?? 0) + (int)($counts['narrationswanted'] ?? 0);
            if ($wanted > 0 && $made < $wanted) {
                $media = get_string(
                    'mediaincomplete',
                    'mod_aibranchedscenario',
                    (object)['made' => $made, 'wanted' => $wanted]
                );
                // Reasons are the service's own error identifiers, already reduced to a
                // strict character set where they were recorded. Shown as they are: a site
                // owner can act on "insufficientcredits" and cannot act on "some failed".
                $refused = array_values(array_filter((array)($counts['refused'] ?? []), 'is_string'));
                if ($refused) {
                    $media .= ' ' . get_string(
                        'mediarefused',
                        'mod_aibranchedscenario',
                        implode(', ', array_map(static function ($code) {
                            return clean_param($code, PARAM_ALPHANUMEXT);
                        }, array_slice($refused, 0, 6)))
                    );
                }
            }
        }

        // WHAT IS STILL BEING MADE, counted from the files that exist rather than from what a
        // finished job once reported - the media for a pasted scenario is made by a task that
        // writes no counts anywhere the wizard can see. Publishing is refused until these
        // agree, so the page has to be able to ask.
        $pending = ['images' => 0, 'imageswanted' => 0, 'narrations' => 0, 'narrationswanted' => 0];
        if (is_array($definition) && $definition) {
            $pending = (new media_manager($resolved['context'], 1))
                ->working_media_status($resolved['scenario'], $definition);
        }

        return [
            'jobid'         => (int)$job->id,
            'status'        => $job->status,
            'mediaready'    => !empty($pending['complete']),
            'mediamade'     => (int)$pending['images'] + (int)$pending['narrations'],
            'mediawanted'   => (int)$pending['imageswanted'] + (int)$pending['narrationswanted'],
            'errormessage'  => $job->status === generator::JOB_ERROR
                ? helper::safe_error_message((string)$job->errormsg) : '',
            'mediamessage'  => $media,
            'nodecount'     => (int)($definition['stats']['nodecount'] ?? 0),
            'decisioncount' => (int)($definition['stats']['decisioncount'] ?? 0),
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid'         => new external_value(PARAM_INT, 'Job identifier'),
            'status'        => new external_value(PARAM_ALPHA, 'Job status'),
            'errormessage'  => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'Translated failure message, or empty'
            ),
            'mediamessage'  => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'Warning when fewer images were produced than the scenario called for',
                VALUE_DEFAULT,
                ''
            ),
            'mediaready'    => new external_value(
                PARAM_BOOL,
                'Whether every picture and clip this scenario calls for now exists',
                VALUE_DEFAULT,
                true
            ),
            'mediamade'     => new external_value(
                PARAM_INT,
                'Pictures and clips made so far',
                VALUE_DEFAULT,
                0
            ),
            'mediawanted'   => new external_value(
                PARAM_INT,
                'Pictures and clips this scenario calls for',
                VALUE_DEFAULT,
                0
            ),
            'nodecount'     => new external_value(PARAM_INT, 'Nodes in the working copy'),
            'decisioncount' => new external_value(PARAM_INT, 'Decision points in the working copy'),
        ]);
    }
}
