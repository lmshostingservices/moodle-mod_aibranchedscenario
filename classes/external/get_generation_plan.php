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
use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\source_normaliser;

/**
 * What one press of Generate will cost, and what it will replace.
 *
 * The plugin had exactly one button that spends money and less friction in front of it
 * than deleting a forum post: no balance, no estimate, no confirmation, and no warning
 * that it would overwrite a draft the teacher may have spent an hour editing.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_generation_plan extends external_api {
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
     * Estimate the run.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');
        $scenario = $resolved['scenario'];

        $source = scenario_manager::get_source($scenario);
        $source = $source ? source_normaliser::normalise($source) : source_normaliser::blank();
        $decisions = max(3, min(8, (int)$source['decisions']));

        // One image per scene, and a scene per decision plus the beats between them and
        // the endings, which is the same arithmetic the generation request uses.
        $scenes = min(schema::MAX_NODES, ($decisions * 2) + 3);
        $tariff = schema::tariff();

        $wantsimages = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $wantsaudio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');

        $images = $wantsimages ? $scenes : 0;
        $narrations = $wantsaudio ? $decisions : 0;
        $estimate = $tariff['scenario']
            + ($images * $tariff['image'])
            + ($narrations * $tariff['speech']);

        $provider = new lmslabs_provider();
        $status = $provider->status();

        return [
            'decisions'    => $decisions,
            'scenes'       => $scenes,
            'images'       => $images,
            'narrations'   => $narrations,
            'estimate'     => $estimate,
            'credits'      => (int)($status['credits'] ?? 0),
            'unlimited'    => !empty($status['unlimited']),
            'balanceknown' => !empty($status['connected']),
            'enough'       => !empty($status['unlimited'])
                || !empty($status['connected']) === false
                || (int)($status['credits'] ?? 0) >= $estimate,
            'replacesdraft' => !empty($scenario->scenariojson),
            'buyurl'       => (string)($status['buyurl'] ?? ''),
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'decisions'     => new external_value(PARAM_INT, 'Decision points the scenario will contain'),
            'scenes'        => new external_value(PARAM_INT, 'Scenes the scenario will contain'),
            'images'        => new external_value(PARAM_INT, 'Images that will be generated'),
            'narrations'    => new external_value(PARAM_INT, 'Narration clips that will be generated'),
            'estimate'      => new external_value(PARAM_INT, 'Credits the run is expected to cost'),
            'credits'       => new external_value(PARAM_INT, 'Credits currently available'),
            'unlimited'     => new external_value(PARAM_BOOL, 'Whether the plan is unlimited'),
            'balanceknown'  => new external_value(PARAM_BOOL, 'Whether the balance could be read'),
            'enough'        => new external_value(PARAM_BOOL, 'Whether the balance covers the estimate'),
            'replacesdraft' => new external_value(PARAM_BOOL, 'Whether a working copy would be replaced'),
            'buyurl'        => new external_value(PARAM_URL, 'Where more credits can be bought', VALUE_DEFAULT, ''),
        ]);
    }
}
