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
 * Behat step definitions for mod_aibranchedscenario.
 *
 * Publishing a scenario normally needs the external generation service, so this
 * step stands in for it by publishing the module generator's sample scenario.
 *
 * @package    mod_aibranchedscenario
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps for authoring and playing a branching scenario.
 *
 * @package    mod_aibranchedscenario
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_aibranchedscenario extends behat_base {
    /**
     * Save and publish the sample scenario into a named activity.
     *
     * @Given /^the sample scenario is published in the "(?P<activity_string>(?:[^"]|\\")*)" activity$/
     * @param string $activityname Name of the activity instance.
     * @return void
     */
    public function the_sample_scenario_is_published_in_the_activity(string $activityname): void {
        global $DB;

        $scenario = $DB->get_record('aibranchedscenario', ['name' => $activityname], '*', MUST_EXIST);

        $admin = get_admin();

        /** @var mod_aibranchedscenario_generator $generator */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_aibranchedscenario');
        $generator->publish_sample($scenario, (int)$admin->id);
    }
}
