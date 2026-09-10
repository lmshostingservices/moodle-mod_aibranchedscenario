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
 * Saves edited narrative text for one node of the working scenario copy.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_node_text extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module id'),
            'nodeid' => new external_value(PARAM_ALPHANUMEXT, 'Node identifier'),
            'fields' => new external_single_structure([
                'title'             => new external_value(PARAM_TEXT, 'Node title', VALUE_OPTIONAL),
                'situation'         => new external_value(PARAM_TEXT, 'Situation text', VALUE_OPTIONAL),
                'facilitatorspeech' => new external_value(PARAM_TEXT, 'Spoken line', VALUE_OPTIONAL),
                'challenge'         => new external_value(PARAM_TEXT, 'Direct question', VALUE_OPTIONAL),
                'summary'           => new external_value(PARAM_TEXT, 'Outcome summary', VALUE_OPTIONAL),
            ]),
            'choices' => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_ALPHANUMEXT, 'Choice identifier'),
                    'text'        => new external_value(PARAM_TEXT, 'Choice text', VALUE_OPTIONAL),
                    'consequence' => new external_value(PARAM_TEXT, 'Consequence text', VALUE_OPTIONAL),
                    'feedback'    => new external_value(PARAM_TEXT, 'Instructional feedback', VALUE_OPTIONAL),
                ]),
                'Edited choices',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Apply the edits to the working copy.
     *
     * @param int $cmid Course module id.
     * @param string $nodeid Node identifier.
     * @param array $fields Edited node fields.
     * @param array $choices Edited choices.
     * @return array
     */
    public static function execute(int $cmid, string $nodeid, array $fields, array $choices): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'nodeid' => $nodeid, 'fields' => $fields, 'choices' => $choices,
        ]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:manage');

        $definition = scenario_manager::get_working_definition($resolved['scenario']);
        if ($definition === null) {
            throw new \moodle_exception('error:noworkingcopy', 'mod_aibranchedscenario');
        }

        $found = false;
        foreach ($definition['nodes'] as $index => $node) {
            if ($node['id'] !== $params['nodeid']) {
                continue;
            }
            $found = true;
            foreach (['title', 'situation', 'facilitatorspeech', 'challenge', 'summary'] as $key) {
                if (array_key_exists($key, $params['fields'])) {
                    $definition['nodes'][$index][$key] = $params['fields'][$key];
                }
            }
            foreach ($params['choices'] as $edited) {
                foreach ($node['choices'] as $position => $choice) {
                    if ($choice['id'] !== $edited['id']) {
                        continue;
                    }
                    foreach (['text', 'consequence', 'feedback'] as $key) {
                        if (array_key_exists($key, $edited)) {
                            $definition['nodes'][$index]['choices'][$position][$key] = $edited[$key];
                        }
                    }
                }
            }
            break;
        }
        if (!$found) {
            throw new \moodle_exception('error:unknownnode', 'mod_aibranchedscenario');
        }

        scenario_manager::save_definition($resolved['scenario'], $definition);

        return ['saved' => true];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Whether the edits were stored'),
        ]);
    }
}
