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
 * Suggests a value for a single authoring wizard field.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_field extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module id'),
            'field'  => new external_value(PARAM_ALPHANUMEXT, 'Field being suggested'),
            'source' => helper::source_structure(),
        ]);
    }

    /**
     * Ask the service for a suggestion.
     *
     * @param int $cmid Course module id.
     * @param string $field Field being suggested.
     * @param array $source Current wizard values.
     * @return array
     */
    public static function execute(int $cmid, string $field, array $source): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'field' => $field, 'source' => $source,
        ]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');

        if (!in_array($params['field'], helper::suggestable_fields(), true)) {
            throw new \moodle_exception('error:unknownfield', 'mod_aibranchedscenario');
        }

        $generator = new generator();
        $result = $generator->suggest(
            $resolved['scenario'],
            (int)$USER->id,
            $params['field'],
            $params['source']
        );

        $values = [];
        foreach ((array)($result['values'] ?? []) as $value) {
            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return [
            'field'      => $params['field'],
            'suggestion' => (string)($result['suggestion'] ?? ''),
            'values'     => $values,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'field'      => new external_value(PARAM_ALPHANUMEXT, 'Field the suggestion is for'),
            'suggestion' => new external_value(PARAM_TEXT, 'Suggested value for a text field'),
            'values'     => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Suggested option key'),
                'Suggested values for a multi-select field'
            ),
        ]);
    }
}
