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
            // The lens and the light used to be named here as well as in the frame's own
            // composition and lighting, where they are chosen for the scene - so a 40mm
            // anamorphic was asserted over a 28mm corner shot, and a "bright soft key
            // light" over a half-closed blind. A treatment is the medium and the palette;
            // the lens and the light belong to the frame. "Cinematic, shallow depth of
            // field" was also the most diluted phrase available: it sits on millions of
            // captions spanning every look, so it carries almost no direction, and what
            // it does carry is generic prettiness.
            'photorealistic' => 'documentary reportage on colour negative, available light only, '
                . 'restrained true-to-life colour, deep blacks',
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
        $object = self::focal_object(
            (string)($node['situation'] ?? '') . ' ' . (string)($node['challenge'] ?? '') . ' '
            . implode(' ', array_map(static function ($choice) {
                return (string)($choice['text'] ?? '');
            }, (array)($node['choices'] ?? [])))
        );

        // A priority ladder, not a queue.
        //
        // $parts is what may be trimmed: the anchor, who is present, and the narrative
        // prose. $directed is what may not - the object, the shot, the light, the mood and
        // the staging. Those five are how the frame is PHOTOGRAPHED, and they are the
        // difference between a directed picture and a catalogue one; they used to sit at
        // the tail of the trim budget, so on any scene with a few sentences in it they were
        // the first thing cut, and every wordy scene came back as a boardroom.
        //
        // Ordering alone was not enough either: a scenario with a long location, long
        // character records and a long teacher direction could still push them off the end.
        // So each of those three inputs is capped as well, and what the trim actually
        // reaches is the narrative prose - which is the one thing there is always too much
        // of, and the one thing a model cannot photograph anyway.
        $parts = [
            self::series_anchor($definition, $style),
            $people,
            self::moment($node, $situation, $crisis),
        ];
        $directed = $object
            . self::composition($node, $framecount) . ' '
            . self::lighting($definition, $node, $crisis) . ' '
            . self::mood($node, $crisis) . ' '
            . self::staging() . ' ';

        // The safety direction is appended after the rest has been cut to fit. Cutting from
        // the tail would otherwise remove the only text that forbids lettering, real people
        // and injury. Putting it last also puts it where a model weights it most, with no
        // teacher text after it.
        $direction = self::direction();
        // Capped: a teacher may write six hundred characters of direction, and it is the
        // last word on content - but it is not worth the lighting and the staging of the
        // frame it is directing.
        $teacher = trim((string)($node['imageprompt'] ?? ''));
        $teacherline = $teacher !== ''
            ? 'Additional direction: ' . \core_text::substr($teacher, 0, 300) . ' '
            : '';
        $body = self::fit(
            implode(' ', array_filter($parts)),
            self::MAX_PROMPT - \core_text::strlen($direction)
                - \core_text::strlen($teacherline) - \core_text::strlen($directed) - 1
        );
        $prompt = trim($body . ' ' . $directed . $teacherline . $direction);

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
        // Two hundred characters explaining the INTENT of the continuity rules to a thing
        // that has no use for intent - in a budget where every character not spent on what
        // is physically in front of the camera is a character the model fills in from the
        // training mean, and the training mean for "meeting room, professionals" IS the
        // stock photograph. The invariants are stated once, as facts.
        $anchor = 'One frame from a single continuous photographic set: the same place, the '
            . 'same occasion, the same people, the same light and the same treatment in every '
            . 'frame. Only the people named below appear in this frame.';
        if ($setting !== '') {
            // Capped, for the same reason the cast records are: a location description that
            // runs to a paragraph spends the budget that the object, the light and the hands
            // need, and the frame that comes back is an extremely well specified room with
            // nothing happening in it.
            $anchor .= ' Location, unchanged throughout: '
                . \core_text::substr($setting, 0, 220) . '.';
        }
        // It is no use insisting that the time of day and the light are the same in every
        // frame without ever saying what they are. Told only to keep them constant, a model
        // picks afresh each time and the set arrives in six different lightings - which is
        // exactly the fault the anchor exists to prevent. They are named here, and derived
        // from the scenario rather than chosen at random, so the same scenario asks for the
        // same light every time it is generated.
        // The light is chosen from the SETTING and ramped by stage, per frame, so it is
        // built in moment() rather than pinned here. A rig that contradicts its own
        // location - west windows on a night shift - is resolved by the model rendering
        // neither convincingly, and that mush is what reads back as poor lighting.

        // Asking for people "in the middle third" is a written request for the centred,
        // symmetrical
        // stock frame - it forbids the asymmetry, the negative space and the foreground
        // occlusion that are most of what separates a directed picture from a stock one.
        // The shape of the frame is worth fixing; where people stand in it is the
        // composition's business, and the composition is chosen per scene below.
        $anchor .= ' Shot in landscape, wider than it is tall.';
        $anchor .= ' Visual treatment, unchanged throughout: ' . self::style_phrase($style) . '.';
        // The cast sheet goes in EVERY frame's brief, whether or not this particular scene
        // happens to mention a person by name. It did not before: a character was described
        // only when their name appeared in that node's own prose, so a lawyer called Mark -
        // established in scene one, named in scene one - was simply absent from the brief
        // for scene three, and the model, told to draw a lawyer with nothing said about
        // which one, drew a different person. Continuity cannot be asserted by a sentence
        // promising "the same recurring cast" while the description of that cast comes and
        // goes; it has to be the same description every time, present every time.
        $sheet = self::cast_sheet($definition);
        if ($sheet !== '') {
            $anchor .= ' ' . $sheet;
        }
        return $anchor;
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
     * The hour and the light for this scenario's whole set of images.
     *
     * The anchor promises every frame shares a time of day and a source of light, and never
     * said what either was, so each frame was free to invent its own and a set came back in
     * six different lightings. One of five is chosen from the scenario's own title and
     * setting, which means it is stable: regenerate the same scenario and the light does not
     * move. It is a hash rather than a choice because there is nothing in the definition that
     * honestly says what time of day it is, and inventing a field for it would be a bigger
     * promise than the picture needs.
     *
     * @param array $definition The whole scenario.
     * @return string
     */
    protected static function light_for(array $definition): string {
        $lights = [
            'mid-morning, daylight through windows on one side of the room',
            'early afternoon, flat overhead daylight with the blinds half drawn',
            'late afternoon, low warm daylight from one end of the room',
            'early evening, overhead interior lighting with the windows dark',
            'first thing in the morning, thin cool daylight and the lights still on',
        ];
        $seed = (string)($definition['title'] ?? '') . '|' . (string)($definition['setting'] ?? '');
        $index = hexdec(substr(md5($seed === '|' ? 'aibs' : $seed), 0, 4)) % count($lights);
        return $lights[$index];
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
                return 'In frame: ' . implode(' and ', array_slice($known, 0, 2))
                    . ', as the cast sheet describes them.';
            }
            return 'People in frame: one worker, seen from behind or in three-quarter view, '
                . 'face not the subject of the image.';
        }

        // The cast sheet in the anchor has already said, at length, that these people do
        // not change. Saying it again adds nothing per frame and raises the attention the
        // model pays to continuity at the expense of what is happening.
        return 'In frame: ' . implode('; ', $described) . '.';
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

        // The decision itself was never in the brief. A slide says a lawyer is being pushed
        // on price while the client cares about timing, and offers three ways to answer it -
        // and the picture was briefed from the opening two sentences alone, so it showed a
        // meeting room. What the scene is ABOUT is the question and the options; they are
        // the difference between one scene and the next, and they are what a learner is
        // looking at when the picture is beside them.
        $challenge = self::condense($crisis && !empty($node['crisisvariant']['challenge'])
            ? (string)$node['crisisvariant']['challenge']
            : (string)($node['challenge'] ?? ''));
        $asked = $challenge !== ''
            ? 'The question hanging over the room: ' . $challenge . ' ' : '';

        // The options say what is actually at stake without ever being shown as words: they
        // tell the model what the disagreement is over, which is what puts real tension into
        // the posture and the eye-lines instead of a polite generic conference.
        $options = [];
        foreach ((array)($node['choices'] ?? []) as $choice) {
            $line = trim((string)($choice['text'] ?? ''));
            if ($line !== '') {
                $options[] = self::condense($line);
            }
            if (count($options) >= 4) {
                break;
            }
        }
        // The three options were handed over as abstract propositions - "hold the price",
        // "offer a discount" - and a model cannot photograph a proposition. It rendered the
        // only concrete noun within reach, which is why a slide about a fee dispute came
        // back as a meeting room. What the disagreement is OVER becomes an object on the
        // table instead; the propositions themselves are dropped.
        $stake = '';

        return $shot . ' ' . $named
            . ($action !== '' ? 'What is happening: ' . $action . ' ' : '')
            . $said . $asked . $stake;
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
                => 'a laminated shift rota on the table, names rubbed out and rewritten',
            'invoice|fee|price|cost|budget|quote|discount|margin'
                => 'a printed cost breakdown lying between them, one line ringed in biro',
            'deadline|timing|delay|schedule|timeline|overdue|completion|handover'
                => 'a wall planner behind them with one date circled and three crossings-out',
            'contract|clause|draft|term|agreement|signature|sign'
                => 'a thick draft agreement open flat, one clause flagged with a bent sticky note',
            'sample|batch|spec|tolerance|defect|faulty'
                => 'a labelled sample bag set down between them, its label half peeled',
            'complaint|escalat|grievance|incident|report'
                => 'a printed email folded in three and flattened out again on the table',
            'audit|evidence|compliance|record|logbook'
                => 'a ring binder open at a tabbed divider, one page turned back on itself',
            'medication|patient|clinical|dose|chart'
                => 'an observation chart clipped to a board, the top sheet curling',
            'training|competenc|assessment|learner|student'
                => 'a marked-up assessment cover sheet with one box left unticked',
            'safety|hazard|ppe|risk|injury|incident'
                => 'a safety checklist on a clipboard, the last two lines blank',
            'machine|equipment|line|conveyor|plant|breakdown|maintenance'
                => 'a maintenance log open on the bench, the last entry unfinished',
        ];
        foreach ($objects as $pattern => $object) {
            if (preg_match('/\b(' . $pattern . ')/u', $haystack)) {
                return 'On the surface between them: ' . $object
                    . ', pushed halfway across and left there. ';
            }
        }
        // No frame is left without an object. A model needs a noun, and "a meeting" is not
        // one - handed nothing physical it renders the training mean, which is the stock
        // photograph. A plain object beats no object every time, and it still gives the
        // scene a foreground, a place for hands to be, and something for eyes to go to.
        return 'On the surface between them: a clipboard face-down beside a mug gone cold, '
            . 'pushed halfway across and left there. ';
    }

    /**
     * The lighting rig for this scenario, and how hard it falls in this frame.
     *
     * It used to be one of five times of day picked by hashing the scenario, which put
     * "late afternoon, low sun through west windows" on a night shift and flat warm
     * shadows in a clinical room. A rig that contradicts its own location is resolved by
     * the model rendering neither convincingly, and that mush is what reads back as poor
     * lighting. The rig is chosen from the SETTING, so it can always be true of the place.
     *
     * The anchor has always promised that how hard the light falls changes from frame to
     * frame, and nothing ever expressed that change, so a set arrived in ten identically
     * lit frames. The contrast ramp is the dramatic arc - same window, same lamp, same
     * palette, so continuity holds - and it costs about ninety characters.
     *
     * @param array $definition The whole scenario.
     * @param array $node The node being drawn.
     * @param bool $crisis Whether this is the escalated variant.
     * @return string
     */
    protected static function lighting(array $definition, array $node, bool $crisis): string {
        $where = \core_text::strtolower(
            (string)($definition['setting'] ?? '') . ' ' . (string)($definition['title'] ?? '')
        );
        $rigs = [
            'ward|clinic|hospital|theatre|surgery|laborator|pharmac'
                => 'overhead fluorescent only; flat cool-green toplight; no shadows on the '
                    . 'walls; faint reflections in the worktop',
            'night|shift|depot|control room|dispatch'
                => 'the room dark but for two desk lamps and a monitor; warm pools of light '
                    . 'with black falloff between them; faces lit from below by the screen',
            'warehouse|workshop|factory|plant|yard|site|garage'
                => 'a single high skylight; a dusty shaft of light landing on the floor; '
                    . 'everything outside the shaft in warm shade',
            'atrium|lobby|foyer|reception|showroom|glazed'
                => 'flat overcast daylight through a full-height glazed wall camera-right; '
                    . 'soft, even and cool grey; no hard shadow anywhere',
        ];
        $rig = 'one bank of windows camera-left with the blinds half closed; slatted daylight '
            . 'across the back wall; faces lit from one side; the far corner of the room falls '
            . 'to deep shadow';
        foreach ($rigs as $pattern => $candidate) {
            if (preg_match('/(' . $pattern . ')/u', $where)) {
                $rig = $candidate;
                break;
            }
        }

        $type = (string)($node['type'] ?? 'decision');
        $stage = (int)($node['stage'] ?? 1);
        if ($type === 'outcome') {
            $ramp = ((string)($node['outcome'] ?? 'mixed')) === 'strong'
                ? ' The light reaches the faces again and the room opens up behind them.'
                : ' The faces have dropped into the shadow side of the room while the '
                    . 'background behind them stays bright.';
        } else if ($crisis) {
            $ramp = ' Hardest light of the set: the key side of each face is bright, the '
                . 'shadow side falls to near black, the background goes dark behind them.';
        } else if ($stage <= 1) {
            $ramp = ' The light is open and even here; both sides of every face are lit.';
        } else {
            $ramp = ' The light has gone harder; one side of each face is now in shadow.';
        }
        return 'Light, the same source in every frame: ' . $rig . '.' . $ramp;
    }

    /**
     * How this frame is composed, chosen from what is in it rather than at random.
     *
     * The five composition lines were picked by hashing the node id, so the framing had no
     * relationship to the content: an over-the-shoulder two-shot on a scene with one person
     * in it, a wide establishing frame on the emotional turn. Randomness is not direction.
     * Cast size and what kind of moment this is decide the shot; the hash only picks between
     * variants that are all appropriate.
     *
     * Each line carries a focal length, a camera height and something in the foreground,
     * because those three are what make a frame look directed rather than taken.
     *
     * @param array $node The node being drawn.
     * @param int $people How many named people are in this frame.
     * @return string
     */
    protected static function composition(array $node, int $people): string {
        if ($people <= 1) {
            $family = [
                '85mm, camera just below eye height, waist-up, the subject on the left third '
                    . 'looking into open space on the right; both hands in frame.',
                '50mm, camera at seated eye height, the subject small against the window with '
                    . 'the room dark around them; one hand resting on the table edge.',
            ];
        } else if ($people === 2) {
            $family = [
                '35mm, over the near person\'s shoulder; that shoulder is dark and out of '
                    . 'focus and fills the left third; the other face is sharp on the right third.',
                '50mm from one side of the table; both in profile, the empty space between '
                    . 'them carrying the tension.',
            ];
        } else {
            $family = [
                '28mm from the corner of the room at seated eye height; the group forms a '
                    . 'triangle; whoever is standing is cut off at the chin by the top edge.',
                '24mm from the doorway, the dark edge of the doorframe running down the left '
                    . 'of the frame; the group small in the lower half, the room above them.',
            ];
        }
        $pick = $family[abs(crc32((string)($node['id'] ?? ''))) % count($family)];
        if ((string)($node['type'] ?? '') !== 'outcome' && (int)($node['stage'] ?? 1) > 1) {
            $pick .= ' Leave a wide empty space on the side the speaker is facing.';
        }
        return $pick;
    }

    /**
     * The things a directed photograph has and a stock photograph does not.
     *
     * @return string
     */
    protected static function staging(): string {
        return 'Hands are doing something specific rather than resting: one person leaning '
            . 'forward on straight arms, palms flat; another turning a pen without looking at '
            . 'it. Eyelines are deliberate: someone is watching the object on the table rather '
            . 'than the person speaking. One small incongruous thing is in the room - a cycle '
            . 'helmet on a chair, a coffee gone cold and skinned over. Not a stock photograph: '
            . 'nobody looks at the camera, nobody smiles for it, no staged handshake, no folded '
            . 'arms, no spotless glass boardroom.';
    }

    /**
     * How this frame should feel, by where it sits in the story.
     *
     * It used to be the tail of the narrative block, which meant that on a wordy scene -
     * or a scenario whose character records ran long - it was trimmed away along with the
     * prose. That took the crisis direction with it: the one sentence saying the moment has
     * escalated AND that nobody is hurt. Mood is direction about the photograph, not part
     * of the story, so it travels with the light.
     *
     * @param array $node The node being drawn.
     * @param bool $crisis Whether this is the escalated variant.
     * @return string
     */
    protected static function mood(array $node, bool $crisis): string {
        if ((string)($node['type'] ?? '') === 'outcome') {
            return self::outcome_mood((string)($node['outcome'] ?? 'mixed'));
        }
        if ($crisis) {
            return 'The moment has escalated. Tight framing, people close together, urgency in '
                . 'posture and gesture, harder shadows. Tense but not violent, and nobody is hurt.';
        }
        if ((int)($node['stage'] ?? 1) <= 1) {
            // Words like "outwardly ordinary", "routine work continuing" and "steady light"
            // were
            // asking for a boring picture with flat light, and receiving one.
            return 'Nothing has gone wrong yet and everyone is still being reasonable, which '
                . 'is what makes it worth looking at.';
        }
        return 'It has been building for a while. Attention has narrowed onto the people who '
            . 'have to decide and the room has gone quieter around them.';
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
        // A blanket "no text anywhere" fought the props, and the best props are paper: it
        // removed the
        // ringed figure, the marked-up clause and the crossed-out date, which are the most
        // concrete things a scene has. Paperwork is present and simply unreadable, so the
        // object survives and no garbled letterforms appear.
        return 'No legible text anywhere: paperwork, screens and signage are present but turned '
            . 'away, cropped, or thrown far enough out of focus that nothing reads. No '
            . 'captions, subtitles, watermarks, logos or brand marks. Do not depict any real, '
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
            $stop = max(
                (int)strrpos($cut, '. '),
                (int)strrpos($cut, '? '),
                (int)strrpos($cut, '! ')
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
        return $count;
    }
}
