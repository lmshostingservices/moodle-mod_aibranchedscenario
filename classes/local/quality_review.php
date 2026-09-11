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
 * Reads a working copy for scenarios that are playable but not worth playing.
 *
 * The validator answers a different question. It asks whether the graph holds together:
 * every target exists, nothing is unreachable, no cycles, no dead ends. A scenario can
 * satisfy every one of those and still be worthless — four choices that all lead to the
 * same node, skill scores that never move whatever the learner picks, three rephrasings
 * of one action. A generator that has just been taught to connect its nodes is exactly
 * the generator that connects them all to the same place.
 *
 * Nothing here rejects anything. These are warnings shown to the teacher on the draft
 * review page, because a slightly flat scenario is still the teacher's to publish, and a
 * refusal at this point would only send them back to a generator that would do the same
 * thing again. The point is that they see it before their learners do.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quality_review {
    /** @var int Percentage similarity at which two choice texts count as the same choice. */
    const SIMILAR_ENOUGH = 90;

    /**
     * @var int Below this length, two strings must match exactly to count as the same.
     *
     * Percentage similarity is meaningless on short text: "Consequence of A" and
     * "Consequence of B" are 94% alike and say completely different things, while any
     * two short sentences drawn from the same scene share most of their characters. The
     * fuzzy comparison only earns its place once there is enough text for the shared
     * characters to mean something.
     */
    const MIN_FUZZY_CHARS = 40;

    /**
     * Inspect a normalised working definition.
     *
     * @param array $definition A definition that has already passed the validator.
     * @return array List of ['nodeid' => string, 'node' => string, 'message' => string].
     *               Empty when nothing is worth saying.
     */
    public static function warnings(array $definition): array {
        $out = [];
        $nodes = $definition['nodes'] ?? [];
        if (!is_array($nodes) || !$nodes) {
            return $out;
        }

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'outcome') {
                continue;
            }
            $out = array_merge($out, self::node_warnings($node));
        }

        $scenario = self::scenario_warnings($nodes);
        return array_merge($scenario, $out);
    }

    /**
     * Warnings about one decision or beat node.
     *
     * @param array $node A normalised node.
     * @return array
     */
    protected static function node_warnings(array $node): array {
        $out = [];
        $id = (string)($node['id'] ?? '');
        $title = (string)($node['title'] ?? $id);
        $choices = array_values((array)($node['choices'] ?? []));
        $count = count($choices);

        $add = function (string $key, $a = null) use (&$out, $id, $title) {
            $out[] = [
                'nodeid'  => $id,
                'node'    => $title,
                'message' => get_string('quality:' . $key, 'mod_aibranchedscenario', $a),
            ];
        };

        // A beat that carries the story forward legitimately has one way on. A node that
        // asks the learner to decide and then offers a single answer is not a decision.
        if ($count === 1 && ($node['type'] ?? '') === 'decision') {
            $add('onechoice');
        }

        if ($count < 2) {
            return $out;
        }

        // Scores that never move. Reported separately from identical-but-nonzero deltas,
        // because "nothing you choose is scored" and "everything scores the same" read as
        // different problems to the person reading the page.
        $vectors = [];
        $allzero = true;
        foreach ($choices as $choice) {
            $vector = [];
            foreach (schema::skills() as $skill) {
                $value = (int)($choice['skills'][$skill] ?? 0);
                $vector[] = $value;
                if ($value !== 0) {
                    $allzero = false;
                }
            }
            $vectors[implode(',', $vector)] = true;
        }
        $flatscoring = $allzero || count($vectors) === 1;
        if ($allzero) {
            $add('noscoring');
        } else if (count($vectors) === 1) {
            $add('samescoring');
        }

        // Branching that does not branch — but only when nothing else distinguishes the
        // choices either.
        //
        // Choices deliberately converge in this plugin's whole architecture: branch and
        // bottleneck, where two routes rejoin and what the learner did on the way is
        // carried in the skill scores rather than in the path. A node whose choices share
        // a target but score differently is that pattern working correctly, and flagging
        // it would put a note on every well-built scenario until teachers stopped reading
        // the panel. It is only decorative when the target is the same AND the scoring is
        // the same, so nothing whatever turns on the answer.
        //
        // The auto target is exempt outright: it means "the ending this learner has
        // earned", which differs per learner by definition.
        $targets = [];
        foreach ($choices as $choice) {
            $targets[(string)($choice['next'] ?? '')] = true;
        }
        if (count($targets) === 1 && $flatscoring && !isset($targets[schema::auto_target()])) {
            $add('sametarget', $count);
        }

        // Choices that are the same choice worded differently, and consequences that do
        // not distinguish one choice from another.
        foreach (self::duplicate_pairs($choices, 'text') as $pair) {
            $add('duplicatechoice', (object)['a' => $pair[0], 'b' => $pair[1]]);
        }
        foreach (self::duplicate_pairs($choices, 'consequence') as $pair) {
            $add('duplicateconsequence', (object)['a' => $pair[0], 'b' => $pair[1]]);
        }

        return $out;
    }

    /**
     * Pairs of choices whose given field says substantially the same thing.
     *
     * @param array $choices Normalised choices.
     * @param string $field Field to compare.
     * @return array List of [letterA, letterB].
     */
    protected static function duplicate_pairs(array $choices, string $field): array {
        $pairs = [];
        $count = count($choices);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = self::normalise((string)($choices[$i][$field] ?? ''));
                $b = self::normalise((string)($choices[$j][$field] ?? ''));
                if ($a === '' || $b === '') {
                    continue;
                }
                $same = $a === $b;
                if (
                    !$same
                        && \core_text::strlen($a) >= self::MIN_FUZZY_CHARS
                        && \core_text::strlen($b) >= self::MIN_FUZZY_CHARS
                ) {
                    similar_text($a, $b, $percent);
                    $same = $percent >= self::SIMILAR_ENOUGH;
                }
                if ($same) {
                    $pairs[] = [
                        (string)($choices[$i]['letter'] ?? ($i + 1)),
                        (string)($choices[$j]['letter'] ?? ($j + 1)),
                    ];
                }
            }
        }
        return $pairs;
    }

    /**
     * Warnings about the scenario as a whole.
     *
     * @param array $nodes All normalised nodes.
     * @return array
     */
    protected static function scenario_warnings(array $nodes): array {
        $out = [];
        $outcomes = [];
        $decisions = 0;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'outcome') {
                $outcomes[(string)($node['id'] ?? '')] = true;
            } else if (($node['type'] ?? '') === 'decision') {
                $decisions++;
            }
        }

        // One ending reached by every route is a story, not a branching scenario. The
        // validator is satisfied by a single reachable outcome; a teacher should not be.
        if (count($outcomes) === 1 && $decisions >= 2) {
            $out[] = [
                'nodeid'  => '',
                'node'    => '',
                'message' => get_string('quality:oneoutcome', 'mod_aibranchedscenario'),
            ];
        }

        return $out;
    }

    /**
     * Reduce text to what it says, so wording differences do not hide a repeated choice.
     *
     * @param string $value Raw text.
     * @return string
     */
    protected static function normalise(string $value): string {
        $value = \core_text::strtolower(strip_tags($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', (string)$value));
    }
}
