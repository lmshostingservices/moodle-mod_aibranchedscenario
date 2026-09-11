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
        // Spelling is a property of the whole document, not of any node, so it is checked
        // before the node walk and survives the early return below. It did not, and the
        // check quietly never ran on a definition whose nodes had not been read yet.
        $spelling = self::spelling_warnings($definition);
        $nodes = $definition['nodes'] ?? [];
        if (!is_array($nodes) || !$nodes) {
            return $spelling;
        }

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'outcome') {
                continue;
            }
            $out = array_merge($out, self::node_warnings($node));
        }

        $scenario = self::scenario_warnings($nodes);
        return array_merge($spelling, $scenario, $out);
    }

    /**
     * Words spelled for the wrong variety of English.
     *
     * A teacher picks en-AU and the writing comes back with "finalizing" and "organize" in
     * it. The prompt asks for the chosen variety in as many words, but asking is not the
     * same as getting, and an RTO sending this to learners will be the one who notices.
     *
     * This does not rewrite anything. It reads the scenario the service returned and says
     * which words disagree with the language that was asked for, so the teacher can fix
     * them in the editor before publishing rather than after a learner reads them. The list
     * is a closed set of common pairs, not a rule about suffixes, because a rule about
     * suffixes flags "size" and "prize" and stops being worth reading.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function spelling_warnings(array $definition): array {
        $language = (string)($definition['language'] ?? '');
        // Only the English varieties differ this way, and only these three differ from
        // each other in a way a reader notices.
        $british = ['en-AU', 'en-GB', 'en-NZ'];
        if (!in_array($language, array_merge($british, ['en-US']), true)) {
            return [];
        }
        $wantsbritish = in_array($language, $british, true);

        // Pairs written as american => british. "practice" and "license" are deliberately
        // absent: in British and Australian English both are correct as nouns and wrong
        // only as verbs, and a checker that cannot tell one from the other would flag the
        // correct spelling about as often as the wrong one.
        $pairs = [
            'analyze' => 'analyse', 'apologize' => 'apologise', 'authorize' => 'authorise',
            'behavior' => 'behaviour', 'canceled' => 'cancelled', 'center' => 'centre',
            'color' => 'colour', 'criticize' => 'criticise', 'defense' => 'defence',
            'emphasize' => 'emphasise', 'favor' => 'favour', 'finalize' => 'finalise',
            'fulfill' => 'fulfil', 'honor' => 'honour', 'judgment' => 'judgement',
            'labor' => 'labour', 'minimize' => 'minimise',
            'neighbor' => 'neighbour', 'organize' => 'organise',
            'prioritize' => 'prioritise', 'realize' => 'realise', 'recognize' => 'recognise',
            'summarize' => 'summarise', 'traveled' => 'travelled', 'utilize' => 'utilise',
        ];

        $text = \core_text::strtolower(self::all_prose($definition));
        $found = [];
        foreach ($pairs as $american => $britishword) {
            $wrong = $wantsbritish ? $american : $britishword;
            // Matched as a stem, with the trailing e taken off first, so that finalize,
            // finalizes, finalized and finalizing all count. Adding a suffix to the whole
            // word instead gave "finalize" + "ing" = "finalizeing", which matches nothing,
            // so the check silently found no problems in text full of them. Bounded at both
            // ends, so "color" does not fire on "Colorado".
            $stem = preg_quote(rtrim($wrong, 'e'), '/');
            if (preg_match('/\b' . $stem . '(e|es|ed|ing|er|ers|ation|ations|s|ly)?\b/u', $text)) {
                $found[$wrong] = $wantsbritish ? $britishword : $american;
            }
        }
        if (!$found) {
            return [];
        }
        $shown = array_slice(array_keys($found), 0, 8);
        return [[
            'nodeid'  => '',
            'node'    => get_string('quality:spellingnode', 'mod_aibranchedscenario'),
            'message' => get_string('quality:spelling', 'mod_aibranchedscenario', (object)[
                'language' => $language,
                'words'    => implode(', ', $shown),
            ]),
        ]];
    }

    /**
     * Every piece of prose in a definition, run together for a text search.
     *
     * @param array $definition A validated definition.
     * @return string
     */
    protected static function all_prose(array $definition): string {
        $bits = [];
        $walk = function ($value) use (&$walk, &$bits) {
            if (is_string($value)) {
                $bits[] = $value;
                return;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };
        $walk($definition);
        return implode(' ', $bits);
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
