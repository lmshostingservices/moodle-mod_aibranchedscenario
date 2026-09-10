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

        require_login($course, false, $cm);
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
        return get_string($code, 'mod_aibranchedscenario');
    }

    /**
     * Media URL maps for a published revision.
     *
     * @param context_module $context Module context.
     * @param stdClass $revision Revision record.
     * @return array Keys: scene, narration.
     */
    public static function media_urls(context_module $context, stdClass $revision): array {
        $media = new media_manager($context);
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
    public static function node_payload(array $node, stdClass $attempt, array $mediaurls = []): array {
        $situation = $node['situation'];
        $speech = $node['facilitatorspeech'];
        $challenge = $node['challenge'];

        $crisis = false;
        if (
            !empty($node['crisisvariant'])
                && (int)$attempt->tension >= schema::CRISIS_TENSION_THRESHOLD
        ) {
            $crisis = true;
            $situation = $node['crisisvariant']['situation'];
            if ($node['crisisvariant']['facilitatorspeech'] !== '') {
                $speech = $node['crisisvariant']['facilitatorspeech'];
            }
            if ($node['crisisvariant']['challenge'] !== '') {
                $challenge = $node['crisisvariant']['challenge'];
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
            'crisis'        => $crisis,
            'outcome'       => $node['outcome'],
            'summary'       => $node['summary'],
            'summaryparas'   => self::paragraph_list($node['summary']),
            'imageurl'      => $mediaurls['scene'][$node['id']] ?? '',
            'imagealt'      => $node['imagealt'],
            'audiourl'      => $mediaurls['narration'][$node['id']] ?? '',
            'choices'       => $choices,
        ];
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
            'situationparas' => self::paragraphs_structure('Situation rendered as escaped paragraphs'),
            'speech'        => new external_value(PARAM_TEXT, 'What a character says'),
            'challenge'     => new external_value(PARAM_TEXT, 'The direct question put to the learner'),
            'crisis'        => new external_value(PARAM_BOOL, 'Whether the crisis variant is showing'),
            'outcome'       => new external_value(PARAM_ALPHA, 'Outcome band for terminal nodes'),
            'summary'       => new external_value(PARAM_TEXT, 'Outcome summary text'),
            'summaryparas'   => self::paragraphs_structure('Outcome summary as escaped paragraphs'),
            'imageurl'      => new external_value(PARAM_URL, 'Scene image URL, or empty'),
            'imagealt'      => new external_value(PARAM_TEXT, 'Scene image alternative text'),
            'audiourl'      => new external_value(PARAM_URL, 'Narration audio URL, or empty'),
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
            'audience'         => new external_value(PARAM_TEXT, 'Intended audience', VALUE_DEFAULT, ''),
            'setting'          => new external_value(
                PARAM_ALPHANUMEXT,
                'Physical or virtual setting',
                VALUE_DEFAULT,
                'trainingroom'
            ),
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
            'decisions'        => new external_value(PARAM_INT, 'Target number of decision points', VALUE_DEFAULT, 5),
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
            'audience'         => (string)($source['audience'] ?? ''),
            'setting'          => (string)($source['setting'] ?? 'trainingroom'),
            'atmosphere'       => (string)($source['atmosphere'] ?? 'tension'),
            'openingsituation' => (string)($source['openingsituation'] ?? ''),
            'centralproblem'   => (string)($source['centralproblem'] ?? ''),
            'whyhard'          => array_values((array)($source['whyhard'] ?? [])),
            'stakes'           => array_values((array)($source['stakes'] ?? [])),
            'participantrole'  => (string)($source['participantrole'] ?? ''),
            'characters'       => array_values((array)($source['characters'] ?? [])),
            'principles'       => array_values((array)($source['principles'] ?? [])),
            'decisions'        => (int)($source['decisions'] ?? 5),
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
                'value' => $value,
                'raw'   => (int)$attempt->{$skill},
            ];
        }

        $journey = [];
        foreach ($manager->build_journey($attempt) as $step) {
            $step['consequenceparas'] = self::paragraph_list($step['consequence']);
            $step['feedbackparas'] = self::paragraph_list($step['feedback']);
            $journey[] = $step;
        }

        $outcomenode = $manager->get_node($attempt->currentnode);
        $debrief = $definition['debrief'];

        return [
            'attemptid'    => (int)$attempt->id,
            'attemptno'    => (int)$attempt->attemptno,
            'outcome'      => (string)$attempt->outcome,
            'outcomelabel' => $attempt->outcome !== ''
                ? get_string('outcome:' . $attempt->outcome, 'mod_aibranchedscenario') : '',
            'outcometitle' => $outcomenode['title'] ?? '',
            'outcomeparas'  => self::paragraph_list($outcomenode['summary'] ?? ''),
            'score'        => round((float)$attempt->score, 1),
            'decisions'    => (int)($state['decisions'] ?? 0),
            'metrics'      => self::metrics($attempt),
            'radar'        => $radar,
            'journey'      => $journey,
            'whatmattered' => array_values($debrief['whatmattered']),
            'criticaldecisions' => array_values($debrief['criticaldecisions']),
            'practice'     => array_values($debrief['practice']),
            'sourceconnection' => $debrief['sourceconnection'],
            'sourceconnectionparas' => self::paragraph_list($debrief['sourceconnection']),
            'takeaways'    => array_values(array_map(function ($takeaway) {
                return [
                    'heading'  => $takeaway['heading'],
                    'body'     => $takeaway['body'],
                    'bodyparas' => self::paragraph_list($takeaway['body']),
                ];
            }, $definition['takeaways'])),
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
            'outcomeparas'  => self::paragraphs_structure('Outcome summary as escaped paragraphs'),
            'score'        => new external_value(PARAM_FLOAT, 'Decision quality as a percentage'),
            'decisions'    => new external_value(PARAM_INT, 'Number of decisions taken'),
            'metrics'      => self::metrics_structure(),
            'radar'        => new external_multiple_structure(
                new external_single_structure([
                    'skill' => new external_value(PARAM_ALPHA, 'Skill key'),
                    'label' => new external_value(PARAM_TEXT, 'Translated skill label'),
                    'value' => new external_value(PARAM_FLOAT, 'Normalised value between 0 and 1'),
                    'raw'   => new external_value(PARAM_INT, 'Raw accumulated value'),
                ])
            ),
            'journey'      => new external_multiple_structure(
                new external_single_structure([
                    'seq'             => new external_value(PARAM_INT, 'Decision sequence number'),
                    'nodetitle'       => new external_value(PARAM_TEXT, 'Title of the decision point'),
                    'choicetext'      => new external_value(PARAM_TEXT, 'What the learner chose'),
                    'signal'          => new external_value(PARAM_ALPHA, 'Signal type of the choice'),
                    'consequence'     => new external_value(PARAM_TEXT, 'What happened next'),
                    'consequenceparas' => self::paragraphs_structure('Consequence as escaped paragraphs'),
                    'feedback'        => new external_value(PARAM_TEXT, 'Instructional feedback'),
                    'feedbackparas'    => self::paragraphs_structure('Feedback as escaped paragraphs'),
                    'principle'       => new external_value(PARAM_TEXT, 'Decision principle tested'),
                ])
            ),
            'whatmattered' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A lesson the scenario demonstrated')
            ),
            'criticaldecisions' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A decision that changed the outcome')
            ),
            'practice'     => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A behaviour to use in practice')
            ),
            'sourceconnection' => new external_value(PARAM_TEXT, 'How the lessons connect to the source content'),
            'sourceconnectionparas' => self::paragraphs_structure('Source connection as escaped paragraphs'),
            'takeaways'    => new external_multiple_structure(
                new external_single_structure([
                    'heading'  => new external_value(PARAM_TEXT, 'Takeaway heading'),
                    'body'     => new external_value(PARAM_TEXT, 'Takeaway body'),
                    'bodyparas' => self::paragraphs_structure('Takeaway body as escaped paragraphs'),
                ])
            ),
        ]);
    }
}
