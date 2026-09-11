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
 * So every brief is built from the same four parts, in the same order:
 *
 *   1. A series anchor, identical in every scene of a scenario. Same location, same
 *      light, same lens. This is what makes the set hang together.
 *   2. The people actually present, described from the scenario's own character
 *      records, so a person looks like themselves each time they appear.
 *   3. The moment: what is happening, and how it should feel, derived from where the
 *      scene sits in the story and whether it is a crisis or an ending.
 *   4. Direction the model needs and a learner should never see - no lettering, no
 *      logos, nobody recognisable.
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
            'photorealistic' => 'photorealistic documentary photography, bright natural daylight, '
                . 'evenly lit, 35mm lens, shallow depth of field, true-to-life colour',
            'cinematic'      => 'cinematic still, anamorphic 40mm lens, bright soft key light with '
                . 'gentle falloff, restrained teal and amber palette, filmic grain',
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

        $parts = [];
        $parts[] = self::series_anchor($definition, $style);

        $people = self::people_in_scene($definition, $node, $situation, $crisis);
        if ($people !== '') {
            $parts[] = $people;
        }

        $parts[] = self::moment($node, $situation, $crisis);

        // A teacher's own direction is the last word on content, so it can override
        // anything above it.
        $teacher = trim((string)($node['imageprompt'] ?? ''));
        if ($teacher !== '') {
            $parts[] = 'Additional direction: ' . $teacher;
        }

        // The safety direction is appended after the rest has been cut to fit. Three
        // people with full appearance records plus a crisis moment can reach the ceiling
        // on their own, and cutting from the tail would have removed the only text that
        // forbids lettering, real people and injury. Putting it last also puts it where
        // a model weights it most, with no teacher text after it.
        $direction = self::direction();
        $body = self::fit(
            implode(' ', array_filter($parts)),
            self::MAX_PROMPT - \core_text::strlen($direction) - 1
        );
        $prompt = trim($body . ' ' . $direction);

        return [
            'prompt'     => $prompt,
            'scenetitle' => \core_text::substr(trim((string)($node['title'] ?? '')), 0, self::MAX_TITLE),
            'style'      => \core_text::substr(self::style_phrase($style), 0, self::MAX_STYLE),
            'alt'        => self::alt_text($node, $situation, $crisis),
        ];
    }

    /**
     * The part of the brief that is identical for every scene in a scenario.
     *
     * @param array $definition The whole scenario.
     * @param string $style Image style key.
     * @return string
     */
    protected static function series_anchor(array $definition, string $style): string {
        $setting = trim((string)($definition['setting'] ?? ''));
        // An anchor that promises "the same lighting" is contradicted a few sentences
        // later by the crisis and outcome moods, which deliberately harden the light,
        // and "the same people" is contradicted by naming only whoever is in this scene.
        // An instruction the rest of the prompt overrides teaches a model that the whole
        // paragraph is soft, so this says exactly what does and does not change.
        $anchor = 'One frame from a single continuous set of images for one workplace training '
            . 'scenario. Every frame is the same place on the same occasion, with the same '
            . 'recurring cast, the same time of day, the same source of light and the same '
            . 'visual treatment, so the set reads as one shoot rather than as unrelated '
            . 'pictures. Framing, who is present, and how hard the light falls change from '
            . 'frame to frame; the location, the palette and where the light comes from do '
            . 'not. Only the people named below appear in this frame.';
        if ($setting !== '') {
            $anchor .= ' Location, unchanged throughout: ' . $setting . '.';
        }
        $anchor .= ' Visual treatment, unchanged throughout: ' . self::style_phrase($style) . '.';
        return $anchor;
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
            if (strpos($haystack, $name) !== false || ($first !== '' && strpos($haystack, $first) !== false)) {
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
            $bits = [$person['name']];
            if (!empty($person['role'])) {
                $bits[] = $person['role'];
            }
            if (!empty($person['appearance'])) {
                $bits[] = $person['appearance'];
            }
            if (!empty($person['gender'])) {
                $bits[] = $person['gender'];
            }
            $described[] = implode(', ', $bits);
            if (count($described) >= 3) {
                break;
            }
        }

        if (!$described) {
            return 'People in frame: one worker, seen from behind or in three-quarter view, '
                . 'face not the subject of the image.';
        }

        return 'People in frame, who must look the same in every image of this set: '
            . implode('; ', $described) . '.';
    }

    /**
     * Describe what is happening and how the frame should feel.
     *
     * @param array $node The node.
     * @param string $situation The prose for this scene.
     * @param bool $crisis Whether this is the crisis variant.
     * @return string
     */
    protected static function moment(array $node, string $situation, bool $crisis): string {
        $action = self::condense($situation);
        $type = (string)($node['type'] ?? 'decision');
        $stage = (int)($node['stage'] ?? 1);

        if ($type === 'outcome') {
            $mood = self::outcome_mood((string)($node['outcome'] ?? 'mixed'));
        } else if ($crisis) {
            $mood = 'The moment has escalated. Tight framing, people close together, urgency in '
                . 'posture and gesture, harder shadows. Tense but not violent, and nobody is hurt.';
        } else if ($stage <= 1) {
            $mood = 'Early in the story and outwardly ordinary. Wider framing, steady light, '
                . 'routine work continuing around the moment.';
        } else {
            $mood = 'The situation has been building. Medium framing, attention narrowing onto '
                . 'the people who have to decide, light a little lower and more directional.';
        }

        // A numeral in an image brief invites a slate, a corner caption or a strip
        // number, which the direction forbids, and the number was not even reliable:
        // branch nodes share a stage, so a set could contain three "Frame 2"s.
        $shot = $type === 'outcome'
            ? 'This is the closing moment of the set.'
            : ($stage <= 1 ? 'This is an early moment in the set.' : 'This is a later moment in the set.');

        // What a character says, and what the learner is being asked, are the two lines
        // that make one scene different from the next. Briefing only the situation prose
        // produced a set of interchangeable meeting-room pictures.
        $spoken = self::condense((string)($node['facilitatorspeech'] ?? ''));
        if ($crisis && !empty($node['crisisvariant']['facilitatorspeech'])) {
            $spoken = self::condense((string)$node['crisisvariant']['facilitatorspeech']);
        }
        $said = $spoken !== ''
            ? 'One person is saying, in substance: ' . $spoken
                . ' Show them mid-sentence and the others reacting to it. '
            : '';

        $moment = trim((string)($node['title'] ?? ''));
        $named = $moment !== '' ? 'This moment is ' . $moment . '. ' : '';

        return $shot . ' ' . $named
            . ($action !== '' ? 'What is happening: ' . $action . ' ' : '') . $said . $mood;
    }

    /**
     * How an ending should look, by outcome band.
     *
     * @param string $band One of strong, mixed, highrisk.
     * @return string
     */
    protected static function outcome_mood(string $band): string {
        $moods = [
            'strong'   => 'The situation is resolved and under control. Open framing, lighter and '
                . 'calmer, people at ease in their posture, work proceeding safely.',
            'mixed'    => 'Resolved, but late and at a cost. Neutral framing, flat even light, '
                . 'the aftermath of something that took longer than it should have.',
            'highrisk' => 'The consequence has landed. Cooler light, harder shadow, the aftermath '
                . 'of something that went wrong. Sober and serious; show no injury and no blood.',
        ];
        return $moods[$band] ?? $moods['mixed'];
    }

    /**
     * Direction the model needs and a learner should never see the result of.
     *
     * @return string
     */
    protected static function direction(): string {
        return 'Do not render any text, lettering, numerals, captions, subtitles, signage, '
            . 'watermarks, logos or brand marks anywhere in the image. Do not depict any real, '
            . 'identifiable or public person, and do not imitate any living person\'s likeness. '
            . 'Everyone shown is an adult in ordinary workplace clothing: no children or young '
            . 'people, no nudity, no weapons. No injury, no blood, no physical harm, and no '
            . 'medical procedure shown in detail. Workplace-appropriate for adult vocational '
            . 'learners.';
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
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $kept = array_slice(array_filter(array_map('trim', $sentences)), 0, 2);
        return \core_text::substr(implode(' ', $kept), 0, 600);
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
            $prompt = trim(\core_text::substr($prompt, 0, $max));
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
        return $count;
    }
}
