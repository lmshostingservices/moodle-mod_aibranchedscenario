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
 * What good writing looks like in this product, in one place.
 *
 * There are two ways a scenario gets written. A teacher can paste a prompt into ChatGPT and
 * bring the answer back, or they can spend credits and have the service write it. For four
 * releases those two were held to different standards, and the paid one was the lower: the
 * pasted prompt carried about fourteen and a half thousand characters of craft instruction
 * and the generate request carried none of it. Everything below was stated on the free path
 * and left to the service's own prompt on the paid one.
 *
 * That gap was visible in what came back. The best option arrived first at every decision.
 * Examples and pitfalls arrived folded into a summary as prose. Australian spelling did not
 * survive. None of it was mysterious once the two prompts were read side by side: the paid
 * route had never been asked.
 *
 * So the rules live here and both routes read them from here. The wording differs in length
 * because the channels differ - the pasted prompt has room to show a worked example, the
 * generate route has a 2000-character field it shares with the teacher's own brief - but a
 * rule cannot now exist on one route and not the other, because there is one list.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_standard {
    /**
     * @var int Characters of the generate route's instructions field always kept for the
     * teacher's own words.
     *
     * Without a floor the standard would grow until there was no room left to say what the
     * scenario is actually about, and a perfectly written scenario about the wrong subject
     * is worth nothing. The rules are dropped from the bottom of the list instead, which is
     * why the list is in priority order.
     */
    const TEACHER_FLOOR = 500;

    /**
     * The rules, in priority order.
     *
     * Order is what survives a squeeze, so it is not alphabetical and it is not the order
     * they were written in. The ones at the top are the ones whose absence has actually been
     * watched to produce bad scenarios on a live site; the ones at the bottom are craft that
     * a competent writer tends to get right anyway.
     *
     * Each rule has a short form for the generate route and a long form for the pasted
     * prompt. The long form is the text that prompt has always carried, moved here unchanged.
     *
     * @return array id => ['short' => string, 'long' => string]
     */
    public static function rules(): array {
        return [
            // Watched to fail: the service returned the strongest option first every time.
            'position' => [
                'short' => 'No option is obviously correct, and the strongest must not always '
                    . 'be first - vary its position between decisions.',
                'long'  => '- No choice may be obviously correct on its face, and the '
                    . 'strongest must not always be the first one listed. Vary which '
                    . 'position it takes from one decision to the next: a learner who '
                    . 'notices that A is always right stops reading the options, and the '
                    . 'scenario then measures whether they spotted the pattern rather than '
                    . 'whether they know the material.',
            ],
            // Watched to fail: both arrived inside the summary as prose, so the teaching
            // slide showed a rule and nothing a learner could use.
            'principle' => [
                'short' => '"example" and "pitfall" are their own non-empty fields on every '
                    . 'principle, never folded into the summary: the example is words a '
                    . 'learner could say; the pitfall is the plausible version that fails.',
                'long'  => '- Every principle carries an "example" and a "pitfall", each in '
                    . 'its own field and never folded into the summary as prose. The example '
                    . 'is the words a learner could actually say or do, written as a line of '
                    . 'speech or a concrete action - "So what I am hearing is the deadline is '
                    . 'the problem, not the guidelines. Have I got that right?" - never a '
                    . 'restatement of the principle. The pitfall is the plausible-sounding '
                    . 'version that does not work, and why it does not: "Asking \'does that '
                    . 'make sense?\', which invites a yes and tells you nothing." These are '
                    . 'taught before the scenario starts, so they are the difference between '
                    . 'a learner who knows the principle and one who can use it.',
            ],
            // Watched to fail: en-AU was chosen and American spelling came back.
            'variety' => [
                'short' => 'Write every word in the spelling variety of the language field, '
                    . 'whatever the source uses.',
                // The concrete detail used to live only in the paste route's own rule
                // list, stated a second time beside this one - so the prompt printed the
                // same instruction twice, and the PAID route, which is built from this
                // standard alone, got the vaguer half of it. "Use that variety's spelling"
                // is a principle a model can agree with and still write "organize"; the
                // examples are what actually change the output. They belong here, where
                // both routes read them.
                'long'  => '- Write EVERY word of the scenario in the language named in '
                    . '"language", using that variety\'s spelling, punctuation and idiom '
                    . 'throughout - titles, situations, choices, consequences, feedback, '
                    . 'debrief, all of it. The source content\'s own spelling does not decide '
                    . 'this and must not be copied: a teacher who chose en-AU gets Australian '
                    . 'spelling even if the material they pasted was written in the United '
                    . 'States. For en-AU and en-GB that means -ise not -ize (finalise, '
                    . 'organise, recognise), -our not -or (behaviour, favour), -re not -er '
                    . '(centre), and travelled, practise as the verb, programme for a plan. '
                    . 'For en-US it means the opposite. Never mix the two inside one '
                    . 'scenario.',
            ],
            // The single biggest lever on whether a debrief teaches anything.
            'mechanism' => [
                'short' => 'Feedback explains the mechanism and never restates the choice: '
                    . 'what the behaviour did to the other person, and what that changes '
                    . 'next. Name them.',
                'long'  => '- Feedback explains the MECHANISM, never restates the choice. The '
                    . 'learner has just read what they did; telling them again teaches '
                    . 'nothing. Every feedback must answer why it worked or why it did not, '
                    . 'in this shape: what the behaviour did to the other person, and what '
                    . 'that changes next. Name the person. "You addressed Priya\'s confusion '
                    . 'directly" is a restatement and is wrong; "Reading it out with her made '
                    . 'the gap hers to find, so she raised the next one instead of working '
                    . 'around you" is the mechanism and is right.',
            ],
            'inroom' => [
                'short' => 'Consequences happen in the room. Never write a verdict - no '
                    . '"correct", "well done", "unfortunately" - and never mention scores or '
                    . 'skills where the learner reads.',
                'long'  => '- Consequences are shown happening in the room. Never write a '
                    . 'verdict: no "correct", "incorrect", "well done", "unfortunately", "the '
                    . 'best choice would have been", and no mention of scores, skills or what '
                    . 'was being assessed anywhere the learner reads.',
            ],
            'options' => [
                'short' => 'Every option is what a competent, tired person might do. The '
                    . 'weaker ones are reasonable readings, not obvious mistakes; the '
                    . 'strongest is never the longest, the kindest-sounding, or the only one '
                    . 'that asks a question.',
                'long'  => '- Every choice is what a competent, tired person might actually do '
                    . 'on the day. The weaker options are reasonable readings of the '
                    . 'situation rather than obvious mistakes, and the stronger one is never '
                    . 'the longest, the kindest sounding, or the only one that mentions '
                    . 'asking a question.',
            ],
            'moment' => [
                'short' => 'Second person, present tense, in the moment; never open with '
                    . '"Imagine" or "In this scenario".',
                'long'  => '- The learner is in the moment, in second person and present '
                    . 'tense, not being told about a topic. Never open a scene with '
                    . '"Imagine", "In this scenario" or "You will learn".',
            ],
            'question' => [
                'short' => 'Each "challenge" is a question to the learner and ends in a '
                    . 'question mark.',
                'long'  => '- "challenge" must be a question addressed to the learner and must '
                    . 'end in a question mark: "What do you say to Jamie?", "How do you '
                    . 'answer that?". An instruction such as "Ensure Jamie understands the '
                    . 'guidelines." is wrong, because it sits directly above the lettered '
                    . 'options and reads as one of them rather than as the question they '
                    . 'answer.',
            ],
            'unnamed' => [
                'short' => 'Never name the skill being assessed outside the debrief.',
                'long'  => '- Never name the skill being assessed in "situation", "challenge", '
                    . 'a choice\'s "text", "consequence" or "feedback". Name it only in the '
                    . 'debrief.',
            ],
            'cost' => [
                'short' => 'When a choice goes badly, say what it cost the other person, not '
                    . 'that it was a poor choice.',
                'long'  => '- The same applies when a choice goes badly: say what it cost the '
                    . 'other person and what they will do differently because of it, not that '
                    . 'it was a poor choice. One or two sentences, in the same voice as the '
                    . 'story, not praise, not a lecture, and never addressed to the learner '
                    . 'as a student.',
            ],
            'cast' => [
                'short' => 'Name the same cast in every scene; no unnamed stand-ins.',
                'long'  => '- The scenario has a named cast, and every scene names the people '
                    . 'in it. If Mark the lawyer is in scene one, scene three refers to Mark '
                    . 'by name and not to "the lawyer" or "a colleague". Do not introduce a '
                    . 'new unnamed person to do a job one of the named cast would do, and do '
                    . 'not change anyone\'s role, seniority or gender part-way through. The '
                    . 'illustrations are briefed from this text, so a scene that stops naming '
                    . 'someone is a scene that gets a picture of a stranger.',
            ],
            'motives' => [
                'short' => 'Everyone else wants something of their own and has a reason for '
                    . 'being difficult; nobody is a strawman.',
                'long'  => '- The other people want something of their own and have their own '
                    . 'reason for being difficult. Nobody is a strawman and nobody exists '
                    . 'only to be corrected.',
            ],
            'debrief' => [
                'short' => 'The debrief names what mattered rather than repeating the story.',
                'long'  => '- The debrief names what mattered rather than repeating the story.',
            ],
        ];
    }

    /**
     * The standard as the pasted prompt states it: every rule, at length.
     *
     * @return string[] One line per rule, in priority order.
     */
    public static function long_lines(): array {
        $out = [];
        foreach (self::rules() as $rule) {
            $out[] = $rule['long'];
        }
        return $out;
    }

    /**
     * The standard in full, for a route with a field of its own to put it in.
     *
     * The same twelve rules the pasted prompt states, in the same words. Until the service
     * grew a field for them, the paid route got a compressed version that fitted beside the
     * teacher's brief in one small field - every rule present, but each cut to a line. This is
     * the same twelve rules at the length the pasted prompt states them, which is what
     * makes the two routes one product. It is not the whole pasted prompt: the schema
     * skeleton and the worked node are written in the plugin's own import JSON and belong
     * only to the route where a person pastes them into an assistant.
     *
     * @param int $budget Characters available. Zero or less returns nothing.
     * @return string
     */
    public static function full_text(int $budget): string {
        if ($budget <= 0) {
            return '';
        }
        $text = "Write every scenario to this standard.\n\n" . implode("\n\n", self::long_lines());
        return \core_text::strlen($text) <= $budget ? $text : \core_text::substr($text, 0, $budget);
    }

    /**
     * The standard as the generate route states it, cut to the room available.
     *
     * Rules are added in priority order while they fit. What does not fit is not silently
     * lost - it is reported, so the caller can say which rules this request relied on the
     * service's own prompt for rather than stating itself.
     *
     * @param int $budget Characters available for the standard.
     * @param array|null $dropped Receives the ids of the rules that did not fit.
     * @return string
     */
    public static function short_text(int $budget, ?array &$dropped = null): string {
        $dropped = [];
        $intro = 'Write to this standard.';
        $text = $intro;
        foreach (self::rules() as $id => $rule) {
            $next = $text . ' ' . $rule['short'];
            if (\core_text::strlen($next) > $budget) {
                $dropped[] = $id;
                continue;
            }
            $text = $next;
        }
        return $text === $intro ? '' : $text;
    }
}
