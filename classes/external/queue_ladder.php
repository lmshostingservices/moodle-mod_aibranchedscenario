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

/**
 * Queue all three of an activity's scenarios from one press.
 *
 * An activity holds a foundation, an intermediate and an advanced scenario, written from
 * the same source material and testing the same principles at rising difficulty. There is
 * nothing for a teacher to fill in between them, so asking them to describe the situation
 * once and then press Generate three times is work the product should be doing.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_ladder extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Queue one generation job per rung.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');

        $generator = new generator();
        $queued = $generator->queue_ladder($resolved['scenario'], (int)$USER->id, $params['cmid']);

        $jobs = [];
        foreach ($queued as $tier => $jobid) {
            $jobs[] = ['tier' => (int)$tier, 'jobid' => (int)$jobid];
        }

        return ['jobs' => $jobs, 'status' => generator::JOB_QUEUED];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobs' => new external_multiple_structure(
                new external_single_structure([
                    'tier'  => new external_value(PARAM_INT, 'Which rung this job is writing'),
                    'jobid' => new external_value(PARAM_INT, 'Identifier of the queued job'),
                ])
            ),
            'status' => new external_value(PARAM_ALPHA, 'Current status of the queued jobs'),
        ]);
    }
}
