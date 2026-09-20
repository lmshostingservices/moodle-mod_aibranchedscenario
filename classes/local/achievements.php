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

namespace mod_aibranchedscenario\local;

/**
 * What a learner did, named.
 *
 * NOT POINTS, AND NOT "ANSWERED FIVE QUESTIONS".
 *
 * An achievement that counts actions rewards persistence, which the learner already had.
 * These name BEHAVIOURS - the ones the scenario exists to teach - so that reading the list
 * afterwards tells somebody something true about how they handled it rather than how far
 * they got. "You held a safe decision while somebody senior was leaning on you" is a
 * sentence a learner can take back to work. "You scored 82%" is not.
 *
 * DERIVED, NEVER AUTHORED. Every one of these is worked out from the decisions already
 * recorded against the attempt, so a teacher writes nothing extra, no table stores
 * anything extra, and a scenario written before any of this existed earns them the moment
 * a learner plays it. The cost of adding one is a function; the cost of authoring one
 * would have been a new field on every scenario ever written.
 *
 * DERIVED ALSO MEANS HONEST. The product cannot award "held your ground" to a scenario
 * where nobody was pushing, because the pressure has to be in the data for the test to
 * pass at all.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievements {
    /**
     * @var int How settled the room has to be at the end to count as leaving nothing behind.
     *
     * Expressed against the teacher's own red band rather than as a number of its own: a
     * scenario whose bands the teacher has moved has moved what "settled" means, and an
     * achievement that ignored that would be congratulating a learner on the wrong thing.
     */
    const SETTLED_MARGIN = 10;

    /**
     * Everything this learner earned, in the order it would read best.
     *
     * @param array $journey The decisions taken, from attempt_manager::build_journey().
     * @param array $metrics The closing readings, keyed by metric.
     * @param int $bandred Where the teacher says a reading becomes a problem.
     * @return array List of ['id', 'hidden'] for each earned achievement.
     */
    public static function earned(array $journey, array $metrics, int $bandred): array {
        $out = [];

        // A decision taken well WHILE SOMEBODY WAS PUSHING is the whole point of putting
        // pressure in a scenario, and it is the one thing a multiple-choice question can
        // never test. Awarded on the pressure that was actually in the room, so a scenario
        // where nobody pushed cannot earn it.
        foreach ($journey as $step) {
            if (!empty($step['underpressure']) && ($step['signal'] ?? '') === 'positive') {
                $out[] = ['id' => 'groundheld', 'hidden' => false];
                break;
            }
        }

        // RECOVERY IS A SKILL, and it is the one a scenario can teach that a test cannot.
        // A learner who chose badly, saw what it cost and then put it right has done
        // something harder than a learner who never slipped - and the product used to tell
        // them only that they had lost marks.
        $slipped = false;
        foreach ($journey as $step) {
            $signal = $step['signal'] ?? '';
            if ($signal === 'negative') {
                $slipped = true;
                continue;
            }
            if ($slipped && $signal === 'positive') {
                $out[] = ['id' => 'recovered', 'hidden' => false];
                break;
            }
        }

        // Every decision the best available. Hidden, because a learner who is told this is
        // possible starts replaying to collect it rather than deciding, which is the exact
        // behaviour the whole product is built to avoid.
        $decisions = count($journey);
        $best = count(array_filter($journey, static function ($step) {
            return ($step['signal'] ?? '') === 'positive';
        }));
        if ($decisions > 0 && $best === $decisions) {
            $out[] = ['id' => 'cleanrun', 'hidden' => true];
        }

        // Nothing left behind: the room is calmer than the teacher's own problem line by a
        // clear margin, and trust has not been spent to get there. A scenario can be
        // finished with a good score and a wrecked room, and that is worth distinguishing.
        $tension = (int)($metrics['tension'] ?? 100);
        $trust = (int)($metrics['trust'] ?? 0);
        if ($decisions > 0 && $tension <= max(0, $bandred - self::SETTLED_MARGIN) && $trust >= $bandred) {
            $out[] = ['id' => 'nothingleft', 'hidden' => false];
        }

        return $out;
    }

    /**
     * The earned achievements, ready for a template.
     *
     * @param array $journey The decisions taken.
     * @param array $metrics The closing readings.
     * @param int $bandred Where a reading becomes a problem.
     * @return array
     */
    public static function cards(array $journey, array $metrics, int $bandred): array {
        $cards = [];
        foreach (self::earned($journey, $metrics, $bandred) as $earned) {
            $cards[] = [
                'id'     => $earned['id'],
                'hidden' => $earned['hidden'],
                'title'  => get_string('award:' . $earned['id'], 'mod_aibranchedscenario'),
                'detail' => get_string('award:' . $earned['id'] . 'detail', 'mod_aibranchedscenario'),
            ];
        }
        return $cards;
    }
}
