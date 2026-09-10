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
use mod_aibranchedscenario\local\attempt_manager;

/**
 * Deletes a learner attempt and its decision log.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_attempt extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module id'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt identifier'),
        ]);
    }

    /**
     * Delete the attempt.
     *
     * @param int $cmid Course module id.
     * @param int $attemptid Attempt identifier.
     * @return array
     */
    public static function execute(int $cmid, int $attemptid): array {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'attemptid' => $attemptid]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:deleteattempts');

        $attempt = $DB->get_record('aibranchedscenario_attempts', ['id' => $params['attemptid']], '*', IGNORE_MISSING);
        if (!$attempt || (int)$attempt->scenarioid !== (int)$resolved['scenario']->id) {
            throw new \moodle_exception('error:attemptnotfound', 'mod_aibranchedscenario');
        }

        attempt_manager::delete_attempt((int)$attempt->id);
        aibranchedscenario_update_grades($resolved['scenario'], (int)$attempt->userid);

        return ['deleted' => true];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether the attempt was removed'),
        ]);
    }
}
