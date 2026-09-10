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
use mod_aibranchedscenario\local\ai\credentials;
use mod_aibranchedscenario\local\ai\image_prompt;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\validation_exception;
use mod_aibranchedscenario\local\validator;

/**
 * Imports a scenario definition supplied as JSON, without using the AI service.
 *
 * This is the route that lets a teacher author or reuse a scenario when no AI
 * credentials are configured, and it is what the automated tests use.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_definition extends external_api {
    /**
     * Describe the parameters.
     *
     * The definition is a JSON document, transported base64 encoded. Its narrative
     * fields legitimately contain angle brackets, quotation marks and non-ASCII text,
     * so passing the JSON as a cleaned string parameter would silently corrupt it,
     * and passing it uncleaned would place an unvalidated string in the parameter
     * layer. Base64 gives the parameter a shape that can be strictly validated on
     * arrival while leaving the document itself byte-exact. Moodle's PARAM_BASE64
     * follows the PEM convention of 64 character lines, so callers wrap the encoded
     * document accordingly; base64_decode() skips that whitespace.
     *
     * Nothing is trusted once decoded: the bytes are length checked, JSON decoded and
     * passed through the plugin's whitelist validator, which drops every key it does
     * not know and stores every string as plain text.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module id'),
            'definition' => new external_value(
                PARAM_BASE64,
                'Scenario definition: a JSON document, base64 encoded'
            ),
        ]);
    }

    /**
     * Validate and store the supplied definition as the working copy.
     *
     * @param int $cmid Course module id.
     * @param string $definition Scenario definition: a JSON document, base64 encoded.
     * @return array
     */
    public static function execute(int $cmid, string $definition): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'definition' => $definition]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:manage');

        $json = base64_decode($params['definition'], true);
        if ($json === false) {
            return [
                'imported'  => false,
                'nodecount' => 0,
                'mediaqueued' => 0,
                'problems'  => get_string('error:notjson', 'mod_aibranchedscenario'),
            ];
        }

        try {
            $clean = validator::validate_json($json);
        } catch (validation_exception $e) {
            return [
                'imported'  => false,
                'nodecount' => 0,
                'mediaqueued' => 0,
                'problems'  => implode("\n", array_slice($e->get_problems(), 0, 20)),
            ];
        }

        scenario_manager::save_definition($resolved['scenario'], $clean, [
            'provider'        => 'manual',
            'timegenerated'   => time(),
            'contractversion' => $clean['version'],
            'nodecount'       => (int)($clean['stats']['nodecount'] ?? 0),
            'decisioncount'   => (int)($clean['stats']['decisioncount'] ?? 0),
        ]);

        // An imported scenario used to arrive with no pictures, because the media loop
        // ran only inside the generation task. A teacher who drafted the scenario
        // elsewhere and pasted it in still wants the illustrations, and the definition
        // they pasted carries an imageprompt for every node.
        $media = 0;
        if (self::wants_media($resolved['scenario']) && credentials::are_configured()) {
            $task = new \mod_aibranchedscenario\task\generate_media();
            $task->set_custom_data((object)['cmid' => (int)$params['cmid']]);
            $task->set_userid((int)$USER->id);
            \core\task\manager::queue_adhoc_task($task, true);
            $media = image_prompt::count_images($clean);
        }

        return [
            'imported'  => true,
            'nodecount' => (int)($clean['stats']['nodecount'] ?? 0),
            'mediaqueued' => $media,
            'problems'  => '',
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'imported'  => new external_value(PARAM_BOOL, 'Whether the definition was stored'),
            'nodecount' => new external_value(PARAM_INT, 'Nodes in the stored definition'),
            'mediaqueued' => new external_value(
                PARAM_INT,
                'Scene images queued for background generation',
                VALUE_DEFAULT,
                0
            ),
            'problems'  => new external_value(PARAM_TEXT, 'Validation problems when the import was rejected'),
        ]);
    }

    /**
     * Whether this activity is set up to want images or narration.
     *
     * @param \stdClass $scenario Activity instance.
     * @return bool
     */
    protected static function wants_media(\stdClass $scenario): bool {
        $images = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $audio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');
        return $images || $audio;
    }
}
