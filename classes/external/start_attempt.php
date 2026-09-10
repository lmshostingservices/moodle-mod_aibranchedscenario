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
use mod_aibranchedscenario\local\attempt_manager;

/**
 * Starts or resumes the current user's attempt and returns the current node.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_attempt extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'Course module id'),
            'forcenew' => new external_value(
                PARAM_BOOL,
                'Abandon any open attempt and start again',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Start or resume the attempt.
     *
     * @param int $cmid Course module id.
     * @param bool $forcenew Whether to force a new attempt.
     * @return array
     */
    public static function execute(int $cmid, bool $forcenew): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'forcenew' => $forcenew]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:attempt');
        $scenario = $resolved['scenario'];

        if ($params['forcenew'] && empty($scenario->allowreplay)) {
            throw new \moodle_exception('error:replaynotallowed', 'mod_aibranchedscenario');
        }

        $manager = attempt_manager::for_scenario($scenario);
        $attempt = $manager->start_or_resume((int)$USER->id, (bool)$params['forcenew']);

        $node = $manager->get_node($attempt->currentnode);
        if (!$node) {
            throw new \moodle_exception('error:unknownnode', 'mod_aibranchedscenario');
        }

        $mediaurls = helper::media_urls($resolved['context'], $manager->get_revision());
        $seq = count(attempt_manager::get_events((int)$attempt->id));

        return [
            'attemptid'  => (int)$attempt->id,
            'attemptno'  => (int)$attempt->attemptno,
            'status'     => $attempt->status,
            'nextseq'    => $seq + 1,
            'metrics'    => helper::metrics($attempt),
            'node'       => helper::node_payload($node, $attempt, $mediaurls),
            'resumed'    => $seq > 0,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(PARAM_INT, 'Attempt identifier'),
            'attemptno' => new external_value(PARAM_INT, 'Attempt number for this user'),
            'status'    => new external_value(PARAM_ALPHA, 'Attempt status'),
            'nextseq'   => new external_value(PARAM_INT, 'Sequence number the next decision must use'),
            'metrics'   => helper::metrics_structure(),
            'node'      => helper::node_structure(),
            'resumed'   => new external_value(PARAM_BOOL, 'Whether an existing attempt was resumed'),
        ]);
    }
}
