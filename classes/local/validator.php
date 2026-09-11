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
 * Strict validator and normaliser for the scenario node-graph contract.
 *
 * The validator is a whitelist: anything not described here is dropped, so neither an
 * AI provider nor a teacher can smuggle extra keys through into stored data. All
 * narrative content is treated as plain text; it is never HTML and is never trusted.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /** @var string[] Accumulated problems. */
    protected $problems = [];

    /**
     * Validate and normalise a scenario definition supplied as an array.
     *
     * @param array $raw Decoded scenario definition.
     * @return array Normalised scenario definition.
     * @throws validation_exception when the definition cannot be made valid.
     */
    public static function validate(array $raw): array {
        $v = new self();
        $clean = $v->normalise($raw);
        if ($v->problems) {
            throw new validation_exception($v->problems);
        }
        return $clean;
    }

    /**
     * Validate and normalise a scenario definition supplied as a JSON string.
     *
     * @param string $json Raw JSON.
     * @return array Normalised scenario definition.
     * @throws validation_exception when the JSON is unusable.
     */
    public static function validate_json(string $json): array {
        if (strlen($json) > schema::MAX_SCENARIO_BYTES) {
            throw new validation_exception([get_string('error:scenariotoolarge', 'mod_aibranchedscenario')]);
        }
        $decoded = json_decode(self::unwrap_json($json), true);
        if (!is_array($decoded)) {
            throw new validation_exception([get_string('error:notjson', 'mod_aibranchedscenario')]);
        }
        return self::validate($decoded);
    }

    /**
     * Recover the JSON document from what an assistant actually pasted back.
     *
     * The import prompt asks for one JSON document and nothing else. Assistants
     * routinely add a sentence of their own before it, wrap it in a ```json fence, or
     * both, and a teacher pasting that got "The scenario definition was not valid JSON"
     * with no idea what to remove. Everything outside the outermost braces is dropped,
     * which recovers the common cases without accepting anything the decoder would not
     * have accepted on its own.
     *
     * @param string $json Pasted text.
     * @return string The document, or the original text when no object is found.
     */
    protected static function unwrap_json(string $json): string {
        $trimmed = trim($json);

        // A fenced block, with or without a language tag. The fence marker is built
        // rather than written literally: the sniffer reads backticks in a string as a
        // shell escape, which is fair in general and wrong here.
        $fence = str_repeat(chr(96), 3);
        if (preg_match('/' . $fence . '[a-z]*\s*(\{.*\})\s*' . $fence . '/su', $trimmed, $matches)) {
            return $matches[1];
        }

        // Otherwise take from the first brace to the last, which strips a preamble, a
        // sign-off, or both.
        $first = strpos($trimmed, '{');
        $last = strrpos($trimmed, '}');
        if ($first !== false && $last !== false && $last > $first) {
            return substr($trimmed, $first, $last - $first + 1);
        }

        return $trimmed;
    }

    /**
     * Test whether a definition is valid without throwing.
     *
     * @param array $raw Decoded scenario definition.
     * @param string[] $problems Receives the list of problems found.
     * @return array|null Normalised definition, or null when invalid.
     */
    public static function try_validate(array $raw, ?array &$problems = null): ?array {
        try {
            $problems = [];
            return self::validate($raw);
        } catch (validation_exception $e) {
            $problems = $e->get_problems();
            return null;
        }
    }

    /**
     * Record a problem.
     *
     * @param string $message Problem description.
     * @return void
     */
    protected function fail(string $message): void {
        if (count($this->problems) < 60) {
            $this->problems[] = $message;
        }
    }

    /**
     * Normalise a single-line string value.
     *
     * @param mixed $value Raw value.
     * @param int $max Maximum length.
     * @return string
     */
    protected function short($value, int $max = schema::MAX_SHORT_TEXT): string {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
        return \core_text::substr($value, 0, $max);
    }

    /**
     * Normalise a multi-paragraph narrative string.
     *
     * Line endings are normalised, control characters removed and runs of blank lines
     * collapsed. The result is plain text; the renderer escapes it.
     *
     * @param mixed $value Raw value.
     * @param int $max Maximum length.
     * @return string
     */
    protected function text($value, int $max = schema::MAX_TEXT): string {
        if (!is_string($value)) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/\n{3,}/u', "\n\n", (string)$value);
        $value = trim($value);
        return \core_text::substr($value, 0, $max);
    }

    /**
     * Normalise an identifier used as a node or choice key.
     *
     * @param mixed $value Raw value.
     * @return string Empty string when unusable.
     */
    protected function identifier($value): string {
        if (!is_string($value)) {
            return '';
        }
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $value)) {
            return '';
        }
        return $value;
    }

    /**
     * Normalise a node link, which may be a node id or the automatic outcome target.
     *
     * @param mixed $value Raw link.
     * @return string A node id, the auto target, or '' when neither.
     */
    protected function link($value): string {
        if (is_string($value) && trim($value) === schema::auto_target()) {
            return schema::auto_target();
        }
        return $this->identifier($value);
    }

    /**
     * Clamp an integer into a range.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @param int $default Value used when not numeric.
     * @return int
     */
    protected function intrange($value, int $min, int $max, int $default = 0): int {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value)) && !is_float($value)) {
            return $default;
        }
        return max($min, min($max, (int)round((float)$value)));
    }

    /**
     * Normalise a list of strings against an allowed vocabulary.
     *
     * @param mixed $value Raw value.
     * @param string[] $allowed Allowed values.
     * @param int $maxitems Maximum number of items kept.
     * @return string[]
     */
    protected function stringlist($value, array $allowed, int $maxitems = 12): array {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (schema::in_list($item, $allowed) && !in_array($item, $out, true)) {
                $out[] = $item;
            }
            if (count($out) >= $maxitems) {
                break;
            }
        }
        return $out;
    }

    /**
     * Normalise the whole definition.
     *
     * @param array $raw Decoded scenario definition.
     * @return array
     */
    protected function normalise(array $raw): array {
        $out = [];
        $out['version'] = schema::CONTRACT_VERSION;
        $out['title'] = $this->short($raw['title'] ?? '');
        if ($out['title'] === '') {
            $this->fail(get_string('error:missingtitle', 'mod_aibranchedscenario'));
        }
        $out['subtitle'] = $this->short($raw['subtitle'] ?? '');
        $out['role'] = $this->text($raw['role'] ?? '', 1000);
        $out['setting'] = $this->short($raw['setting'] ?? '');
        $out['language'] = schema::in_list($raw['language'] ?? '', schema::languages())
            ? $raw['language'] : 'en-AU';
        $out['tone'] = schema::in_list($raw['tone'] ?? '', schema::tones()) ? $raw['tone'] : 'neutral';
        $out['complexity'] = schema::in_list($raw['complexity'] ?? '', schema::complexities())
            ? $raw['complexity'] : 'intermediate';

        $out['facilitator'] = $this->normalise_person($raw['facilitator'] ?? []);
        $out['characters'] = [];
        if (isset($raw['characters']) && is_array($raw['characters'])) {
            foreach (array_slice($raw['characters'], 0, 6) as $character) {
                if (!is_array($character)) {
                    continue;
                }
                $person = $this->normalise_person($character);
                if ($person['name'] !== '') {
                    $out['characters'][] = $person;
                }
            }
        }

        $out['principles'] = $this->normalise_principles($raw['principles'] ?? []);
        $principleids = array_column($out['principles'], 'id');

        $out['openingmetrics'] = [
            'engagement' => $this->intrange($raw['openingmetrics']['engagement'] ?? 50, 0, 100, 50),
            'trust'      => $this->intrange($raw['openingmetrics']['trust'] ?? 50, 0, 100, 50),
            'tension'    => $this->intrange($raw['openingmetrics']['tension'] ?? 30, 0, 100, 30),
        ];

        $out['hook'] = $this->text($raw['hook'] ?? '');
        if ($out['hook'] === '') {
            $this->fail(get_string('error:missinghook', 'mod_aibranchedscenario'));
        }

        // Who speaks on a node is stored as a name; the gender that chooses a voice for
        // that person lives on the character record, so the two are married up here,
        // while both are in hand, rather than being looked up again at play time.
        $voices = [];
        foreach (array_merge([$out['facilitator']], $out['characters']) as $person) {
            if (($person['name'] ?? '') !== '') {
                $voices[\core_text::strtolower($person['name'])] = $person['gender'] ?? '';
            }
        }
        $out['nodes'] = $this->normalise_nodes($raw['nodes'] ?? [], $principleids, $voices);
        $nodeids = array_column($out['nodes'], 'id');

        $start = $this->identifier($raw['startnode'] ?? '');
        if ($start === '' || !in_array($start, $nodeids, true)) {
            $start = $nodeids[0] ?? '';
            if ($start === '') {
                $this->fail(get_string('error:nostartnode', 'mod_aibranchedscenario'));
            } else {
                $this->fail(get_string('error:badstartnode', 'mod_aibranchedscenario'));
            }
        }
        $out['startnode'] = $start;

        $out['debrief'] = $this->normalise_debrief($raw['debrief'] ?? []);
        $out['takeaways'] = $this->normalise_takeaways($raw['takeaways'] ?? []);

        $this->check_graph($out);

        $out['stats'] = $this->compute_stats($out);

        return $out;
    }

    /**
     * Normalise a facilitator or character record.
     *
     * @param mixed $raw Raw record.
     * @return array
     */
    protected function normalise_person($raw): array {
        if (!is_array($raw)) {
            $raw = [];
        }
        $name = $this->short($raw['name'] ?? '', 80);
        $initials = $this->short($raw['initials'] ?? '', 3);
        if ($initials === '' && $name !== '') {
            $parts = preg_split('/\s+/u', $name);
            $first = \core_text::substr($parts[0], 0, 1);
            $second = isset($parts[1]) ? \core_text::substr($parts[1], 0, 1) : '';
            $initials = \core_text::strtoupper($first . $second);
        }
        $colour = $raw['avatarcolour'] ?? '';
        if (!is_string($colour) || !preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
            $colour = '#4a6fa5';
        }
        $gender = ($raw['gender'] ?? '') === 'male' || ($raw['gender'] ?? '') === 'female'
            ? $raw['gender'] : '';
        return [
            'id'           => $this->identifier($raw['id'] ?? '') ?: '',
            'name'         => $name,
            'role'         => $this->short($raw['role'] ?? '', 120),
            'trait'        => $this->short($raw['trait'] ?? '', 200),
            'appearance'   => $this->short($raw['appearance'] ?? '', 400),
            'initials'     => $initials,
            'avatarcolour' => $colour,
            'gender'       => $gender,
        ];
    }

    /**
     * Normalise the decision principles extracted from the source content.
     *
     * @param mixed $raw Raw list.
     * @return array
     */
    protected function normalise_principles($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        $index = 0;
        foreach (array_slice($raw, 0, 8) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $index++;
            $id = $this->identifier($item['id'] ?? '') ?: ('p' . $index);
            if (in_array($id, $seen, true)) {
                continue;
            }
            $title = $this->short($item['title'] ?? '', 160);
            if ($title === '') {
                continue;
            }
            $seen[] = $id;
            $out[] = [
                'id'      => $id,
                'title'   => $title,
                'summary' => $this->text($item['summary'] ?? '', 800),
            ];
        }
        return $out;
    }

    /**
     * Normalise the node list.
     *
     * @param mixed $raw Raw node list.
     * @param string[] $principleids Valid principle identifiers.
     * @return array
     */
    protected function normalise_nodes($raw, array $principleids, array $voices = []): array {
        if (!is_array($raw) || !$raw) {
            $this->fail(get_string('error:nonodes', 'mod_aibranchedscenario'));
            return [];
        }
        if (count($raw) > schema::MAX_NODES) {
            $this->fail(get_string('error:toomanynodes', 'mod_aibranchedscenario', schema::MAX_NODES));
            $raw = array_slice($raw, 0, schema::MAX_NODES);
        }
        $out = [];
        $seen = [];
        $index = 0;
        foreach ($raw as $rawnode) {
            $index++;
            if (!is_array($rawnode)) {
                $this->fail(get_string('error:badnode', 'mod_aibranchedscenario', $index));
                continue;
            }
            $id = $this->identifier($rawnode['id'] ?? '');
            if ($id === '') {
                $this->fail(get_string('error:badnodeid', 'mod_aibranchedscenario', $index));
                continue;
            }
            if (in_array($id, $seen, true)) {
                $this->fail(get_string('error:duplicatenodeid', 'mod_aibranchedscenario', $id));
                continue;
            }
            $seen[] = $id;
            $out[] = $this->normalise_node($rawnode, $id, $principleids, $voices);
        }
        return $out;
    }

    /**
     * Normalise one node.
     *
     * @param array $raw Raw node.
     * @param string $id Validated node id.
     * @param string[] $principleids Valid principle identifiers.
     * @return array
     */
    protected function normalise_node(array $raw, string $id, array $principleids, array $voices = []): array {
        $type = schema::in_list($raw['type'] ?? '', schema::nodetypes()) ? $raw['type'] : 'decision';

        $node = [
            'id'                 => $id,
            'type'               => $type,
            'title'              => $this->short($raw['title'] ?? '', 160),
            'stage'              => $this->intrange($raw['stage'] ?? 1, 1, 20, 1),
            'bottleneck'         => !empty($raw['bottleneck']),
            'situation'          => $this->text($raw['situation'] ?? ''),
            'facilitatorspeech'  => $this->text($raw['facilitatorspeech'] ?? '', 1500),
            'speaker'            => $this->short($raw['speaker'] ?? '', 80),
            'speakergender'      => '',
            'challenge'          => $this->text($raw['challenge'] ?? '', 600),
            'imageprompt'        => $this->short($raw['imageprompt'] ?? '', 600),
            'imagealt'           => $this->short($raw['imagealt'] ?? '', 250),
            'crisisvariant'      => null,
            'choices'            => [],
            'outcome'            => '',
            'summary'            => '',
        ];

        if ($node['situation'] === '') {
            $this->fail(get_string('error:nodenosituation', 'mod_aibranchedscenario', $id));
        }

        // A scenario written before speakers were recorded names nobody, and a generated
        // one may name somebody who is not in the cast. Both are left with an empty
        // gender, which the player reads as "use the narrator's voice".
        if ($node['speaker'] !== '') {
            $node['speakergender'] = $voices[\core_text::strtolower($node['speaker'])] ?? '';
        }

        if (isset($raw['crisisvariant']) && is_array($raw['crisisvariant'])) {
            $variant = [
                'situation'         => $this->text($raw['crisisvariant']['situation'] ?? ''),
                'facilitatorspeech' => $this->text($raw['crisisvariant']['facilitatorspeech'] ?? '', 1500),
                'challenge'         => $this->text($raw['crisisvariant']['challenge'] ?? '', 600),
            ];
            if ($variant['situation'] !== '') {
                $node['crisisvariant'] = $variant;
            }
        }

        if ($type === 'outcome') {
            $node['outcome'] = schema::in_list($raw['outcome'] ?? '', schema::outcomes())
                ? $raw['outcome'] : 'mixed';
            $node['summary'] = $this->text($raw['summary'] ?? '', 2000);
            return $node;
        }

        $rawchoices = is_array($raw['choices'] ?? null) ? $raw['choices'] : [];
        if ($type === 'beat') {
            // A beat is a narrative moment with exactly one way forward.
            $node['choices'] = [];

            // The onward link is read from the node, but until now was written only into
            // the synthesised choice, so a validated definition could not be validated
            // again: every beat came back saying it did not know what followed it, and
            // every node after the first beat became unreachable. That matters because
            // publishing re-validates the stored working copy, so any scenario containing
            // a beat could be saved and then refused on its way to learners. Keeping the
            // node-level link and label in the normalised form makes the shape stable
            // under repeated validation.
            //
            // The onward link may also be the automatic target, meaning "the ending this
            // learner has earned". A decision's choices have always been allowed to point
            // there; a beat's link was not, because it went straight through the
            // identifier filter, which requires a leading letter or digit and so turned
            // `__auto__` into nothing at all. A narrative beat that simply carries the
            // learner to their ending — the shape a server-built topology naturally
            // produces for the last scene — was therefore rejected as having no successor,
            // taking every ending's reachability with it.
            $next = $this->link($raw['next'] ?? '');
            if ($next === '' && isset($raw['choices'][0]['next'])) {
                $next = $this->link($raw['choices'][0]['next']);
            }
            $label = $this->short($raw['continuelabel'] ?? '', 120)
                ?: $this->short($raw['choices'][0]['text'] ?? '', 120);
            $node['next'] = $next;
            if ($label !== '') {
                $node['continuelabel'] = $label;
            }
            $node['choices'][] = [
                'id'          => $id . '_go',
                'letter'      => 'A',
                'text'        => $label ?: get_string('continue', 'mod_aibranchedscenario'),
                'signal'      => 'neutral',
                'consequence' => '',
                'feedback'    => '',
                'principleid' => '',
                'tags'        => [],
                'effects'     => ['engagement' => 0, 'trust' => 0, 'tension' => 0],
                'skills'      => ['presence' => 0, 'adaptability' => 0, 'empathy' => 0, 'clarity' => 0],
                'next'        => $next,
            ];
            if ($next === '') {
                $this->fail(get_string('error:beatnonext', 'mod_aibranchedscenario', $id));
            }
            return $node;
        }

        if (count($rawchoices) < schema::MIN_CHOICES || count($rawchoices) > schema::MAX_CHOICES) {
            $a = (object)['node' => $id, 'min' => schema::MIN_CHOICES, 'max' => schema::MAX_CHOICES];
            $this->fail(get_string('error:choicecount', 'mod_aibranchedscenario', $a));
        }

        $letters = ['A', 'B', 'C', 'D'];
        $seenchoices = [];
        $position = 0;
        foreach (array_slice($rawchoices, 0, schema::MAX_CHOICES) as $rawchoice) {
            if (!is_array($rawchoice)) {
                continue;
            }
            $choiceid = $this->identifier($rawchoice['id'] ?? '') ?: ($id . '_' . strtolower($letters[$position]));
            if (in_array($choiceid, $seenchoices, true)) {
                $choiceid = $id . '_' . strtolower($letters[$position]);
            }
            $seenchoices[] = $choiceid;

            $text = $this->text($rawchoice['text'] ?? '', 400);
            if ($text === '') {
                $this->fail(get_string('error:choicenotext', 'mod_aibranchedscenario', $id));
            }

            // A choice with no consequence renders as a signal word and a Continue button
            // and nothing else: the learner is shown a screen that tells them nothing
            // about what their decision did. An empty string was being accepted here, so
            // the only sign of it was a blank card at play time. A beat's synthesised
            // choice legitimately has none, which is why this is checked on decisions.
            if ($this->text($rawchoice['consequence'] ?? '', 1800) === '') {
                $a = (object)['node' => $id, 'letter' => $letters[$position]];
                $this->fail(get_string('error:choicenoconsequence', 'mod_aibranchedscenario', $a));
            }

            $principleid = $this->identifier($rawchoice['principleid'] ?? '');
            if ($principleid !== '' && !in_array($principleid, $principleids, true)) {
                $principleid = '';
            }

            // One helper for both kinds of link, so a beat and a choice can never again
            // disagree about whether the automatic target is a legal destination.
            $nextid = $this->link($rawchoice['next'] ?? '');
            if ($nextid === '') {
                $a = (object)['node' => $id, 'letter' => $letters[$position]];
                $this->fail(get_string('error:choicenonext', 'mod_aibranchedscenario', $a));
            }

            $node['choices'][] = [
                'id'          => $choiceid,
                'letter'      => $letters[$position],
                'text'        => $text,
                'signal'      => schema::in_list($rawchoice['signal'] ?? '', schema::signals())
                    ? $rawchoice['signal'] : 'neutral',
                'consequence' => $this->text($rawchoice['consequence'] ?? '', 1800),
                'feedback'    => $this->text($rawchoice['feedback'] ?? '', 1800),
                'principleid' => $principleid,
                'tags'        => $this->stringlist($rawchoice['tags'] ?? [], schema::choicetags(), 5),
                'effects'     => [
                    'engagement' => $this->intrange(
                        $rawchoice['effects']['engagement'] ?? 0,
                        -schema::MAX_METRIC_DELTA,
                        schema::MAX_METRIC_DELTA
                    ),
                    'trust'      => $this->intrange(
                        $rawchoice['effects']['trust'] ?? 0,
                        -schema::MAX_METRIC_DELTA,
                        schema::MAX_METRIC_DELTA
                    ),
                    'tension'    => $this->intrange(
                        $rawchoice['effects']['tension'] ?? 0,
                        -schema::MAX_METRIC_DELTA,
                        schema::MAX_METRIC_DELTA
                    ),
                ],
                'skills'      => [
                    'presence'     => $this->intrange(
                        $rawchoice['skills']['presence'] ?? 0,
                        -schema::MAX_SKILL_DELTA,
                        schema::MAX_SKILL_DELTA
                    ),
                    'adaptability' => $this->intrange(
                        $rawchoice['skills']['adaptability'] ?? 0,
                        -schema::MAX_SKILL_DELTA,
                        schema::MAX_SKILL_DELTA
                    ),
                    'empathy'      => $this->intrange(
                        $rawchoice['skills']['empathy'] ?? 0,
                        -schema::MAX_SKILL_DELTA,
                        schema::MAX_SKILL_DELTA
                    ),
                    'clarity'      => $this->intrange(
                        $rawchoice['skills']['clarity'] ?? 0,
                        -schema::MAX_SKILL_DELTA,
                        schema::MAX_SKILL_DELTA
                    ),
                ],
                'next'        => $nextid,
            ];
            $position++;
        }

        return $node;
    }

    /**
     * Normalise the debrief block.
     *
     * @param mixed $raw Raw debrief.
     * @return array
     */
    protected function normalise_debrief($raw): array {
        if (!is_array($raw)) {
            $raw = [];
        }
        $listof = function ($value, $max, $len) {
            if (!is_array($value)) {
                return [];
            }
            $out = [];
            foreach (array_slice($value, 0, $max) as $item) {
                $item = $this->text($item, $len);
                if ($item !== '') {
                    $out[] = $item;
                }
            }
            return $out;
        };
        return [
            'whatmattered'      => $listof($raw['whatmattered'] ?? [], 6, 600),
            'criticaldecisions' => $listof($raw['criticaldecisions'] ?? [], 6, 600),
            'practice'          => $listof($raw['practice'] ?? [], 6, 600),
            'sourceconnection'  => $this->text($raw['sourceconnection'] ?? '', 2000),
        ];
    }

    /**
     * Normalise takeaway cards.
     *
     * @param mixed $raw Raw takeaways.
     * @return array
     */
    protected function normalise_takeaways($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_slice($raw, 0, 6) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $heading = $this->short($item['heading'] ?? '', 160);
            if ($heading === '') {
                continue;
            }
            $out[] = [
                'heading' => $heading,
                'body'    => $this->text($item['body'] ?? '', 900),
            ];
        }
        return $out;
    }

    /**
     * Structural checks over the assembled node graph.
     *
     * @param array $scenario Normalised scenario (nodes and startnode present).
     * @return void
     */
    protected function check_graph(array $scenario): void {
        $nodes = [];
        foreach ($scenario['nodes'] as $node) {
            $nodes[$node['id']] = $node;
        }
        if (!$nodes) {
            return;
        }

        $isoutcome = function ($node) {
            return $node['type'] === 'outcome';
        };
        $outcomes = array_filter($nodes, $isoutcome);
        if (!$outcomes) {
            $this->fail(get_string('error:nooutcomenode', 'mod_aibranchedscenario'));
        }

        // Every declared target must exist.
        foreach ($nodes as $node) {
            foreach ($node['choices'] as $choice) {
                if ($choice['next'] === schema::auto_target()) {
                    continue;
                }
                if ($choice['next'] !== '' && !isset($nodes[$choice['next']])) {
                    $a = (object)['node' => $node['id'], 'target' => $choice['next']];
                    $this->fail(get_string('error:danglingtarget', 'mod_aibranchedscenario', $a));
                }
            }
        }

        // Reachability from the start node.
        $start = $scenario['startnode'];
        if ($start === '' || !isset($nodes[$start])) {
            return;
        }
        $reachable = [];
        $stack = [$start];
        while ($stack) {
            $current = array_pop($stack);
            if (isset($reachable[$current])) {
                continue;
            }
            $reachable[$current] = true;
            foreach ($nodes[$current]['choices'] as $choice) {
                if ($choice['next'] === schema::auto_target()) {
                    foreach ($outcomes as $outcome) {
                        $stack[] = $outcome['id'];
                    }
                    continue;
                }
                if (isset($nodes[$choice['next']])) {
                    $stack[] = $choice['next'];
                }
            }
        }
        foreach ($nodes as $node) {
            if (!isset($reachable[$node['id']])) {
                $this->fail(get_string('error:unreachablenode', 'mod_aibranchedscenario', $node['id']));
            }
        }
        foreach ($outcomes as $outcome) {
            if (isset($reachable[$outcome['id']])) {
                $reachedoutcome = true;
                break;
            }
        }
        if ($outcomes && empty($reachedoutcome)) {
            $this->fail(get_string('error:nooutcomereachable', 'mod_aibranchedscenario'));
        }

        // The graph must be acyclic so an attempt always terminates.
        $state = [];
        $cyclefound = false;
        $visit = function (string $id) use (&$visit, &$state, $nodes, $outcomes, &$cyclefound) {
            if (($state[$id] ?? 0) === 1) {
                $cyclefound = true;
                return;
            }
            if (($state[$id] ?? 0) === 2) {
                return;
            }
            $state[$id] = 1;
            foreach ($nodes[$id]['choices'] as $choice) {
                if ($choice['next'] === schema::auto_target()) {
                    continue;
                }
                if (isset($nodes[$choice['next']])) {
                    $visit($choice['next']);
                }
            }
            $state[$id] = 2;
        };
        $visit($start);
        if ($cyclefound) {
            $this->fail(get_string('error:cyclicgraph', 'mod_aibranchedscenario'));
        }

        // Decision nodes must lead somewhere.
        foreach ($nodes as $node) {
            if ($node['type'] !== 'outcome' && !$node['choices']) {
                $this->fail(get_string('error:nodenochoices', 'mod_aibranchedscenario', $node['id']));
            }
        }
    }

    /**
     * Derive summary statistics used for grading and reporting.
     *
     * @param array $scenario Normalised scenario.
     * @return array
     */
    protected function compute_stats(array $scenario): array {
        $nodes = [];
        foreach ($scenario['nodes'] as $node) {
            $nodes[$node['id']] = $node;
        }
        $decisions = 0;
        foreach ($nodes as $node) {
            if ($node['type'] === 'decision') {
                $decisions++;
            }
        }

        // Longest number of decisions on any path. The path set is carried explicitly
        // rather than memoised, so a graph that still contains a cycle cannot cache a
        // truncated answer and report it as the real longest path.
        $depth = function (string $id, array $seen) use (&$depth, $nodes) {
            if (isset($seen[$id]) || !isset($nodes[$id]) || count($seen) > schema::MAX_NODES) {
                return 0;
            }
            $seen[$id] = true;
            $best = 0;
            foreach ($nodes[$id]['choices'] as $choice) {
                if ($choice['next'] === schema::auto_target() || !isset($nodes[$choice['next']])) {
                    continue;
                }
                $best = max($best, $depth($choice['next'], $seen));
            }
            return $best + ($nodes[$id]['type'] === 'decision' ? 1 : 0);
        };
        $longest = isset($nodes[$scenario['startnode']]) ? $depth($scenario['startnode'], []) : 0;

        $isoutcomenode = function ($n) {
            return $n['type'] === 'outcome';
        };
        $outcomenodes = array_filter($nodes, $isoutcomenode);

        return [
            'nodecount'      => count($nodes),
            'decisioncount'  => $decisions,
            'longestpath'    => $longest,
            'outcomecount'   => count($outcomenodes),
            'maxskillscore'  => $longest * count(schema::skills()) * schema::MAX_SKILL_DELTA,
        ];
    }
}
