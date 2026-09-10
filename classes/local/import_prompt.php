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
 * The prompt a teacher can take to any assistant to produce an importable scenario.
 *
 * Generation through LMS Labs is the fast path, but it is not the only one: a site
 * without credits, or a teacher who would rather argue with a draft somewhere else,
 * still needs a way in. This composes a prompt describing exactly the document the
 * import box accepts, so what comes back pastes straight in.
 *
 * The rules are read from the schema rather than written out by hand, so a change to
 * the allowed values cannot leave the prompt describing a document the validator will
 * reject.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_prompt {
    /**
     * The prompt text.
     *
     * @param int $decisions How many decisions the scenario should contain.
     * @return string
     */
    public static function text(int $decisions = 5): string {
        $decisions = max(3, min(8, $decisions));
        $metrics = implode(', ', schema::metrics());
        $skills = implode(', ', schema::skills());
        $tones = implode(', ', schema::tones());
        $complexities = implode(', ', schema::complexities());

        $lines = [
            'You are writing a branching workplace scenario for a Moodle activity. Read the '
                . 'source content at the end of this message and build the scenario from it.',
            '',
            'Answer with one JSON document and nothing else. No commentary before or after, '
                . 'no code fence, no trailing commas.',
            '',
            'THE SHAPE',
            '',
            self::skeleton(),
            '',
            'ONE DECISION NODE, WRITTEN OUT',
            '',
            'This shows the register and the spread of choices. Do not reuse its people, '
                . 'its place or its wording.',
            '',
            self::worked_node(),
            '',
            'THE RULES',
            '',
            '- "version" is 1. "startnode" must be the id of a decision node.',
            '- There must be exactly ' . $decisions . ' nodes of type "decision", numbered in '
                . '"stage" from 1 upwards, plus between two and four nodes of type "outcome".',
            '- Every decision node needs between two and four choices. Every choice\'s "next" '
                . 'must be the id of another node, or "__auto__" to let the activity pick the '
                . 'next stage.',
            '- Every outcome node needs an "outcome" of one of: '
                . implode(', ', schema::outcomes()) . '. Use "highrisk" for the ending '
                . 'where the consequence actually lands. Any other word is silently '
                . 'turned into "mixed", which mislabels the ending for the learner.',
            '- "language" is one of: ' . implode(', ', schema::languages())
                . '. Choose the one matching the source content.',
            '- "signal" on a choice is one of: positive, neutral, negative.',
            '- "skills" carries all four of: ' . $skills . '. Each is a whole number from -2 to 2. '
                . 'These are the only values that affect the learner\'s grade, so they must reflect '
                . 'how good the choice actually is.',
            '- "effects" carries all three of: ' . $metrics . '. Each is a whole number from -20 '
                . 'to 20. These change the mood of the scenario and do not affect the grade.',
            '- "openingmetrics" carries the same three, each from 0 to 100.',
            '- "principleid" on a choice must match the "id" of one of the principles.',
            '- "tone" is one of: ' . $tones . '. "complexity" is one of: ' . $complexities . '.',
            '- No choice may be obviously correct on its face. Each should be what a reasonable '
                . 'person would do given a different reading of the situation.',
            '- "imageprompt" describes the scene for an illustrator. Never ask for text, '
                . 'lettering, logos, identifiable real people or visible injury.',
            '- Every "id" is lowercase letters, digits, hyphens and underscores only, '
                . 'starts with a letter or digit, and is at most 64 characters. Ids with '
                . 'spaces, dots or capitals are rejected and the node is dropped.',
            '- Links only ever point forward, to a later stage or to an outcome node. '
                . 'The graph must contain no loop back to an earlier node and every node '
                . 'must be reachable from "startnode". A scenario failing either is '
                . 'rejected whole.',
            '- Every node needs a non-empty "situation", outcome nodes included, and the '
                . 'document needs a non-empty "title" and "hook".',
            '- Anything longer than its limit is truncated rather than rejected: '
                . '"situation" 4000 characters, "summary" 2000, "consequence" and '
                . '"feedback" 1800 each, "challenge" 600, "imageprompt" 600 on one line, '
                . '"imagealt" 250, a choice\'s "text" 400, any title or name 255.',
            '- Give one or two mid-story decision nodes a "crisisvariant" carrying its '
                . 'own "situation", "facilitatorspeech" and "challenge": the same moment '
                . 'as it plays out when tension has already run high. The activity shows '
                . 'it instead of the normal scene once tension reaches '
                . schema::CRISIS_TENSION_THRESHOLD . '.',
            '- "tags" on a choice, where used, come only from: '
                . implode(', ', schema::choicetags()) . '.',
            '- "facilitator" is the person the learner deals with most; "characters" are '
                . 'anyone else. Give each a "gender" of male, female or neutral: the '
                . 'illustrator uses it to keep the same person recognisable from one '
                . 'frame to the next. Give no age and no ethnicity.',
            '- Write in the language and spelling of the source content.',
            '',
            'WHAT MAKES IT GOOD',
            '',
            '- The learner is in the moment, in second person and present tense, not '
                . 'being told about a topic. Never open a scene with "Imagine", "In this '
                . 'scenario" or "You will learn".',
            '- The other people want something of their own and have their own reason for '
                . 'being difficult. Nobody is a strawman and nobody exists only to be '
                . 'corrected.',
            '- Every choice is what a competent, tired person might actually do on the '
                . 'day. The weaker options are reasonable readings of the situation '
                . 'rather than obvious mistakes, and the stronger one is never the '
                . 'longest, the kindest sounding, or the only one that mentions asking a '
                . 'question.',
            '- Consequences are shown happening in the room. Never write a verdict: no '
                . '"correct", "incorrect", "well done", "unfortunately", "the best choice '
                . 'would have been", and no mention of scores, skills or what was being '
                . 'assessed anywhere the learner reads.',
            '- Feedback says what the choice cost or bought, in the same voice as the '
                . 'story: one or two sentences of consequence, not praise, not a lecture, '
                . 'and never addressed to the learner as a student.',
            '- Never name the skill being assessed in "situation", "challenge", a '
                . 'choice\'s "text", "consequence" or "feedback". Name it only in the '
                . 'debrief.',
            '- The debrief names what mattered rather than repeating the story.',
        ];

        return implode("\n", $lines);
    }

    /**
     * One decision node written out in full.
     *
     * The rules below the skeleton say what a good choice set is; without a sample of
     * the target, a model resolves "no choice may be obviously correct" against "the
     * skills must reflect how good the choice is" by making the good one visible. This
     * shows three choices a competent, tired person might each defend, and feedback
     * written as consequence rather than as a verdict.
     *
     * @return string
     */
    protected static function worked_node(): string {
        return implode("\n", [
            '{',
            '  "id": "n2", "type": "decision", "stage": 2,',
            '  "title": "The second time she asks",',
            '  "situation": "Priya stops writing. The bed four fluid chart, she says '
                . 'again, quieter this time. Behind you the day staff are already pulling '
                . 'out chairs. You are eleven minutes past the end of a shift you were '
                . 'promised would end on time.",',
            '  "challenge": "What do you do?",',
            '  "choices": [',
            '    {"id": "n2_a", "signal": "negative",',
            '     "text": "Tell her what you remember and say you will check the chart '
                . 'from home if anything is off.",',
            '     "consequence": "She writes it down. She does not ask you the third '
                . 'question she had.",',
            '     "feedback": "The handover ended on your certainty rather than on '
                . 'hers."},',
            '    {"id": "n2_b", "signal": "positive",',
            '     "text": "Sit down, open the chart, and read the last two entries out '
                . 'with her.",',
            '     "consequence": "It takes four minutes. She finds the gap you had not '
                . 'noticed and marks it.",',
            '     "feedback": "Checking together cost the four minutes and closed the '
                . 'gap."},',
            '    {"id": "n2_c", "signal": "neutral",',
            '     "text": "Ask her to put her questions in the ward group chat so you '
                . 'can answer them properly later.",',
            '     "consequence": "She agrees. By the time you reply she has already '
                . 'made her own call on it.",',
            '     "feedback": "The question moved off the ward and the decision was '
                . 'made without you."}',
            '  ]',
            '}',
        ]);
    }

    /**
     * The document skeleton, abbreviated to one example of each repeated part.
     *
     * @return string
     */
    protected static function skeleton(): string {
        $document = [
            'version'   => 1,
            'title'     => 'Short title naming the moment, not the topic',
            'subtitle'  => 'One line setting the scene',
            'role'      => 'You are the ... (who the learner plays)',
            'setting'   => 'Where this happens',
            'language'  => 'en-AU',
            'tone'      => 'neutral',
            'complexity' => 'intermediate',
            'facilitator' => [
                'name' => 'Given name', 'role' => 'Their job',
                'trait' => 'Behaviour under pressure', 'gender' => 'female',
                'appearance' => 'Build, hair and clothing, for illustration only',
            ],
            'characters' => [[
                'name' => 'Given name', 'role' => 'Their job', 'trait' => 'Behaviour under pressure',
                'gender' => 'male', 'appearance' => 'For illustration only',
            ]],
            'principles' => [[
                'id' => 'p1', 'title' => 'Something a person does', 'summary' => 'Why it matters here',
            ]],
            'openingmetrics' => ['engagement' => 55, 'trust' => 50, 'tension' => 30],
            'hook'      => 'The first two or three sentences the learner reads',
            'startnode' => 'n1',
            'nodes'     => [
                [
                    'id' => 'n1', 'type' => 'decision', 'stage' => 1, 'title' => 'Scene title',
                    'situation' => 'What is happening, in second person',
                    'challenge' => 'The question put to the learner',
                    'imageprompt' => 'The scene for an illustrator',
                    'imagealt' => 'The same scene described for a screen reader',
                    'choices' => [[
                        'id' => 'n1_a', 'text' => 'What the learner does',
                        'signal' => 'positive', 'consequence' => 'What happens next',
                        'feedback' => 'What this told us', 'principleid' => 'p1',
                        'effects' => ['engagement' => 5, 'trust' => 10, 'tension' => -10],
                        'skills' => ['presence' => 2, 'adaptability' => 0, 'empathy' => 1, 'clarity' => 2],
                        'next' => '__auto__',
                    ]],
                ],
                [
                    'id' => 'end_strong', 'type' => 'outcome', 'outcome' => 'strong',
                    'title' => 'How it ends', 'situation' => 'The closing scene',
                    'summary' => 'What their decisions came to',
                ],
            ],
            'debrief' => [
                'whatmattered' => ['...'], 'criticaldecisions' => ['...'],
                'practice' => ['...'], 'sourceconnection' => 'How this ties back to the source',
            ],
            'takeaways' => [['heading' => 'Short heading', 'body' => 'One or two sentences']],
        ];

        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
