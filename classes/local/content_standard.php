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
            // An invented regulation is the one fault here that can cost a customer their
            // registration rather than just reading badly, so it sits with the rules that
            // were watched to fail rather than with the craft ones.
            'law' => [
                'short' => 'Never invent legislation, regulation numbers, clause numbers, '
                    . 'standards or legal duties. Where a legal duty is not in the supplied '
                    . 'material, write what a worker must do in practice and name no law.',
                'long'  => '- Never invent legislation, regulation numbers, clause numbers, '
                    . 'code or standard references, penalties or legal duties. This is a '
                    . 'training product used by registered training organisations, and a '
                    . 'scenario that cites a section number which does not say what the '
                    . 'scenario claims is a finding against the provider, not a writing '
                    . 'fault. Where the supplied material states a requirement, put it in '
                    . 'plain English and separate what the rule says from what it means on '
                    . 'the day: "Workers must take reasonable care for their own safety and '
                    . 'the safety of others. In practice, that means not carrying on with a '
                    . 'task you can see is putting someone else at risk." Where the material '
                    . 'does NOT state it, write what a worker should do and name no law, no '
                    . 'act and no clause.',
            ],
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
            // The debrief used to be four closing lists, and this rule counted them against
            // the principles taught. The lists are gone: the debrief is now one slide per
            // decision showing every option with the paragraph saying where it leads, so
            // what has to be true is that those three paragraphs are three DIFFERENT
            // outcomes. Three that say the same thing produce a slide that looks complete
            // and teaches nothing, which is the same failure in its new shape.
            'outcomespread' => [
                'short' => 'The three outcome notes on a decision describe three different '
                    . 'outcomes, one clearly the best available and one clearly the worst.',
                'long'  => '- Every option carries an "outcomenote", and the learner is shown '
                    . 'all three side by side after they finish. They must read as three '
                    . 'different futures: one option is the best available and the note says '
                    . 'why, one costs the most and the note says what it costs, and the '
                    . 'middle one is the plausible compromise with the part it leaves undone '
                    . 'named. Write the cost in terms of the people in the room - what they '
                    . 'stop bringing you, what they decide alone next time - not as a verdict '
                    . 'on the learner. Do not write the same sentiment three times and do not '
                    . 'restate the option itself: the paragraph exists to say what taking it '
                    . 'leads to, which the option does not.',
            ],
            // Watched to fail twice, once for each cause. A total failure has to LOOK like
            // one: the score already says nought, and the readings beside it have to agree.
            // When a teacher could pick the length, three decisions of effects written for
            // five left engagement at 14. The length is fixed now, and the same symptom
            // arrives from effects written too small - five decisions at minus five ends a
            // total failure at 25.
            'effectrange' => [
                'short' => 'Effects are large enough that five consistently poor choices '
                    . 'take a reading all the way to the end of its scale.',
                'long'  => '- Size the "effects" so the readings can actually reach their '
                    . 'ends. They start near the middle and there are exactly five '
                    . 'decisions, so a learner taking the worst option every time must '
                    . 'finish with engagement and trust at nought and tension at a hundred. '
                    . 'That means the poor options carry effects around 10 to 20, not 3 to '
                    . '5. A run in which the learner got nothing right scores 0% - if the '
                    . 'readings beside that score still sit near the middle, the screen is '
                    . 'telling them two different things about the same run.',
            ],
            // THE PRODUCT IS CALLED AI BRANCHED SCENARIO.
            //
            // Nothing asked for branching until v2.4.0. The route contract stated the rule
            // on day one and no prompt repeated it, so the scenario shipped inside the
            // plugin as its worked example broke it on three of its five decisions - every
            // option leading to the same screen, which is a linear lesson with meters.
            'branching' => [
                'short' => 'Different choices lead to genuinely different screens, and the '
                    . 'paths reconverge later.',
                'long'  => '- The learner\'s choices must change WHERE THEY GO, not only '
                    . 'what the readings say. At least two of the five decisions send a '
                    . 'poor choice somewhere a good choice does not - a different scene, a '
                    . 'different conversation, a consequence that has to be dealt with. '
                    . 'Paths reconverge at a later decision so the scenario stays one '
                    . 'story, but a learner who chooses badly must SEE something a learner '
                    . 'who chooses well never sees. A decision whose options all point at '
                    . 'the same next screen is not a decision; it is a question with a '
                    . 'score attached.',
            ],
            // What turns a branching quiz into a simulation: the world remembering.
            'state' => [
                'short' => 'Choices leave facts behind, and later screens are written '
                    . 'against them.',
                'long'  => '- Use "setflags" on a choice to record what it left behind - '
                    . '{"hazard_reported": true}, {"lead_still_in_use": true} - and use '
                    . '"variants" on a later node to write the version of that moment that '
                    . 'belongs to a learner carrying that fact. Two or three across the '
                    . 'scenario is enough. This is what makes the difference between a '
                    . 'story that scores you and one that remembers you: the supervisor who '
                    . 'says "thanks for flagging that earlier" to one learner and "why was '
                    . 'this not reported?" to another, on the same screen. Every flag a '
                    . 'variant waits on must be set by some choice, or that screen can '
                    . 'never appear.',
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
            // WITHOUT PRESSURE, A SAFETY OR CONDUCT DECISION ANSWERS ITSELF.
            //
            // The strongest single lever on whether a scenario teaches anything. A learner
            // who is not under pressure picks the careful option every time, which measures
            // whether they can read rather than whether they can act.
            'pressure' => [
                'short' => 'The learner is under named pressure - time, authority, peer, '
                    . 'customer, cost, convenience or embarrassment - and at least two '
                    . 'decisions are made under it.',
                'long'  => '- The learner must be under PRESSURE, named in the scene rather '
                    . 'than described in the abstract, and at least two of the five decisions '
                    . 'are made while it is on them. Use the pressures that actually operate '
                    . 'on people at work: time ("we need this before lunch"), authority ("I '
                    . 'have been doing this twenty years, it is fine"), peer ("everyone does '
                    . 'it this way"), customer ("they are waiting"), cost ("there is not '
                    . 'another one"), convenience ("it will take a minute") and embarrassment '
                    . '("you are the new one and everybody else has started"). Put it in '
                    . 'somebody\'s mouth or in the state of the room, never as narration '
                    . 'saying the learner feels pressured. Without it the careful option is '
                    . 'obvious and the scenario measures reading, not judgement.',
            ],
            // Experience first, rule second. A learner who has just watched it happen has
            // somewhere to put the rule; one who is told the rule first has nowhere.
            'noticing' => [
                'short' => 'The opening scene contains something to notice and does not point '
                    . 'at it; the learner looks before they are told.',
                'long'  => '- The scenario opens on a scene that CONTAINS the problem rather '
                    . 'than announcing it. Something is wrong, or about to be, and the writing '
                    . 'does not point at it: describe the room as a person standing in it '
                    . 'would see it, and let the learner find what matters. The imageprompt '
                    . 'for that scene must put the same thing in the picture - a lead across a '
                    . 'walkway, glasses pushed up on a helmet, a pallet half across an exit - '
                    . 'so the illustration is part of the problem and not decoration. Never '
                    . 'write "you notice the damaged lead" on the screen where noticing is the '
                    . 'point; write what is there, and let the decision reveal whether they '
                    . 'saw it.',
            ],
            // People do not talk like policies, and a learner who reads dialogue that nobody
            // would say stops believing the situation.
            'spoken' => [
                'short' => 'Dialogue is what a person would actually say out loud, never '
                    . 'procedure language in quotation marks.',
                'long'  => '- Everything anybody says must be what a person would actually say '
                    . 'out loud, in their own register. "It will be right, we only need it for '
                    . 'another ten minutes" is dialogue. "You must immediately cease operation '
                    . 'of this equipment in accordance with organisational procedures" is a '
                    . 'procedure with quotation marks around it, and it tells the learner they '
                    . 'are reading a compliance module. The options a learner chooses between '
                    . 'are also things they would say - short, spoken, sometimes awkward.',
            ],
            // The near miss is the cheapest memory the product can make: the rule attaches
            // to the moment it was nearly broken, which is where it will be recalled.
            'closecall' => [
                'short' => 'Where the story allows it, a plausible poor choice produces a near '
                    . 'miss - something almost happens, nobody is hurt, and the scenario '
                    . 'carries on.',
                'long'  => '- Where the subject allows it, one plausible poor choice should '
                    . 'produce a CLOSE CALL rather than a disaster: something almost happens, '
                    . 'nobody is hurt, and the story carries on with everyone slightly quieter. '
                    . 'Write it as the moment it was, not as a warning about it - the sound, '
                    . 'the pause, what somebody said afterwards. A near miss attaches the rule '
                    . 'to the moment it was nearly broken, which is where a learner will '
                    . 'actually recall it. Do not follow it with a lecture; the next decision '
                    . 'is the lesson.',
            ],
            // Remembering the answer is not learning the principle. The last decision is
            // where the difference shows.
            'transfer' => [
                'short' => 'The final decision applies the same principle in a DIFFERENT '
                    . 'situation, so the learner demonstrates the rule rather than recalling '
                    . 'the earlier answer.',
                'long'  => '- The last decision must apply the same principle to a DIFFERENT '
                    . 'situation from the one the scenario has been working in. If the '
                    . 'scenario taught "unsafe equipment is stopped, isolated and reported" '
                    . 'through a damaged lead, the final decision is about a guard, a ladder '
                    . 'or a vehicle - near enough that the principle carries, far enough that '
                    . 'the remembered answer does not. This is the difference between a '
                    . 'learner who knows the rule and one who memorised "the damaged lead was '
                    . 'option B", and it is the only decision in the scenario that can tell '
                    . 'them apart.',
            ],
            // Closure, and a second test: can they do for somebody else what was done for
            // them?
            'callback' => [
                'short' => 'Something set up in the opening returns near the end and the '
                    . 'learner has to act on it.',
                'long'  => '- Something established in the first scene must RETURN near the '
                    . 'end in a way that asks the learner to act. The new starter who said '
                    . '"tell me if I am doing anything wrong" is about to do the thing the '
                    . 'learner has just been taught not to do; the supervisor who dismissed a '
                    . 'concern in scene one asks the learner\'s opinion in scene seven. It '
                    . 'closes the story and it tests the principle a second way - whether they '
                    . 'can do for somebody else what the scenario did for them.',
            ],
            // The cheapest way to teach noticing, and the only one a list of sentences
            // cannot do.
            'spotit' => [
                'short' => 'Where a decision is about noticing something in the scene, give '
                    . 'each option a "hotspot" - x, y, w, h as percentages of the frame - so '
                    . 'the learner can answer by pointing at it.',
                'long'  => '- Where a decision is really about NOTICING something, let the '
                    . 'learner answer by pointing at it. Give each option a "hotspot" of '
                    . '{"x": 0-100, "y": 0-100, "w": 0-100, "h": 0-100} - percentages of the '
                    . 'frame, measured from the top left - locating that option in the '
                    . 'scene\'s own illustration, and write the imageprompt so the thing is '
                    . 'actually there to be found. "Take a look around before you start" with '
                    . 'a damaged lead across a walkway, a pallet half over an exit and a '
                    . 'worker with their glasses pushed up teaches noticing; "which of these '
                    . 'three is the hazard?" teaches reading. Use it on one or two decisions '
                    . 'where looking is the skill, not on all five. Options without a hotspot '
                    . 'still work normally, and the lettered list is always there as well, so '
                    . 'nothing is lost for a learner who cannot use a pointer.',
            ],
            // The paper the judgement is actually made over. Free to produce, unlike the
            // picture of it, and unlike the picture of it, readable.
            'artefact' => [
                'short' => 'Where the judgement turns on a document, give the node an '
                    . '"artefact" - kind, title, subtitle, fields of {label, value, flagged} '
                    . 'and lines - instead of summarising the document in the situation text.',
                'long'  => '- Where the judgement turns on a DOCUMENT, put the document in '
                    . 'the learner\'s hands rather than summarising it. Give the node an '
                    . '"artefact": {"kind": one of permit, sds, checklist, email, sign, '
                    . 'record, document; "title"; "subtitle" for a reference or a location; '
                    . '"fields": [{"label", "value", "flagged": true on the line that is '
                    . 'wrong}]; "lines": [plain lines of body text]; "footer"}. Eight fields '
                    . 'at most - a permit with twenty lines is a form, and nobody reads past '
                    . 'the fourth. "The permit expired at two o\'clock and it is half past" '
                    . 'is a comprehension question with the answer already in it; a permit '
                    . 'whose "Valid until" line says 14:00 while the scene says the shift is '
                    . 'running late is the job. Do not write the artefact as an image prompt: '
                    . 'a generated photograph of a permit is unreadable on a phone, invisible '
                    . 'to a screen reader, and comes back with invented words on it. Mark the '
                    . 'wrong line with "flagged" - nothing is drawn on it during the decision, '
                    . 'it is what lets the debrief name what they should have caught - and '
                    . 'make sure at least one option actually acts on it.',
            ],
            // The world remembering in the picture as well as in the words.
            'remembers' => [
                'short' => 'Where an earlier choice would leave a visible trace, give the '
                    . 'later node "marks": [{flag, label, tone, x, y}] so the picture shows it.',
                'long'  => '- Where a choice leaves something you would SEE later - a lockout '
                    . 'tag, a barrier, a sign on a door, a machine still running - give the '
                    . 'later node "marks": [{"flag": the flag that choice sets, "label": up to '
                    . 'about four words, "tone": neutral, caution, danger or resolved, "x" and '
                    . '"y": percentages of the frame}]. The tag appears only for a learner who '
                    . 'set that flag, so two learners see different pictures of the same room. '
                    . 'Four marks at most on one picture. This is the visual half of the same '
                    . 'memory the variants give you in words; use both on the decision that '
                    . 'matters most, because a learner who sees the tag they hung two stages '
                    . 'ago understands that the world kept what they did in a way no sentence '
                    . 'achieves.',
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
            'motives' => [
                'short' => 'Everyone else wants something of their own and has a reason for '
                    . 'being difficult; nobody is a strawman.',
                'long'  => '- The other people want something of their own and have their own '
                    . 'reason for being difficult. Nobody is a strawman and nobody exists '
                    . 'only to be corrected.',
            ],
            'debrief' => [
                'short' => 'An outcome note names what mattered rather than repeating the story.',
                'long'  => '- An outcome note names what mattered rather than repeating the '
                    . 'story. The learner has just watched what happened; the paragraph is '
                    . 'for what it cost and why.',
            ],
            'concrete' => [
                'short' => 'Every principle, consequence and outcome note names a behaviour, a '
                    . 'decision or a consequence a person could watch happen. No concept is '
                    . 'left as an abstract explanation.',
                'long'  => '- Every principle, every consequence and every outcome note lands on '
                    . 'something someone could watch happen: a behaviour, a sentence spoken, '
                    . 'a decision taken, a result that followed. No concept is left as an '
                    . 'abstract explanation. "Use active listening" is abstract and is wrong. '
                    . '"Let her finish, then say back the part you are not sure about - I '
                    . 'understand the roster change is the problem, not the hours. Have I got '
                    . 'that right?" is a behaviour and is right. If a paragraph could be '
                    . 'moved into a scenario about a different job without changing a word, '
                    . 'it is too abstract to teach anything.',
            ],
            'reading' => [
                'short' => 'Write for an adult at work, not an academic: short sentences, '
                    . 'everyday words, one idea per sentence. Explain any technical term in '
                    . 'plain English the first time it appears.',
                'long'  => '- Write for an adult reading at work, on a phone, possibly in '
                    . 'their second language. Short sentences. Everyday words. One idea per '
                    . 'sentence. Explain any technical or industry term in plain English the '
                    . 'first time it appears. "Employees should utilise appropriate '
                    . 'communication methodologies when managing interpersonal disputes" is '
                    . 'wrong; "Workers should stay calm and be respectful when they disagree '
                    . 'with someone" says the same thing and can be read. Simple is not the '
                    . 'same as childish: the situations stay adult and the decisions stay '
                    . 'hard.',
            ],
            'notai' => [
                'short' => 'No AI register: never "In today\'s fast-paced workplace", "It is '
                    . 'important to note", "plays a crucial role", "In conclusion", "By '
                    . 'fostering a culture of", "navigating the complexities of".',
                'long'  => '- Do not write in the register a model defaults to. Never use "In '
                    . 'today\'s fast-paced workplace", "It is important to note", "plays a '
                    . 'crucial role", "is essential for success", "In conclusion", "This '
                    . 'comprehensive approach", "By fostering a culture of", "navigating the '
                    . 'complexities of", or a sentence that opens by announcing what it is '
                    . 'about to do. A learner recognises this register instantly and stops '
                    . 'believing the scenario, and once they stop believing it the decisions '
                    . 'stop mattering to them.',
            ],
            'cast-variety' => [
                'short' => 'Vary the names. Not John, Jane, Sarah, Alex or Sam every time; '
                    . 'use names that suit the workplace being described.',
                'long'  => '- Vary the names, and pick them to suit the workplace being '
                    . 'described rather than reaching for the same handful every time. John, '
                    . 'Jane, Sarah, Alex and Sam in every scenario a teacher generates is the '
                    . 'clearest tell that nothing was written for them in particular.',
            ],
            'industry' => [
                'short' => 'Stage every scene in the industry named in "setting" - its work, '
                    . 'its equipment, its pressures. Never the default meeting room.',
                'long'  => '- Stage every scene in the industry named in "setting", using the '
                    . 'work that is actually done there: a warehouse has deliveries, pallets, '
                    . 'forklifts and a shift running late; a ward has handovers, charts, '
                    . 'families and a bed that is needed; a kitchen has a service on, a docket '
                    . 'rail and a delivery that is wrong. The default meeting room with '
                    . 'people around a table is what comes back when the industry has not '
                    . 'been thought about, and it is the single clearest sign the scenario '
                    . 'could have been written for anyone.',
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
