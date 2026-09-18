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
        // Principles are a property of the document too, and like spelling they have to be
        // checked before the node walk or they are lost to the early return below.
        $spelling = array_merge($spelling, self::principle_warnings($definition));
        // The debrief is a property of the document as well, and it is the part a learner
        // reaches last and remembers longest.
        $spelling = array_merge($spelling, self::debrief_warnings($definition));
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
        $debrief = (array)($definition['debrief'] ?? []);
        foreach (['whatmattered', 'criticaldecisions', 'practice'] as $page) {
            foreach ((array)($debrief[$page] ?? []) as $line) {
                if (is_string($line)) {
                    $bits[] = $line;
                }
            }
        }
        $bits[] = (string)($debrief['sourceconnection'] ?? '');
        foreach ((array)($definition['takeaways'] ?? []) as $takeaway) {
            if (is_array($takeaway)) {
                $bits[] = (string)($takeaway['heading'] ?? '');
                $bits[] = (string)($takeaway['body'] ?? '');
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
     * A debrief that closes the loop on fewer ideas than the scenario opened with.
     *
     * The scenario teaches N principles, tests them, and then looks back at them. Those
     * three counts should be the same number, and nothing checked that they were. Watched
     * to fail on a live scenario: three principles taught at the start, then two lessons
     * learnt, two critical decisions and two takeaways - so a third of what the learner was
     * taught was never looked back at, and the debrief quietly decided which third.
     *
     * Nothing in the definition said how many there should be, so the service chose, and
     * two is the cheapest number that still reads as a list.
     *
     * Like the principle check above, this reports rather than rejects. The teacher has
     * been charged by the time the definition is read, and a scenario that is sound except
     * for a short debrief is not worth throwing away when the missing entry is a sentence
     * they can write in the editor. What it will not do any more is say nothing.
     *
     * @param array $definition A validated definition.
     * @return array Warning rows.
     */
    protected static function debrief_warnings(array $definition): array {
        $principles = array_values(array_filter(
            (array)($definition['principles'] ?? []),
            fn($p) => is_array($p) && trim((string)($p['title'] ?? '')) !== ''
        ));
        $wanted = count($principles);
        if ($wanted < 1) {
            return [];
        }

        $debrief = (array)($definition['debrief'] ?? []);
        // Takeaways are a sibling of the debrief rather than a member of it, so they are
        // counted from where they actually live. They were missed for exactly that reason.
        $lists = [
            'whatmattered'      => (array)($debrief['whatmattered'] ?? []),
            'criticaldecisions' => (array)($debrief['criticaldecisions'] ?? []),
            'practice'          => (array)($debrief['practice'] ?? []),
            'takeaways'         => (array)($definition['takeaways'] ?? []),
        ];

        $out = [];
        foreach ($lists as $key => $list) {
            $have = count(array_filter($list, fn($item) => $item !== '' && $item !== []));
            // MORE than one per principle is a fault too, and `>=` said nothing about it.
            // Four lessons against three principles means one of them answers no principle
            // at all - and the fourth has no picture, because the image map is built from
            // the entries that HAVE a principle behind them.
            if ($have > $wanted) {
                $out[] = [
                    'nodeid'  => 'debrief_' . $key,
                    'node'    => get_string('debrief:' . $key, 'mod_aibranchedscenario'),
                    'message' => get_string(
                        'quality:debrieflong',
                        'mod_aibranchedscenario',
                        (object)[
                            'page'   => get_string('debrief:' . $key, 'mod_aibranchedscenario'),
                            'have'   => $have,
                            'wanted' => $wanted,
                        ]
                    ),
                ];
                continue;
            }
            if ($have === $wanted) {
                continue;
            }
            $out[] = [
                'nodeid'  => 'debrief_' . $key,
                'node'    => get_string('debrief:' . $key, 'mod_aibranchedscenario'),
                'message' => get_string(
                    'quality:debriefshort',
                    'mod_aibranchedscenario',
                    (object)[
                        'page'      => get_string('debrief:' . $key, 'mod_aibranchedscenario'),
                        'have'      => $have,
                        'wanted'    => $wanted,
                    ]
                ),
            ];
        }
        return $out;
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
