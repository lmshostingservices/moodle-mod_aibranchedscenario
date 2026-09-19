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

namespace mod_aibranchedscenario\external;

use completion_info;
use context_module;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use html_writer;
use mod_aibranchedscenario\local\attempt_manager;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\schema;
use stdClass;

/**
 * Shared resolution, permission checks and payload builders for the external API.
 *
 * Every entry point resolves the course module from the supplied id, validates the
 * context and then checks a capability. Nothing about ownership or permission is
 * ever taken from the request body.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Resolve and authorise a request against a course module.
     *
     * @param int $cmid Course module id.
     * @param string $capability Capability the caller must hold.
     * @return array Keys: cm, course, scenario, context.
     */
    public static function resolve(int $cmid, string $capability): array {
        global $DB;

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'aibranchedscenario');
        $context = context_module::instance($cm->id);

        // The login check belongs inside validate_context, not beside it. That method
        // resets the page theme before calling require_login itself, which is what makes
        // it safe from a web service. Calling require_login first sets the page course
        // against a theme that may already be initialised, and Moodle refuses that with a
        // coding exception — thrown before the capability check below was ever reached,
        // so every permission test in this plugin was passing without running.
        \core_external\external_api::validate_context($context);
        require_capability($capability, $context);

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);

        return [
            'cm'       => $cm,
            'course'   => $course,
            'scenario' => $scenario,
            'context'  => $context,
        ];
    }

    /**
     * Split a plain-text narrative string into its paragraphs.
     *
     * The stored value is plain text and is returned as plain text: the caller
     * renders each paragraph through Mustache, which escapes it. No markup from
     * the AI service or from a teacher is ever rendered as HTML by this plugin.
     * Single line breaks inside a paragraph are preserved by CSS rather than by
     * generated tags.
     *
     * @param string $text Plain text value.
     * @return string[] One entry per paragraph, in order.
     */
    public static function paragraph_list(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/\n{2,}/u', $text) as $block) {
            $block = trim($block);
            if ($block !== '') {
                $out[] = $block;
            }
        }
        return $out;
    }

    /**
     * Fields the wizard may ask the service to suggest.
     *
     * @return string[]
     */
    public static function suggestable_fields(): array {
        return [
            'title', 'audience', 'industry', 'setting', 'atmosphere', 'openingsituation',
            'centralproblem', 'whyhard', 'stakes', 'participantrole', 'characterfull',
            'charactertrait', 'characterappearance', 'principles', 'imageprompt',
        ];
    }

    /**
     * Turn a stored job error code into a message that is safe to show a teacher.
     *
     * Provider responses are never echoed back; only the plugin's own error identifier
     * is looked up in the language pack.
     *
     * @param string $stored Stored error identifier.
     * @return string
     */
    public static function safe_error_message(string $stored): string {
        $code = trim(explode(' ', trim($stored))[0] ?? '');
        if ($code === '' || !preg_match('/^[a-z:]+$/', $code)) {
            return get_string('error:generationfailed', 'mod_aibranchedscenario');
        }
        $manager = get_string_manager();
        if (!$manager->string_exists($code, 'mod_aibranchedscenario')) {
            return get_string('error:generationfailed', 'mod_aibranchedscenario');
        }

        $detail = trim(substr(trim($stored), strlen($code)));

        // A rejected scenario is the one failure where the reason is the plugin's own
        // words: the validator's list of what was wrong with the document. Telling a
        // teacher only to "try generating again" sends them to spend the credits a
        // second time on a scenario that will fail in exactly the same way. This is the
        // single exception to never showing a stored detail, and it is safe precisely
        // because the text is ours, built from field names and rule names, and never
        // carries provider output or learner content.
        if ($code === 'error:invalidgeneratedscenario' && $detail !== '') {
            return get_string($code, 'mod_aibranchedscenario') . ' '
                . get_string('error:validationdetail', 'mod_aibranchedscenario', $detail);
        }

        // The shape accepted here is the service's own failure code, optionally followed
        // by its own one-line explanation, which the provider has already reduced to
        // plain text and capped. Requiring a bare identifier threw that explanation
        // away and left every failure reading "no reason given" - the opposite of what
        // the detail was added for. A provider stack trace or a multi-line message
        // still does not match and is still discarded.
        if ($detail === '' || !preg_match('/^[A-Z][A-Z0-9_]{1,40}(: [^\r\n]{1,200})?$/u', $detail)) {
            $detail = get_string('error:nodetail', 'mod_aibranchedscenario');
        }

        return get_string($code, 'mod_aibranchedscenario', $detail);
    }

    /**
     * A tier number that is certainly one of the rungs.
     *
     * The tier arrives from a browser on every authoring call, so it is normalised in one
     * place rather than trusted in eight. Anything unrecognised is the foundation rung,
     * which is the activity as it was before the ladder existed.
     *
     * @param mixed $tier Whatever the caller sent.
     * @return int
     */
    public static function tier($tier): int {
        $tier = (int)$tier;
        return isset(\mod_aibranchedscenario\local\schema::tiers()[$tier]) ? $tier : 1;
    }

    /**
     * Media URL maps for a published revision.
     *
     * @param context_module $context Module context.
     * @param stdClass $revision Revision record.
     * @return array Keys: scene, narration.
     */
    public static function media_urls(context_module $context, stdClass $revision): array {
        // The revision knows which rung it belongs to, and the rung is what separates one
        // scenario's pictures from another's in the file areas.
        $media = new media_manager($context, self::tier($revision->tier ?? 1));
        return [
            'scene'     => $media->urls_for_revision(media_manager::AREA_REVISION_SCENE, (int)$revision->revision),
            'narration' => $media->urls_for_revision(media_manager::AREA_REVISION_NARRATION, (int)$revision->revision),
        ];
    }

    /**
     * Build the payload the player needs for one node.
     *
     * Nothing that would reveal an unchosen branch, a consequence or teacher feedback
     * is included; those are only returned once a decision has actually been recorded.
     *
     * @param array $node Normalised node.
     * @param stdClass $attempt Attempt record, used to select the crisis variant.
     * @param array $mediaurls Node id to media URL maps, keyed 'scene' and 'narration'.
     * @return array
     */
    public static function node_payload(
        array $node,
        stdClass $attempt,
        array $mediaurls = [],
        array $state = []
    ): array {
        $situation = $node['situation'];
        $speech = $node['facilitatorspeech'];
        $challenge = $node['challenge'];

        // THE WORLD REACTING TO WHAT THIS LEARNER DID.
        //
        // This used to read one field, crisisvariant, gated on one metric passing one
        // threshold. A node now carries a list of variants with conditions - on a flag the
        // learner set, on a reading, or on how many poor calls they have made - and the
        // first that holds is what they are shown. The crisis case is still in that list;
        // the validator appends it, so a scenario written before any of this behaves
        // exactly as it did.
        //
        // The state is passed in rather than decoded here because the caller already has
        // it, and because a payload that went and read the database would be a second
        // place the answer is worked out.
        $variant = null;
        foreach ((array)($node['variants'] ?? []) as $candidate) {
            if (attempt_manager::condition_holds((array)($candidate['when'] ?? []), $attempt, $state)) {
                $variant = $candidate;
                break;
            }
        }
        $crisis = false;
        if ($variant !== null) {
            // Still reported as "crisis" to the screen when it IS the crisis case, because
            // that is what draws the escalated styling. A variant that fires on a flag is
            // the world remembering, not the room boiling over, and should not look alarmed.
            $crisis = isset($variant['when']['metric'])
                && $variant['when']['metric'] === 'tension';
            $situation = $variant['situation'];
            if (($variant['facilitatorspeech'] ?? '') !== '') {
                $speech = $variant['facilitatorspeech'];
            }
            if (($variant['challenge'] ?? '') !== '') {
                $challenge = $variant['challenge'];
            }
        }

        $choices = [];
        foreach ($node['choices'] as $choice) {
            $choices[] = [
                'id'     => $choice['id'],
                'letter' => $choice['letter'],
                'text'   => $choice['text'],
            ];
        }

        return [
            'id'            => $node['id'],
            'type'          => $node['type'],
            'title'         => $node['title'],
            'stage'         => (int)$node['stage'],
            'situation'     => $situation,
            'situationparas' => self::paragraph_list($situation),
            'speech'        => $speech,
            'challenge'     => $challenge,
            // Authors write the challenge line as an instruction about as often as they
            // write it as a question ("Ensure Jamie understands the guidelines."), and sat
            // directly above the lettered options that reads as a statement with answers
            // under it. When it is not already a question the player adds the question
            // itself rather than leaving the learner to infer one.
            'challengeisquestion' => substr(rtrim($challenge), -1) === '?',
            'askwhatyoudo'  => substr(rtrim($challenge), -1) !== '?'
                && !($node['type'] === 'beat' && count($choices) === 1),
            'crisis'        => $crisis,
            'outcome'       => $node['outcome'],
            'summary'       => $node['summary'],
            'summaryparas'   => self::paragraph_list($node['summary']),
            // A crisis frame is stored beside the calm one under the same node id with a
            // suffix, so a learner who has driven the tension up sees the escalated moment.
            //
            // NO FALLBACK to the calm frame. It used to fall back, and that is the same
            // fault as everywhere else in this release: a crisis screen showing the picture
            // of the room BEFORE it went wrong is not a cheaper version of the right
            // picture, it is the wrong picture, and it is invisible to every check because
            // it still resolves to a picture. It survived the sweep of v1.82.0 because it is
            // written as a `?:` rather than as a named fallback.
            'imageurl'      => $crisis
                ? (string)($mediaurls['scene'][$node['id'] . '_crisis'] ?? '')
                : (string)($mediaurls['scene'][$node['id']] ?? ''),
            'imagealt'      => $node['imagealt'] !== ''
                ? $node['imagealt']
                : \mod_aibranchedscenario\local\ai\image_prompt::alt_text($node, $situation, $crisis),
            'audiourl'      => $mediaurls['narration'][$node['id']] ?? '',
            // The character's own line, played when the learner clicks the avatar.
            'speechurl'     => $mediaurls['narration'][$node['id'] . '_said'] ?? '',
            'speaker'       => (string)($node['speaker'] ?? ''),
            'choices'       => $choices,
            // A beat carries the story forward and has one synthesised way on. Rendered
            // in the lettered choice list it read as a multiple-choice question with a
            // single answer, which is not a decision and should not look like one.
            'iscontinue'    => $node['type'] === 'beat' && count($choices) === 1,
        ];
    }

    /**
     * Dress one decision's slide for the debrief: the picture, the paragraphs, the clips.
     *
     * The picture is the REACTION frame for the option the learner took - the face that
     * has just received the consequence - not the decision's own establishing shot. That
     * is the most relevant image the scenario owns for this slide, and one it has already
     * paid for, which is why the debrief commissions nothing of its own any more. Where a
     * revision predates the reaction frames, the node's own scene stands in; where there
     * is neither, the slide renders without a picture rather than with a borrowed one.
     *
     * @param array $slide One entry from attempt_manager::build_decision_slides().
     * @param array $narration Narration urls by key.
     * @param array $scene Scene image urls by key.
     * @return array
     */
    public static function decision_slide(array $slide, array $narration, array $scene): array {
        $reaction = media_manager::reaction_key((string)$slide['nodeid'], (string)$slide['signal']);
        $options = [];
        foreach ($slide['options'] as $option) {
            $options[] = [
                'letter'     => (string)$option['letter'],
                'text'       => (string)$option['text'],
                'signal'     => (string)$option['signal'],
                'signalclass' => 'aibs-signal-' . (string)$option['signal'],
                'chosen'     => (bool)$option['chosen'],
                'principle'  => (string)$option['principle'],
                'noteparas'  => self::paragraph_list((string)$option['outcomenote']),
                'audiourl'   => (string)($narration[media_manager::outcome_key((string)$option['choiceid'])] ?? ''),
            ];
        }
        // The principle this decision tested, taken from the option the learner actually
        // took. Every option at a decision normally carries the same one - it is the idea
        // the decision exists to test - so reading it off the chosen option gives the
        // slide its heading without needing a field of its own on the node.
        $principle = '';
        foreach ($slide['options'] as $option) {
            if (!empty($option['chosen'])) {
                $principle = (string)$option['principle'];
            }
        }
        return [
            'seq'         => (int)$slide['seq'],
            'nodetitle'   => (string)$slide['nodetitle'],
            'principle'   => $principle,
            'challenge'   => (string)$slide['challenge'],
            'choicetext'  => (string)$slide['choicetext'],
            'signal'      => (string)$slide['signal'],
            'signalclass' => 'aibs-signal-' . (string)$slide['signal'],
            'imageurl'    => (string)($scene[$reaction] ?? $scene[(string)$slide['nodeid']] ?? ''),
            'options'     => $options,
        ];
    }

    /**
     * The shape one decision slide returns.
     *
     * @return external_multiple_structure
     */
    public static function decision_slide_shape(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'seq'         => new external_value(PARAM_INT, 'Its position, one based'),
                'nodetitle'   => new external_value(PARAM_TEXT, 'Title of the decision'),
                'principle'   => new external_value(PARAM_TEXT, 'The principle it tested, or empty'),
                'challenge'   => new external_value(PARAM_TEXT, 'The question that was put'),
                'choicetext'  => new external_value(PARAM_TEXT, 'The option the learner took'),
                'signal'      => new external_value(PARAM_ALPHA, 'Signal of the option taken'),
                'signalclass' => new external_value(PARAM_NOTAGS, 'CSS modifier for that signal'),
                'imageurl'    => new external_value(PARAM_URL, 'Reaction frame for what they chose, or empty'),
                'options'     => new external_multiple_structure(
                    new external_single_structure([
                        'letter'     => new external_value(PARAM_TEXT, 'A, B or C'),
                        'text'       => new external_value(PARAM_TEXT, 'The option'),
                        'signal'     => new external_value(PARAM_ALPHA, 'positive, neutral or negative'),
                        'signalclass' => new external_value(PARAM_NOTAGS, 'CSS modifier for that signal'),
                        'chosen'     => new external_value(PARAM_BOOL, 'Whether this is the one taken'),
                        'principle'  => new external_value(PARAM_TEXT, 'The principle it tests, or empty'),
                        'noteparas'  => self::paragraphs_structure(
                            'Where this option leads and why, as plain paragraphs'
                        ),
                        'audiourl'   => new external_value(PARAM_URL, 'Clip reading it, or empty'),
                    ]),
                    'Every option that was open at this decision'
                ),
            ]),
            'One slide per decision: what was chosen, and where every option led',
            VALUE_DEFAULT,
            []
        );
    }

    /**
     * The external description of a narrative field returned as plain paragraphs.
     *
     * @param string $description Human readable description of the field.
     * @return external_multiple_structure
     */
    public static function paragraphs_structure(string $description): external_multiple_structure {
        return new external_multiple_structure(new external_value(PARAM_TEXT, 'A paragraph'), $description);
    }

    /**
     * The external structure describing a node payload.
     *
     * @return external_single_structure
     */
    public static function node_structure(): external_single_structure {
        return new external_single_structure([
            'id'            => new external_value(PARAM_ALPHANUMEXT, 'Node identifier'),
            'type'          => new external_value(PARAM_ALPHA, 'Node type'),
            'title'         => new external_value(PARAM_TEXT, 'Short node title'),
            'stage'         => new external_value(PARAM_INT, 'Narrative stage number'),
            'situation'     => new external_value(PARAM_TEXT, 'Situation text'),
            'situationparas' => self::paragraphs_structure('Situation rendered as plain text; escape before use as HTML'),
            'speech'        => new external_value(PARAM_TEXT, 'What a character says'),
            'challenge'     => new external_value(PARAM_TEXT, 'The direct question put to the learner'),
            'challengeisquestion' => new external_value(
                PARAM_BOOL,
                'Whether the challenge line is already phrased as a question'
            ),
            'askwhatyoudo'  => new external_value(
                PARAM_BOOL,
                'Whether the player should add the question above the options itself'
            ),
            'crisis'        => new external_value(PARAM_BOOL, 'Whether the crisis variant is showing'),
            'outcome'       => new external_value(PARAM_ALPHA, 'Outcome band for terminal nodes'),
            'summary'       => new external_value(PARAM_TEXT, 'Outcome summary text'),
            'summaryparas'   => self::paragraphs_structure('Outcome summary as plain text; escape before use as HTML'),
            'imageurl'      => new external_value(PARAM_URL, 'Scene image URL, or empty'),
            'imagealt'      => new external_value(PARAM_TEXT, 'Scene image alternative text'),
            'audiourl'      => new external_value(PARAM_URL, 'Narration audio URL, or empty'),
            'speechurl'     => new external_value(PARAM_URL, 'The speaker\'s own line, or empty'),
            'speaker'       => new external_value(PARAM_TEXT, 'Who says the spoken line, or empty'),
            'iscontinue'    => new external_value(
                PARAM_BOOL,
                'Whether this node offers one way on rather than a decision'
            ),
            'choices'       => new external_multiple_structure(
                new external_single_structure([
                    'id'     => new external_value(PARAM_ALPHANUMEXT, 'Choice identifier'),
                    'letter' => new external_value(PARAM_ALPHA, 'Display letter'),
                    'text'   => new external_value(PARAM_TEXT, 'Choice text'),
                ])
            ),
        ]);
    }

    /**
     * The external structure describing the live metrics of an attempt.
     *
     * @return external_single_structure
     */
    public static function metrics_structure(): external_single_structure {
        return new external_single_structure([
            'engagement' => new external_value(PARAM_INT, 'Engagement, 0 to 100'),
            'trust'      => new external_value(PARAM_INT, 'Trust, 0 to 100'),
            'tension'    => new external_value(PARAM_INT, 'Tension, 0 to 100'),
        ]);
    }

    /**
     * Extract the live metrics from an attempt.
     *
     * @param stdClass $attempt Attempt record.
     * @return array
     */
    public static function metrics(stdClass $attempt): array {
        return [
            'engagement' => (int)$attempt->engagement,
            'trust'      => (int)$attempt->trust,
            'tension'    => (int)$attempt->tension,
        ];
    }

    /**
     * The external structure describing the authoring wizard inputs.
     *
     * @return external_single_structure
     */
    public static function source_structure(): external_single_structure {
        return new external_single_structure([
            'brief'            => new external_value(PARAM_TEXT, 'Quick start brief', VALUE_DEFAULT, ''),
            'sourcecontent'    => new external_value(PARAM_TEXT, 'Pasted source content', VALUE_DEFAULT, ''),
            'title'            => new external_value(PARAM_TEXT, 'Scenario title', VALUE_DEFAULT, ''),
            'industry'         => new external_value(PARAM_ALPHANUMEXT, 'Subject domain', VALUE_DEFAULT, 'training'),
            'industryother'    => new external_value(PARAM_TEXT, 'Industry when Other is chosen', VALUE_DEFAULT, ''),
            'audience'         => new external_value(PARAM_TEXT, 'Intended audience', VALUE_DEFAULT, ''),
            'setting'          => new external_value(
                PARAM_ALPHANUMEXT,
                'Physical or virtual setting',
                VALUE_DEFAULT,
                'trainingroom'
            ),
            'settingother'     => new external_value(PARAM_TEXT, 'Setting when Other is chosen', VALUE_DEFAULT, ''),
            'atmosphere'       => new external_value(
                PARAM_ALPHANUMEXT,
                'Emotional register',
                VALUE_DEFAULT,
                'tension'
            ),
            'openingsituation' => new external_value(PARAM_TEXT, 'Opening situation', VALUE_DEFAULT, ''),
            'centralproblem'   => new external_value(PARAM_TEXT, 'Central problem', VALUE_DEFAULT, ''),
            'whyhard'          => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Complication key'),
                'Why the situation is hard',
                VALUE_DEFAULT,
                []
            ),
            'stakes'           => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Stake key'),
                'What is at stake',
                VALUE_DEFAULT,
                []
            ),
            'participantrole'  => new external_value(PARAM_TEXT, 'The role the learner plays', VALUE_DEFAULT, ''),
            'characters'       => new external_multiple_structure(
                new external_single_structure([
                    'name'       => new external_value(PARAM_TEXT, 'Character name'),
                    'role'       => new external_value(PARAM_TEXT, 'Character role', VALUE_DEFAULT, ''),
                    'trait'      => new external_value(PARAM_TEXT, 'Defining trait', VALUE_DEFAULT, ''),
                    'appearance' => new external_value(PARAM_TEXT, 'Appearance notes', VALUE_DEFAULT, ''),
                    'gender' => new external_value(PARAM_ALPHA, 'male or female', VALUE_DEFAULT, ''),
                ]),
                'Characters in the scenario',
                VALUE_DEFAULT,
                []
            ),
            'principles'       => new external_multiple_structure(
                new external_single_structure([
                    'title'   => new external_value(PARAM_TEXT, 'Principle title'),
                    'summary' => new external_value(PARAM_TEXT, 'Principle summary', VALUE_DEFAULT, ''),
                ]),
                'Decision principles drawn from the source content',
                VALUE_DEFAULT,
                []
            ),
            // Still declared, and no longer the teacher's to set: source_normaliser
            // overwrites it with schema::DECISIONS. Removing it from the structure while
            // source_payload() still emitted it broke every call that carries a source -
            // an external structure and the payload that fills it are one description in
            // two places, and taking a field out of one of them is a breaking change of
            // exactly the kind this release was already caught by.
            //
            // AND IT MUST NOT BE REQUIRED, which is what it was.
            //
            // The teacher's decision-count control was removed when the length was fixed at
            // five, so the wizard stopped sending this key - and a required key that the
            // form cannot supply fails the whole call. Every service that carries a source
            // was refused: Save, "fill this in for me", and every suggestion button, all
            // reporting "Invalid parameter value detected" with the reason buried in
            // debuginfo where a production site never shows it.
            //
            // Declared with the fixed length as its default, so a caller that does not send
            // it is not making a mistake - it is agreeing with the plugin. The value is
            // overwritten by source_normaliser either way.
            'decisions'        => new external_value(
                PARAM_INT,
                'Decisions, fixed by the plugin',
                VALUE_DEFAULT,
                schema::DECISIONS
            ),
            'tone'             => new external_value(PARAM_ALPHANUMEXT, 'Writing tone', VALUE_DEFAULT, 'neutral'),
            'complexity'       => new external_value(
                PARAM_ALPHANUMEXT,
                'Narrative complexity',
                VALUE_DEFAULT,
                'intermediate'
            ),
            'imagestyle'       => new external_value(
                PARAM_ALPHANUMEXT,
                'Scene image style',
                VALUE_DEFAULT,
                'cinematic'
            ),
            'imageprompt'      => new external_value(PARAM_TEXT, 'Extra image direction', VALUE_DEFAULT, ''),
            'openingmetrics'   => new external_single_structure([
                'engagement' => new external_value(PARAM_INT, 'Opening engagement', VALUE_DEFAULT, 50),
                'trust'      => new external_value(PARAM_INT, 'Opening trust', VALUE_DEFAULT, 50),
                'tension'    => new external_value(PARAM_INT, 'Opening tension', VALUE_DEFAULT, 30),
            ], 'Opening room dynamics', VALUE_DEFAULT, ['engagement' => 50, 'trust' => 50, 'tension' => 30]),
        ]);
    }

    /**
     * Shape stored wizard inputs for the external return value.
     *
     * @param array $source Normalised wizard inputs.
     * @return array
     */
    public static function source_payload(array $source): array {
        return [
            'brief'            => (string)($source['brief'] ?? ''),
            'sourcecontent'    => (string)($source['sourcecontent'] ?? ''),
            'title'            => (string)($source['title'] ?? ''),
            'industry'         => (string)($source['industry'] ?? 'training'),
            'industryother'    => (string)($source['industryother'] ?? ''),
            'audience'         => (string)($source['audience'] ?? ''),
            'setting'          => (string)($source['setting'] ?? 'trainingroom'),
            'settingother'     => (string)($source['settingother'] ?? ''),
            'atmosphere'       => (string)($source['atmosphere'] ?? 'tension'),
            'openingsituation' => (string)($source['openingsituation'] ?? ''),
            'centralproblem'   => (string)($source['centralproblem'] ?? ''),
            'whyhard'          => array_values((array)($source['whyhard'] ?? [])),
            'stakes'           => array_values((array)($source['stakes'] ?? [])),
            'participantrole'  => (string)($source['participantrole'] ?? ''),
            'characters'       => array_values((array)($source['characters'] ?? [])),
            'principles'       => array_values((array)($source['principles'] ?? [])),
            'decisions'        => schema::DECISIONS,
            'tone'             => (string)($source['tone'] ?? 'neutral'),
            'complexity'       => (string)($source['complexity'] ?? 'intermediate'),
            'imagestyle'       => (string)($source['imagestyle'] ?? 'cinematic'),
            'imageprompt'      => (string)($source['imageprompt'] ?? ''),
            'openingmetrics'   => [
                'engagement' => (int)($source['openingmetrics']['engagement'] ?? 50),
                'trust'      => (int)($source['openingmetrics']['trust'] ?? 50),
                'tension'    => (int)($source['openingmetrics']['tension'] ?? 30),
            ],
        ];
    }

    /**
     * Update completion and grades once an attempt has finished.
     *
     * @param stdClass $cm Course module record.
     * @param stdClass $scenario Activity instance.
     * @param stdClass $attempt Finished attempt.
     * @return void
     */
    public static function on_attempt_finished($cm, stdClass $scenario, stdClass $attempt): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/aibranchedscenario/lib.php');

        aibranchedscenario_update_grades($scenario, (int)$attempt->userid);

        $course = get_course($scenario->course);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && !empty($scenario->completionfinish)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$attempt->userid);
        }
    }

    /**
     * Recalculate the grade AND the completion state for one learner.
     *
     * Everything that removes an attempt needs this and nothing had it. Three delete paths,
     * the module delete, the course reset and the privacy provider all pushed grades and
     * left completion exactly where it was - so a learner whose attempt was deleted, or who
     * exercised their right to erasure, kept the tick that says they finished a scenario
     * they now have no attempts at. On a privacy request that is a record of the learner
     * surviving the request in a table the provider reports as cleared.
     *
     * Safe to call when there is no course module - during a course delete, for instance -
     * and when completion is off.
     *
     * @param stdClass $scenario Activity instance.
     * @param int $userid The learner, or 0 for everyone in the activity.
     * @return void
     */
    public static function recalculate_for_user(stdClass $scenario, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/aibranchedscenario/lib.php');

        aibranchedscenario_update_grades($scenario, $userid);

        $cm = get_coursemodule_from_instance(
            'aibranchedscenario',
            (int)$scenario->id,
            (int)$scenario->course,
            false,
            IGNORE_MISSING
        );
        if (!$cm) {
            return;
        }
        $course = $DB->get_record('course', ['id' => (int)$scenario->course], '*', IGNORE_MISSING);
        if (!$course) {
            return;
        }
        $completion = new completion_info($course);
        if (!$completion->is_enabled($cm)) {
            return;
        }
        // COMPLETION_UNKNOWN makes Moodle ask the module again rather than trusting the
        // stored state, which is the point: the stored state is what is wrong.
        $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
    }

    /**
     * Build the debrief payload for a finished attempt.
     *
     * @param stdClass $scenario Activity instance.
     * @param stdClass $attempt Finished attempt.
     * @return array
     */
    public static function debrief_payload(stdClass $scenario, stdClass $attempt): array {
        $manager = attempt_manager::for_attempt($scenario, $attempt);
        $definition = $manager->get_definition();
        $state = $manager->decode_state($attempt);

        $radar = [];
        foreach ($manager->radar_values($attempt) as $skill => $value) {
            $radar[] = [
                'skill' => $skill,
                'label' => get_string('skill:' . $skill, 'mod_aibranchedscenario'),
                // A bar with a number on it and no explanation is the whole grade arriving
                // unexplained. These four are what the learner is actually marked on, and
                // they were the only numbers in the product with nothing saying what they
                // meant - while the three that are explicitly not the grade had a legend.
                'description' => get_string('skill:' . $skill . 'desc', 'mod_aibranchedscenario'),
                'value' => $value,
                'raw'   => (int)$attempt->{$skill},
            ];
        }

        // The clip for each decision already exists: it is the one played on the
        // consequence screen when the learner made that choice, stored under the choice's
        // own id. The debrief was not asking for it, so the record of a decision was silent
        // while the moment it describes had a voice.
        $cm = get_coursemodule_from_instance('aibranchedscenario', (int)$scenario->id, 0, false, IGNORE_MISSING);
        $narration = [];
        $scene = [];
        if ($cm) {
            $revision = $manager->get_revision();
            $media = self::media_urls(context_module::instance($cm->id), $revision);
            $narration = $media['narration'];
            $scene = $media['scene'];
        }

        $outcomenode = $manager->get_node($attempt->currentnode);

        // ONE SLIDE PER DECISION, AND NOTHING ELSE.
        //
        // What used to follow here were four lists of closing advice and a set of takeaway
        // cards, each with pictures and clips of its own. They are gone. The debrief is the
        // decisions now: what was chosen, and what every other option would have cost.
        $slides = [];
        foreach ($manager->build_decision_slides($attempt) as $slide) {
            $slides[] = self::decision_slide($slide, $narration, $scene);
        }

        return [
            'attemptid'    => (int)$attempt->id,
            'attemptno'    => (int)$attempt->attemptno,
            'outcome'      => (string)$attempt->outcome,
            'outcomelabel' => $attempt->outcome !== ''
                ? get_string('outcome:' . $attempt->outcome, 'mod_aibranchedscenario') : '',
            'outcometitle' => $outcomenode['title'] ?? '',
            'outcomeaudiourl'  => (string)($narration[$outcomenode['id'] ?? ''] ?? ''),
            // The ending's own frame. The four page-level ones that used to sit beside it -
            // whatmatteredimageurl and its siblings - went in v2.0.0, and the pages they
            // belonged to have now gone with them.
            'outcomeimageurl'  => (string)($scene[$outcomenode['id'] ?? ''] ?? ''),
            'outcomeparas'  => self::paragraph_list($outcomenode['summary'] ?? ''),
            'score'        => round((float)$attempt->score, 1),
            'decisions'    => (int)($state['decisions'] ?? 0),
            'metrics'      => self::metrics($attempt),
            'radar'        => $radar,
            'slides'       => $slides,
        ];
    }

    /**
     * The external structure describing a debrief payload.
     *
     * @return external_single_structure
     */
    public static function debrief_structure(): external_single_structure {
        return new external_single_structure([
            'attemptid'    => new external_value(PARAM_INT, 'Attempt identifier'),
            'attemptno'    => new external_value(PARAM_INT, 'Attempt number'),
            'outcome'      => new external_value(PARAM_ALPHA, 'Outcome band reached'),
            'outcomelabel' => new external_value(PARAM_TEXT, 'Translated outcome band label'),
            'outcometitle' => new external_value(PARAM_TEXT, 'Title of the outcome node'),
            'outcomeaudiourl' => new external_value(PARAM_URL, 'Narration for the ending, or empty'),
            'outcomeimageurl' => new external_value(PARAM_URL, 'Picture for the ending, or empty'),
            'outcomeparas'  => self::paragraphs_structure('Outcome summary as plain text; escape before use as HTML'),
            'score'        => new external_value(PARAM_FLOAT, 'Decision quality as a percentage'),
            'decisions'    => new external_value(PARAM_INT, 'Number of decisions taken'),
            'metrics'      => self::metrics_structure(),
            'radar'        => new external_multiple_structure(
                new external_single_structure([
                    'skill' => new external_value(PARAM_ALPHA, 'Skill key'),
                    'label' => new external_value(PARAM_TEXT, 'Translated skill label'),
                    'description' => new external_value(PARAM_TEXT, 'What the skill measures'),
                    'value' => new external_value(PARAM_FLOAT, 'Normalised value between 0 and 1'),
                    'raw'   => new external_value(PARAM_INT, 'Raw accumulated value'),
                ])
            ),
            // The journey list is gone from here too. It said what was chosen and what
            // followed; the slides say that and what every other option would have cost,
            // on the same screen. Two lists of the same decisions on one debrief is the
            // repetition this release exists to remove.
            'slides'       => self::decision_slide_shape(),
        ]);
    }
}
