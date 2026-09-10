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
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Saves the authoring wizard inputs for a scenario activity.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_source extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module id'),
            'source' => helper::source_structure(),
        ]);
    }

    /**
     * Save the wizard inputs.
     *
     * @param int $cmid Course module id.
     * @param array $source Wizard inputs.
     * @return array
     */
    public static function execute(int $cmid, array $source): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'source' => $source]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:manage');

        $clean = scenario_manager::save_source($resolved['scenario'], $params['source']);

        return ['saved' => true, 'source' => helper::source_payload($clean)];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved'  => new external_value(PARAM_BOOL, 'Whether the inputs were stored'),
            'source' => helper::source_structure(),
        ]);
    }
}
