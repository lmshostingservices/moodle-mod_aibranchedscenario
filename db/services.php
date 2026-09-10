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

/**
 * External function definitions.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_aibranchedscenario_save_source' => [
        'classname'   => 'mod_aibranchedscenario\external\save_source',
        'description' => 'Save the authoring wizard inputs for a scenario activity.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:manage',
        'services'    => [],
    ],

    'mod_aibranchedscenario_import_definition' => [
        'classname'   => 'mod_aibranchedscenario\external\import_definition',
        'description' => 'Import a scenario definition supplied as JSON, without using the AI service.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:manage',
        'services'    => [],
    ],

    'mod_aibranchedscenario_populate_wizard' => [
        'classname'   => 'mod_aibranchedscenario\external\populate_wizard',
        'description' => 'Fill the authoring wizard from a brief and pasted source content.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:generate',
        'services'    => [],
    ],

    'mod_aibranchedscenario_suggest_field' => [
        'classname'   => 'mod_aibranchedscenario\external\suggest_field',
        'description' => 'Suggest a value for a single authoring wizard field.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:generate',
        'services'    => [],
    ],

    'mod_aibranchedscenario_queue_generation' => [
        'classname'   => 'mod_aibranchedscenario\external\queue_generation',
        'description' => 'Queue generation of a branching scenario from the stored wizard inputs.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:generate',
        'services'    => [],
    ],

    'mod_aibranchedscenario_get_job_status' => [
        'classname'   => 'mod_aibranchedscenario\external\get_job_status',
        'description' => 'Report the progress of a queued generation job.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:generate',
        'services'    => [],
    ],

    'mod_aibranchedscenario_save_node_text' => [
        'classname'   => 'mod_aibranchedscenario\external\save_node_text',
        'description' => 'Save edited narrative text for one node of the working scenario copy.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:manage',
        'services'    => [],
    ],

    'mod_aibranchedscenario_publish_scenario' => [
        'classname'   => 'mod_aibranchedscenario\external\publish_scenario',
        'description' => 'Publish the working copy as a new immutable revision.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:publish',
        'services'    => [],
    ],

    'mod_aibranchedscenario_start_attempt' => [
        'classname'   => 'mod_aibranchedscenario\external\start_attempt',
        'description' => 'Start or resume the current user attempt and return the current node.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:attempt',
        'services'    => [],
    ],

    'mod_aibranchedscenario_submit_choice' => [
        'classname'   => 'mod_aibranchedscenario\external\submit_choice',
        'description' => 'Record a decision and return its consequence and the next node.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:attempt',
        'services'    => [],
    ],

    'mod_aibranchedscenario_get_debrief' => [
        'classname'   => 'mod_aibranchedscenario\external\get_debrief',
        'description' => 'Return the debrief for a finished attempt owned by the current user.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:attempt',
        'services'    => [],
    ],

    'mod_aibranchedscenario_delete_attempt' => [
        'classname'   => 'mod_aibranchedscenario\external\delete_attempt',
        'description' => 'Delete a learner attempt and its decision log.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/aibranchedscenario:deleteattempts',
        'services'    => [],
    ],
];
