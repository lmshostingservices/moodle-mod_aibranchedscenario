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
 * Restore task definition for mod_aibranchedscenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aibranchedscenario/backup/moodle2/restore_aibranchedscenario_stepslib.php');

/**
 * Restore task for the AI branched scenario activity.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_aibranchedscenario_activity_task extends restore_activity_task {
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
        $this->add_step(new restore_aibranchedscenario_activity_structure_step(
            'aibranchedscenario_structure',
            'aibranchedscenario.xml'
        ));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array Of restore_decode_content.
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('aibranchedscenario', ['intro'], 'aibranchedscenario');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array Of restore_decode_rule.
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule(
            'AIBRANCHEDSCENARIOVIEWBYID',
            '/mod/aibranchedscenario/view.php?id=$1',
            'course_module'
        );
        $rules[] = new restore_decode_rule(
            'AIBRANCHEDSCENARIOVIEWBYB',
            '/mod/aibranchedscenario/view.php?b=$1',
            'aibranchedscenario'
        );
        $rules[] = new restore_decode_rule(
            'AIBRANCHEDSCENARIOINDEX',
            '/mod/aibranchedscenario/index.php?id=$1',
            'course'
        );

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the
     * restore_logs_processor when restoring aibranchedscenario logs.
     *
     * @return array Of restore_log_rule.
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('aibranchedscenario', 'add', 'view.php?id={course_module}', '{aibranchedscenario}');
        $rules[] = new restore_log_rule('aibranchedscenario', 'update', 'view.php?id={course_module}', '{aibranchedscenario}');
        $rules[] = new restore_log_rule('aibranchedscenario', 'view', 'view.php?id={course_module}', '{aibranchedscenario}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the
     * restore_logs_processor when restoring course logs.
     *
     * @return array Of restore_log_rule.
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('aibranchedscenario', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
