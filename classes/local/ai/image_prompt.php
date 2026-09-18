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

namespace mod_aibranchedscenario\local\ai;

use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\schema;

/**
 * Composes the image brief for one scene.
 *
 * A scenario's scenes are generated as separate requests, minutes apart, by a model
 * with no memory between them. Sending each scene's narrative prose on its own — which
 * is what this plugin used to do — produces six unrelated pictures: a different room, a
 * different Sam, a different time of day. That reads as broken rather than atmospheric,
 * and it is the single thing that most undermines a scenario visually.
 *
 * So every brief describes ONE PHOTOGRAPH, in plain prose, in one paragraph:
 *
 *   - what kind of picture it is, in the medium the teacher chose;
 *   - how the moment should feel, as behaviour a camera can see;
 *   - the setting, who is in shot, what is happening in the scenario's own words, and
 *     the line somebody is saying;
 *   - then fixed text the trim cannot reach: the continuity statement, the cast sheet,
 *     the chosen treatment, the teacher's own direction, the light, and the short list
 *     of what must not be in the picture.
 *
 * It used to be a page of labelled stage-direction blocks - a series anchor, a lighting
 * rig, a mood heading in capitals, a composition, a staging note - and it contradicted
 * itself: one sentence asked for "the room dark around them" and another for "nothing
 * crushed to black"; one asked for "arms folding" and another forbade "folded arms". A
 * model given contradictory instructions resolves them by disregarding most of what it was
 * told, which is why adding more direction made the pictures worse. Direction is not the
 * same thing as more words.
 *
 * The order matters as much as the content. Everything that must survive is appended AFTER
 * the length trim, because whatever sits at the end of the trimmable part is the first
 * thing discarded - which is how the cast sheet, the treatment and the teacher's direction
 * were being thrown away on exactly the scenes long enough to need them.
 *
 * Composition is deterministic: the same node always yields the same brief, so
 * regenerating one scene cannot quietly change the look of the set.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_prompt {
    /** @var int The service accepts a prompt of at most this many characters. */
    const MAX_PROMPT = 3000;

    /** @var int The service rejects a prompt shorter than this. */
    const MIN_PROMPT = 10;

    /** @var int The service accepts a scene title of at most this many characters. */
    const MAX_TITLE = 300;

    /** @var int The service accepts a style of at most this many characters. */
    const MAX_STYLE = 300;

    /**
     * How each of the plugin's style choices should be described to the model.
     *
     * The stored value is a single word chosen in the wizard. A word alone gives a
     * model very little; these phrases carry the lens, the light and the palette so
     * that "cinematic" means the same thing in scene one and scene six.
     *
     * @return array Style key mapped to its description.
     */
    protected static function style_phrases(): array {
        return [
            // The lens and the light used to be named here as well as in the frame's own
            // composition and lighting, where they are chosen for the scene - so a 40mm
            // anamorphic was asserted over a 28mm corner shot, and a "bright soft key
            // light" over a half-closed blind. A treatment is the medium and the palette;
            // the lens and the light belong to the frame. "Cinematic, shallow depth of
            // field" was also the most diluted phrase available: it sits on millions of
            // captions spanning every look, so it carries almost no direction, and what
            // it does carry is generic prettiness.
            // "deep blacks" was one more nudge towards a dark frame on the treatment that
            // is meant to look like real life. Noir below keeps its shadows, because a
            // teacher who picks noir has asked for them.
            'photorealistic' => 'documentary reportage on colour negative, available light only, '
                . 'restrained true-to-life colour, clean open shadows',
            'cinematic'      => 'shot on 35mm colour negative, fine grain in the shadows, mild '
                . 'halation on the brightest highlights, muted palette with one warm accent',
            'illustration'   => 'clean editorial illustration, flat shapes with subtle texture, '
                . 'limited three-colour palette, confident line work',
            'watercolour'    => 'loose watercolour illustration, visible paper grain, soft bleeding '
                . 'edges, restrained washes',
            'noir'           => 'high contrast black and white photography, hard directional light, '
                . 'deep shadow, visible grain',
            'oil'            => 'oil painting, visible brushwork, warm earth palette, painterly '
                . 'edges, gallery lighting',
        ];
    }

    /**
     * Compose the brief for one scene.
     *
     * @param array $definition The whole validated scenario.
     * @param array $node The node the image belongs to.
     * @param string $style One of the plugin's image styles.
     * @param bool $crisis Whether this is the crisis variant of the node.
     * @return array Keys: prompt, scenetitle, style, alt.
     */
    public static function for_node(array $definition, array $node, string $style, bool $crisis = false): array {
        $situation = $crisis && !empty($node['crisisvariant']['situation'])
            ? (string)$node['crisisvariant']['situation']
            : (string)($node['situation'] ?? '');

        $people = self::people_in_scene($definition, $node, $situation, $crisis);
        $framecount = max(1, substr_count($people, ';') + ($people !== '' ? 1 : 0));
        // A model renders nouns. This one line is the difference between a photograph of a
        // fee dispute and a photograph of a meeting room.
        // Read from the CRISIS text when this is the crisis frame. It was always read from
        // the calm version, so the frame whose situation is "the machine is screaming and
        // people have stepped back" was given the prop belonging to the quiet scene before
        // it.
        $object = self::focal_object(
            $situation . ' '
            . (string)($crisis && !empty($node['crisisvariant']['challenge'])
                ? $node['crisisvariant']['challenge'] : ($node['challenge'] ?? '')) . ' '
            . implode(' ', array_map(static function ($choice) {
                return (string)($choice['text'] ?? '');
            }, (array)($node['choices'] ?? [])))
        );

        // ONE PARAGRAPH DESCRIBING A PHOTOGRAPH, not a page of stage directions.
        //
        // What this used to build was 2,788 characters of labelled blocks - a series
        // anchor, a cast sheet, a composition, a lighting rig, a mood heading in capitals,
        // a staging note, a list of prohibitions - and it argued with itself. One frame
        // asked for "the room dark around them" and, a sentence later, for "nothing crushed
        // to black". Another asked for "arms folding" and then forbade "folded arms". It
        // described most people as "seen from behind, face not the subject of the image",
        // so the pictures had no faces in them. A model given contradictory instructions
        // resolves them by ignoring most of what it was told, which is why the pictures
        // came back generic and dark however much direction was added.
        //
        // Direction is not the same thing as more words. This describes the photograph that
        // should exist, once, in plain prose, and stops.
        $prompt = self::scene_paragraph($definition, $node, $situation, $crisis, $object, $style);

        return [
            'prompt'     => $prompt,
            'scenetitle' => \core_text::substr(trim((string)($node['title'] ?? '')), 0, self::MAX_TITLE),
            'style'      => \core_text::substr(self::style_phrase($style), 0, self::MAX_STYLE),
            'alt'        => self::alt_text($node, $situation, $crisis),
        ];
    }

    /**
     * Compose the brief for a consequence's reaction frame.
     *
     * THE CUT TO THE FACE.
     *
     * The consequence screen showed the decision's own photograph again. The reasoning
     * written into the player was that the room has not changed because the learner chose
     * something in it. True, and beside the point: the PEOPLE have changed, and the people
     * are what the screen is about. A film does not hold on the wide shot while somebody
     * reacts.
     *
     * So this is deliberately not a scene brief. Closer framing, one or two people, and
     * what their face and hands are doing - the same discipline as the crisis frame, aimed
     * at a person rather than at a room. Everything that keeps the set coherent is
     * unchanged, because it comes from the same fixed_tail() the scene frames use: same
     * cast sheet, same genders, same treatment, same safety clauses.
     *
     * One frame per outcome signal rather than per choice. A node's three choices usually
     * resolve to positive, neutral and negative, and the reaction a learner needs to see is
     * the reaction to THAT.
     *
     * @param array $definition The whole validated scenario.
     * @param array $node The decision node this reaction follows.
     * @param string $style One of the plugin's image styles.
     * @param string $signal positive, neutral or negative.
     * @return array Keys: prompt, scenetitle, style, alt.
     */
    public static function for_reaction(
        array $definition,
        array $node,
        string $style,
        string $signal
    ): array {
        // What actually happened, in the scenario's own words: the consequence text of a
        // choice carrying this signal. Briefing from the node's situation instead would
        // describe the moment BEFORE the decision, which is the picture this frame exists
        // to stop repeating.
        $consequence = '';
        foreach ((array)($node['choices'] ?? []) as $choice) {
            if ((string)($choice['signal'] ?? '') !== $signal) {
                continue;
            }
            $consequence = trim((string)($choice['consequence'] ?? ''));
            if ($consequence !== '') {
                break;
            }
        }
        if ($consequence === '') {
            return ['prompt' => '', 'scenetitle' => '', 'style' => '', 'alt' => ''];
        }

        $setting = trim((string)($definition['setting'] ?? ''));
        $setting = $setting !== '' ? \core_text::substr($setting, 0, 220) : 'a workplace';

        // Who is in the frame. The reaction is on somebody's face, so a named person is
        // worth far more here than "workers" - and people_in_scene() reads the consequence
        // text for names the same way it reads a situation.
        $people = self::people_in_scene($definition, $node, $consequence, false);
        $cast = $people !== ''
            ? 'The person in shot is ' . rtrim(trim($people), '.') . '. '
            : '';

        $teacher = trim((string)($node['imageprompt'] ?? ''));
        $teacherline = $teacher !== ''
            ? 'Additional direction: '
                . self::sentence(self::dequote(self::clip($teacher, 300))) . ' '
            : '';

        $body = self::opener($style) . ' '
            . rtrim(self::reaction_feel($signal), '.') . '. '
            . 'Closer than a room shot: head and shoulders, or head and hands, so the '
            . 'reaction on their face is the subject of the picture. '
            . 'The setting is still ' . rtrim($setting, '.') . ', recognisably the same '
            . 'place as the wider frames in this set, but out of focus behind them. '
            . $cast
            . 'What has just happened: '
            . self::sentence(self::dequote(self::clip(
                trim(preg_replace('/\s+/u', ' ', $consequence)),
                600
            ))) . ' '
            . 'Show the moment just after it landed, not the moment it was decided. '
            . 'Nobody is speaking. ';

        $tail = self::fixed_tail($definition, $style, $teacherline);
        $prompt = self::fit($body, self::MAX_PROMPT - \core_text::strlen($tail) - 1);

        return [
            'prompt'     => trim($prompt . ' ' . $tail),
            'scenetitle' => \core_text::substr(
                trim((string)($node['title'] ?? '')),
                0,
                self::MAX_TITLE
            ),
            'style'      => \core_text::substr(self::style_phrase($style), 0, self::MAX_STYLE),
            'alt'        => self::reaction_alt($consequence),
        ];
    }

    /**
     * How a reaction should read, by what the decision cost.
     *
     * Behaviour a camera can see, never an emotion word on its own: "relieved" is a label,
     * "the breath they had been holding going out of them" is a photograph.
     *
     * @param string $signal positive, neutral or negative.
     * @return string
     */
    protected static function reaction_feel(string $signal): string {
        if ($signal === 'positive') {
            return 'The moment something goes right: the tension going out of someone\'s '
                . 'shoulders, a small nod, the beginning of relief rather than celebration';
        }
        if ($signal === 'negative') {
            return 'The moment the cost lands on somebody: the face of a person taking in '
                . 'news they did not want, jaw set, eyes down, absolutely still';
        }
        return 'The moment nothing is settled: a person left holding a question, mouth '
            . 'half open as if about to say something and not saying it, unresolved';
    }

    /**
     * Alt text for a reaction frame.
     *
     * The same shape as alt_text() above: the scenario's own words, condensed. The picture
     * shows somebody reacting to what happened, so what happened is what describes it.
     *
     * @param string $consequence What happened.
     * @return string
     */
    protected static function reaction_alt(string $consequence): string {
        return \core_text::substr(self::condense($consequence), 0, 250);
    }

    /**
     * The whole brief for one frame, as a single described photograph.
     *
     * @param array $definition The whole scenario.
     * @param array $node The node this frame belongs to.
     * @param string $situation The situation text for this frame.
     * @param bool $crisis Whether this is the crisis variant.
     * @param string $object The focal object clause, or an empty string.
     * @param string $style One of the plugin's image styles.
     * @return string
     */
    protected static function scene_paragraph(
        array $definition,
        array $node,
        string $situation,
        bool $crisis,
        string $object,
        string $style
    ): string {
        $setting = trim((string)($definition['setting'] ?? ''));
        $setting = $setting !== '' ? \core_text::substr($setting, 0, 220) : 'a workplace';

        $people = self::people_in_scene($definition, $node, $situation, $crisis);
        $cast = $people !== ''
            ? 'The people in shot are ' . rtrim(trim($people), '.') . '. '
            : '';

        // The scenario's own words for what is happening, as prose rather than as a field.
        $what = trim(preg_replace('/\s+/u', ' ', $situation));
        $what = $what !== '' ? self::clip($what, 700) : '';

        // THE LINE SOMEBODY IS SAYING, which the rewrite dropped.
        //
        // It is the single most photographable thing on a decision slide: it tells the
        // model that one person has the floor and the others are reacting, which is the
        // difference between a photograph of a conversation and a photograph of some
        // people near a table. Losing it was the one real piece of content the rewrite
        // lost, as opposed to the film-school jargon it was meant to lose.
        $speech = $crisis && !empty($node['crisisvariant']['facilitatorspeech'])
            ? (string)$node['crisisvariant']['facilitatorspeech']
            : (string)($node['facilitatorspeech'] ?? '');
        $speech = trim(preg_replace('/\s+/u', ' ', $speech));
        // Trimmed of ANY sentence-ending punctuation before the closing quote is added.
        // Trimming only the full stop produced 'can we just fix it ourselves?."' - a
        // question mark, a full stop and a quote in a row, which is the kind of small mess
        // that makes a brief read as machine-assembled.
        // No quotation marks, which is a decision this codebase already made and which I
        // re-broke: a quote inside an image brief invites the model to letter it into the
        // picture as a speech bubble or a caption. The words stay, the marks come off.
        $saidline = $speech !== ''
            ? 'One of them is saying, in substance: '
                . self::sentence(self::dequote(self::clip($speech, 240)))
                . ' Show them mid-sentence with the others listening and reacting. '
            : '';

        // THE CAST SHEET, which the rewrite dropped with the rest of the anchor.
        //
        // It is what stops the lawyer called Mark in scene one being a different person in
        // scene three: every frame carries the same description of every character, so the
        // model is never left to invent one. Dropping it was the most expensive thing the
        // rewrite did, because a set whose people change is a set a learner cannot follow -
        // and it is not a fault a single picture ever shows, only the set.
        $sheet = self::cast_sheet($definition);
        $sheet = $sheet !== '' ? rtrim($sheet) . ' ' : '';

        $teacher = trim((string)($node['imageprompt'] ?? ''));
        // Labelled, so the teacher's own words are visibly the last word on content rather
        // than running into the sentence before them.
        $teacherline = $teacher !== ''
            ? 'Additional direction: '
                . self::sentence(self::dequote(self::clip($teacher, 300))) . ' '
            : '';

        // WHAT MUST SURVIVE A TRIM GOES AFTER IT, NOT BEFORE IT.
        //
        // The cast sheet, the treatment and the teacher's own direction were written into
        // the body - which is the only part fit() is allowed to cut - and they were written
        // at the END of it, so they were the first three things discarded. On any wordy
        // node the set came back in mixed styles, the characters were free to change
        // appearance, and the teacher's direction was silently ignored. Every one of those
        // is documented three comments above as the thing that must not be lost.
        //
        // They are part of the fixed tail now. Only the narrative prose is trimmable, which
        // is what the priority ladder was always supposed to mean.
        $body = self::opener($style) . ' '
            . rtrim(self::feel($node, $crisis, $definition), '.') . '. '
            . 'The setting is ' . rtrim($setting, '.') . '. '
            . $cast
            . ($what !== '' ? 'What is happening: ' . self::sentence(self::dequote($what)) . ' ' : '')
            . $saidline
            . (($object !== '' && self::wants_prop($node))
                ? rtrim(trim($object), '.') . '. ' : '')
            . '';

        // Continuity, cast and treatment: fixed, and phrased so they do not assert more
        // than is true. "The same people" used to be stated on every frame while each frame
        // named a different subset of them, and an instruction the rest of the prompt
        // contradicts teaches a model that the whole paragraph is soft.
        $tail = self::fixed_tail($definition, $style, $teacherline);
        $prompt = self::fit($body, self::MAX_PROMPT - \core_text::strlen($tail) - 1);
        return trim($prompt . ' ' . $tail);
    }

    /**
     * Everything a frame's brief must carry whatever else is trimmed.
     *
     * The cast sheet, each person's gender, the treatment, the teacher's own direction and
     * the safety clauses. These were once written into the body - the only part fit() is
     * allowed to cut - and at the END of it, so they were the first things discarded on any
     * wordy node: the set came back in mixed styles, characters changed appearance between
     * frames and the teacher's direction was silently ignored.
     *
     * Extracted so the reaction frames below carry byte-for-byte the same tail as the scene
     * frames. Two copies of this text would have drifted the first time one was edited, and
     * a set whose frames carry different cast sheets is a set whose people change.
     *
     * @param array $definition The whole scenario.
     * @param string $style One of the plugin's image styles.
     * @param string $teacherline The teacher's own direction, already formatted, or empty.
     * @return string
     */
    protected static function fixed_tail(array $definition, string $style, string $teacherline): string {
        $sheet = self::cast_sheet($definition);
        $sheet = $sheet !== '' ? rtrim($sheet) . ' ' : '';

        $fixed = 'The same place and the same treatment as the other images in this set, so '
            . 'they read as one continuous series. Only the people named above appear in '
            . 'this frame. '
            . $sheet
            . self::gender_line($definition)
            . 'Treatment, identical in every frame of the set: '
            . rtrim(self::style_phrase($style), '.') . '. ';

        // How it should look, then what must not be in it. The look clause follows the
        // treatment: telling a noir frame it wants "soft natural lighting", or an oil
        // painting that it is "a realistic photograph", is the same self-contradiction the
        // rewrite existed to remove, reintroduced as fixed text.
        return $fixed . $teacherline . self::look($style) . ' '
            . 'Paperwork, screens and signage may be present but turned away or out of focus '
            . 'so that no text is readable. No captions, subtitles, watermarks, logos or '
            . 'brand marks. Do not depict any real or identifiable person, and do not '
            . 'imitate any living person\'s likeness. Everyone shown is an adult dressed as '
            . 'the scenario describes: no children or young people, no nudity, no weapons, '
            . 'no violence, no injury and no medical procedure shown in detail. '
            . 'Workplace-appropriate for adult vocational learners.';
    }

    /**
     * How the frame is announced, in the medium the teacher actually chose.
     *
     * Every brief opened "A realistic professional workplace training photograph" whatever
     * the style, and closed with "Realistic and authentic" - so a teacher who chose
     * watercolour, illustration or oil had a photograph asserted twice against their
     * chosen treatment, in fixed text, while the treatment clause itself was the part the
     * trim removed. Four of the six paid styles could not work.
     *
     * @param string $style One of the plugin's image styles.
     * @return string
     */
    protected static function opener(string $style): string {
        $openers = [
            'illustration' => 'A professional workplace training illustration.',
            'watercolour'  => 'A watercolour illustration for workplace training.',
            'oil'          => 'An oil painting for workplace training.',
            'noir'         => 'A black and white workplace training photograph.',
        ];
        return $openers[$style] ?? 'A realistic professional workplace training photograph.';
    }

    /**
     * The look clause, which must agree with the treatment rather than fight it.
     *
     * "Soft natural lighting, bright and well-exposed" was stated on every frame including
     * the noir one, whose whole treatment is hard directional light and deep shadow. The
     * exposure floor is still stated everywhere, because an image a learner cannot read
     * teaches nothing - but it is stated in terms the chosen medium can honour.
     *
     * @param string $style One of the plugin's image styles.
     * @return string
     */
    protected static function look(string $style): string {
        $common = 'Natural expressions, diverse everyday workers. Landscape orientation, '
            . 'clean composition with quiet space around the subject. Nobody posing for the '
            . 'camera and nobody looking at it.';
        $light = [
            'noir' => 'Hard directional light as the treatment describes, with every face '
                . 'still clearly readable and no part of the subject lost in black.',
            'oil' => 'Even gallery lighting, every face clearly readable.',
            'illustration' => 'Clear even light, every face clearly readable.',
            'watercolour' => 'Clear even light, every face clearly readable.',
        ];
        $default = 'Soft natural lighting, bright and well-exposed, every face clearly '
            . 'visible and nothing crushed to black.';
        return ($light[$style] ?? $default) . ' ' . $common;
    }

    /**
     * Does this text mention that name as a word in its own right?
     *
     * @param string $haystack Lowercased text to search.
     * @param string $name Lowercased name or first name.
     * @return bool
     */
    protected static function names_someone(string $haystack, string $name): bool {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        return preg_match('/\b' . preg_quote($name, '/') . '\b/u', $haystack) === 1;
    }

    /**
     * Take at most this many characters, and stop at a word.
     *
     * The situation, the spoken line and the teacher's direction were each cut at a fixed
     * character count and then had a full stop put on the end, so a long one arrived as
     * "...situationsentencefragmen." - which is not a shorter instruction, it is a sentence
     * the model has to guess the end of, and guessing is what produces the generic frame.
     * The same reasoning the trim at the end of the brief already used, applied where the
     * cutting actually happens.
     *
     * @param string $text Text to shorten.
     * @param int $max Character ceiling.
     * @return string
     */
    protected static function clip(string $text, int $max): string {
        $text = trim($text);
        if (\core_text::strlen($text) <= $max) {
            return $text;
        }
        $cut = \core_text::substr($text, 0, $max);
        // Character offsets throughout: strrpos() returns bytes, and mixing the two is why
        // the sentence-boundary guard did nothing at all on any non-Latin script.
        $space = \core_text::strrpos($cut, ' ');
        if ($space !== false && $space > (int)($max * 0.5)) {
            $cut = \core_text::substr($cut, 0, $space);
        }
        return rtrim($cut, " ,;:-");
    }

    /**
     * Take the quotation marks off borrowed text, keeping the words.
     *
     * @param string $text Text taken from the scenario.
     * @return string
     */
    protected static function dequote(string $text): string {
        // U+2019 is the typographic APOSTROPHE as well as a closing single quote, and it is
        // what every assistant-written scenario uses. Stripping it turned "I don't think
        // we're ready" into "I dont think were ready" - the scenario's own words, mangled,
        // in a paid request. Only paired quotation marks come off; the apostrophe stays.
        return trim((string)preg_replace(
            '/["\x{201C}\x{201D}\x{00AB}\x{00BB}\x{201E}\x{300C}\x{300D}]/u',
            '',
            $text
        ));
    }

    /**
     * End a piece of borrowed text as one sentence, without doubling its punctuation.
     *
     * The scenario's own prose arrives ending in every possible way - a full stop, a
     * question mark, or a closing quotation mark where a principle's example is a line
     * somebody says. Appending a full stop to all of them produced "...ourselves?." and
     * '..."before they start.".', and stripping the punctuation instead left a quotation
     * mark that never closed. It is terminated only when it needs terminating.
     *
     * @param string $text Text taken from the scenario.
     * @return string
     */
    protected static function sentence(string $text): string {
        $text = rtrim($text);
        if ($text === '') {
            return '';
        }
        return preg_match('/[.?!"\']$/u', $text) ? $text : $text . '.';
    }

    /**
     * Does this frame want the object on the table in it?
     *
     * The prop clause describes a decision moment - something pushed halfway across the
     * bench and left there, waiting on somebody. It was being printed on every frame, so
     * an ending where the matter was settled and a lesson slide teaching a rule both had
     * the same unfinished paperwork lying between the people in them. Continuity is worth
     * a lot; the same unresolved prop in a resolved scene is not continuity, it is a
     * contradiction the viewer has to explain away.
     *
     * @param array $node The node.
     * @return bool
     */
    protected static function wants_prop(array $node): bool {
        $id = (string)($node['id'] ?? '');
        if (strpos($id, 'lesson_') === 0 || strpos($id, 'debrief_') === 0) {
            return false;
        }
        return (string)($node['type'] ?? '') !== 'outcome';
    }

    /**
     * How this frame should feel, as the subject of the photograph.
     *
     * One clause naming what a camera would see, taken from where the frame sits in the
     * story. It replaces a block of capitalised mood headings that a model read as text to
     * render rather than as direction.
     *
     * @param array $node The node.
     * @param bool $crisis Whether this is the crisis variant.
     * @return string
     */
    protected static function feel(array $node, bool $crisis, array $definition = []): string {
        // WHO IS IN IT, AND WHERE.
        //
        // Every branch of this used to say "colleagues", "workmate" and "the room". A
        // scenario set on a packing line was told "nothing urgent left in the room"; a
        // scenario with one character was told "colleagues" and, from the prop clause,
        // "on the surface between them". The plural and the room were asserted as fixed
        // text over whatever the scenario actually was.
        $cast = count((array)($definition['characters'] ?? []))
            + (!empty($definition['facilitator']['name']) ? 1 : 0);
        $alone = $cast === 1;
        $who = $alone ? 'A worker' : 'Workers';
        $are = $alone ? 'is' : 'are';

        // A lesson slide teaches the rule; it should show the rule being kept.
        if (strpos((string)($node['id'] ?? ''), 'lesson_') === 0) {
            return $alone
                ? 'A worker doing this part of the job properly and without fuss'
                : 'A worker doing this part of the job properly and without fuss, while a '
                    . 'workmate nearby sees them do it';
        }
        // A debrief page is a summary, not a moment in the story.
        if (strpos((string)($node['id'] ?? ''), 'debrief_') === 0) {
            return $alone
                ? 'A worker at the end of the day, thinking the whole thing over calmly'
                : 'Workers talking the whole thing over calmly afterwards, nothing urgent '
                    . 'left between them';
        }
        if ((string)($node['type'] ?? '') === 'outcome') {
            $outcome = (string)($node['outcome'] ?? 'mixed');
            if ($outcome === 'strong') {
                return 'The closing moment of the set: ' . lcfirst($who) . ' at the end of a '
                    . 'difficult conversation that has gone well, visibly relieved, '
                    . 'people who know it worked';
            }
            if ($outcome === 'highrisk') {
                return 'The closing moment of the set: the aftermath of a conversation that '
                    . 'has gone badly. ' . $who . ' ' . $are . ' serious and subdued, nobody '
                    . 'quite looking at anyone else';
            }
            return 'The closing moment of the set: ' . lcfirst($who) . ' at the end of a '
                . 'conversation with the matter only partly settled, thoughtful and undecided';
        }
        if ($crisis) {
            return $alone
                ? 'A worker at the worst moment of this, concentrating hard, the situation '
                    . 'having got away from them'
                : 'Workers in the middle of a tense disagreement at work, one of them '
                    . 'holding a hand up to pause the other, everyone concentrating hard';
        }
        if ((int)($node['stage'] ?? 1) <= 1) {
            return $alone
                ? 'A worker going about an ordinary task, just beginning to notice that '
                    . 'something is not right'
                : 'Workers in a calm, everyday exchange at work, one of them beginning to '
                    . 'notice that something is not right';
        }
        return $alone
            ? 'A worker working through a difficult call, attentive and serious'
            : 'Workers working through a difficult conversation at work, attentive and '
                . 'serious, the first real disagreement showing';
    }


    /**
     * Each person's gender, stated on its own, in its own sentence.
     *
     * It was already in the cast sheet - as one comma-separated item among four, between a
     * job title and a description of somebody's jacket. An image model weights a short
     * explicit sentence far more heavily than a clause buried in a list, and this is the
     * one attribute a learner notices being wrong the instant they see it: a woman in the
     * frame beside the word "he" is the whole illusion gone.
     *
     * Where a gender is genuinely unstated the person is simply left out of this sentence.
     * Saying "gender not specified" would be worse than useless - it invites the model to
     * choose and to tell itself it was asked to.
     *
     * In the FIXED TAIL, with the cast sheet, where the length trim cannot reach it.
     *
     * @param array $definition The whole scenario.
     * @return string The sentence, or empty when nobody's gender is recorded.
     */
    protected static function gender_line(array $definition): string {
        $stated = [];
        $seen = [];
        $everyone = array_merge(
            !empty($definition['facilitator']['name']) ? [$definition['facilitator']] : [],
            (array)($definition['characters'] ?? [])
        );
        foreach ($everyone as $person) {
            $name = trim((string)($person['name'] ?? ''));
            $gender = (string)($person['gender'] ?? '');
            if ($name === '' || isset($seen[\core_text::strtolower($name)])) {
                continue;
            }
            if ($gender !== 'male' && $gender !== 'female' && $gender !== 'non-binary') {
                continue;
            }
            $seen[\core_text::strtolower($name)] = true;
            $stated[] = $name . ' is ' . ($gender === 'non-binary' ? 'non-binary' : $gender);
            if (count($stated) >= 6) {
                break;
            }
        }
        if (!$stated) {
            return '';
        }
        return 'Gender, exactly as stated here and never changed between frames: '
            . implode('; ', $stated) . '. ';
    }

    /**
     * Every person this scenario can show, described identically in every frame's brief.
     *
     * @param array $definition The whole scenario.
     * @return string
     */
    protected static function cast_sheet(array $definition): string {
        $people = [];
        $seen = [];
        $everyone = array_merge(
            !empty($definition['facilitator']['name']) ? [$definition['facilitator']] : [],
            (array)($definition['characters'] ?? [])
        );
        foreach ($everyone as $person) {
            $name = trim((string)($person['name'] ?? ''));
            if ($name === '' || isset($seen[\core_text::strtolower($name)])) {
                continue;
            }
            $seen[\core_text::strtolower($name)] = true;
            $bits = [$name];
            foreach (['role', 'gender', 'appearance', 'trait'] as $field) {
                $value = trim((string)($person[$field] ?? ''));
                if ($value !== '') {
                    $bits[] = $value;
                }
            }
            // Capped per person. A scenario whose character records run to a paragraph each
            // pushed everything after the cast sheet - the object, the light, the hands,
            // the scene itself - out of the budget, so the frame that came back was three
            // extremely well described people standing in nothing.
            $people[] = \core_text::substr(implode(', ', $bits), 0, 140);
            if (count($people) >= 6) {
                break;
            }
        }
        if (!$people) {
            return '';
        }
        return 'The cast, identical in every frame - face, age, build, gender presentation, '
            . 'hair and clothing: ' . implode('; ', $people) . '. No substitutions.';
    }


    /**
     * Describe the people who appear in this scene.
     *
     * A character is included when the scene names them, so that a figure in the frame
     * is someone the learner has met rather than a stranger. Their description comes
     * from the scenario's own record of them, which is what keeps them recognisable
     * from one scene to the next.
     *
     * @param array $definition The whole scenario.
     * @param array $node The node.
     * @param string $situation The prose for this scene.
     * @param bool $crisis Whether this is the crisis variant.
     * @return string
     */
    protected static function people_in_scene(array $definition, array $node, string $situation, bool $crisis): string {
        $speech = $crisis && !empty($node['crisisvariant']['facilitatorspeech'])
            ? (string)$node['crisisvariant']['facilitatorspeech']
            : (string)($node['facilitatorspeech'] ?? '');
        $haystack = \core_text::strtolower($situation . ' ' . $speech . ' ' . (string)($node['title'] ?? ''));

        $candidates = [];
        $facilitator = $definition['facilitator'] ?? [];
        if (!empty($facilitator['name']) && $speech !== '') {
            // The facilitator is speaking, so they are in the frame.
            $candidates[] = $facilitator;
        }
        foreach ((array)($definition['characters'] ?? []) as $character) {
            if (empty($character['name'])) {
                continue;
            }
            $name = \core_text::strtolower($character['name']);
            $first = preg_split('/\s+/u', $name)[0];
            // MATCHED AS WORDS, NOT AS SUBSTRINGS.
            //
            // A plain strpos() put Mark, Ana and Bill in a frame whose text read "the
            // billing report was marked up during the analysis" and named none of them:
            // mark is inside marked, ana inside analysis, bill inside billing. Sam is
            // inside sample, Ed inside edited, Al inside also. The frame then showed three
            // people who are not in that scene, described in full, which is worse than
            // showing nobody.
            if (self::names_someone($haystack, $name) || self::names_someone($haystack, $first)) {
                $candidates[] = $character;
            }
        }

        $described = [];
        $seen = [];
        foreach ($candidates as $person) {
            $key = \core_text::strtolower($person['name']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            // Names only. The cast sheet a few sentences above has already given every
            // one of these people their full appearance record; repeating it here spent
            // three hundred characters saying nothing new, and pushed the staging - which
            // is the part that decides whether the picture is any good - off the end of
            // the budget entirely.
            $described[] = $person['name'];
            if (count($described) >= 3) {
                break;
            }
        }

        // Falling through to a faceless stranger was how a set acquired people who were in
        // no other frame. When the scenario has a cast, the scene shows members of THAT
        // cast even where this node's prose happens to name nobody - the cast sheet in the
        // anchor above says who they are, so the model has a person to draw rather than an
        // invitation to invent one.
        if (!$described) {
            $known = array_values(array_filter(array_map(
                static function ($person) {
                    return trim((string)($person['name'] ?? ''));
                },
                array_merge(
                    !empty($definition['facilitator']['name']) ? [$definition['facilitator']] : [],
                    (array)($definition['characters'] ?? [])
                )
            )));
            if ($known) {
                return implode(' and ', array_slice($known, 0, 2))
                    . ', looking as the cast sheet describes them';
            }
            // It used to hide them: "seen from behind or in three-quarter view, face not
            // the subject of the image". So the frames a scenario falls back to most often
            // had no faces in them, which is the one thing a picture of a conversation has
            // to have. A learner remembers a person, not the back of a head.
            return 'two or three colleagues, faces visible and clearly lit';
        }

        // The cast sheet in the anchor has already said, at length, that these people do
        // not change. Saying it again adds nothing per frame and raises the attention the
        // model pays to continuity at the expense of what is happening.
        return implode('; ', $described);
    }


    /**
     * One physical object this scene is actually about.
     *
     * A model renders nouns. Handed "the client cares about money more than timing" it has
     * no pixels to attach that to, so it drops the proposition and draws the only concrete
     * noun within reach - a meeting room - which is the honest answer to a question that
     * described no object. It is also the whole of the memorability argument: a meeting
     * room is not retrievable a year later because it is identical to ten thousand other
     * meeting rooms already in memory, while a fee estimate with one figure ringed twice in
     * red biro is.
     *
     * The scenario does not carry an object field yet, so the object is read out of the
     * words the scene already uses. Crude next to one an author would choose, and far
     * better than none.
     *
     * @param string $text Everything the scene says.
     * @return string A sentence naming an object, or an empty string.
     */
    protected static function focal_object(string $text): string {
        $haystack = \core_text::strtolower($text);
        // Ordered: the first that matches wins, so the more specific subjects are asked
        // about before the general ones.
        $objects = [
            'roster|rota|shift|cover|staffing'
                => 'a laminated rota sheet on the table, its surface scuffed from repeated rubbing out',
            'invoice|fee|price|cost|budget|quote|discount|margin'
                => 'a printed spreadsheet lying face up, one row ringed in biro',
            'deadline|timing|delay|schedule|timeline|overdue|completion|handover'
                => 'a wall planner behind them, one square ringed in marker and several scored through',
            'contract|clause|draft|term|agreement|signature|sign'
                => 'a thick bound document open flat, one page flagged with a bent sticky note',
            'sample|batch|spec|tolerance|defect|faulty'
                => 'a sealed sample bag set down on the surface, its tag half peeled away',
            'complaint|escalat|grievance|incident|report'
                => 'a sheet of paper folded in three and flattened out again on the table',
            'audit|evidence|compliance|record|logbook'
                => 'a ring binder open at a coloured tabbed divider, one page turned back on itself',
            'medication|patient|clinical|dose|chart'
                => 'a printed chart clipped to a board, the top sheet curling at the corner',
            'training|competenc|assessment|learner|student'
                => 'a marked-up cover sheet on the desk, one tick box still empty',
            'safety|hazard|ppe|risk|injury|incident'
                => 'a checklist on a clipboard, the last two lines still blank',
            'machine|equipment|line|conveyor|plant|breakdown|maintenance'
                => 'a hardbacked log book open on the bench, the last line only part written',
        ];
        // DESCRIBED BY SHAPE, NOT BY WHAT IT SAYS.
        //
        // Every one of these used to be identified by its lettering - "names rubbed out and
        // rewritten", "one line ringed in biro", "one box left unticked" - in a brief whose
        // closing instruction is that no text in the picture may be readable. A prop that
        // can only be recognised by reading it is a prop that argues with the safety line,
        // and the model resolves that by rendering legible text or by rendering neither.
        // They are recognisable now by their form: a tab, a ring, a fold, an empty box.
        foreach ($objects as $pattern => $object) {
            if (preg_match('/\b(' . $pattern . ')/u', $haystack)) {
                return self::prop_phrase($object);
            }
        }
        // No frame is left without an object. A model needs a noun, and "a meeting" is not
        // one - handed nothing physical it renders the training mean, which is the stock
        // photograph. A plain object beats no object every time, and it still gives the
        // scene a foreground, a place for hands to be, and something for eyes to go to.
        return self::prop_phrase('a clipboard face down beside a mug gone cold');
    }

    /**
     * Put the object into the frame without assuming two people are sitting across a table.
     *
     * "On the surface between them, pushed halfway across" described a decision moment with
     * at least two people in it, and it was printed on single-person scenarios and on
     * standing scenes alike.
     *
     * @param string $object The object itself.
     * @return string
     */
    protected static function prop_phrase(string $object): string {
        return 'In the foreground, close enough to be part of the scene: ' . $object . '. ';
    }







    /**
     * Reduce narrative prose to the action worth drawing.
     *
     * Dialogue is removed because a picture cannot carry it and its presence tends to
     * push a model toward speech bubbles and lettering, which the direction forbids.
     *
     * @param string $text Scene prose.
     * @return string
     */
    protected static function condense(string $text): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }
        // Quoted speech used to be dropped, on the reasoning that a quote in an image
        // brief invites lettering or a speech bubble. What it actually did was remove the
        // most concrete thing on the page: a scene whose whole content was a line of
        // dialogue condensed to nothing, and the model was left inventing a generic
        // office. The quotation marks come off instead, so the words describe what is
        // being said while the safety direction below keeps text out of the picture.
        $text = trim((string)preg_replace(
            '/["\x{201C}\x{201D}]([^"\x{201C}\x{201D}]*)["\x{201C}\x{201D}]/u',
            '$1',
            $text
        ));
        // Two sentences and 600 characters, out of a 3,000-character budget of which the
        // brief was using barely a third. On a five-sentence situation three sentences were
        // thrown away before the model ever saw them, so it was asked to picture a fragment
        // and returned a generic meeting room - which is the honest answer to a generic
        // question. It reads the paragraph.
        // Five sentences of narrative was the wrong correction to two. Prose written to be
        // READ is abstract - it states what people want and believe - and a model cannot
        // photograph a belief, so the extra sentences did not make the picture more
        // specific; they crowded out the staging, which is the part that does. Two
        // sentences of situation, and the budget spent on hands and eyelines instead.
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $kept = array_slice(array_filter(array_map('trim', $sentences)), 0, 2);
        return \core_text::substr(implode(' ', $kept), 0, 480);
    }

    /**
     * The description a screen reader announces for this image.
     *
     * The generated scenario may supply one, in the scenario's own language, which is
     * always preferable to anything composed here. This is the fallback, used when it
     * did not, so that a scene image is never announced with nothing at all.
     *
     * @param array $node The node.
     * @param string $situation The prose for this scene.
     * @param bool $crisis Whether this is the crisis variant.
     * @return string
     */
    public static function alt_text(array $node, string $situation, bool $crisis): string {
        $supplied = trim((string)($node['imagealt'] ?? ''));
        if ($supplied !== '') {
            return \core_text::substr($supplied, 0, 250);
        }
        $fallback = self::condense($situation);
        if ($fallback === '') {
            $fallback = trim((string)($node['title'] ?? ''));
        }
        if ($crisis && $fallback !== '') {
            $fallback = $fallback . ' The situation has escalated.';
        }
        return \core_text::substr($fallback, 0, 250);
    }

    /**
     * The model-facing description of a style key.
     *
     * @param string $style Style key.
     * @return string
     */
    protected static function style_phrase(string $style): string {
        $phrases = self::style_phrases();
        return $phrases[$style] ?? $phrases['cinematic'];
    }

    /**
     * Bring a composed brief within the length the service accepts.
     *
     * @param string $prompt Composed brief.
     * @return string
     */
    protected static function fit(string $prompt, int $max = self::MAX_PROMPT): string {
        $prompt = trim(preg_replace('/\s+/u', ' ', $prompt));
        if (\core_text::strlen($prompt) > $max) {
            // Cutting at the character was leaving briefs ending "...leaning forward on
            // straight arms with both palms", which is not a shorter instruction - it is a
            // sentence the model has to guess the end of, and guessing is what produces
            // the generic frame. It falls back to the last full stop instead, so what
            // survives is whole.
            $cut = trim(\core_text::substr($prompt, 0, $max));
            // Uses core_text::strrpos, not strrpos. The byte offset a plain strrpos() returns
            // was being handed to a CHARACTER-indexed substr, so on any script with
            // multi-byte characters the offset overshot the string, substr returned it
            // unchanged, and the brief ended mid-word - which is the exact failure this
            // code exists to prevent, silently switched off for every non-Latin language.
            $stop = max(
                (int)\core_text::strrpos($cut, '. '),
                (int)\core_text::strrpos($cut, '? '),
                (int)\core_text::strrpos($cut, '! ')
            );
            if ($stop > (int)($max * 0.6)) {
                $cut = \core_text::substr($cut, 0, $stop + 1);
            }
            $prompt = trim($cut);
        }
        if (\core_text::strlen($prompt) < self::MIN_PROMPT) {
            // Nothing usable was supplied; a bare but valid brief still beats a failure.
            $prompt = 'A workplace training scene.';
        }
        return $prompt;
    }

    /**
     * The number of images a scenario will generate, for cost estimation.
     *
     * Every image is billed, so a teacher should be told what a run will cost before
     * it starts rather than after. Crisis variants are counted because they are
     * generated as their own frame.
     *
     * @param array $definition Validated scenario.
     * @return int
     */
    public static function count_images(array $definition): int {
        $count = 0;
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (trim((string)($node['situation'] ?? '')) !== '') {
                $count++;
            }
            if (!empty($node['crisisvariant']['situation'])) {
                $count++;
            }
        }

        // One picture per principle, briefed from the principle's own words. Added when the
        // lesson slides stopped borrowing a decision node's photograph - and the estimate
        // has to move with it, or the quota is charged for twelve pictures while fourteen
        // are made. This is the third time a picture has been added without the count: the
        // harness now compares this against a real run, which is what caught it.
        foreach ((array)($definition['principles'] ?? []) as $principle) {
            $text = trim((string)($principle['summary'] ?? '') . ' ' . (string)($principle['example'] ?? ''));
            if ($text !== '') {
                $count++;
            }
        }

        // ONE PICTURE PER DEBRIEF ENTRY, not per page, since v1.81.0.
        //
        // Each of the four pages used to get one frame briefed from the whole page's text.
        // The entries are what a learner reads one at a time, so each entry has its own
        // frame, keyed to match the narration clip that reads it.
        //
        // This estimate has now been wrong three separate times, always the same way: a
        // picture was added and the count was not. That is why the harness compares this
        // function against what a REAL run asks for rather than against itself - a mirror
        // checked only against its own reflection is not a check.
        $debrief = (array)($definition['debrief'] ?? []);
        $entries = array_merge(
            array_values((array)($debrief['whatmattered'] ?? [])),
            array_values((array)($debrief['criticaldecisions'] ?? [])),
            array_values((array)($debrief['practice'] ?? [])),
            array_values(array_map(
                static function ($takeaway) {
                    return trim(trim((string)($takeaway['heading'] ?? ''), " .") . '. '
                        . (string)($takeaway['body'] ?? ''));
                },
                (array)($definition['takeaways'] ?? [])
            ))
        );
        foreach ($entries as $entry) {
            if (trim((string)$entry) !== '') {
                $count++;
            }
        }

        // The opening establishing frame: the place before anyone has done anything. One
        // picture, and it removes the duplicate every learner saw in the first ten seconds.
        if (
            trim((string)($definition['hook'] ?? '')) !== ''
                || trim((string)($definition['setting'] ?? '')) !== ''
        ) {
            $count++;
        }

        // The reaction frames: one per outcome signal each decision node actually uses.
        // Asked of the node rather than assumed, so a node whose choices are all negative
        // is billed for one reaction and not three.
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (!is_array($node)) {
                continue;
            }
            // The signals_used() helper already excludes a signal with no consequence text to brief
            // from, so this counts what the run will actually ask for. It used to re-check
            // the text here while the run did not, which is exactly the kind of drift the
            // harness comparison against a real run exists to catch.
            $count += count(media_manager::signals_used($node));
        }

        return $count;
    }
}
