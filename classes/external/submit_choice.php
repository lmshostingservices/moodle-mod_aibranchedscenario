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
use mod_aibranchedscenario\local\media_manager;

/**
 * Records a decision and returns its consequence and the next node.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_choice extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module id'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt identifier'),
            'nodeid'    => new external_value(PARAM_ALPHANUMEXT, 'Node the learner is answering'),
            'choiceid'  => new external_value(PARAM_ALPHANUMEXT, 'Chosen choice identifier'),
            'seq'       => new external_value(PARAM_INT, 'Sequence number of this decision, starting at 1'),
            // DECLARED LAST, AND IT MATTERS. The external dispatcher validates arguments by
            // name and then calls execute() POSITIONALLY in this declaration order, so a
            // parameter inserted in the middle of this list silently shifts every argument
            // after it. That fault shipped once, in v2.5.1, and cost three releases.
            //
            // VALUE_DEFAULT rather than required, because a browser holding a cached copy of
            // the player will not send it, and a learner mid-attempt when the site upgrades
            // must not have their next decision refused.
            'unsure'    => new external_value(
                PARAM_BOOL,
                'Whether the learner said they were not sure, before seeing the outcome',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Record the decision.
     *
     * @param int $cmid Course module id.
     * @param int $attemptid Attempt identifier.
     * @param string $nodeid Node identifier.
     * @param string $choiceid Choice identifier.
     * @param int $seq Sequence number.
     * @param bool $unsure Whether the learner said they were not sure.
     * @return array
     */
    public static function execute(
        int $cmid,
        int $attemptid,
        string $nodeid,
        string $choiceid,
        int $seq,
        bool $unsure = false
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'attemptid' => $attemptid, 'nodeid' => $nodeid,
            'choiceid' => $choiceid, 'seq' => $seq, 'unsure' => $unsure,
        ]);
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:attempt');

        $attempt = attempt_manager::get_owned_attempt($params['attemptid'], (int)$USER->id);
        if (!$attempt || (int)$attempt->scenarioid !== (int)$resolved['scenario']->id) {
            throw new \moodle_exception('error:attemptnotfound', 'mod_aibranchedscenario');
        }

        $manager = attempt_manager::for_attempt($resolved['scenario'], $attempt);
        $result = $manager->submit_choice(
            $attempt,
            $params['nodeid'],
            $params['choiceid'],
            $params['seq'],
            (bool)$params['unsure']
        );

        $node = $manager->get_node($result['nextnodeid']);
        if (!$node) {
            throw new \moodle_exception('error:unknownnode', 'mod_aibranchedscenario');
        }

        if ($result['finished']) {
            helper::on_attempt_finished($resolved['cm'], $resolved['scenario'], $attempt);
        }

        $mediaurls = helper::media_urls($resolved['context'], $manager->get_revision());

        return [
            'seq'            => (int)$result['seq'],
            'signal'         => $result['signal'],
            'consequence'    => $result['consequence'],
            'consequenceparas' => helper::paragraph_list($result['consequence']),
            'feedback'       => $result['feedback'],
            'feedbackparas'   => helper::paragraph_list($result['feedback']),
            'principle'      => $result['principle'],
            // Narration for the branch the learner actually took. Stored under the choice
            // id alongside the node clips.
            'audiourl'       => $mediaurls['narration'][$params['choiceid']] ?? '',
            // THE REACTION SHOT, for the screen that used to redraw the decision.
            //
            // A frame per outcome signal, generated with the scenario, so the consequence
            // shows what the decision did to somebody rather than the room it was taken in.
            // Empty for a scenario generated before v1.81.0, or where that one frame
            // failed - the player then falls back to the decision's own picture, which is
            // what it always showed and is better than an empty column.
            'reactionimageurl' => (string)($mediaurls['scene'][media_manager::reaction_key(
                (string)$params['nodeid'],
                (string)$result['signal']
            )] ?? ''),
            'before'         => $result['before'],
            'after'          => $result['after'],
            'finished'       => (bool)$result['finished'],
            // Decoded AFTER the choice was submitted, so a flag this very choice set is
            // already true for the screen it leads to - which is what makes an immediate
            // consequence read as caused rather than as coincidence.
            'node'           => helper::node_payload(
                $node,
                $attempt,
                $mediaurls,
                $manager->decode_state($attempt)
            ),
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'seq'             => new external_value(PARAM_INT, 'Sequence number recorded'),
            'signal'          => new external_value(PARAM_ALPHA, 'Whether the choice read as positive, neutral or negative'),
            'consequence'     => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'What happened next'
            ),
            'consequenceparas' => helper::paragraphs_structure('Consequence as plain text; escape before use as HTML'),
            'feedback'        => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'Instructional feedback'
            ),
            'feedbackparas'    => helper::paragraphs_structure('Feedback as plain text; escape before use as HTML'),
            'principle'       => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'Decision principle this choice tested'
            ),
            'audiourl'        => new external_value(PARAM_URL, 'Narration for this consequence, or empty'),
            'reactionimageurl' => new external_value(
                PARAM_URL,
                'The reaction frame for this outcome, or empty to fall back to the scene',
                VALUE_DEFAULT,
                ''
            ),
            'before'          => helper::metrics_structure(),
            'after'           => helper::metrics_structure(),
            'finished'        => new external_value(PARAM_BOOL, 'Whether the attempt has now finished'),
            'node'            => helper::node_structure(),
        ]);
    }
}
