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
 * Backup task definition for mod_aibranchedscenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aibranchedscenario/backup/moodle2/backup_aibranchedscenario_stepslib.php');

/**
 * Backup task for the AI branched scenario activity.
 *
 * Provides the activity structure step and the content link encoder.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_aibranchedscenario_activity_task extends backup_activity_task {
    /**
     * Define particular settings this activity can have.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define the steps this activity task performs.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_aibranchedscenario_activity_structure_step(
            'aibranchedscenario_structure',
            'aibranchedscenario.xml'
        ));
    }

    /**
     * Encode all the links to this activity so they can be decoded on restore.
     *
     * @param string $content Content to encode.
     * @return string The content with the links encoded.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/aibranchedscenario', '#');

        // Link to the list of scenarios in a course.
        $content = preg_replace(
            "#($base)/index\.php\?id=(\d+)#",
            '$@AIBRANCHEDSCENARIOINDEX*$2@$',
            $content
        );

        // Link to a scenario view by course module id.
        $content = preg_replace(
            "#($base)/view\.php\?id=(\d+)#",
            '$@AIBRANCHEDSCENARIOVIEWBYID*$2@$',
            $content
        );

        // Link to a scenario view by instance id.
        $content = preg_replace(
            "#($base)/view\.php\?b=(\d+)#",
            '$@AIBRANCHEDSCENARIOVIEWBYB*$2@$',
            $content
        );

        return $content;
    }
}
