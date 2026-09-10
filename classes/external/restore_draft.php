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
use core_external\external_single_structure;
use core_external\external_value;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Put back the working copy that the last generation or import replaced.
 *
 * Exactly one step is kept, and restoring swaps the two, so an undo can itself be
 * undone and nothing further back is reachable. Published revisions are untouched:
 * this only ever moves the draft.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_draft extends external_api {
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
     * Restore the previous working copy.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:manage');

        $restored = scenario_manager::restore_previous($resolved['scenario']);
        $definition = scenario_manager::get_working_definition($resolved['scenario']);

        return [
            'restored'  => $restored,
            'nodecount' => is_array($definition) ? count($definition['nodes']) : 0,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'restored'  => new external_value(PARAM_BOOL, 'Whether a previous copy was put back'),
            'nodecount' => new external_value(PARAM_INT, 'Scenes in the restored copy'),
        ]);
    }
}
