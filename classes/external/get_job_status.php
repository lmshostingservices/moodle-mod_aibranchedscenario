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
        $media = '';
        $result = json_decode((string)$job->resultjson, true);
        if (is_array($result) && isset($result['media']['imageswanted'])) {
            $made = (int)$result['media']['images'];
            $wanted = (int)$result['media']['imageswanted'];
            if ($wanted > 0 && $made < $wanted) {
                $media = get_string(
                    'mediaincomplete',
                    'mod_aibranchedscenario',
                    (object)['made' => $made, 'wanted' => $wanted]
                );
            }
        }

        return [
            'jobid'         => (int)$job->id,
            'status'        => $job->status,
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
            'errormessage'  => new external_value(PARAM_TEXT, 'Translated failure message, or empty'),
            'mediamessage'  => new external_value(
                PARAM_TEXT,
                'Warning when fewer images were produced than the scenario called for',
                VALUE_DEFAULT,
                ''
            ),
            'nodecount'     => new external_value(PARAM_INT, 'Nodes in the working copy'),
            'decisioncount' => new external_value(PARAM_INT, 'Decision points in the working copy'),
        ]);
    }
}
