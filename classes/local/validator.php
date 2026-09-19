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
    /**
     * @var bool Whether the fixed shape is enforced or merely expected.
     *
     * True for anything being made: a generated scenario, a pasted one, an import. False
     * for something already stored, which was written under whatever contract was current
     * at the time and must not be made unpublishable by an upgrade. See validate_stored().
     */
    protected $strictshape = true;

    /** @var string[] Accumulated problems. */
    protected $problems = [];

    /**
     * Validate and normalise a scenario definition supplied as an array.
     *
     * @param array $raw Decoded scenario definition.
     * @return array Normalised scenario definition.
     * @throws validation_exception when the definition cannot be made valid.
     */
    public static function validate(array $raw, bool $strictshape = true): array {
        $v = new self();
        $v->strictshape = $strictshape;
        $clean = $v->normalise($raw);
        if ($v->problems) {
            throw new validation_exception($v->problems);
        }
        return $clean;
    }

    /**
     * Validate something this plugin is ALREADY HOLDING, rather than something new.
     *
     * THE SHAPE RULES ARE ABOUT WHAT GETS MADE, NOT ABOUT WHAT EXISTS.
     *
     * v2.3.0 fixed the length at schema::DECISIONS and the width at schema::CHOICES.
     * Applied to new content that is the point of the release. Applied to a draft written
     * in August it is a plugin update that makes a teacher's own work unpublishable and
     * uneditable, for a rule that did not exist when they wrote it: publish() re-validates,
     * so does every single-node text edit, and there is no control in the editor for adding
     * the third option the rule now demands. The scenario is then stuck, and nothing on
     * screen suggests a way out.
     *
     * So the shape rules hold for new scenarios - generated, pasted, imported - and are
     * reported rather than enforced for one already stored. Everything else in the
     * contract is checked exactly as before: this is not a lenient validator, it is the
     * same validator with two rules that only a NEW scenario has to satisfy.
     *
     * @param array $raw The stored definition.
     * @return array
     */
    public static function validate_stored(array $raw): array {
        return self::validate($raw, false);
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
        $document = self::unwrap_json($json);
        $decoded = json_decode($document, true);
        if (!is_array($decoded)) {
            // Assistants quote speech inside a JSON string without escaping the quotation
            // marks - "example": ""Have I got that right?"" - which is the single most
            // common reason a pasted scenario will not decode. It is repairable without
            // guessing at meaning, so it is repaired rather than refused.
            $decoded = json_decode(self::escape_inner_quotes($document), true);
        }
        if (!is_array($decoded)) {
            throw new validation_exception([self::json_problem($document)]);
        }
        return self::validate($decoded);
    }

    /**
     * Recover the decoded document from pasted text, without validating it.
     *
     * The two halves of validate_json() - getting a usable document out of what somebody
     * actually pasted, and checking that document against the contract - are separate
     * jobs, and only the second one knows how long a scenario has to be. Splitting them
     * lets the repair be tested on its own: a fixture proving that unescaped quoted speech
     * survives should not also have to be a complete five-decision scenario, or the test
     * reports on the shape rule every time somebody changes the shape rule.
     *
     * @param string $json Pasted text.
     * @return array|null The decoded document, or null if it could not be recovered.
     */
    public static function decode_pasted(string $json): ?array {
        if (strlen($json) > schema::MAX_SCENARIO_BYTES) {
            return null;
        }
        $document = self::unwrap_json($json);
        $decoded = json_decode($document, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(self::escape_inner_quotes($document), true);
        }
        return is_array($decoded) ? $decoded : null;
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
     * Escape quotation marks that appear inside a JSON string value.
     *
     * An assistant writing a line of speech into a value produces text like
     *   "pitfall": "Finishing with "Great, we're all agreed then", which invites ..."
     * and every one of those inner quotation marks ends the string early, taking the
     * whole document with it.
     *
     * A quotation mark can only be a closing one when what follows it is something JSON
     * allows after a value or a key: a colon, a closing brace or bracket, the end of the
     * document, or a comma followed by the start of another value or key. A comma
     * followed by an ordinary word - which is what "agreed then", which invites looks
     * like - is prose, so that quote is content and is escaped.
     *
     * This runs only after a decode has already failed, so a well-formed document is
     * never touched by it.
     *
     * @param string $json The document as pasted.
     * @return string The document with content quotes escaped.
     */
    protected static function escape_inner_quotes(string $json): string {
        $out = '';
        $instring = false;
        $length = strlen($json);
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($char === '\\' && $instring) {
                // An escape sequence passes through whole, so \" is never re-read as a
                // quotation mark.
                $out .= $char . ($json[$i + 1] ?? '');
                $i++;
                continue;
            }
            if ($char !== '"') {
                $out .= $char;
                continue;
            }
            if (!$instring) {
                $instring = true;
                $out .= $char;
                continue;
            }
            if (self::closes_string($json, $i)) {
                $instring = false;
                $out .= $char;
                continue;
            }
            $out .= '\\"';
        }
        return $out;
    }

    /**
     * Whether the quotation mark at this position ends the string it is inside.
     *
     * @param string $json The whole document.
     * @param int $position Offset of the quotation mark.
     * @return bool
     */
    protected static function closes_string(string $json, int $position): bool {
        $next = self::next_meaningful($json, $position + 1);
        if ($next === null) {
            // Nothing but whitespace to the end of the document.
            return true;
        }
        if ($next[0] === ':' || $next[0] === '}' || $next[0] === ']') {
            return true;
        }
        if ($next[0] !== ',') {
            return false;
        }
        // A comma only ends a value when another value or key follows it. Anything else
        // is a comma inside a sentence, and the quote before it is part of the text.
        $after = self::next_meaningful($json, $next[1] + 1);
        if ($after === null) {
            return true;
        }
        return strpos('"{[-0123456789tfn', $after[0]) !== false;
    }

    /**
     * The next character that is not whitespace, with its offset.
     *
     * @param string $json The whole document.
     * @param int $from Offset to start from.
     * @return array|null [character, offset], or null at the end of the document.
     */
    protected static function next_meaningful(string $json, int $from): ?array {
        $length = strlen($json);
        for ($i = $from; $i < $length; $i++) {
            if (!ctype_space($json[$i])) {
                return [$json[$i], $i];
            }
        }
        return null;
    }

    /**
     * Say what is wrong with a document that will not decode.
     *
     * "The scenario definition was not valid JSON" tells a teacher nothing they can act
     * on in a document of several hundred lines. The line that broke it is named, and
     * quoted back, so they can see what to change. The text quoted is their own pasted
     * content and goes only to them.
     *
     * @param string $document The document as pasted.
     * @return string A message naming the line, where one can be found.
     */
    protected static function json_problem(string $document): string {
        $lines = preg_split('/\n/', $document) ?: [];
        foreach ($lines as $number => $line) {
            // An unbalanced number of unescaped quotation marks on one line is what an
            // unescaped quote inside a value looks like from the outside.
            $stripped = preg_replace('/\\\\./', '', $line);
            if (substr_count((string)$stripped, '"') % 2 === 1) {
                return get_string('error:notjsonline', 'mod_aibranchedscenario', (object)[
                    'line' => $number + 1,
                    'text' => \core_text::substr(trim($line), 0, 160),
                ]);
            }
        }
        return get_string('error:notjson', 'mod_aibranchedscenario');
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
        return self::clip_to_words($value, $max);
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
        return self::clip_to_words($value, $max);
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
        if (!is_string($value)) {
            return '';
        }
        // The word "auto" is what an assistant writes when it has been told the activity can pick
        // the next stage, and it is not a node id, so the whole scenario was rejected as
        // unreachable. The two spellings mean the same thing and both are accepted.
        $trimmed = strtolower(trim($value));
        if ($trimmed === schema::auto_target() || $trimmed === 'auto') {
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

        // The debrief block and the takeaway cards are gone. They were four lists of
        // advice at the end of a scenario - lessons learnt, critical decisions, practice
        // points, takeaways - each on its own page, each saying a version of the same
        // thing, and none of them tied to what the learner actually did. Everything they
        // carried now lives on each choice's outcome note, on the slide for the decision
        // it belongs to. Anything a scenario still sends under those keys is dropped here
        // rather than stored, so the contract has one place a lesson can live.
        $this->check_graph($out);

        $out['stats'] = $this->compute_stats($out);

        // Exactly five decisions on the longest path. Not a maximum, not a default: the
        // length is fixed, the wizard no longer offers a choice, and the debrief is built
        // out of exactly five slides. A scenario of some other length would render a
        // debrief with slides missing or slides spare, so it is refused here where a
        // teacher can be told why, rather than at play time where a learner finds out.
        if ((int)$out['stats']['longestpath'] !== schema::DECISIONS && $this->strictshape) {
            $a = (object)['expected' => schema::DECISIONS,
                'found' => (int)$out['stats']['longestpath']];
            $this->fail(get_string('error:decisioncount', 'mod_aibranchedscenario', $a));
        }

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
        // Anything that was not exactly "male" or "female" used to be replaced with an
        // empty string and nobody was told - so "woman", "f", "Female" and a typo all
        // became "not stated", the image brief then omitted the person's gender entirely,
        // and the picture model chose for itself. With a name like Alex it chose freely,
        // which is how a learner ended up reading "he" beside a photograph of a woman.
        //
        // Two changes. Common spellings are understood rather than discarded, and
        // non-binary is accepted because it is a value the service can legitimately send.
        // What is genuinely unknown stays empty - but see pronoun_conflicts() and the
        // image brief, both of which now treat empty as "say nothing" rather than as
        // "anything goes".
        $gender = $this->normalise_gender($raw['gender'] ?? '');
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
     * Cut a field to its ceiling without severing a word.
     *
     * Both of the text helpers ended in a bare `core_text::substr()`, so a field at its
     * limit was cut mid-word: a learner read "the machine is vibr", and a picture brief
     * built from that text was briefed from a half-sentence. The image brief has had a
     * word-safe trim since v1.70.0; the fields a learner actually reads did not, and the
     * one harness check that looked at it fed a single unbroken token, which cannot detect
     * word severing at all.
     *
     * The cut falls back to the hard one when there is no space to cut at - a single long
     * token, or a language that does not put spaces between words - because a field cut to
     * nothing is worse than a field cut mid-word.
     *
     * @param string $value The cleaned value.
     * @param int $max Ceiling in characters.
     * @return string
     */
    protected static function clip_to_words(string $value, int $max): string {
        if (\core_text::strlen($value) <= $max) {
            return $value;
        }
        $cut = \core_text::substr($value, 0, $max);
        $space = max(strrpos($cut, ' '), strrpos($cut, "\n"));
        // Only back off to a word boundary when doing so keeps most of the field. Cutting
        // a 200-character limit down to 40 to land on a space loses more than it saves.
        if ($space !== false && $space > (int)($max * 0.6)) {
            return rtrim(\core_text::substr($cut, 0, $space));
        }
        return $cut;
    }

    /**
     * Read a gender the service sent, in the spellings it actually sends.
     *
     * A closed set of three values, matched loosely on the way in and stored exactly on the
     * way out, so everything downstream - the voice picker, the image brief, the pronoun
     * check - reads one vocabulary rather than three.
     *
     * @param mixed $raw Whatever arrived in the gender field.
     * @return string One of male, female, non-binary, or empty when genuinely unstated.
     */
    protected function normalise_gender($raw): string {
        if (!is_string($raw)) {
            return '';
        }
        $value = \core_text::strtolower(trim($raw));
        if ($value === '') {
            return '';
        }
        $known = [
            'male'       => ['male', 'm', 'man', 'boy', 'he', 'him'],
            'female'     => ['female', 'f', 'woman', 'girl', 'she', 'her'],
            'non-binary' => ['non-binary', 'nonbinary', 'non binary', 'nb', 'enby', 'they',
                'them', 'other'],
        ];
        foreach ($known as $canonical => $spellings) {
            if (in_array($value, $spellings, true)) {
                return $canonical;
            }
        }
        return '';
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
            $taught = $this->split_taught(
                $this->text($item['summary'] ?? '', 800),
                $this->text($item['example'] ?? '', 600),
                $this->text($item['pitfall'] ?? '', 600)
            );
            $out[] = [
                'id'      => $id,
                'title'   => $title,
                'summary' => $taught['summary'],
                // A principle stated is a principle forgotten. These are the words a
                // learner can actually use, and the ones that sound reasonable and are
                // not.
                //
                // They used to be optional, and the consequence was visible on screen: a
                // teaching slide carrying one sentence and half a screen of nothing,
                // because the service had simply left them out.
                //
                // From v1.20.0 a missing one rejected the whole definition, and that was
                // the wrong lever. The teacher has already been charged by the time the
                // definition is read, so refusing it over two sentences threw away a
                // scenario that was otherwise sound and took their credits with it. The
                // principle is named at review instead - see quality_review - where the
                // teacher can type the example in, which is where a human filling a gap
                // belongs. The player already draws the slide without them.
                'example' => $taught['example'],
                'pitfall' => $taught['pitfall'],
            ];
        }
        return $out;
    }

    /**
     * Pull an example and a pitfall back out of a summary that swallowed them.
     *
     * The service does not always put these in their own fields. It often writes one
     * paragraph - "Seek opportunities where both parties can benefit. Example: "If we
     * extend the contract duration, could we discuss a price adjustment?" Pitfall: Viewing
     * negotiation as a zero-sum game." - and leaves example and pitfall empty.
     *
     * Everything downstream then behaves as though the principle taught a rule and nothing
     * else. The two cards on the teaching slide are drawn only when their field has
     * something in it, so they did not appear; the slide showed a heading and a wall of
     * prose with the good bits buried in the middle of it; and the definition was, for a
     * while, refused outright for fields whose content was sitting right there in the
     * summary.
     *
     * So the words are put where they belong. A field the service did fill is never
     * overwritten - this only recovers what would otherwise be lost - and a summary with no
     * marker in it is returned exactly as it came.
     *
     * @param string $summary The principle's summary as written.
     * @param string $example The example field, which may be empty.
     * @param string $pitfall The pitfall field, which may be empty.
     * @return array Keys: summary, example, pitfall.
     */
    protected function split_taught(string $summary, string $example, string $pitfall): array {
        $result = ['summary' => $summary, 'example' => $example, 'pitfall' => $pitfall];
        if ($summary === '' || ($example !== '' && $pitfall !== '')) {
            return $result;
        }

        // The labels a generator actually writes, longest first so that "common mistake"
        // is matched before "mistake" could be. A label counts only at the start of a
        // sentence or a line, which is where a label goes - otherwise "for example" in the
        // middle of a sentence would cut the summary in half.
        $labels = [
            'example' => ['for example', 'example', 'sounds like', 'say something like', 'try'],
            'pitfall' => ['common mistake', 'common pitfall', 'pitfall', 'avoid', 'not this',
                'what not to do', 'the trap'],
        ];

        $found = [];
        foreach ($labels as $field => $words) {
            foreach ($words as $word) {
                $pattern = '/(?:^|(?<=[.!?"\x{201D}])\s+|\n)\s*' . preg_quote($word, '/')
                    . '\s*[:\x{2014}\x{2013}-]\s*/iu';
                if (preg_match($pattern, $summary, $m, PREG_OFFSET_CAPTURE)) {
                    $found[$field] = ['start' => $m[0][1], 'body' => $m[0][1] + strlen($m[0][0])];
                    break;
                }
            }
        }
        if (!$found) {
            return $result;
        }

        // Each label runs to the next label or to the end, so the order they appear in is
        // what bounds them, not the order they are listed above.
        $bounds = $found;
        uasort($bounds, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        $starts = array_column($bounds, 'start');
        $first = min($starts);

        $keys = array_keys($bounds);
        foreach ($keys as $i => $field) {
            $from = $bounds[$field]['body'];
            $to = isset($keys[$i + 1]) ? $bounds[$keys[$i + 1]]['start'] : strlen($summary);
            $text = trim(substr($summary, $from, max(0, $to - $from)));
            // A label the service wrote but left nothing after is not worth acting on, and
            // a field it filled properly is never replaced.
            if ($text !== '' && $result[$field] === '') {
                $result[$field] = $this->text($text, 600);
            }
        }

        // What is left is the principle itself. If the summary was nothing but labels there
        // is no rule left to state, so the original is kept rather than leaving the slide
        // with a heading and no lead.
        $lead = trim(substr($summary, 0, $first));
        if ($lead !== '') {
            $result['summary'] = $this->text($lead, 800);
        }
        return $result;
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

        // EVERY MEDIA FILENAME IN A SCENARIO HAS TO BE UNIQUE ACROSS THE WHOLE SCENARIO.
        //
        // A node's picture is stored as <nodeid>, its crisis frame as <nodeid>_crisis, and
        // a choice's narration as <choiceid> - all in one file area per kind. Choice ids
        // were only checked for uniqueness WITHIN their own node, so two nodes both
        // offering "escalate" produced two files called escalate.mp3. Publishing flattens
        // the area, and the second copy throws: the teacher's publish died with a raw
        // exception, and on the import route the exception escaped the ad-hoc task, which
        // Moodle then retried forever - regenerating and re-billing every image and clip on
        // every attempt. An assistant writing five decision nodes reuses ids like
        // "escalate", "wait" or "a" across all of them without a second thought.
        //
        // Node ids are collected first, so a choice can never take the name of a node that
        // has not been reached yet either.
        $taken = [];
        // The other five families of media filename, which this did not reserve.
        //
        // It covered node ids, crisis frames and choice ids - two of the seven things
        // media_manager writes into those file areas. A node legitimately called
        // "debrief_practice" collides with the debrief page's picture; one called "opening"
        // collides with the opening narration; a choice called "n1_said" collides with node
        // n1's spoken line; a choice called "record_x" collides with the record clip for a
        // choice called "x". A collision is not a cosmetic problem here: publishing drops
        // the second file, so one screen silently gets another screen's picture or another
        // screen's voice.
        $taken[] = 'opening';
        // The debrief used to write one picture per entry across four families of its own -
        // debrief_lesson_0, debrief_takeaway_2 and the rest. Those pages are gone and the
        // slides that replaced them reuse the reaction frame of the option the learner
        // took, so the only numbered family left to guard is the teaching frames.
        $reservedprefixes = ['lesson_'];
        // The names nothing in the scenario may be called. Separate from $taken, which also
        // holds every node id so that a CHOICE cannot take one - a node checked against
        // $taken would always appear to clash with itself.
        $reservednames = ['opening'];
        foreach ($principleids as $principleid) {
            $taken[] = 'lesson_' . $principleid;
            $reservednames[] = 'lesson_' . $principleid;
        }

        foreach ($raw as $rawnode) {
            $nodeid = is_array($rawnode) ? $this->identifier($rawnode['id'] ?? '') : '';
            if ($nodeid !== '') {
                $taken[] = $nodeid;
                $taken[] = $nodeid . '_crisis';
                $taken[] = $nodeid . '_said';
                // The reaction frames, one per outcome signal. A node called "n1" and a
                // node called "n1_after_negative" would otherwise fight over one filename,
                // and publishing drops the second - so one screen silently shows another
                // screen's picture.
                foreach (schema::signals() as $signal) {
                    $taken[] = $nodeid . '_after_' . $signal;
                }
            }
            foreach ((array)($rawnode['choices'] ?? []) as $rawchoice) {
                $choiceid = is_array($rawchoice) ? $this->identifier($rawchoice['id'] ?? '') : '';
                if ($choiceid !== '') {
                    $taken[] = 'record_' . $choiceid;
                }
            }
        }

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
            // THE RESERVED LIST WAS ONLY EVER APPLIED TO CHOICE IDS.
            //
            // It was built here, carried into normalise_node() and used there to make choice
            // ids unique; node ids were checked against other node ids and nothing else. So
            // a node called "opening", "lesson_p1" or "debrief_practice_0" was accepted and
            // then collided with a picture the media manager writes under that exact name -
            // and publishing DROPS the second file, so one screen silently shows another
            // screen's photograph. Found by a harness check that builds the image map from a
            // definition whose node is named after a lesson and counts the entries: two
            // things, one key, and the count was one short.
            //
            // Checked against $reservednames rather than $taken, because $taken deliberately
            // holds every node id already - it exists so a choice cannot take one - and a
            // node id would therefore always appear to clash with itself.
            if ($this->reserved_name($id, $reservednames, $reservedprefixes)) {
                $this->fail(get_string('error:reservednodeid', 'mod_aibranchedscenario', $id));
                continue;
            }
            $seen[] = $id;
            $out[] = $this->normalise_node($rawnode, $id, $principleids, $voices, $taken);
        }
        return $out;
    }

    /**
     * Is this identifier one the media manager already writes a file under?
     *
     * Exact names for the fixed families, and a prefix for the numbered ones - the debrief's
     * entry index is not known until the debrief is read, and a numbered family can only be
     * guarded by reserving the whole prefix.
     *
     * @param string $id The identifier being claimed.
     * @param string[] $names Reserved exact names.
     * @param string[] $prefixes Reserved prefixes.
     * @return bool
     */
    protected function reserved_name(string $id, array $names, array $prefixes): bool {
        if (in_array($id, $names, true)) {
            return true;
        }
        foreach ($prefixes as $prefix) {
            if (strpos($id, $prefix) === 0) {
                return true;
            }
        }
        // The reaction frames are keyed by SUFFIX - <nodeid>_after_<signal> - so a node
        // called "n1_after_negative" collides with node n1's negative reaction whatever n1
        // is called. A prefix list cannot see that, which is why the first harness check
        // written for it passed for the wrong reason: the fixture was rejected for having
        // no situation text, and I read the rejection as proof of a guard that did not
        // exist. A check that throws is not a check that throws for the right reason.
        foreach (schema::signals() as $signal) {
            $suffix = '_after_' . $signal;
            if (
                \core_text::strlen($id) > \core_text::strlen($suffix)
                    && substr($id, -\core_text::strlen($suffix)) === $suffix
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalise one node.
     *
     * @param array $raw Raw node.
     * @param string $id Validated node id.
     * @param string[] $principleids Valid principle identifiers.
     * @return array
     */
    protected function normalise_node(
        array $raw,
        string $id,
        array $principleids,
        array $voices = [],
        array &$taken = []
    ): array {
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
            'variants'           => [],
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

        // THE WORLD REACTING TO WHAT THE LEARNER DID.
        //
        // There used to be exactly one of these - crisisvariant - gated on one metric
        // passing one threshold, on one field. It was the right idea with one condition
        // available, so a scenario could say "this has got tense" and nothing else. It
        // could not say "you never reported that hazard, and it is still here".
        //
        // A node now carries a LIST of variants, each with a condition, first match wins in
        // document order. That is what turns a branching quiz into something that remembers:
        // a flag set at stage one changes the wording at stage three, with no extra screen
        // and no extra picture.
        //
        // crisisvariant still works, unchanged, as shorthand for the tension case, and is
        // appended last so an explicit variant always wins. It is kept rather than migrated
        // because every scenario in existence uses it and none of them have been
        // regenerated - which is the v2.3.0 lesson, learned expensively.
        $node['variants'] = [];
        foreach ((array)($raw['variants'] ?? []) as $rawvariant) {
            if (!is_array($rawvariant)) {
                continue;
            }
            $condition = $this->condition($rawvariant['when'] ?? []);
            $variant = [
                'when'              => $condition,
                'situation'         => $this->text($rawvariant['situation'] ?? ''),
                'facilitatorspeech' => $this->text($rawvariant['facilitatorspeech'] ?? '', 1500),
                'challenge'         => $this->text($rawvariant['challenge'] ?? '', 600),
            ];
            // A variant with no condition would fire on every attempt and replace the node
            // it belongs to, which is a scenario with a screen nobody can ever see behind
            // it. A variant with no situation has nothing to show.
            if ($condition !== null && $variant['situation'] !== '') {
                $node['variants'][] = $variant;
            }
        }

        if (isset($raw['crisisvariant']) && is_array($raw['crisisvariant'])) {
            $variant = [
                'situation'         => $this->text($raw['crisisvariant']['situation'] ?? ''),
                'facilitatorspeech' => $this->text($raw['crisisvariant']['facilitatorspeech'] ?? '', 1500),
                'challenge'         => $this->text($raw['crisisvariant']['challenge'] ?? '', 600),
            ];
            if ($variant['situation'] !== '') {
                $node['crisisvariant'] = $variant;
                $node['variants'][] = $variant + ['when' => [
                    'metric'  => 'tension',
                    'atleast' => schema::CRISIS_TENSION_THRESHOLD,
                ]];
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

        // Exactly three. The debrief gives every decision a slide carrying one paragraph
        // per option, and a scenario offering two on one screen and four on the next
        // produces five slides that do not look like each other.
        //
        // TOO MANY IS TRIMMED; TOO FEW IS REFUSED.
        //
        // The difference is whether the plugin can fix it without inventing meaning. A
        // fourth option can be dropped - the three that remain are the author's own, and
        // the ones kept are chosen to span the signals so the slide still has a best and a
        // worst to compare. A missing third option cannot be written here: anything this
        // code produced would be a choice a learner could take that nobody wrote.
        //
        // This distinction is the whole lesson of the outcomenote fault below. A rule that
        // refuses what it could repair does not protect the contract, it just moves the
        // failure onto a teacher who has already been charged.
        if (count($rawchoices) > schema::CHOICES) {
            $rawchoices = self::trim_choices($rawchoices);
        }
        if (count($rawchoices) !== schema::CHOICES && $this->strictshape) {
            $a = (object)['node' => $id, 'count' => schema::CHOICES];
            $this->fail(get_string('error:choicecount', 'mod_aibranchedscenario', $a));
        }
        // Still a floor, in both modes: a decision with one way out is not a decision, and
        // that rule predates the fixed width by two years.
        if (count($rawchoices) < 2) {
            $a = (object)['node' => $id, 'count' => schema::CHOICES];
            $this->fail(get_string('error:choicecount', 'mod_aibranchedscenario', $a));
        }

        $letters = ['A', 'B', 'C'];
        $position = 0;
        foreach (array_slice($rawchoices, 0, schema::MAX_CHOICES) as $rawchoice) {
            if (!is_array($rawchoice)) {
                continue;
            }
            // Checked against every id already used anywhere in this scenario, not just
            // within this node. A clash falls back to the node's own name plus the choice
            // letter, which is unique by construction.
            $choiceid = $this->identifier($rawchoice['id'] ?? '') ?: ($id . '_' . strtolower($letters[$position]));
            if (in_array($choiceid, $taken, true)) {
                $choiceid = $id . '_' . strtolower($letters[$position]);
            }
            $taken[] = $choiceid;

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

            // The paragraph the debrief slide shows against this option: what it costs,
            // what it teaches, and how it leaves the people in the room.
            //
            // DERIVED WHEN IT IS MISSING, NEVER REJECTED FOR IT.
            //
            // This was a hard requirement for exactly one afternoon, and it broke live
            // generation completely. The field is new, the LMS Labs service does not send
            // it yet, and a plugin release cannot make a running service start sending a
            // field it has never heard of. So every generation came back, was rejected by
            // this rule, and the teacher was charged for a scenario they never saw. The
            // paste route worked, because that prompt was updated in the same release -
            // which is exactly how a fault like this hides: the half you can see is fine.
            //
            // The lesson is a rule, not a patch: a NEW REQUIRED FIELD IS A BREAKING CHANGE
            // to every producer that has not shipped it yet. It can be required of the
            // prompt, which is ours, and it cannot be required of the wire.
            //
            // Derived from the choice's own consequence and feedback, which every scenario
            // already carries and which together say close to what the note is for: the
            // consequence is what happened, the feedback is why it mattered. That is the
            // scenario's own words about that option, not something invented here - and
            // quality_review reports it, so a teacher knows which notes were written for
            // the slide and which were assembled from what was already there.
            $outcomenote = $this->text($rawchoice['outcomenote'] ?? '', schema::MAX_OUTCOME_NOTE);
            if ($outcomenote === '') {
                $outcomenote = self::derive_outcome_note(
                    $this->text($rawchoice['consequence'] ?? '', 1800),
                    $this->text($rawchoice['feedback'] ?? '', 1800)
                );
            }

            $principleid = $this->identifier($rawchoice['principleid'] ?? '');
            if ($principleid !== '' && !in_array($principleid, $principleids, true)) {
                $principleid = '';
            }

            // One helper for both kinds of link, so a beat and a choice can never again
            // disagree about whether the automatic target is a legal destination.
            $nextid = $this->link($rawchoice['next'] ?? '');
            if ($nextid === '') {
                // A choice that names no successor means "carry on", which is exactly what
                // the automatic target means - so it is read that way rather than rejected.
                //
                // It was rejected, and it rejected the whole scenario with it. An assistant
                // writing five decision nodes states "next" on the ones with an obvious
                // successor and omits it on the LAST one, where there is no later stage to
                // name - which is precisely the node that has to reach an ending. The
                // scenario then failed with "choice A on node n5 does not lead anywhere"
                // followed by all three endings being unreachable, and a teacher who pasted
                // it had no way to act on that: the fault was in the model's output and the
                // only person who could fix it was the person who wrote the prompt.
                //
                // Read as automatic, the last node's choices carry the learner to the
                // ending they earned, which is what was meant. Where a later stage does
                // exist the automatic target finds it, so nothing that used to work
                // changes. A target that is stated but does not exist is still a fault -
                // that is a typo, not an omission, and silently redirecting it would hide
                // a broken branch.
                $nextid = schema::auto_target();
            }

            $node['choices'][] = [
                'id'          => $choiceid,
                'letter'      => $letters[$position],
                'text'        => $text,
                'signal'      => schema::in_list($rawchoice['signal'] ?? '', schema::signals())
                    ? $rawchoice['signal'] : 'neutral',
                'consequence' => $this->text($rawchoice['consequence'] ?? '', 1800),
                'feedback'    => $this->text($rawchoice['feedback'] ?? '', 1800),
                'outcomenote' => $outcomenote,
                // WHAT THIS CHOICE CHANGES ABOUT THE WORLD.
                //
                // The readings already carried something forward, but only as three
                // numbers - a scenario could know the room had got tense and could not
                // know the hazard was still there. A flag is a fact: hazard_reported,
                // lead_still_in_use. Later screens are written against it, so a decision
                // taken at stage one is why stage three reads the way it does.
                //
                // Stored in the attempt's state blob, which is free-form, so this needs no
                // schema change and no upgrade step.
                'setflags'    => $this->flags($rawchoice['setflags'] ?? []),
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
     * Keep schema::CHOICES options out of a node that offered more, spanning the signals.
     *
     * A service that writes four options is not producing a broken scenario, it is
     * producing one the debrief slide cannot lay out. Dropping one is a repair the plugin
     * can make honestly, because every option kept was written by the author.
     *
     * Which one goes matters. Taking the first three would happily keep three positives
     * and drop the only option that costs anything, leaving a slide with nothing to
     * compare - so one option is kept from each signal where there is one, and the
     * remaining place goes to whatever came first among the rest.
     *
     * @param array $rawchoices The choices as written, more than schema::CHOICES of them.
     * @return array Exactly schema::CHOICES of them, in the author's order.
     */
    protected static function trim_choices(array $rawchoices): array {
        $keep = [];
        foreach (schema::signals() as $signal) {
            foreach ($rawchoices as $at => $choice) {
                if (isset($keep[$at]) || !is_array($choice)) {
                    continue;
                }
                if ((string)($choice['signal'] ?? '') === $signal) {
                    $keep[$at] = true;
                    break;
                }
            }
        }
        foreach ($rawchoices as $at => $choice) {
            if (count($keep) >= schema::CHOICES) {
                break;
            }
            if (is_array($choice)) {
                $keep[$at] = true;
            }
        }
        // Back into the order the author wrote them, then cut to length: the signal walk
        // above collects them in signal order, and a slide that reordered the options
        // would disagree with the decision screen the learner actually saw.
        ksort($keep);
        $out = [];
        foreach (array_keys($keep) as $at) {
            $out[] = $rawchoices[$at];
        }
        return array_slice($out, 0, schema::CHOICES);
    }

    /**
     * Normalise the facts a choice sets about the world.
     *
     * Names are normalised the same way node and choice ids are, so a scenario cannot set
     * "Hazard Reported" and then test for "hazard_reported" and quietly never match - which
     * would be a screen nobody can ever reach, and invisible to every check that only looks
     * at whether the screen exists.
     *
     * @param mixed $raw Flag name to boolean.
     * @return array Normalised name to boolean.
     */
    protected function flags($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $name => $value) {
            $name = $this->identifier((string)$name);
            if ($name !== '') {
                $out[$name] = (bool)$value;
            }
        }
        return $out;
    }

    /**
     * Normalise one variant condition, or null when it is not one this engine can answer.
     *
     * Deliberately three shapes and no more. A condition language grows until nobody can
     * say what a scenario will do, and the review panel has to be able to explain in one
     * sentence why a screen did or did not appear:
     *
     *   {"flag": "hazard_reported", "is": true}    something the learner did
     *   {"metric": "tension", "atleast": 75}       a reading that has run high or low
     *   {"signals": "negative", "atleast": 3}      how many poor calls they have made
     *
     * The third is what "if the learner has made three unsafe decisions" means, and it is
     * the one that lets a scenario escalate rather than merely react.
     *
     * @param mixed $raw The raw condition.
     * @return array|null The condition, or null when it cannot be answered.
     */
    protected function condition($raw): ?array {
        if (!is_array($raw)) {
            return null;
        }
        $flag = $this->identifier($raw['flag'] ?? '');
        if ($flag !== '') {
            return ['flag' => $flag, 'is' => !empty($raw['is'])];
        }
        $metric = (string)($raw['metric'] ?? '');
        if (schema::in_list($metric, schema::metrics())) {
            // isset() is not enough: the wire mapper writes an explicit null for a bound
            // the service did not send, so a condition with neither would otherwise be read
            // as "at least null", which is nought, which is always true - a variant that
            // fires on every attempt and replaces the node behind it.
            if (($raw['atleast'] ?? null) !== null) {
                return ['metric' => $metric, 'atleast' => $this->intrange($raw['atleast'], 0, 100)];
            }
            if (($raw['atmost'] ?? null) !== null) {
                return ['metric' => $metric, 'atmost' => $this->intrange($raw['atmost'], 0, 100)];
            }
            return null;
        }
        $signal = (string)($raw['signals'] ?? '');
        if (schema::in_list($signal, schema::signals())) {
            return [
                'signals' => $signal,
                // At least one, or the condition is true before the learner has done
                // anything and the variant is simply the node's own text.
                'atleast' => max(1, $this->intrange($raw['atleast'] ?? 1, 1, schema::MAX_NODES)),
            ];
        }
        return null;
    }

    /**
     * Build an outcome note from what the choice already says about itself.
     *
     * Used when a producer has not sent one - which today means every scenario from the
     * LMS Labs route, because the field is newer than the service.
     *
     * The consequence says what happened; the feedback says why it mattered. Run together
     * they are close to what the debrief slide needs, and they are the scenario's own
     * words rather than anything invented here. A purpose-written note is better and the
     * prompt asks for one; this is what stops a missing field being a rejected scenario a
     * teacher has already paid for.
     *
     * @param string $consequence What happened when this option was taken.
     * @param string $feedback Why it mattered.
     * @return string The note, or empty when there is nothing to build one from.
     */
    public static function derive_outcome_note(string $consequence, string $feedback): string {
        $parts = [];
        foreach ([$consequence, $feedback] as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        if ($parts === []) {
            return '';
        }
        return self::clip_to_words(implode(' ', $parts), schema::MAX_OUTCOME_NOTE);
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
                    // The automatic target is the next stage where there is one, and the
                    // earned ending where there is not, so both are reachable through it.
                    // Counting only the endings made every scene after an "auto" choice
                    // unreachable and rejected the scenario whole.
                    $onward = attempt_manager::next_stage_node($scenario['nodes'], $nodes[$current]);
                    if ($onward !== '') {
                        $stack[] = $onward;
                    }
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
        $depth = function (string $id, array $seen) use (&$depth, $nodes, $scenario) {
            if (isset($seen[$id]) || !isset($nodes[$id]) || count($seen) > schema::MAX_NODES) {
                return 0;
            }
            $seen[$id] = true;
            $best = 0;
            foreach ($nodes[$id]['choices'] as $choice) {
                $target = $choice['next'];
                if ($target === schema::auto_target()) {
                    // Following the automatic link forward is what keeps the progress rail
                    // honest: counting it as the end made a scenario whose choices all say
                    // "auto" report a longest path of one decision.
                    $target = attempt_manager::next_stage_node($scenario['nodes'], $nodes[$id]);
                }
                if ($target === '' || !isset($nodes[$target])) {
                    continue;
                }
                $best = max($best, $depth($target, $seen));
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
