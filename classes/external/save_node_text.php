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
use mod_aibranchedscenario\local\schema;

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
                    'id'          => new external_value(PARAM_ALPHANUMEXT, 'Choice identifier, empty for a new one'),
                    'text'        => new external_value(PARAM_TEXT, 'Choice text', VALUE_OPTIONAL),
                    'consequence' => new external_value(PARAM_TEXT, 'Consequence text', VALUE_OPTIONAL),
                    'feedback'    => new external_value(PARAM_TEXT, 'Instructional feedback', VALUE_OPTIONAL),
                    'signal'      => new external_value(PARAM_ALPHA, 'positive, neutral or negative', VALUE_OPTIONAL),
                    'next'        => new external_value(PARAM_ALPHANUMEXT, 'Node this choice leads to', VALUE_OPTIONAL),
                ]),
                'The complete set of choices for this node, in order',
                VALUE_DEFAULT,
                []
            ),
            'replacechoices' => new external_value(
                PARAM_BOOL,
                'Treat the choices list as the whole set, so one can be added or removed',
                VALUE_DEFAULT,
                false
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
    public static function execute(
        int $cmid,
        string $nodeid,
        array $fields,
        array $choices,
        bool $replacechoices = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'nodeid' => $nodeid, 'fields' => $fields, 'choices' => $choices,
            'replacechoices' => $replacechoices,
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
            // The branching of a branching-scenario authoring tool used to be read only:
            // a teacher could reword a choice but not change where it led, what it was
            // worth, or how many there were. With replacechoices the submitted list is
            // the whole set for this node, so one can be added or taken away; without
            // it the old behaviour of editing in place is kept.
            $existing = [];
            foreach ($node['choices'] as $choice) {
                $existing[$choice['id']] = $choice;
            }

            if ($params['replacechoices']) {
                $rebuilt = [];
                foreach ($params['choices'] as $position => $edited) {
                    $base = $existing[$edited['id']] ?? [
                        'id'          => $node['id'] . '_' . chr(ord('a') + $position),
                        'letter'      => strtoupper(chr(ord('a') + $position)),
                        'text'        => '',
                        'signal'      => 'neutral',
                        'consequence' => '',
                        'feedback'    => '',
                        'principleid' => '',
                        'tags'        => [],
                        'effects'     => [],
                        'skills'      => [],
                        'next'        => schema::auto_target(),
                    ];
                    $rebuilt[] = self::apply_choice($base, $edited);
                }
                $definition['nodes'][$index]['choices'] = $rebuilt;
            } else {
                foreach ($params['choices'] as $edited) {
                    foreach ($node['choices'] as $position => $choice) {
                        if ($choice['id'] !== $edited['id']) {
                            continue;
                        }
                        $definition['nodes'][$index]['choices'][$position] =
                            self::apply_choice($choice, $edited);
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

    /**
     * Copy the editable fields of one submitted choice onto the stored one.
     *
     * Anything not submitted keeps its stored value, so an editor that offers only
     * some of these fields cannot silently blank the rest.
     *
     * @param array $choice The stored choice.
     * @param array $edited The submitted values.
     * @return array
     */
    protected static function apply_choice(array $choice, array $edited): array {
        foreach (['text', 'consequence', 'feedback', 'next'] as $key) {
            if (array_key_exists($key, $edited)) {
                $choice[$key] = $edited[$key];
            }
        }
        if (array_key_exists('signal', $edited) && in_array($edited['signal'], schema::signals(), true)) {
            $choice['signal'] = $edited['signal'];
        }
        return $choice;
    }
}
