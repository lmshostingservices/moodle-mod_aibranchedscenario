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

namespace mod_aibranchedscenario\completion;

use core_completion\activity_custom_completion;
use mod_aibranchedscenario\local\attempt_manager;

/**
 * Activity custom completion rules.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Whether the given rule is complete for the current user.
     *
     * @param string $rule The completion rule.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instance = $DB->get_record('aibranchedscenario', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if (empty($instance->completionfinish)) {
            return COMPLETION_INCOMPLETE;
        }

        $minscore = (float)$instance->completionminscore;
        if ($minscore <= 0) {
            $complete = $DB->record_exists('aibranchedscenario_attempts', [
                'scenarioid' => $instance->id,
                'userid'     => $this->userid,
                'status'     => attempt_manager::STATUS_FINISHED,
            ]);
        } else {
            $complete = $DB->record_exists_select(
                'aibranchedscenario_attempts',
                'scenarioid = :sid AND userid = :uid AND status = :status AND score >= :minscore',
                [
                    'sid'      => $instance->id,
                    'uid'      => $this->userid,
                    'status'   => attempt_manager::STATUS_FINISHED,
                    'minscore' => $minscore,
                ]
            );
        }

        return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules this activity defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionfinish'];
    }

    /**
     * Descriptions of the custom rules for the activity information display.
     *
     * @return array Rule name to description.
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $instance = $DB->get_record('aibranchedscenario', ['id' => $this->cm->instance], '*', IGNORE_MISSING);
        $minscore = $instance ? (int)$instance->completionminscore : 0;

        if ($minscore > 0) {
            $description = get_string('completiondetail:finishwithscore', 'mod_aibranchedscenario', $minscore);
        } else {
            $description = get_string('completiondetail:finish', 'mod_aibranchedscenario');
        }

        return ['completionfinish' => $description];
    }

    /**
     * The order the rules are shown in.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionfinish',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
