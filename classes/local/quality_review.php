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
    /**
     * @var int How many decisions must send a poor choice somewhere a good one does not.
     *
     * Not all five. Branch-and-bottleneck means paths REJOIN, so a well-built scenario
     * legitimately has decisions whose options converge - what it must not have is a
     * scenario where that is true of all of them, which is a linear lesson with meters.
     */
    const MIN_BRANCHING_DECISIONS = 2;

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
     * @var int How many of the five decisions must have somebody speaking at the learner.
     *
     * Pressure arrives through people. Two is the floor rather than five, because a
     * scenario where every single decision has somebody leaning on the learner stops being
     * pressure and becomes noise.
     */
    const MIN_SPOKEN_DECISIONS = 2;

    /**
     * @var int Below this many decisions, a definition is a fragment rather than a scenario.
     *
     * Both the trade-off check and the pressure check are properties of a WHOLE scenario -
     * "most of the decisions decide nothing", "nobody anywhere puts the learner under
     * pressure" - and neither means anything about a single node examined on its own. The
     * shipped shape is five decisions; anything under three is a piece of one.
     */
    const MIN_DECISIONS_TO_JUDGE = 3;

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
        // Principles are a property of the document too, and like spelling they have to be
        // checked before the node walk or they are lost to the early return below.
        $spelling = array_merge($spelling, self::principle_warnings($definition));
        // The debrief is a property of the document as well, and it is the part a learner
        // reaches last and remembers longest.
        $spelling = array_merge($spelling, self::debrief_warnings($definition));
        $spelling = array_merge($spelling, self::reading_range_warnings($definition));
        $spelling = array_merge($spelling, self::branching_warnings($definition));
        // Whether the decisions are decisions at all, which is the thing every other
        // mechanism in the product depends on.
        $spelling = array_merge($spelling, self::tradeoff_warnings($definition));
        $spelling = array_merge($spelling, self::pressure_warnings($definition));
        // Pronouns are a property of the whole document too: the sentence that contradicts
        // a cast record can be anywhere in it.
        $spelling = array_merge($spelling, self::pronoun_warnings($definition));
        // And the scoring vocabulary, which is supposed to stay in the debrief.
        $spelling = array_merge($spelling, self::skillname_warnings($definition));
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
     * Is every option on a decision either plainly good or plainly bad?
     *
     * THE ONE CHECK THE REST OF THE PRODUCT DEPENDS ON.
     *
     * Branching, carried-forward flags, recovery paths, delayed consequences and the
     * debrief's comparison of the road taken to the roads not taken all assume a learner
     * might genuinely take the weaker road. If the weaker options are obviously weak, none
     * of that machinery ever runs: every learner takes the good path, the alternative
     * scenes are dead content nobody is shown, and the scenario measures whether somebody
     * can read rather than whether they can act.
     *
     * A tempting option is tempting because it GAINS something. "Move it out of the
     * walkway and finish the job first" buys time and costs safety; that is a decision.
     * "Carry on using the damaged lead" buys nothing and costs everything; that is a
     * comprehension question wearing a scenario's clothes.
     *
     * So the test is mechanical and does not need to read the prose: on a real decision at
     * least one option trades - it moves at least one reading the right way and at least
     * one the wrong way. Tension reads upside down, so it is flipped before comparing.
     *
     * @param array $definition The working definition.
     * @return array Warnings.
     */
    protected static function tradeoff_warnings(array $definition): array {
        $out = [];
        $flat = [];
        $decisions = 0;
        foreach (($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $decisions++;
            $trades = false;
            foreach (($node['choices'] ?? []) as $choice) {
                if (self::choice_trades($choice)) {
                    $trades = true;
                    break;
                }
            }
            if (!$trades) {
                $flat[] = $node['title'] !== '' ? $node['title'] : $node['id'];
            }
        }

        // One flat decision in five is a bottleneck a designer may have meant - a moment
        // where there genuinely is only one defensible answer. Most of them flat is a
        // scenario where nothing is being decided.
        if (
            $decisions >= self::MIN_DECISIONS_TO_JUDGE
                && count($flat) > max(1, (int)floor($decisions / 2))
        ) {
            $out[] = [
                'nodeid'  => '',
                'node'    => trim((string)($definition['title'] ?? '')),
                'message' => get_string(
                    'quality:notradeoff',
                    'mod_aibranchedscenario',
                    (object)['flat' => count($flat), 'total' => $decisions,
                    'names' => implode(
                        ', ',
                        array_slice($flat, 0, 3)
                    )]
                ),
            ];
        }
        return $out;
    }

    /**
     * Does this option buy the learner something as well as costing them something?
     *
     * @param array $choice A validated choice.
     * @return bool
     */
    protected static function choice_trades(array $choice): bool {
        $gains = false;
        $costs = false;
        foreach (schema::metrics() as $metric) {
            $effect = (int)($choice['effects'][$metric] ?? 0);
            if ($effect === 0) {
                continue;
            }
            // Tension is the one reading where less is better, so its sign is flipped
            // before it is compared with the others.
            $good = $metric === 'tension' ? -$effect : $effect;
            if ($good > 0) {
                $gains = true;
            } else {
                $costs = true;
            }
        }
        return $gains && $costs;
    }

    /**
     * Is anybody putting the learner under pressure?
     *
     * Pressure is what makes a conduct or safety decision hard, and it arrives through
     * people: somebody says the job is late, or that they have done it this way for
     * twenty years, or that the customer is waiting. A scenario where nobody speaks is a
     * scenario where the careful option costs the learner nothing to take.
     *
     * Checked structurally rather than by hunting for phrases: a decision carries a spoken
     * line or it does not, and a word list would only ever find the pressures somebody
     * thought to list.
     *
     * @param array $definition The working definition.
     * @return array Warnings.
     */
    protected static function pressure_warnings(array $definition): array {
        $decisions = 0;
        $spoken = 0;
        foreach (($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $decisions++;
            if (trim((string)($node['facilitatorspeech'] ?? '')) !== '') {
                $spoken++;
            }
        }
        if ($decisions < self::MIN_DECISIONS_TO_JUDGE || $spoken >= self::MIN_SPOKEN_DECISIONS) {
            return [];
        }
        return [[
            'nodeid'  => '',
            'node'    => trim((string)($definition['title'] ?? '')),
            'message' => get_string(
                'quality:nopressure',
                'mod_aibranchedscenario',
                (object)['spoken' => $spoken, 'wanted' => self::MIN_SPOKEN_DECISIONS]
            ),
        ]];
    }

    /**
     * Principles taught as a rule and nothing else.
     *
     * A principle carries an example - the words a learner could actually say - and a
     * pitfall - the plausible version that does not work. Without them the teaching slide
     * states a rule and stops, which is the thing a scenario exists to avoid.
     *
     * This used to reject the whole definition. It was the wrong lever: by the time the
     * definition is read the teacher has been charged, so a scenario that was otherwise
     * sound was thrown away, and their credits with it, over two sentences a human can
     * write in a few seconds. It is said here instead, where the teacher is already
     * reading the scenario and can type the missing line into the editor before
     * publishing.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function principle_warnings(array $definition): array {
        $out = [];
        foreach ((array)($definition['principles'] ?? []) as $principle) {
            if (!is_array($principle)) {
                continue;
            }
            $title = trim((string)($principle['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            foreach (['example', 'pitfall'] as $needed) {
                if (trim((string)($principle[$needed] ?? '')) !== '') {
                    continue;
                }
                $out[] = [
                    'nodeid'  => (string)($principle['id'] ?? ''),
                    'node'    => $title,
                    'message' => get_string(
                        'quality:principleneeds' . $needed,
                        'mod_aibranchedscenario',
                        $title
                    ),
                ];
            }
        }
        return $out;
    }

    /**
     * Prose that calls a character by a pronoun their record contradicts.
     *
     * The gender on the cast record and the pronoun in the sentence are produced by two
     * different steps and, until now, were never compared by anything. So a scenario could
     * carry a record saying Alex is female and prose saying "He feels some decisions are
     * not well thought out", and the picture - briefed from the record - showed a woman
     * beside the word "he". Every check in the plugin passed. The learner saw it in a
     * second.
     *
     * Only sentences that name the character are read. A pronoun three paragraphs away
     * belongs to somebody else, and a scenario with two people in it would otherwise flag
     * on every page.
     *
     * Reported rather than rejected, for the same reason the principle check reports: the
     * teacher has been charged, and a pronoun is a one-word fix in the editor. What it will
     * not do is stay silent.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function pronoun_warnings(array $definition): array {
        $people = array_merge(
            [$definition['facilitator'] ?? []],
            (array)($definition['characters'] ?? [])
        );
        // Narrative only. all_prose() walks the WHOLE definition, cast records included, so
        // reading it here matched every character's own name field against the name fields
        // beside it and reported a conflict on a scenario whose prose never mentioned them.
        // Caught by the first test written for this check, which is the argument for
        // writing the test before believing the code.
        $prose = self::narrative_prose($definition);
        $out = [];
        $seen = [];
        foreach ($people as $person) {
            if (!is_array($person)) {
                continue;
            }
            $name = trim((string)($person['name'] ?? ''));
            $gender = (string)($person['gender'] ?? '');
            // Nothing to contradict: an unstated gender is not a conflict, it is a gap,
            // and the image brief handles a gap by saying nothing rather than by guessing.
            if ($name === '' || ($gender !== 'male' && $gender !== 'female')) {
                continue;
            }
            if (in_array($name, $seen, true)) {
                continue;
            }
            $seen[] = $name;
            $wrong = $gender === 'male'
                ? '/\b(she|her|hers|herself)\b/iu'
                : '/\b(he|him|his|himself)\b/iu';
            $others = [];
            foreach ($people as $other) {
                $othername = is_array($other) ? trim((string)($other['name'] ?? '')) : '';
                if ($othername !== '' && $othername !== $name) {
                    $others[] = $othername;
                }
            }
            $window = self::sentences_naming($prose, $name, $others);
            if ($window === '' || !preg_match($wrong, $window, $match)) {
                continue;
            }
            $out[] = [
                'nodeid'  => 'cast_' . \core_text::strtolower($name),
                'node'    => $name,
                'message' => get_string(
                    'quality:pronounconflict',
                    'mod_aibranchedscenario',
                    (object)[
                        'name'    => $name,
                        'gender'  => get_string(
                            'gender:' . ($gender === 'male' ? 'male' : 'female'),
                            'mod_aibranchedscenario'
                        ),
                        'pronoun' => \core_text::strtolower($match[1]),
                    ]
                ),
            ];
        }
        return $out;
    }

    /**
     * The scoring vocabulary appearing in the scenario itself.
     *
     * The four skills - presence, adaptability, empathy, clarity - are how the debrief
     * describes what a learner did. They are not words that belong in the story: a scene
     * that says "this calls for empathy" is showing the learner the marking scheme while
     * they are being marked, and it tells them which option to pick without teaching them
     * anything about why.
     *
     * The content standard has asked for this since v1.44.0 and nothing has ever read the
     * generated text back to see whether it was obeyed - so the rule was a request. Asking
     * is not the same as getting, which this plugin has now learned in four separate places.
     *
     * Whole words only. "Clarity" is a skill name; "clarify the deadline" is a person doing
     * their job, and a check that flags the second stops being read.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function skillname_warnings(array $definition): array {
        $out = [];
        $skills = schema::skills();
        $places = [];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (!is_array($node)) {
                continue;
            }
            $id = (string)($node['id'] ?? '');
            $title = trim((string)($node['title'] ?? '')) ?: $id;
            foreach (['situation', 'challenge', 'facilitatorspeech', 'summary'] as $field) {
                $places[] = [$id, $title, (string)($node[$field] ?? '')];
            }
            foreach ((array)($node['choices'] ?? []) as $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                foreach (['text', 'consequence', 'feedback'] as $field) {
                    $places[] = [$id, $title, (string)($choice[$field] ?? '')];
                }
            }
        }
        // The teaching slides count too: a principle named after a skill teaches the
        // scoreboard rather than the job.
        foreach ((array)($definition['principles'] ?? []) as $principle) {
            if (!is_array($principle)) {
                continue;
            }
            $title = trim((string)($principle['title'] ?? ''));
            foreach (['title', 'summary', 'example', 'pitfall'] as $field) {
                $places[] = [(string)($principle['id'] ?? ''), $title, (string)($principle[$field] ?? '')];
            }
        }

        $seen = [];
        foreach ($places as $place) {
            [$id, $title, $text] = $place;
            if (trim($text) === '') {
                continue;
            }
            foreach ($skills as $skill) {
                if (isset($seen[$id . '|' . $skill])) {
                    continue;
                }
                if (!preg_match('/\b' . preg_quote($skill, '/') . '\b/iu', $text)) {
                    continue;
                }
                $seen[$id . '|' . $skill] = true;
                $out[] = [
                    'nodeid'  => $id,
                    'node'    => $title,
                    'message' => get_string(
                        'quality:skillnameleak',
                        'mod_aibranchedscenario',
                        (object)['skill' => $skill, 'where' => $title !== '' ? $title : $id]
                    ),
                ];
            }
        }
        return $out;
    }

    /**
     * The text a learner actually reads, and nothing else.
     *
     * Distinct from all_prose(), which walks everything in the definition including ids,
     * filenames and the cast records themselves. For a pronoun check that distinction is
     * the difference between reading a story and reading a database row.
     *
     * @param array $definition A validated definition.
     * @return string
     */
    protected static function narrative_prose(array $definition): string {
        $bits = [
            (string)($definition['hook'] ?? ''),
            (string)($definition['setting'] ?? ''),
        ];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (!is_array($node)) {
                continue;
            }
            foreach (['situation', 'challenge', 'facilitatorspeech', 'summary', 'body'] as $field) {
                $bits[] = (string)($node[$field] ?? '');
            }
            foreach ((array)($node['crisis'] ?? []) as $value) {
                if (is_string($value)) {
                    $bits[] = $value;
                }
            }
            foreach ((array)($node['choices'] ?? []) as $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                // The field is 'text', not 'label'. The validator stores a choice's wording under
                // 'text' (validator.php:956) and this read 'label', which does not exist on
                // a validated choice - so every option's wording was excluded from the
                // pronoun check and a female Alex called "he" inside an option shipped
                // silently. The fixtures written for the check used hand-built arrays with
                // 'label' in them, so the test agreed with the bug. Fixtures are built
                // through the validator now, which is the only way a fixture can disagree
                // with the code it is testing.
                foreach (['text', 'label', 'consequence', 'feedback'] as $field) {
                    $bits[] = (string)($choice[$field] ?? '');
                }
            }
        }
        // The teaching slides are prose a learner reads, and were left out: a pronoun
        // conflict in a principle's summary, example or pitfall was invisible.
        foreach ((array)($definition['principles'] ?? []) as $principle) {
            if (!is_array($principle)) {
                continue;
            }
            foreach (['title', 'summary', 'example', 'pitfall'] as $field) {
                $bits[] = (string)($principle[$field] ?? '');
            }
        }
        // The outcome notes ARE the debrief now, so they are what the spelling check has to
        // read. The four lists it used to read no longer exist, and a spelling check that
        // stopped covering the debrief the day the debrief moved is a check that reports
        // clean because it is looking at nothing.
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            foreach ((array)($node['choices'] ?? []) as $choice) {
                $bits[] = (string)($choice['outcomenote'] ?? '');
            }
        }
        return implode(' ', array_filter($bits, fn($bit) => trim($bit) !== ''));
    }

    /**
     * The sentences that mention a person, and the one immediately after each.
     *
     * A pronoun usually lands in the sentence after the one that introduced the name -
     * "Alex reads the roster. She has been here twice this week" - so the following
     * sentence is part of the window. Two sentences is where the confidence stops: past
     * that the pronoun is as likely to belong to whoever was named next.
     *
     * @param string $prose All the scenario's text.
     * @param string $name The person's name.
     * @param string[] $others Everybody else's names.
     * @return string The sentences that concern them, joined.
     */
    protected static function sentences_naming(string $prose, string $name, array $others = []): string {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $prose) ?: [];
        $quoted = preg_quote($name, '/');
        $window = [];
        foreach ($sentences as $index => $sentence) {
            if (!preg_match('/\b' . $quoted . '\b/iu', $sentence)) {
                continue;
            }
            $window[$index] = $sentence;
            $next = $sentences[$index + 1] ?? null;
            if ($next === null) {
                continue;
            }
            // Not if the next sentence names somebody else - then the pronoun in it is as
            // likely to be theirs, and reading it here is how a check earns a reputation
            // for crying wolf and stops being read.
            foreach ($others as $other) {
                if ($other !== '' && preg_match('/\b' . preg_quote($other, '/') . '\b/iu', $next)) {
                    continue 2;
                }
            }
            $window[$index + 1] = $next;
        }
        return implode(' ', $window);
    }

    /**
     * Decisions whose three options do not make a comparison.
     *
     * WHAT THIS REPLACED, AND WHY.
     *
     * It used to count the four closing lists against the principles taught - three
     * principles, three lessons learnt, three takeaways - because the debrief was those
     * lists and a short one meant a principle was never looked back at.
     *
     * The debrief is not lists any more. Each decision gets a slide showing every option
     * with the paragraph saying where it leads, and the learning is the COMPARISON between
     * them. So the thing worth reporting changed with it: a slide only teaches anything if
     * its three notes are three different outcomes. Three paragraphs that say the same
     * thing, or a note that only restates the option it belongs to, produce a slide that
     * looks complete and carries nothing.
     *
     * Reports rather than rejects, like everything else here: the teacher has been charged
     * by the time the definition is read, and these are sentences they can edit.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function debrief_warnings(array $definition): array {
        $out = [];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $choices = (array)($node['choices'] ?? []);
            if (count($choices) < 2) {
                continue;
            }
            $title = trim((string)($node['title'] ?? '')) ?: (string)($node['id'] ?? '');

            // A slide that cannot say which option was best and which worst is not a
            // comparison. The signals are what the slide bands its options by, so all
            // three carrying the same one leaves a learner three paragraphs and no ranking.
            $signals = [];
            foreach ($choices as $choice) {
                $signals[(string)($choice['signal'] ?? 'neutral')] = true;
            }
            if (count($signals) < 2) {
                $out[] = [
                    'nodeid'  => (string)($node['id'] ?? ''),
                    'node'    => $title,
                    'message' => get_string('quality:flatsignals', 'mod_aibranchedscenario', $title),
                ];
            }

            // Notes that repeat each other. Compared on their normalised words rather than
            // character for character: the failure this catches is the model writing one
            // sentiment three times, which rarely comes back byte-identical.
            $seen = [];
            foreach ($choices as $choice) {
                $note = self::normalise_for_compare((string)($choice['outcomenote'] ?? ''));
                if ($note === '') {
                    continue;
                }
                if (isset($seen[$note])) {
                    $out[] = [
                        'nodeid'  => (string)($node['id'] ?? ''),
                        'node'    => $title,
                        'message' => get_string(
                            'quality:duplicateoutcomenote',
                            'mod_aibranchedscenario',
                            $title
                        ),
                    ];
                    break;
                }
                $seen[$note] = true;
            }

            // A note the plugin assembled rather than one the writer wrote. Derived notes
            // are the consequence and the feedback run together - the scenario's own words,
            // and better than a hole - but they were written for a different screen, so
            // they read as a summary of what happened rather than as what this option
            // would have cost. Reported once per decision, because a teacher who sees it
            // on every slide learns nothing from the fifth.
            foreach ($choices as $choice) {
                $note = trim((string)($choice['outcomenote'] ?? ''));
                $said = trim((string)($choice['consequence'] ?? ''))
                    . ' ' . trim((string)($choice['feedback'] ?? ''));
                $joined = trim($said);
                if ($note === '' || $joined === '') {
                    continue;
                }
                if (self::normalise_for_compare($note) === self::normalise_for_compare($joined)) {
                    $out[] = [
                        'nodeid'  => (string)($node['id'] ?? ''),
                        'node'    => $title,
                        'message' => get_string(
                            'quality:derivedoutcomenote',
                            'mod_aibranchedscenario',
                            $title
                        ),
                    ];
                    break;
                }
            }

            // A note that only says the option again. The paragraph exists to say what
            // taking it COSTS; one that restates the option teaches nothing and reads, on
            // the slide, as the same line printed twice.
            foreach ($choices as $choice) {
                $note = self::normalise_for_compare((string)($choice['outcomenote'] ?? ''));
                $text = self::normalise_for_compare((string)($choice['text'] ?? ''));
                if ($note === '' || $text === '') {
                    continue;
                }
                if (
                    $note === $text || ($note !== '' && strpos($note, $text) === 0
                        && strlen($note) < strlen($text) * 1.5)
                ) {
                    $out[] = [
                        'nodeid'  => (string)($node['id'] ?? ''),
                        'node'    => $title,
                        'message' => get_string(
                            'quality:restatedoutcomenote',
                            'mod_aibranchedscenario',
                            (object)['node' => $title, 'letter' => (string)($choice['letter'] ?? '')]
                        ),
                    ];
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Decisions that do not branch, in a product called AI Branched Scenario.
     *
     * THE RULE WAS WRITTEN DOWN ON DAY ONE AND NOTHING EVER READ IT.
     *
     * The route contract the service is built against has said since v1, rule 7: "different
     * choices lead to genuinely different nodes... do not emit a graph in which every
     * choice from a node points at the same target." Nothing checked it. It was not in this
     * plugin's own prompt or content standard at all, and the scenario shipped inside the
     * plugin as its worked example - the one teachers copy - broke it on three of its five
     * decisions.
     *
     * What that produces is a linear lesson with mood meters: a choice moves three numbers,
     * changes one paragraph you read once, and hands you the same next decision as
     * everybody else. The graph was always able to branch. The content never did, and no
     * check could see it.
     *
     * A BEAT IS NOT A BRANCH. A node with one way off it that rejoins immediately is a
     * transition - two of them side by side are two paragraphs, not two paths - so the
     * targets are followed through beats before being compared. That distinction is the
     * whole difference between this check working and this check agreeing with the fault.
     *
     * Reports rather than rejects, like everything else here: a scenario that does not
     * branch is still playable, and the teacher has already been charged by the time the
     * definition is read.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function branching_warnings(array $definition): array {
        $nodes = [];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (is_array($node) && trim((string)($node['id'] ?? '')) !== '') {
                $nodes[(string)$node['id']] = $node;
            }
        }

        // Where a choice ACTUALLY lands, which is not where it points: a beat is a
        // paragraph with one way off it, and two choices that reach the same decision
        // through two different beats have not branched, they have read different prose.
        $lands = function (string $target) use ($nodes) {
            $seen = [];
            while (
                isset($nodes[$target])
                    && ($nodes[$target]['type'] ?? '') === 'beat'
                    && !isset($seen[$target])
            ) {
                $seen[$target] = true;
                $onward = '';
                foreach ((array)($nodes[$target]['choices'] ?? []) as $choice) {
                    $onward = (string)($choice['next'] ?? '');
                    break;
                }
                $onward = $onward ?: (string)($nodes[$target]['next'] ?? '');
                if ($onward === '' || $onward === schema::auto_target()) {
                    break;
                }
                $target = $onward;
            }
            return $target;
        };

        $out = [];
        $flat = [];
        $decisions = 0;
        $branching = 0;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $choices = (array)($node['choices'] ?? []);
            if (count($choices) < 2) {
                continue;
            }
            $decisions++;
            $title = trim((string)($node['title'] ?? '')) ?: (string)($node['id'] ?? '');
            $targets = [];
            $auto = false;
            foreach ($choices as $choice) {
                $next = (string)($choice['next'] ?? '');
                // The automatic target resolves to the ending the learner earned, which is
                // branching - late-bound, on the whole journey rather than on this choice.
                // Counting it as flat would report the LAST decision of every scenario
                // ever written, which is a rule nobody can satisfy.
                if ($next === schema::auto_target()) {
                    $auto = true;
                    continue;
                }
                $targets[$lands($next)] = true;
            }
            if (count($targets) > 1) {
                $branching++;
                continue;
            }
            // THE AUTOMATIC TARGET IS NEITHER A FAULT NOR EVIDENCE.
            //
            // It resolves to the ending the learner earned, which IS branching - late
            // bound, on the whole journey - so reporting it would flag the last decision of
            // every scenario ever written. But counting it toward the minimum lets a
            // scenario that branches nowhere pass by having two decisions that both end it,
            // which was true of the first cut of this check and is exactly the shape of
            // fault it exists to catch.
            if (!$auto) {
                $flat[] = $title;
            }
        }

        // A FLAG NOBODY SETS, AND A FLAG NOBODY READS.
        //
        // Both are content that cannot do anything, and both are invisible to every other
        // check: the screen exists, the choice exists, and they never meet. A variant
        // waiting on a flag nothing sets is a screen no learner can ever be shown - paid
        // for, illustrated, and unreachable.
        $set = [];
        $read = [];
        $marks = [];
        foreach ($nodes as $node) {
            foreach ((array)($node['choices'] ?? []) as $choice) {
                foreach (array_keys((array)($choice['setflags'] ?? [])) as $flag) {
                    $set[$flag] = true;
                }
            }
            foreach ((array)($node['variants'] ?? []) as $variant) {
                if (isset($variant['when']['flag'])) {
                    $read[(string)$variant['when']['flag']] = true;
                }
            }
            // A mark on a picture READS a flag exactly as a variant does. It has to count,
            // or a scenario that remembers a decision visually rather than in words would be
            // told the flag it depends on is never read - which is the review nagging about
            // the more sophisticated of the two ways of doing the same thing.
            foreach ((array)($node['marks'] ?? []) as $mark) {
                $flag = (string)($mark['flag'] ?? '');
                if ($flag === '') {
                    continue;
                }
                $read[$flag] = true;
                $marks[] = ['node' => $node, 'mark' => $mark];
            }
        }
        foreach (array_keys($read) as $flag) {
            if (!isset($set[$flag])) {
                $out[] = [
                    'nodeid'  => 'flag_' . $flag,
                    'node'    => $flag,
                    'message' => get_string('quality:deadflag', 'mod_aibranchedscenario', $flag),
                ];
            }
        }
        foreach (array_keys($set) as $flag) {
            if (!isset($read[$flag])) {
                $out[] = [
                    'nodeid'  => 'flag_' . $flag,
                    'node'    => $flag,
                    'message' => get_string('quality:unreadflag', 'mod_aibranchedscenario', $flag),
                ];
            }
        }

        // A FAULT ON A DOCUMENT THE LEARNER CANNOT ACT ON.
        //
        // A flagged line is the author saying "this is the thing to catch". On a beat there
        // is nothing to catch it WITH - one way forward, and the learner clicks past the
        // fault they were meant to notice. That is worse than not showing the document,
        // because it teaches that reading the permit changes nothing.
        foreach ($nodes as $node) {
            $artefact = (array)($node['artefact'] ?? []);
            if (!$artefact || ($node['type'] ?? '') === 'decision') {
                continue;
            }
            $flagged = false;
            foreach ((array)($artefact['fields'] ?? []) as $field) {
                $flagged = $flagged || !empty($field['flagged']);
            }
            if (!$flagged) {
                continue;
            }
            // The review runs on stored definitions as well as on freshly validated ones,
            // so the kind is checked against the list here rather than assumed - a string
            // identifier built from unchecked data is a fatal error, not a warning.
            $kind = schema::in_list($artefact['kind'] ?? '', schema::artefactkinds())
                ? (string)$artefact['kind'] : 'document';
            $out[] = [
                'nodeid'  => (string)($node['id'] ?? ''),
                'node'    => (string)($node['title'] ?? ''),
                'message' => get_string(
                    'quality:artefactnotread',
                    'mod_aibranchedscenario',
                    (object)[
                        'kind' => \core_text::strtolower(
                            get_string('artefact:' . $kind, 'mod_aibranchedscenario')
                        ),
                        'node' => (string)($node['title'] ?? $node['id'] ?? ''),
                    ]
                ),
            ];
        }

        // A TAG THAT CAN NEVER APPEAR. Reported per mark rather than per flag, because the
        // author needs to know which picture is waiting for something that never happens -
        // "nothing sets warn_left_running" sends them looking through five nodes.
        foreach ($marks as $entry) {
            if (isset($set[(string)$entry['mark']['flag']])) {
                continue;
            }
            $out[] = [
                'nodeid'  => (string)($entry['node']['id'] ?? ''),
                'node'    => (string)($entry['node']['title'] ?? ''),
                'message' => get_string(
                    'quality:markneverset',
                    'mod_aibranchedscenario',
                    (object)[
                        'node'  => (string)($entry['node']['title'] ?? $entry['node']['id'] ?? ''),
                        'label' => (string)$entry['mark']['label'],
                        'flag'  => (string)$entry['mark']['flag'],
                    ]
                ),
            ];
        }

        // JUDGED ON THE SCENARIO, NOT ON EACH DECISION.
        //
        // The first cut of this reported every decision whose options converge, which
        // would nag on a correctly built scenario: branch-and-bottleneck means paths
        // REJOIN, so a five-decision scenario with two real branch points legitimately has
        // three decisions that converge. A panel that complains about a good design is one
        // teachers learn to scroll past, which is how the debrief warnings got ignored.
        //
        // Two branch points is what the content standard asks for, and the message names
        // the flat decisions so the teacher has somewhere to start rather than a verdict.
        if ($decisions > 1 && $branching < self::MIN_BRANCHING_DECISIONS) {
            array_unshift(
                $out,
                [
                    'nodeid'  => 'scenario',
                    'node'    => trim((string)($definition['title'] ?? '')),
                    'message' => get_string(
                        $branching === 0
                            ? 'quality:nobranchingatall' : 'quality:notenoughbranching',
                        'mod_aibranchedscenario',
                        (object)[
                            'have'   => $branching,
                            'wanted' => self::MIN_BRANCHING_DECISIONS,
                            'flat'   => implode('", "', array_slice($flat, 0, 4)),
                        ]
                    ),
                ]
            );
        }
        return $out;
    }

    /**
     * Readings that cannot reach their ends across the five decisions.
     *
     * A learner who gets EVERY decision wrong should see the scoreboard say so. The score
     * does - it is normalised by the decisions taken, so all-wrong is nought. The three
     * readings are absolute, and whether they bottom out depends entirely on how big the
     * author wrote the effects.
     *
     * This is the length problem in its second form. When a teacher could pick the length,
     * a three-decision scenario left engagement at 14 after every wrong answer, because
     * three decisions of effects written for five could not span the range. The length is
     * fixed now, so that cause is gone - and the same symptom arrives from effects written
     * too small. Five decisions at minus five is a total failure ending at 25, with the
     * score beside it reading nought: the two halves of one screen disagreeing, and the
     * learner told that a run in which they got nothing right went half well.
     *
     * Walked by STAGE rather than by node, because a learner passes through one node per
     * stage. Taking the most damaging option available at each stage is the worst run the
     * scenario allows; if that cannot reach the end of the scale, nothing can.
     *
     * Reports rather than rejects. The teacher has been charged by the time the definition
     * is read, and the fix is a number in the editor.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function reading_range_warnings(array $definition): array {
        // A definition with no opening readings is a fragment, not a scenario - the
        // validator always supplies them. Judging one would report every node fixture in
        // the review panel as unable to reach a scale it never had.
        if (!isset($definition['openingmetrics'])) {
            return [];
        }
        $opening = (array)$definition['openingmetrics'];
        $stages = [];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $stage = (int)($node['stage'] ?? 0);
            foreach ((array)($node['choices'] ?? []) as $choice) {
                foreach (schema::metrics() as $metric) {
                    $effect = (int)($choice['effects'][$metric] ?? 0);
                    $stages[$metric][$stage]['down'] =
                        min($stages[$metric][$stage]['down'] ?? 0, $effect);
                    $stages[$metric][$stage]['up'] =
                        max($stages[$metric][$stage]['up'] ?? 0, $effect);
                }
            }
        }

        $out = [];
        foreach (schema::metrics() as $metric) {
            if (empty($stages[$metric])) {
                continue;
            }
            $start = (int)($opening[$metric] ?? 50);
            $down = 0;
            $up = 0;
            foreach ($stages[$metric] as $reach) {
                $down += (int)($reach['down'] ?? 0);
                $up += (int)($reach['up'] ?? 0);
            }
            // Tension reads the other way up: its bad end is the ceiling, so the reading
            // that has to be reachable is 100 rather than 0.
            $worst = $metric === 'tension' ? $start + $up : $start + $down;
            $reached = $metric === 'tension' ? $worst >= 100 : $worst <= 0;
            if ($reached) {
                continue;
            }
            $out[] = [
                'nodeid'  => 'metric_' . $metric,
                'node'    => get_string('metric:' . $metric, 'mod_aibranchedscenario'),
                'message' => get_string(
                    'quality:readingrange',
                    'mod_aibranchedscenario',
                    (object)[
                        'metric' => get_string('metric:' . $metric, 'mod_aibranchedscenario'),
                        'worst'  => $worst,
                        'end'    => $metric === 'tension' ? 100 : 0,
                    ]
                ),
            ];
        }
        return $out;
    }

    /**
     * Lower case, collapsed whitespace, no punctuation - for comparing two pieces of prose.
     *
     * @param string $text The text.
     * @return string
     */
    protected static function normalise_for_compare(string $text): string {
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim(\core_text::strtolower($text));
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
            'message' => get_string(
                'quality:spelling',
                'mod_aibranchedscenario',
                (object)[
                    'language' => $language,
                    'words'    => implode(', ', $shown),
                ]
            ),
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
