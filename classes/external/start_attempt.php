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
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;

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
            'tier'     => new external_value(
                PARAM_INT,
                'Which rung of the ladder, 1 to 3',
                VALUE_DEFAULT,
                1
            ),
        ]);
    }

    /**
     * Start or resume the attempt.
     *
     * @param int $cmid Course module id.
     * @param bool $forcenew Whether to force a new attempt.
     * @param int $tier Which rung of the ladder.
     * @return array
     */
    public static function execute(int $cmid, bool $forcenew, int $tier = 1): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'forcenew' => $forcenew, 'tier' => $tier]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:attempt');
        $scenario = $resolved['scenario'];

        if ($params['forcenew'] && empty($scenario->allowreplay)) {
            throw new \moodle_exception('error:replaynotallowed', 'mod_aibranchedscenario');
        }

        // THE LADDER'S ORDER IS ENFORCED HERE, NOT ON THE CHOOSER.
        //
        // The chooser draws a locked card as locked, which is a picture of the rule. The
        // tier arrives as a parameter, so without this a learner could open the advanced
        // scenario by changing a number - and the whole point of the ladder is that they
        // build up to it. Asked of the database, where it is answerable.
        $tier = (int)$params['tier'];
        if (!isset(schema::tiers()[$tier])) {
            throw new \moodle_exception('error:tierunknown', 'mod_aibranchedscenario');
        }
        if (!scenario_manager::tier_open($scenario, (int)$USER->id, $tier)) {
            throw new \moodle_exception('error:tierlocked', 'mod_aibranchedscenario');
        }

        // A RESUMED ATTEMPT IS PLAYED ON THE REVISION IT STARTED ON.
        //
        // This resolved the CURRENT revision and handed the learner its wording, while
        // submit_choice resolved the ATTEMPT'S revision and scored against that one. A
        // learner who came back after the teacher republished therefore read one version of
        // a choice and was graded on another - a different consequence, different skill
        // deltas, and pictures belonging to a scene the node no longer described. Nothing
        // told anyone: both halves worked exactly as written.
        //
        // Attempts have always been pinned to their own revision; only this entry point
        // forgot. The new attempt case still uses the current revision, because a new
        // attempt starts on whatever is published now.
        $manager = attempt_manager::for_scenario($scenario, $tier);
        $attempt = $manager->start_or_resume((int)$USER->id, (bool)$params['forcenew']);
        if ((int)$attempt->revisionid !== (int)$manager->get_revision()->id) {
            $manager = attempt_manager::for_attempt($scenario, $attempt);
        }

        $node = $manager->get_node($attempt->currentnode);
        if (!$node) {
            // The node is gone from the attempt's own revision, which should not happen -
            // a revision is immutable. If it ever does, the learner is holding an attempt
            // that can never be played, never finished and never graded, and on a scenario
            // with one attempt allowed they are stuck there permanently. It is abandoned so
            // they can start again rather than being told to go away.
            $manager->abandon_attempt($attempt);
            throw new \moodle_exception('error:attemptunplayable', 'mod_aibranchedscenario');
        }

        $mediaurls = helper::media_urls($resolved['context'], $manager->get_revision());
        $seq = count(attempt_manager::get_events((int)$attempt->id));

        $journey = [];
        foreach ($manager->build_journey($attempt) as $step) {
            $journey[] = [
                'seq'        => $step['seq'],
                'nodetitle'  => $step['nodetitle'],
                'choicetext' => $step['choicetext'],
                'signal'     => $step['signal'],
            ];
        }

        return [
            'attemptid'  => (int)$attempt->id,
            'attemptno'  => (int)$attempt->attemptno,
            'status'     => $attempt->status,
            'nextseq'    => $seq + 1,
            'metrics'    => helper::metrics($attempt),
            // The state goes with it, so a node can be shown the wording that matches
            // what this learner has already done. A resumed attempt carries its flags.
            'node'       => helper::node_payload(
                $node,
                $attempt,
                $mediaurls,
                $manager->decode_state($attempt)
            ),
            'resumed'    => $seq > 0,
            'journey'    => $journey,
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
            // What the learner has already decided, so a resumed attempt can show its own
            // history rather than starting the record from whatever happens next.
            'journey'   => new external_multiple_structure(
                new external_single_structure([
                    'seq'        => new external_value(PARAM_INT, 'Decision number'),
                    'nodetitle'  => new external_value(PARAM_TEXT, 'The decision point'),
                    'choicetext' => new external_value(PARAM_TEXT, 'What the learner chose'),
                    'signal'     => new external_value(PARAM_ALPHA, 'How the choice was judged'),
                ])
            ),
        ]);
    }
}
