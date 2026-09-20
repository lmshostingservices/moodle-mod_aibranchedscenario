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

/**
 * English language strings.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Branched Scenario';
$string['modulename'] = 'AI Branched Scenario';
$string['modulenameplural'] = 'AI Branched Scenarios';
$string['modulename_help'] = 'AI Branched Scenario turns pasted source content into a realistic branching scenario. Learners take the role of someone inside a situation, make decisions that change what happens next, see the consequences, and finish with a debrief that connects the experience back to the source material. Scenario generation is performed by the LMS Labs service and consumes LMS Labs credits.';
$string['pluginadministration'] = 'AI Branched Scenario administration';
$string['aibranchedscenario:addinstance'] = 'Add a new AI Branched Scenario';
$string['aibranchedscenario:view'] = 'View an AI Branched Scenario';
$string['aibranchedscenario:attempt'] = 'Attempt an AI Branched Scenario';
$string['aibranchedscenario:manage'] = 'Author and edit scenarios';
$string['aibranchedscenario:publish'] = 'Publish a scenario revision';
$string['aibranchedscenario:generate'] = 'Generate scenario content with AI';
$string['aibranchedscenario:viewreports'] = 'View learner attempts and reports';
$string['aibranchedscenario:deleteattempts'] = 'Delete learner attempts';
$string['activityname'] = 'Activity name';
$string['activityname_help'] = 'The name learners see in the course. It is separate from the scenario title, which comes from the authoring wizard.';
$string['presentation'] = 'Presentation';
$string['theme'] = 'Accent theme';
$string['theme_help'] = 'Each theme keeps the same light, uncluttered surface and changes only the accent colour used for progress, choices and highlights.';
$string['scenariolang'] = 'Scenario language';
$string['scenariolang_help'] = 'The language the scenario is written and narrated in. It is independent of the site language.';
$string['showtimeline'] = 'Show the progress rail';
$string['showmetrics'] = 'Show the room dynamics';
$string['showmetrics_help'] = 'Displays engagement, trust and tension as the learner works through the scenario. Turn this off if you would rather learners judged the situation without a running score.';
$string['showdebrief'] = 'Show the debrief';
$string['enableimages'] = 'Generate scene images';
$string['enableimages_help'] = 'Generates one illustration per decision point. This consumes additional LMS Labs credits and makes generation slower.';
$string['enableaudio'] = 'Generate narration';
$string['enableaudio_help'] = 'Generates spoken narration for each scene. This consumes additional LMS Labs credits and makes generation slower.';
$string['attemptsettings'] = 'Attempts and grading';
$string['maxattempts'] = 'Attempts allowed';
$string['maxattempts_help'] = 'How many times a learner may work through the scenario. Choose Unlimited to place no ceiling on attempts.';
$string['unlimited'] = 'Unlimited';
$string['allowreplay'] = 'Allow replay after finishing';
$string['allowreplay_help'] = 'Lets a learner start the scenario again from the debrief. Finished attempts are kept, so replaying never erases what happened the first time.';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'Which attempt is sent to the gradebook when a learner has more than one.';
$string['completionfinish'] = 'Learner must finish the scenario';
$string['completionminscore'] = 'Minimum decision quality';
$string['completionminscore_help'] = 'A percentage between 0 and 100 that a learner must reach on their decisions for the activity to count as complete. Leave it at 0 to accept any finished attempt.';
$string['completiondetail:finish'] = 'Finish the scenario';
$string['completiondetail:finishwithscore'] = 'Finish the scenario with a decision quality of at least {$a}%';
$string['resetattempts'] = 'Delete all learner attempts';
$string['report:view'] = 'View';
$string['report:backtolist'] = 'Back to all attempts';
$string['report:attemptheading'] = '{$a->user}, attempt {$a->attempt}';
$string['report:nodecisions'] = 'This attempt has no recorded decisions yet.';
$string['report:revisiongone'] = 'The revision this attempt was taken against is no longer available, so the decisions cannot be shown.';
$string['error:unknownattempt'] = 'That attempt does not belong to this activity.';
$string['confirmdeleteattempt'] = 'Delete attempt {$a->attempt} by {$a->user}? Their decisions, the record of how they got there and their grade for this activity are removed. This cannot be undone.';
$string['beginscenario'] = 'Start';
$string['resumescenario'] = 'Carry on where you left off';
$string['seeyourresult'] = 'See how you did';
$string['noattemptsleft'] = 'You have used all your attempts at this scenario.';
$string['progress'] = 'Scenario progress';
$string['stagenumber'] = 'Decision {$a}';
$string['stageprogress'] = 'Decision {$a->current} of {$a->total}';
$string['attemptfinished'] = 'Scenario complete';
$string['debriefhidden'] = 'You have reached the end of the scenario. Your teacher has chosen not to show a breakdown of your decisions.';
$string['tensionhigh'] = 'Tension is high';
$string['whatthistests'] = 'What this scenario asks you to apply';
$string['choosealegend'] = 'Choose what you do next';
$string['whatdoyoudo'] = 'What do you do?';
$string['deltaup'] = 'up {$a}';
$string['deltadown'] = 'down {$a}';
$string['deltareduced'] = 'reduced by {$a}';
$string['deltasame'] = 'no change';
$string['step:how'] = 'How this works';
$string['step:howlead'] = 'What you get for one generation, and what it does for a learner that a document cannot.';
$string['benefits:heading'] = 'Why a scenario teaches what a document cannot';
$string['benefits:lead'] = 'A learner can read a procedure and still not apply it under pressure. A scenario puts them in the moment the procedure is about, and makes them decide before they are told the answer.';
$string['benefit:decide'] = 'They decide, then find out';
$string['benefit:decidebody'] = 'Every screen ends in a real choice with no obviously correct option. The learner commits before the consequence appears, so the lesson lands on their own judgement rather than on someone else\'s explanation.';
$string['benefit:decideproof'] = 'Three to eight decision points, each with a different way forward.';
$string['benefit:consequence'] = 'Consequences that carry forward';
$string['benefit:consequencebody'] = 'A choice changes the engagement, trust and tension in the room, and the next scene arrives in the situation the learner created. Cut a corner early and the story gets harder, exactly as it does at work.';
$string['benefit:consequenceproof'] = 'Branching paths with earned endings, not one ending with different wording.';
$string['benefit:voices'] = 'People, not paragraphs';
$string['benefit:voicesbody'] = 'The situation is illustrated and narrated, and each character speaks in their own voice. Clicking someone\'s face to hear what they are thinking is what turns a policy into a person waiting for an answer.';
$string['benefit:voicesproof'] = 'A scene image per screen, a narrator, and separate voices for the people in the room.';
$string['benefit:safe'] = 'The expensive mistake, made safely';
$string['benefit:safebody'] = 'The decisions worth training are the ones that cost money, trust or safety when they go wrong. Here they can go wrong, be seen to go wrong, and be tried again — which is the one thing the real job does not allow.';
$string['benefit:safeproof'] = 'Replay is on by default, and every attempt is kept.';
$string['benefit:evidence'] = 'Evidence you can show an auditor';
$string['benefit:evidencebody'] = 'Every decision a learner made, in order, with what it cost them and why it mattered, against four named skills. Not a completion tick: a record of judgement that stands up when somebody asks how you know they can do it.';
$string['benefit:evidenceproof'] = 'Per-attempt reports, four skill scores and a gradebook item.';
$string['benefit:time'] = 'An afternoon\'s work in minutes';
$string['benefit:timebody'] = 'Paste the policy, unit content or procedure you already have. The situation, the characters, the branches, the pictures and the voices are written from it, and you edit anything you disagree with before a learner sees it.';
$string['benefit:timeproof'] = 'You review and publish; nothing reaches a learner until you say so.';
$string['howto:heading'] = 'How to build one';
$string['howto:lead'] = 'Six steps, and the last one is the one people miss.';
$string['howto:paste'] = 'Paste your source';
$string['howto:pastebody'] = 'The policy, unit content, procedure or notes the scenario should teach. Everything after this is built from it, so the more concrete it is, the more specific the situation.';
$string['howto:shape'] = 'Shape the situation';
$string['howto:shapebody'] = 'Where it happens, who is in the room, what is at stake and how many decisions it should contain. Fill in what you care about and let the rest take its defaults.';
$string['howto:generate'] = 'Generate';
$string['howto:generatebody'] = 'This is the step that spends credits, and it asks you to confirm first. It takes a few minutes, and you can leave the page while it runs.';
$string['howto:review'] = 'Read it before anyone else does';
$string['howto:reviewbody'] = 'Every scene, choice, consequence and piece of feedback is editable. It is your material and your name on it, so change anything that is not how you would put it.';
$string['howto:publish'] = 'Publish';
$string['howto:publishbody'] = 'Publishing takes a snapshot that learners work through. Your working copy stays separate, so you can keep editing without changing what anyone is part-way through.';
$string['howto:play'] = 'Then look at it as a student';
$string['howto:playbody'] = 'Publishing does not change this page: this is the author\'s view. To see the scenario the way a learner does, switch to student view from your profile menu, or open the activity while your role is switched. That is where the pictures, the narration and the branching actually happen.';
$string['reading:docs'] = 'Documentation';
$string['reading:docsbody'] = 'The full guide: what each field does, how the branching and scoring work, and what to do when a generation does not come out the way you wanted.';
$string['reading:pricing'] = 'LMS Labs pricing';
$string['reading:pricingbody'] = 'Credit bundles, what everything else on the platform costs, and how to top up.';
$string['reading:voices'] = 'Voice samples';
$string['reading:voicesbody'] = 'Hear every narration voice before your site administrator chooses which one reads your scenarios.';
$string['pricing:heading'] = 'What this scenario costs';
$string['pricing:lead'] = 'One price per generated scenario, deducted from your credit balance. You are charged when you generate, not when learners play it, and regenerating a scenario is charged again.';
$string['pricing:note'] = 'Pictures and narration are switched on and off in the activity settings, so you can move between these before you generate. Editing a scenario afterwards, publishing it and replaying it are all free.';
$string['price:noimagesnovoice'] = 'Scenario only';
$string['price:imagesnovoice'] = 'With scene images';
$string['price:noimagesvoice'] = 'With narration';
$string['price:imagesvoice'] = 'With scene images and narration';
$string['pricecredits'] = '{$a->credits} credits ({$a->money})';
$string['confirmgenerate:price'] = 'This scenario costs {$a->price}. That covers {$a->images} scene images and {$a->narrations} narration clips.';
$string['listento'] = 'Hear what {$a} is thinking';
$string['deckposition'] = '{$a->current} of {$a->total}';
// The opening brief: what a learner is walking into. Everything here was already in the
// scenario and shown nowhere - a learner started without being told what the three readings
// in the bar measured, and found out when one of them dropped.
$string['brief:working'] = 'What you are working on';
$string['brief:aimup'] = 'get this up';
$string['brief:aimdown'] = 'keep this down';
$string['brief:where'] = 'Where';
$string['brief:who'] = 'Who';
$string['brief:ahead'] = 'Ahead';
$string['brief:decisions'] = '{$a} decisions';
$string['lesson:situation'] = 'The situation';
$string['lesson:number'] = 'Principle {$a}';
$string['lesson:saythis'] = 'Sounds like this';
$string['lesson:notthis'] = 'Not this';
$string['lesson:beginhead'] = 'Begin the scenario';
$string['lesson:ready'] = 'Ready when you are';
$string['lesson:resumehead'] = 'Pick up where you left off';
$string['lesson:resumelead'] = 'Your attempt is still open. You will go back to the decision you were on, with the readings where you left them.';
$string['lesson:readylead'] = 'You will make a series of decisions. There is no obviously right answer waiting to be spotted: each choice is something a reasonable person might do, and each one changes what happens next.';
$string['lesson:pages'] = 'Opening lesson pages';
$string['deckprev'] = 'Previous';
$string['decknext'] = 'Next';
$string['debriefpages'] = 'Debrief pages';
$string['debriefend'] = 'Debrief complete';
$string['debriefendlead'] = 'Go back through any page with the arrows, run the scenario again to try a different path, or keep a copy.';
$string['fullscreen:enter'] = 'Fill the screen';
$string['fullscreen:exit'] = 'Leave full screen';
$string['error:printblocked'] = 'Your browser would not open the print dialogue. Use the browser\'s own print command instead.';
$string['voice:female'] = 'female';
$string['voice:male'] = 'male';
$string['voice:neutral'] = 'neutral';
$string['settings:narratorvoice'] = 'Narrator voice';
$string['settings:narratorvoice_help'] = 'Reads the situation on each screen, and reads a line whose speaker is not named in the cast.';
$string['settings:malevoice'] = 'Male character voice';
$string['settings:malevoice_help'] = 'Speaks the lines of characters recorded as male, so a character does not sound like the narrator.';
$string['settings:femalevoice'] = 'Female character voice';
$string['settings:femalevoice_help'] = 'Speaks the lines of characters recorded as female.';
$string['deltaraised'] = 'raised by {$a}';
$string['continue'] = 'Continue';
$string['seewhathappened'] = 'See how it ended';
$string['working'] = 'Working...';
$string['narration:on'] = 'Narration';
$string['narration:off'] = 'Muted';
$string['narration:blocked'] = 'Tap to play';
$string['narration:none'] = 'No narration';
$string['narration:tipon'] = 'Narration is on. Select to mute it.';
$string['narration:tipoff'] = 'Narration is muted. Select to turn it back on.';
$string['narration:tipblocked'] = 'Your browser blocked the sound from starting on its own. Select to play the narration for this scene.';
$string['narration:tipnone'] = 'This scene has no narration recorded.';
$string['narrationon'] = 'Narration on';
$string['narrationoff'] = 'Narration off';
$string['notpublishedyet'] = 'This scenario has not been published yet.';
$string['previewonly'] = 'You can read this scenario but your role does not allow attempts.';
$string['attemptsremaining'] = 'Attempts remaining: {$a}';
$string['tryagain'] = 'Try again';
$string['printdebrief'] = 'Print or save';
$string['howyouhandledit'] = 'How you handled it';
$string['decisionqualityscore'] = 'Decision quality {$a}%';
$string['decisionstaken'] = 'Decisions taken: {$a}';
// Labels for the closing card. The three above are sentence templates carrying a {$a}
// placeholder, so using them as bare labels left "Decisions taken:" with nothing after the
// colon. A label is not a sentence with its subject removed.
$string['endstat:outcome'] = 'Outcome';
$string['endverdict:perfect'] = 'Every decision was the best one available';
$string['endverdict:strong'] = 'A strong run';
$string['endverdict:mixed'] = 'A mixed run - there is ground to make up';
$string['endverdict:weak'] = 'A costly run - worth another attempt';
$string['endstat:quality'] = 'Decision quality';
$string['endstat:decisions'] = 'Decisions taken';
$string['editscenario'] = 'Edit scenario';
$string['step:source'] = 'Source';
$string['step:scene'] = 'The scene';
$string['step:challenge'] = 'The challenge';
$string['step:people'] = 'The people';
$string['step:design'] = 'Design';
$string['step:build'] = 'Build and publish';
$string['step:sourcelead'] = 'Paste what the scenario should teach. Everything after this is built from it, so the more concrete the source, the more specific the situation. Five key points, 500 to 800 words, is the shape that works.';
$string['step:scenelead'] = 'Where this happens and how it feels when the learner walks in. This is the first thing they read, so it sets whether the scenario feels real or generic.';
$string['step:challengelead'] = 'The problem the learner has to handle. Say why it is hard and what is at stake — a decision with nothing riding on it is not a decision.';
$string['step:peoplelead'] = 'Who the learner is, and who else is in the room. The other people are what makes a choice cost something.';
$string['step:designlead'] = 'How the scenario looks and reads.';
$string['step:buildlead'] = 'Generate the scenario, read every branch, then publish when you are happy. Nothing reaches learners until you publish.';

$string['autofill'] = 'Fill the wizard from this content';
$string['whatnext'] = 'How do you want to build this scenario?';
$string['autofillhint'] = 'The AI reads what you pasted and answers the rest of the wizard for you. You can change any of it afterwards.';
$string['draftelsewhere'] = 'Or draft it in another assistant';
$string['draftelsewherehint'] = 'Useful when this site has no LMS Labs credits, or when you would rather work on the scenario somewhere you can argue with it. Copy the prompt, paste it into an assistant along with your content, then bring the result back here.';
$string['field:industryother'] = 'Which industry? Name it so the scenario belongs to it.';
$string['field:settingother'] = 'Where does this happen? Describe the place in a few words.';
$string['suggest'] = 'Suggest';
$string['nosuggestion'] = 'No suggestion was returned for that field.';
$string['savedraft'] = 'Save draft';
$string['saved'] = 'Saved';
$string['next'] = 'Next';
$string['back'] = 'Back';
$string['savenode'] = 'Save this decision point';
$string['field:ending'] = 'Ending';
$string['field:choice'] = 'Choice {$a}';
$string['field:signal'] = 'How well judged is this?';
$string['field:leadsto'] = 'Leads to';
$string['editnodes'] = 'Edit the generated scenes ({$a} in total)';
$string['generatescenario'] = 'Generate the scenario';
$string['confirmgenerate:title'] = 'Generate this scenario?';
$string['confirmgenerate:cost'] = 'The scenario will have {$a->scenes} scenes, including {$a->images} illustrated ones.';
$string['confirmgenerate:balance'] = 'Your site has {$a} credits.';
$string['confirmgenerate:allowance'] = 'Your daily allowance on this site has only {$a} credits left, so this run will be refused. A site administrator can raise the allowance, or it resets 24 hours after each request.';
$string['confirmgenerate:replaces'] = 'It replaces the current draft, including any text you have edited by hand and every image already generated. You can undo this once afterwards.';
$string['restoredraft'] = 'Undo the last generation';
$string['restoredrafthint'] = 'Puts back the draft as it was before the last generation or import. Only the most recent step is kept.';
$string['restore:nothing'] = 'There is no earlier draft to go back to.';
$string['generateexplain'] = 'Generation reads the source content, identifies the decision principles inside it, and builds a branching scenario around them. It runs in the background and usually takes one to three minutes.';
$string['generationqueued'] = 'Generation queued...';
$string['generationrunning'] = 'Writing the scenario...';
$string['generationeta'] = 'Can take up to 5 minutes to generate.';
$string['generationready'] = 'Scenario ready, reloading...';
$string['mediaincomplete'] = 'Only {$a->made} of {$a->wanted} illustrations and narration clips were generated. The scenario is usable, but some scenes will have no picture and some screens will be silent.';
$string['mediarefused'] = 'The service gave this reason: {$a}.';
$string['mediaqueued'] = '{$a} illustrations and narration clips are being generated in the background. They appear on the scenario once the site\'s scheduled tasks have run, which is usually a few minutes.';
$string['publishscenario'] = 'Publish';
$string['published'] = 'Published';
$string['publishedrevision'] = 'Published revision {$a}';
$string['previewscenario'] = 'Preview as a learner';
$string['review:title'] = 'Review the draft';
// The decision slides. One per decision, replacing the four pages of closing advice.
$string['whythismattered'] = 'Why this mattered';
$string['principletested'] = 'Principle tested:';
$string['decisionnumber'] = 'Decision {$a}';
$string['youchose'] = 'You chose';
$string['youroption'] = 'Your choice';
$string['quality:pronounconflict'] = 'The cast record says {$a->name} is {$a->gender}, but the scenario calls them "{$a->pronoun}". The illustrations are briefed from the record, so a learner will read one thing and see another. Fix the pronoun in the editor, or change the character\'s gender, before publishing.';
// What each picture in the image map illustrates. Shown to a teacher when a revision is
// short of pictures, so "you are four short" can name which four rather than leaving them
// to work it out from the screens.
$string['map:opening'] = 'The opening: the place, before anyone has done anything';
$string['map:lesson'] = 'The slide that teaches "{$a}"';
$string['map:scene'] = 'The decision "{$a}"';
$string['map:crisis'] = 'The escalated version of "{$a}"';
$string['map:reactionpositive'] = 'The reaction after "{$a}" goes well';
$string['map:reactionneutral'] = 'The reaction after "{$a}" settles nothing';
$string['map:reactionnegative'] = 'The reaction after "{$a}" costs something';
// The decision slides: what makes one teach something, and what makes it look complete
// and carry nothing.
$string['quality:flatsignals'] = 'Every option at "{$a}" carries the same signal, so the debrief slide for it has three paragraphs and no ranking - nothing on it says which choice was the better one. Give the options different signals in the editor before publishing.';
$string['quality:duplicateoutcomenote'] = 'Two options at "{$a}" explain their outcome in the same words. The slide for this decision exists to show three different consequences side by side; two that read alike teach nothing by being next to each other.';
$string['quality:restatedoutcomenote'] = 'Option {$a->letter} at "{$a->node}" explains its outcome by repeating the option itself. The paragraph is there to say what taking it costs and what it teaches, and on the slide it will read as the same line printed twice.';
$string['quality:readingrange'] = 'Even taking the worst option at every decision, {$a->metric} only reaches {$a->worst} and never {$a->end}. A learner who gets everything wrong will see a score of 0% next to a reading that says the run went half well. Make the effects on the poor options larger in the editor - across five decisions they need to span the whole scale.';
$string['quality:derivedoutcomenote'] = 'The options at "{$a}" have no paragraph of their own, so the debrief slide is showing what happened and why it mattered, run together. That is the scenario\'s own words and it reads as a summary rather than as what taking each option would have cost. Write a sentence or two against each option in the editor, or regenerate once your service is sending them.';
// The product is called AI Branched Scenario. Until v2.4.0 nothing checked that a scenario
// branched, and the worked example shipped inside the plugin broke the rule three times.
$string['quality:nobranchingatall'] = 'No decision in this scenario changes where the learner goes next. Every path is the same path, so what you have is a scored lesson with three meters rather than a branched scenario. At least {$a->wanted} decisions should send a poor choice somewhere a good choice does not - a different scene, a different conversation, a consequence that has to be dealt with. Start with "{$a->flat}".';
$string['quality:notenoughbranching'] = 'Only {$a->have} of this scenario\'s decisions change where the learner goes next; it needs at least {$a->wanted}. Paths are meant to rejoin, so decisions that converge are fine - but a learner who chooses badly must see something a learner who chooses well never sees. The ones that change nothing are "{$a->flat}".';
$string['award:groundheld'] = 'Held your ground';
$string['award:groundhelddetail'] = 'You made the safe call while somebody was pushing you not to. That is the decision this scenario exists to test, and it is the one a written test cannot.';
$string['award:recovered'] = 'Strong recovery';
$string['award:recovereddetail'] = 'A decision went badly, you saw what it cost, and you put it right. That is harder than never slipping, and it is the part of the job nobody gets to practise anywhere else.';
$string['award:cleanrun'] = 'Not a foot wrong';
$string['award:cleanrundetail'] = 'Every decision was the best one available. Nobody was told this was possible before they started — it is not a target to replay for, it is something you did.';
$string['award:nothingleft'] = 'Nothing left behind';
$string['award:nothingleftdetail'] = 'You finished with the room settled and people still willing to bring you things. A scenario can be finished with a good score and a wrecked room; this one was not.';
$string['award:heading'] = 'What you did';
// CONFIDENCE. One box, volunteered before the outcome is known.
$string['notsure'] = 'I am not sure about this one';
// WORKPLACE ARTEFACTS. What the document announces itself as, above the sheet.
$string['artefact:permit'] = 'Permit to work';
$string['artefact:sds'] = 'Safety data sheet';
$string['artefact:checklist'] = 'Checklist';
$string['artefact:email'] = 'Email';
$string['artefact:sign'] = 'Sign';
$string['artefact:record'] = 'Record';
$string['artefact:document'] = 'Document';
$string['quality:artefactnotread'] = 'The {$a->kind} at "{$a->node}" has a line marked as the one that is wrong, but there is no decision on that screen — the learner reads the fault and then clicks through it. Put the document on the decision it belongs to, or take the marking off the line.';
$string['quality:markneverset'] = 'A picture at "{$a->node}" is waiting to show "{$a->label}" once "{$a->flag}" is set, and nothing in this scenario ever sets it. The tag can never appear.';
$string['quality:notradeoff'] = '{$a->flat} of the {$a->total} decisions offer nothing to weigh up: every option is plainly good or plainly bad, so a learner picks the careful one without thinking ({$a->names}). A tempting option buys something — time, a quiet life, the job finished — and costs something else. Without that, the branches and the delayed consequences are content nobody will ever be shown.';
$string['quality:nopressure'] = 'Only {$a->spoken} of the decisions have somebody speaking to the learner, and at least {$a->wanted} should. Pressure arrives through people — the supervisor who is behind schedule, the workmate who has always done it this way — and without it the careful option costs nothing to take.';
$string['quality:deadflag'] = 'Nothing in this scenario ever sets "{$a}", so the screen written to react to it can never appear. Either set it on a choice, or remove the alternative wording that waits for it.';
$string['quality:unreadflag'] = 'A choice sets "{$a}" and nothing ever reads it. The learner changes something about the world and the world never mentions it again.';
// THE LADDER. An activity holds three scenarios at rising difficulty.
$string['confirmgenerate:noestimate'] = 'The cost estimate could not be read just now, so the price and your balance are not shown below. Generating will still work and will still be charged.';
$string['quality:skillnameleak'] = 'The skill name "{$a->skill}" appears in {$a->where}. Skill names are the scoring language of the debrief; a learner who meets one mid-scenario is being shown the marking scheme while they are being marked. Rewrite it in the words of the job.';
$string['quality:principleneedsexample'] = 'The principle "{$a}" teaches a rule with no example. Add the words a learner could actually say or the action they could take, in the editor before publishing - a learner remembers the words they can use, not the rule.';
$string['quality:principleneedspitfall'] = 'The principle "{$a}" has no pitfall. Add the plausible-sounding version that does not work, and why - that is the thing a learner needs warning about.';
$string['quality:spelling'] = 'This scenario is set to {$a->language}, but the writing uses the other variety\'s spelling in places: {$a->words}. Correct them in the editor before publishing, or the learner reads them.';
$string['quality:spellingnode'] = 'Spelling';
// Looking back at a decision already taken, from the consequence screen itself. The
// read-only list behind the bar answered "what did I pick"; this answers "what did it do",
// which is the question a branching scenario exists to make a learner ask.
$string['reviewback'] = 'Previous decision';
$string['reviewforward'] = 'Next decision';
$string['reviewreturn'] = 'Back to where you were';
$string['reviewnote'] = 'Looking back. Nothing here changes your answers or your score.';
$string['reviewposition'] = 'Decision {$a->current} of {$a->total}';
$string['journey:readonly'] = 'What you have decided so far. Earlier decisions cannot be changed - their consequences have already happened.';
$string['journey:sofar'] = 'Decisions so far';
$string['bandgreen'] = 'Good from';
$string['bandgreen_help'] = 'A reading at or above this counts as good and is shown in green. Everything between this and the lower threshold is shown in amber.

This colours the engagement, trust and tension readings, and the four skill bars in the debrief. Tension is read the other way round: a low tension is a good one, so a tension of 20 is green when this is set to 67.';
$string['bandred'] = 'A problem below';
$string['bandred_help'] = 'A reading below this counts as a problem and is shown in red.

Set both thresholds for the conversation you are teaching. A de-escalation exercise and a sales conversation do not agree about what a trust of 55 means.';
$string['error:bandorder'] = 'The good threshold has to be above the problem threshold.';
$string['error:bandrange'] = 'Thresholds are percentages, so they have to be between 0 and 100.';
$string['report:eyebrow'] = 'Attempts and results';
// THE COHORT REPORT. What a group got wrong, and whether they knew.
$string['report:tab:attempts'] = 'Attempts';
$string['report:tab:cohort'] = 'Where people go wrong';
$string['report:cohorteyebrow'] = 'What this group found hard';
$string['report:cohortintro'] = 'Every number here is a count of decisions people actually made. Nothing is predicted and nobody is profiled — a decision is named, never a learner.';
$string['report:nodatayet'] = 'Nobody has made a decision in this scenario yet. This page fills itself in as people work through it.';
$string['report:notenoughyet'] = 'Only {$a} so far — too few to read anything into the percentages. This decision is listed once {$a} reaches {$b}.';
$string['report:misconceptions'] = 'Worth twenty minutes on Monday';
$string['report:nomisconceptions'] = 'No decision in this scenario is catching out enough of this group to be worth a session. That is the result you want, and it is worth knowing it is not a gap in the data: {$a} decisions have been answered enough times to judge.';
$string['report:confidentwrong'] = 'Confidently wrong';
$string['report:knewtheywereunsure'] = 'Knew they were unsure';
$string['report:misconceptiondetail'] = '{$a->share}% of this group chose a poor option here, and {$a->sureshare}% of them did not say they were unsure. They are not guessing — they think this is right, so they will not ask. Showing them the consequence is what shifts it.';
$string['report:gapdetail'] = '{$a->share}% of this group chose a poor option here, and most of them said they were unsure beforehand. They know where the edge of their knowledge is; telling them the rule is enough.';
$string['report:mostcommonly'] = 'Most of them chose: "{$a}"';
$string['report:testing'] = 'Testing: {$a}';
$string['report:everydecision'] = 'Every decision, option by option';
$string['report:taken'] = '{$a} taken';
$string['report:answeredby'] = 'Answered {$a} times';
$string['report:unsurecount'] = '{$a} said they were not sure';
$string['stat:decisionsmade'] = 'Decisions made';
$string['stat:unsurerate'] = 'Flagged "not sure"';
$string['requirelisten'] = 'Hear each screen out before moving on';
$string['requirelisten_help'] = 'When narration is on, the way forward normally dims while a screen is being read but can still be pressed. Turn this on to hold it closed until the reading finishes, so a learner cannot click past the teaching.

The way forward always opens again on its own if a recording fails or never finishes, so a learner can never be stranded on a screen. It also opens immediately for anyone who has muted the narration.';
$string['review:eyebrow'] = 'Draft, not yet published';
$string['skill:adaptabilitydesc'] = 'Changing course when the room told you something new, rather than pressing on with the plan you arrived with.';
$string['skill:claritydesc'] = 'Saying what you meant plainly enough that nobody had to guess at it afterwards.';
$string['skill:empathydesc'] = 'Reading what the other person needed, and acting on it rather than only noting it.';
$string['skill:presencedesc'] = 'Staying with the conversation under pressure instead of retreating into procedure.';
$string['skills:lead'] = 'This is your grade, and it comes from these four things - not from the engagement, trust and tension readings you watched along the way. Those describe the room; these describe what you did in it.';
$string['standing:mixed'] = 'Moderate';
$string['standing:strong'] = 'Strong';
$string['standing:weak'] = 'Low';
$string['review:back'] = 'Back to the wizard';
$string['review:nodraft'] = 'There is nothing to review yet. Generate a scenario or import one first.';
$string['review:counts'] = '{$a} scenes.';
$string['review:imagecount'] = '{$a} illustrations generated.';
$string['review:leadsto'] = 'Leads to:';
$string['review:autotarget'] = 'the ending the learner has earned';
$string['review:crisis'] = 'If tension is already high';
$string['review:quality'] = 'Worth a look before you publish';
$string['review:qualityhint'] = 'These scenes hold together and will play, so nothing here stops you publishing. They are the places where the generated scenario asks the learner to choose without much turning on the answer.';
$string['review:qualitynone'] = 'Every scene offers choices that lead somewhere different and are scored differently.';
$string['review:qualityat'] = 'In {$a}:';
$string['quality:onechoice'] = 'This scene asks the learner to decide but offers only one answer.';
$string['quality:sametarget'] = 'All {$a} choices lead to the same next scene, so the decision changes nothing.';
$string['quality:noscoring'] = 'No choice here moves any of the four skill scores.';
$string['quality:samescoring'] = 'Every choice here scores identically, so the decision cannot affect the grade.';
$string['quality:duplicatechoice'] = 'Choices {$a->a} and {$a->b} say much the same thing.';
$string['quality:duplicateconsequence'] = 'Choices {$a->a} and {$a->b} lead to much the same consequence.';
$string['quality:oneoutcome'] = 'Every route through this scenario ends at the same outcome.';
$string['reviewdraft'] = 'Review the draft';
$string['reviewdrafthint'] = 'Read every scene and every branch, including the ones a single run would never reach, before you publish.';
$string['unsavedchanges'] = 'You have unsaved changes.';
$string['field:importjson'] = 'Bring in a scenario from an assistant';
$string['field:importjsonhint'] = 'Paste what the assistant gave you back to build or reuse a scenario without spending credits. It is checked before anything is saved.';
$string['importdefinition'] = 'Save and apply';
$string['worknote'] = 'Please keep this page open until it finishes.';
$string['work:checking'] = 'Checking what you pasted';
$string['work:saving'] = 'Saving the scenario';
$string['work:media'] = 'Queueing the illustrations and narration';
$string['work:reading'] = 'Reading your content';
$string['work:filling'] = 'Filling in the wizard';
$string['work:writing'] = 'Writing the scenario';
$string['work:built'] = 'Built {$a->nodes} decision points';
$string['work:mediaqueued'] = 'Queued {$a} illustrations - they arrive in the background';
$string['work:done'] = 'Done';
$string['recordyouchose'] = 'You chose:';
// The picture top-up: the shortfall a teacher is shown, and the controls that fix it.
$string['error:nopublishedrevision'] = 'This activity has no published version yet, so there is nothing to check for missing pictures. Publish it first.';
$string['topup:nothingmissing'] = 'Every picture this scenario calls for is already there.';
$string['topup:heading'] = 'Missing pictures';
$string['topup:short'] = 'This published version is short of {$a} pictures. Slides without their own picture fall back to borrowing another screen\'s, which is why two screens can look the same.';
$string['topup:check'] = 'Check for missing pictures';
$string['topup:make'] = 'Generate the missing pictures';
$string['topup:made'] = 'Made {$a->made} of {$a->wanted}.';
$string['topup:orphaned'] = '{$a} pictures belong to entries that have since been edited or removed. They cost nothing and are not shown to learners.';
$string['topup:shared'] = 'Two or more screens are showing the same picture.';
$string['media:incomplete'] = 'Only {$a->made} of {$a->wanted} pictures and clips were made, so some slides have no picture or no narration. Generate the media again to finish the set. Reason: {$a->why}';
$string['media:nowhy'] = 'the run did not finish';
$string['media:inflight'] = 'The scenario was imported, but its pictures and narration were not started: a media run for this activity is already under way. Wait for it to finish, then generate the media again from the activity settings.';
$string['error:mediainflight'] = 'A media run for this activity is already under way.';
$string['error:attemptunplayable'] = 'That attempt cannot be continued because the scenario it was started on is no longer available. It has been set aside so you can begin again.';
$string['media:overbudget'] = 'The scenario was imported, but its {$a->images} pictures and {$a->clips} narration clips were not made: they would take this account past its daily generation budget. Ask an administrator to raise the budget, or generate the media tomorrow from the activity settings.';
$string['media:notpublished'] = '{$a->made} pictures and clips are ready and waiting, but this activity has not been published, so no learner can see any of them yet. Publish the activity to put the media in front of learners.';
$string['media:defmoved'] = 'The {$a->made} pictures and clips made for this scenario do not match the version that is published, because the scenario was changed after they were requested. They are held back rather than shown on the wrong screens. Generate the media again for the current version.';
$string['media:unpublished'] = 'All {$a->made} pictures and clips were made, but only {$a->published} of them reached the published version, so learners see a scenario with media missing. This happens when the activity is published while the media is still being made. Publish again to copy the finished media across.';
$string['publishdone:title'] = 'Your scenario is live';
$string['publishdone:lead'] = 'Learners on this course can now work through it. To see exactly what they will see, switch the course to student view.';
$string['publishdone:close'] = 'Back to the wizard';
$string['work:publishing'] = 'Publishing the scenario';
$string['field:importjsonpaste'] = 'Paste the prompt output here';
$string['copyprompt'] = 'Copy the prompt';
$string['copyprompthint'] = 'Copies a prompt describing this plugin\'s scenario format, with whatever you have pasted above attached to it. Paste that into ChatGPT, Claude, Copilot or any other assistant, then paste what it gives you back into the box below.';
$string['promptcopied'] = 'The prompt is on your clipboard.';
$string['fillingfields'] = 'Filling the fields the first pass left empty...';
$string['field:sourcecontent'] = 'Source content';
$string['field:sourcecontenthint'] = 'Paste the policy, procedure, unit content or notes the scenario should be built from. <strong>Aim for five key points and 500 to 800 words.</strong> That is the amount one scenario can carry: five decisions, one per point. Up to {$a} characters.';
// The word count under the box. A teacher pasting a heading and two bullets has no way to
// know that is too little until the scenario comes back generic, and one pasting a
// thirty-page policy has no way to know most of it will be ignored. The count says so
// while there is still time to do something about it.
$string['sourcecount'] = '{$a} words';
$string['sourcecount:thin'] = '{$a} words - thin. Under 200 words gives the writer nothing specific to build on, and the scenario comes back generic.';
$string['sourcecount:good'] = '{$a} words - about right for five decisions.';
$string['sourcecount:long'] = '{$a} words - longer than one scenario can use. Everything here is read, but five decisions can only carry about five ideas: cut to the points that matter most, or build a second scenario.';
// The worked example. Teachers ask what "source content" means and the honest answer is
// specific enough that showing beats describing.
$string['sourceexample'] = 'Show me an example';
$string['sourceexamplelead'] = 'This is about 600 words on one topic, written as five points. Yours does not need to look like this - it is the level of detail that matters, not the format.';
$string['sourceexamplewhat'] = 'What makes a good source';
$string['sourceexamplebody'] = '<p><strong>Five points, because there are five decisions.</strong> Each point becomes one moment where the learner has to choose. Fewer than five and the writer invents the rest; many more and the ones you care about get dropped.</p>
<p><strong>Rules with consequences beat definitions.</strong> "Faults are logged on sight" is a rule. "Faults are logged on sight because the night shift starts the machine without being told what happened on ours" is a rule with a consequence, and the consequence is what makes a decision worth taking.</p>
<p><strong>Say what goes wrong in practice.</strong> The plausible wrong answers are the hardest part of a scenario to write, and they are the part you already know. If people put it off until the end of shift, or tell someone in the corridor instead of writing it down, say so - those become the options that are tempting rather than obviously wrong.</p>
<p><strong>Name the pressure.</strong> Time, cost, somebody senior in the room, a target that competes with the rule. Without it every decision has an obvious right answer and nothing is learnt.</p>
<p><strong>Leave real people out.</strong> The scenario invents its own cast. Describe roles - shift supervisor, new starter - not names of people at your organisation.</p>';
$string['sourceexampletext'] = 'Reporting equipment faults on the packing line

Why this matters. A fault that is seen and not written down is a fault the next shift does not know about. Our incident record for the last two years shows that in four of the six machine injuries, somebody on an earlier shift had noticed something and mentioned it verbally to one person. The information existed. It just did not survive the handover.

1. Log a fault the moment it is seen, not at the end of the shift.
The log entry takes about ninety seconds. The reason it gets deferred is that stopping to write during a run feels like the expensive option, and at the end of a shift it feels like the cheap one - by which time the detail has gone. What we need in the entry is what the machine was doing, when it started, and whether it changed. "Number three is noisy" is not a record. "Number three started a knocking sound around 1pm, worse under load" is.

2. Stopping the line is a decision you are allowed to make.
Supervisors defer this because the line stopping is visible and a fault running is not. Nobody has ever been disciplined here for stopping a line. Two people have been disciplined for running one they had been told was unsafe. The rule is that if you are asking yourself whether it should be stopped, that question is the answer.

3. Escalate to the person who can decide, not the person who is nearest.
Telling a colleague is not escalation, and neither is a note left on a desk. The shift manager can authorise a repair and reschedule the run; nobody else on the floor can do either. If they are not contactable, the duty number is on the board and it is answered.

4. A handover names what is unresolved, not just what happened.
The common failure is a handover that reports the shift as a series of completed events. What the incoming supervisor needs is the list of things still open: what is being watched, what has been logged and not yet fixed, and what you would want to know if you were starting now. If you delayed something, say that you delayed it - the delay is usually the useful part.

5. The written record is read by people who were not there.
It will be read by maintenance deciding what to strip down, and by an investigator if something goes wrong later. Both of them need the sequence and the timings. A record that leaves out the hour the fault ran is not neutral - it points the next person away from the thing that mattered.

What tends to go wrong. Operators raise faults and then soften them, because the supervisor is busy. Supervisors watch a fault through to the end of shift because the run is nearly finished. Handovers are given while both people are walking. And everybody writes a shorter log entry than they would want to read.';
$string['field:outcomenote'] = 'Where this option leads';
$string['field:outcomenotehint'] = 'Shown on the debrief slide for this decision, beside the other options. Say what taking this one costs and what it teaches - not what happened, which the consequence above already says.';
$string['field:brief'] = 'Extra direction (optional)';
$string['field:briefhint'] = 'Anything the source content does not say: who this is for, what you want them to practise, a situation you have in mind. One or two sentences is plenty.';
// Three examples rather than a description. Every teacher asked what belongs here answers
// with one of these three shapes - who it is for, what to make it turn on, or a real
// situation to reuse - and none of them are obvious from the phrase "extra direction".
$string['briefexample'] = 'Three things worth putting here';
$string['briefexample:audience'] = 'Who it is for, when the source does not say.';
$string['briefexample:audiencetext'] = 'For new supervisors in their first six months, who know the rule and have not yet had to enforce it with somebody more senior than them.';
$string['briefexample:focus'] = 'What you want the decisions to turn on.';
$string['briefexample:focustext'] = 'Make the hard part the conversation rather than the paperwork. They can all fill in the form; what they avoid is telling somebody their work has to stop.';
$string['briefexample:situation'] = 'A real situation to build from, with the names taken out.';
$string['briefexample:situationtext'] = 'Set it on a late shift with a delivery deadline, where the person raising the fault is agency staff on their second week and the supervisor has met them once.';
$string['useexample'] = 'Use this example';
$string['usedexample'] = 'The example has been put in the box. Replace it with your own content before you generate.';
$string['field:title'] = 'Scenario title';
$string['field:audience'] = 'Audience';
$string['field:industry'] = 'Subject area';
$string['field:setting'] = 'Setting';
$string['field:atmosphere'] = 'Atmosphere';
$string['field:openingsituation'] = 'Opening situation';
$string['field:openingsituationhint'] = 'Where the learner is and what is happening as the scenario opens.';
$string['field:centralproblem'] = 'Central problem';
$string['field:whyhard'] = 'Why it is hard';
$string['field:stakes'] = 'What is at stake';
$string['field:participantrole'] = 'The role the learner plays';
$string['field:character'] = 'Character {$a}';
$string['field:charactername'] = 'Name';
$string['field:characterrole'] = 'Role';
$string['field:charactertrait'] = 'Defining trait';
$string['field:characterappearance'] = 'Appearance';
$string['field:charactergender'] = 'Voice and likeness';
$string['field:charactergendernone'] = 'Not set - the narrator reads their line';
$string['field:charactergenderhelp'] = 'Chooses the voice this person\'s spoken line is read in, and keeps their face the same from one scene image to the next. Left unset, the narrator reads the line and the illustrator is given nothing to hold to.';
$string['gender:female'] = 'Female';
$string['gender:male'] = 'Male';
$string['gender:nonbinary'] = 'Non-binary';
$string['field:decisionsfixed'] = 'Every scenario runs to {$a} decisions. The length is fixed so the debrief can give each decision a screen of its own, and so the number of illustrations is the same every time.';
$string['field:tone'] = 'Tone';
$string['field:complexity'] = 'Complexity';
$string['field:complexityhint'] = 'How much is signposted. Foundation states the problem plainly; Advanced signposts nothing and assumes you are the most senior person who has seen it. For a progression, build one activity at each level and gate them with the course\'s access restrictions.';
$string['field:imagestyle'] = 'Image style';
$string['field:imageprompt'] = 'Extra image direction (optional)';
$string['field:nodetitle'] = 'Title';
$string['field:situation'] = 'Situation';
$string['field:facilitatorspeech'] = 'Spoken line';
$string['field:challenge'] = 'Question put to the learner';
$string['field:outcomesummary'] = 'Outcome summary';
$string['field:choicetext'] = 'Choice';
$string['field:consequence'] = 'Consequence';
$string['field:feedback'] = 'Feedback';
$string['attemptreport'] = 'Attempts';
$string['noattemptsyet'] = 'No learner has attempted this scenario yet.';
$string['noinstances'] = 'There are no branching scenarios in this course.';
$string['learner'] = 'Learner';
$string['attemptnumber'] = 'Attempt';
$string['status'] = 'Status';
$string['score'] = 'Decision quality';
$string['outcome'] = 'Outcome';
$string['started'] = 'Started';
$string['actions'] = 'Actions';
$string['attemptdeleted'] = 'Attempt deleted';
$string['stat:attempts'] = 'Attempts';
$string['stat:learners'] = 'Learners';
$string['stat:finished'] = 'Finished';
$string['stat:averagescore'] = 'Average decision quality';
$string['status:draft'] = 'Draft';
$string['status:published'] = 'Published';
$string['attemptstatus:inprogress'] = 'In progress';
$string['attemptstatus:finished'] = 'Finished';
$string['attemptstatus:abandoned'] = 'Abandoned';
$string['grademethod:last'] = 'Last attempt';
$string['grademethod:first'] = 'First attempt';
$string['grademethod:highest'] = 'Highest attempt';
$string['grademethod:average'] = 'Average of attempts';
// Room dynamics, explained to the learner.
$string['metrics:explain'] = 'What these mean';
$string['metrics:lead'] = 'These three track how the room is responding to you. They shape what happens next — they are not your grade.';
$string['metrics:noright'] = 'There is no right answer to find. There are choices that land, choices that cost you something, and choices that do a bit of both — and the scenario carries all of them forward.';
$string['metrics:up'] = 'Rises when';
$string['metrics:down'] = 'Falls when';
$string['metric:engagementdesc'] = 'How much the other people are still with you.';
$string['metric:engagementup'] = 'you give someone room to finish, or act on what they said.';
$string['metric:engagementdown'] = 'you talk past someone, or decide without them.';
$string['metric:trustdesc'] = 'How far they believe you will do what you say.';
$string['metric:trustup'] = 'you name a problem plainly, or own a delay.';
$string['metric:trustdown'] = 'you smooth something over, or promise more than you can hold.';
$string['metric:tensiondesc'] = 'How much pressure is in the room. Lower is easier to work in, though raising something difficult is sometimes still the right call.';
$string['metric:tensionup'] = 'you raise something difficult, or let a problem run.';
$string['metric:tensiondown'] = 'you defuse a moment, or resolve what was hanging.';
$string['metrics:gradedby'] = 'Your grade comes from four skills instead: presence, adaptability, empathy and clarity. You will see all four in the debrief at the end.';

$string['metric:engagement'] = 'Engagement';
$string['metric:trust'] = 'Trust';
$string['metric:tension'] = 'Tension';
$string['skill:presence'] = 'Presence';
$string['skill:adaptability'] = 'Adaptability';
$string['skill:empathy'] = 'Empathy';
$string['skill:clarity'] = 'Clarity';
$string['outcome:strong'] = 'Strong outcome';
$string['outcome:mixed'] = 'Mixed outcome';
$string['outcome:highrisk'] = 'High risk outcome';
$string['signal:positive'] = 'Well judged';
$string['signal:neutral'] = 'Neutral';
$string['signal:negative'] = 'Costly';
$string['theme:indigo'] = 'Indigo';
$string['theme:slate'] = 'Slate blue';
$string['theme:emerald'] = 'Emerald';
$string['theme:ocean'] = 'Ocean';
$string['theme:amber'] = 'Amber';
$string['theme:violet'] = 'Violet';
$string['industry:training'] = 'Training and assessment';
$string['industry:healthcare'] = 'Healthcare';
$string['industry:agedcare'] = 'Aged care';
$string['industry:mining'] = 'Mining';
$string['industry:construction'] = 'Construction';
$string['industry:hospitality'] = 'Hospitality';
$string['industry:retail'] = 'Retail';
$string['industry:education'] = 'Education';
$string['industry:communityservices'] = 'Community services';
$string['industry:emergencyservices'] = 'Emergency services';
$string['industry:financialservices'] = 'Financial services';
$string['industry:humanresources'] = 'Human resources';
$string['industry:manufacturing'] = 'Manufacturing';
$string['industry:government'] = 'Government';
$string['industry:ittechnology'] = 'IT and technology';
$string['industry:compliance'] = 'Compliance';
$string['industry:cybersecurity'] = 'Cyber security';
$string['industry:sales'] = 'Sales';
$string['industry:leadership'] = 'Leadership';
$string['industry:other'] = 'Other';
$string['setting:trainingroom'] = 'Training room';
$string['setting:hospitalward'] = 'Hospital ward';
$string['setting:constructionsite'] = 'Construction site';
$string['setting:minesite'] = 'Mine site';
$string['setting:customercounter'] = 'Customer counter';
$string['setting:office'] = 'Office';
$string['setting:kitchen'] = 'Kitchen';
$string['setting:vehiclefield'] = 'Vehicle or field';
$string['setting:videocall'] = 'Video call';
$string['setting:warehouse'] = 'Warehouse';
$string['setting:classroom'] = 'Classroom';
$string['setting:other'] = 'Other';
$string['atmosphere:calm'] = 'Calm and professional';
$string['atmosphere:tension'] = 'Tension building';
$string['atmosphere:highpressure'] = 'High pressure';
$string['atmosphere:crisis'] = 'Crisis point';
$string['whyhard:timepressure'] = 'Time pressure';
$string['whyhard:challengingperson'] = 'A challenging person';
$string['whyhard:conflictingpriorities'] = 'Conflicting priorities';
$string['whyhard:missinginformation'] = 'Missing information';
$string['whyhard:policyvsreality'] = 'Policy against reality';
$string['whyhard:emotionalstakes'] = 'Emotional stakes';
$string['whyhard:safetyrisk'] = 'Safety risk';
$string['whyhard:languagebarrier'] = 'Language barrier';
$string['whyhard:powerimbalance'] = 'Power imbalance';
$string['whyhard:ambiguity'] = 'Ambiguity';
$string['whyhard:competingloyalties'] = 'Competing loyalties';
$string['whyhard:publicscrutiny'] = 'Public scrutiny';
$string['stakes:teamtrust'] = 'Team trust';
$string['stakes:learnersafety'] = 'Learner safety';
$string['stakes:jobperformance'] = 'Job performance';
$string['stakes:compliance'] = 'Compliance';
$string['stakes:reputation'] = 'Reputation';
$string['stakes:psychologicalsafety'] = 'Psychological safety';
$string['stakes:customeroutcome'] = 'Customer outcome';
$string['stakes:operationalcontinuity'] = 'Operational continuity';
$string['stakes:legalexposure'] = 'Legal exposure';
$string['stakes:wellbeing'] = 'Wellbeing';
$string['stakes:learningoutcomes'] = 'Learning outcomes';
$string['tone:neutral'] = 'Neutral';
$string['tone:supportive'] = 'Supportive';
$string['tone:direct'] = 'Direct';
$string['tone:formal'] = 'Formal';
$string['tone:conversational'] = 'Conversational';
$string['complexity:foundation'] = 'Foundation';
$string['complexity:intermediate'] = 'Intermediate';
$string['complexity:advanced'] = 'Advanced';
$string['imagestyle:photorealistic'] = 'Photorealistic';
$string['imagestyle:cinematic'] = 'Cinematic';
$string['imagestyle:illustration'] = 'Illustration';
$string['imagestyle:watercolour'] = 'Watercolour';
$string['imagestyle:noir'] = 'Film noir';
$string['imagestyle:oil'] = 'Oil painting';
$string['language:enau'] = 'English (Australia)';
$string['language:engb'] = 'English (United Kingdom)';
$string['language:enus'] = 'English (United States)';
$string['language:ennz'] = 'English (New Zealand)';
$string['language:eses'] = 'Spanish (Spain)';
$string['language:frfr'] = 'French (France)';
$string['language:dede'] = 'German (Germany)';
$string['language:itit'] = 'Italian (Italy)';
$string['language:ptbr'] = 'Portuguese (Brazil)';
$string['language:nlnl'] = 'Dutch (Netherlands)';
$string['language:hiin'] = 'Hindi (India)';
$string['language:idid'] = 'Indonesian (Indonesia)';
$string['language:jajp'] = 'Japanese (Japan)';
$string['language:kokr'] = 'Korean (Korea)';
$string['language:cmncn'] = 'Mandarin Chinese (China)';
$string['language:arxa'] = 'Arabic';
$string['language:vivn'] = 'Vietnamese (Vietnam)';
$string['language:thth'] = 'Thai (Thailand)';
$string['settings:connectionheading'] = 'LMS Labs connection';
$string['settings:connectiondesc'] = 'Scenario generation is performed by the LMS Labs service. This plugin holds no AI provider credentials of its own. Credentials are read from the AI Grader Central Config plugin when it is installed, and from the fields below otherwise.';
$string['settings:status'] = 'Connection status';
$string['settings:preferlocal'] = 'Ignore Central Config';
$string['settings:preferlocal_help'] = 'Always use the Site ID and API key entered below, even when a Central Config plugin is installed.';
$string['settings:centralcomponent'] = 'Central Config component';
$string['settings:centralcomponent_help'] = 'The frankenstyle component name of the Central Config plugin to read credentials from. Leave this at the default unless you have been told otherwise.';
$string['settings:siteid'] = 'LMS Labs Site ID';
$string['settings:siteid_help'] = 'Used only when Central Config is unavailable.';
$string['settings:apikey'] = 'LMS Labs API key';
$string['settings:apikey_help'] = 'Used only when Central Config is unavailable. The key is never shown again after saving and is never sent to the browser.';
$string['settings:apihost'] = 'LMS Labs API host';
$string['settings:apihost_help'] = 'Change this only if you have been given a different endpoint.';
$string['settings:requesttimeout'] = 'Request timeout';
$string['settings:requesttimeout_help'] = 'How long to wait for the LMS Labs service before giving up.';
$string['settings:limitsheading'] = 'Limits and permissions';
$string['settings:limitsheadingdesc'] = 'Bounds on what the plugin will send to the AI service, and what teachers are permitted to generate. Unlike the defaults below, the two permissions here cannot be overridden on an activity.';
$string['settings:maxsourcechars'] = 'Maximum source content';
$string['settings:maxsourcechars_help'] = 'The most characters of pasted source content that will be sent for one scenario.';
$string['settings:dailyquota'] = 'AI credits per user per day';
$string['settings:dailyquota_help'] = 'Bounds accidental or runaway credit use. Each operation counts for what the LMS Labs service charges for it, so one full generation with illustrations counts for far more than one suggested field. A scenario costs roughly 20 credits, or more with images and narration; filling the wizard from source content costs about a dozen. Set 0 to remove the limit.';
$string['settings:allowimages'] = 'Allow scene image generation';
$string['settings:allowimages_help'] = 'When off, teachers cannot turn on scene images for an activity.';
$string['settings:allowaudio'] = 'Allow narration generation';
$string['settings:allowaudio_help'] = 'When off, teachers cannot turn on narration for an activity.';
$string['settings:defaultbandgreen'] = 'Green band starts at';
$string['settings:defaultbandgreen_help'] = 'A reading or skill at this percentage or above is shown in green and described as strong. Anything between the two thresholds is amber. A lower number is a more forgiving marker: at 67 a learner needs two thirds to earn a green.';
$string['settings:defaultbandred'] = 'Red band below';
$string['settings:defaultbandred_help'] = 'A reading or skill under this percentage is shown in red and described as low. It must be lower than the green threshold. At 34 a learner has to fall below a third before anything turns red.';
$string['settings:defaultrequirelisten'] = 'Require narration to finish before continuing';
$string['settings:defaultrequirelisten_help'] = 'Holds the Continue button closed until the narration on a screen has played to the end. The lettered choices are never disabled, so a learner is not locked out of the decision itself. This is a restriction on the learner rather than a presentation choice, so it is off unless asked for, and it does nothing where narration is switched off.';
$string['settings:defaultsheading'] = 'Defaults for new activities';
$string['settings:defaultsheadingdesc'] = 'What a new AI Branched Scenario starts with when a teacher adds one. Every value here can be changed by the teacher on the activity itself, so these set the starting point rather than a rule - the two exceptions are the generation permissions above, which a teacher cannot override.';
$string['settings:defaulttheme'] = 'Default accent colour';
$string['settings:defaulttheme_help'] = 'The accent used for buttons and highlights in the player. It does not affect the green, amber and red used for results, which are fixed so a score always reads the same way.';
$string['settings:defaultlanguage'] = 'Default scenario language';
$string['settings:defaultlanguage_help'] = 'The language and regional spelling a scenario is written in. Australian and British English differ from US English in spelling, so choosing the wrong one produces text that reads as foreign to the learner.';
$string['settings:defaultshowtimeline'] = 'Show progress by default';
$string['settings:defaultshowtimeline_help'] = 'Shows the learner where they are, as "3 of 7", in the top bar.';
$string['settings:defaultshowmetrics'] = 'Show engagement, trust and tension by default';
$string['settings:defaultshowmetrics_help'] = 'The three readings in the top bar that move with each decision. They describe how the room is going, and are not the grade - turn them off to keep the learner focused on the decision rather than on watching a dial.';
$string['settings:defaultshowdebrief'] = 'Show the debrief by default';
$string['settings:defaultshowdebrief_help'] = 'The pages after the last decision: the outcome, the grade and what it was made of, every decision taken, and what to carry away. With this off the learner sees only that the scenario is complete, which suits an assessment you do not want discussed between attempts.';
$string['settings:defaultenableimages'] = 'Generate scene images by default';
$string['settings:defaultenableimages_help'] = 'An illustration for each scene, generated when the scenario is created. This costs additional AI credits.';
$string['settings:defaultenableaudio'] = 'Generate narration by default';
$string['settings:defaultenableaudio_help'] = 'Spoken narration for each screen, in three voices - a narrator and a voice for male and female characters. This costs additional AI credits.';
$string['settings:defaultmaxattempts'] = 'Default attempts allowed';
$string['settings:defaultmaxattempts_help'] = 'How many times a learner may run the scenario. A branching scenario teaches by letting a learner see where a different choice leads, so more than one attempt is usually worth allowing.';
$string['settings:defaultallowreplay'] = 'Offer Try again at the end by default';
$string['settings:defaultallowreplay_help'] = 'Shows a Try again button on the last screen. It is still bound by the attempts allowed above, so switching this off only removes the button rather than the remaining attempts.';
$string['settings:defaultgrademethod'] = 'Default grading method';
$string['settings:defaultgrademethod_help'] = 'Which attempt becomes the grade in the gradebook when a learner runs the scenario more than once.';
$string['settings:askconfidence'] = 'Let learners say they are not sure';
$string['settings:askconfidence_desc'] = 'Adds one optional tick box above the options, read at the moment of the choice and so before the learner knows how it went. It is never required, and the most useful signal it produces comes from leaving it unticked: a poor decision taken by somebody who was sure is a misconception rather than a gap, and that is the distinction the cohort report is built on.';
$string['settings:allowcues'] = 'Allow scenario sounds';
$string['settings:allowcues_desc'] = 'Short synthesised cues that mark what just happened — a soft note for a good decision, a low one when something is going wrong, and a completion sound at the end. There is no buzzer. They cost nothing to produce and are separate from narration, which is a generated voice the site pays for. Learners can still mute them.';
$string['settings:defaultcompletionfinish'] = 'Require the scenario to be finished for completion';
$string['settings:defaultcompletionfinish_help'] = 'Whether reaching the end of the scenario is required for activity completion. Where this is on, the minimum decision quality below applies as well.';
$string['settings:defaultcompletionminscore'] = 'Default minimum decision quality';
$string['settings:defaultcompletionminscore_help'] = 'The score a learner must reach for the activity to count as complete, as a percentage. At 100 only a faultless run completes the activity, so lower this if you want completion to be achievable on a good but imperfect attempt.';
$string['settings:defaultgradepass'] = 'Default grade to pass';
$string['settings:defaultgradepass_help'] = 'The grade a learner must reach to pass, out of the maximum grade. At 100 out of 100 only a faultless run passes.';
$string['settings:jobretention'] = 'Generation job retention (days)';
$string['settings:jobretention_help'] = 'How long generation job records are kept before the cleanup task removes them.';
$string['status:nocredentials'] = 'No LMS Labs credentials are configured, so AI generation is unavailable.';
$string['status:reasonignoring'] = 'This plugin is set to ignore Central Config, and its own Site ID and API key are not both filled in.';
$string['status:reasonnocentral'] = 'The Central Config plugin ({$a}) does not appear to be installed on this site.';
$string['status:reasoncentralpartial'] = 'Central Config ({$a}) has only one of the two values set; both a Site ID and an API key are needed.';
$string['status:reasoncentralempty'] = 'Central Config ({$a}) is installed but holds no Site ID and API key.';
$string['status:reasonlocalpartial'] = 'This plugin\'s own fallback fields are also only half filled in.';
$string['status:keyformat'] = 'Key format';
$string['status:usualkeyformat'] = 'The API key does not match the usual LMS Labs format. It will still be sent; if requests are rejected, check the key.';
$string['status:credentialsource'] = 'Credential source';
$string['status:fromcentral'] = 'Central Config ({$a})';
$string['status:fromlocal'] = 'This plugin\'s own settings';
$string['status:siteid'] = 'Site ID';
$string['status:apikey'] = 'API key';
$string['status:connection'] = 'Connection';
$string['status:connected'] = 'Connected';
$string['status:credits'] = 'Credits available';
$string['status:unlimitedcredits'] = 'Unlimited';
$string['status:unreachable'] = 'The LMS Labs service could not be reached.';
$string['status:unauthorised'] = 'The LMS Labs service rejected these credentials.';
$string['status:unexpected'] = 'The LMS Labs service returned an unexpected response.';
$string['task:cleanupjobs'] = 'Remove expired AI Branched Scenario generation jobs';
$string['error:generic'] = 'Something went wrong. Please try again.';
$string['error:missingidandcmid'] = 'You must supply a course module id or an instance id.';
$string['error:minscorerange'] = 'The minimum decision quality must be between 0 and 100.';
$string['error:nogeneratecap'] = 'You can edit this scenario but not generate one. Generating spends the site\'s credits, so it is a separate permission — ask whoever administers your Moodle to grant you \'Generate scenarios with AI\' on this activity.';
$string['error:nocredentials'] = 'AI generation is unavailable because no LMS Labs credentials are configured. Nothing was sent and no credits were used. Ask a site administrator to set them up.';
$string['error:insufficientcredits'] = 'There are not enough LMS Labs credits to complete this request.';
$string['error:generationconflict'] = 'This generation could not be resumed because the request had already been used for a different one. Nothing was generated and no credits were used. Please generate again.';
$string['error:serviceunauthorised'] = 'The LMS Labs service rejected the site credentials. Nothing was generated and no credits were used. Ask a site administrator to check the site ID and API key.';
$string['error:serviceratelimited'] = 'The LMS Labs service is rate limiting requests. Nothing was generated and no credits were used. Try again shortly.';
$string['error:servicetimeout'] = 'The LMS Labs service did not respond in time. No scenario was saved.';
$string['error:serviceunreadable'] = 'The LMS Labs service returned a response this plugin could not read.';
$string['error:sourcetooshort'] = 'The source content is too short. The LMS Labs service needs at least {$a} characters to work from.';
$string['error:servicefailed'] = 'The LMS Labs service could not complete the request ({$a}).';
// A FAILURE IS NOT A REFUND UNLESS THE SERVICE SAYS SO. These two say the opposite, and the
// wording must not promise a refund the service has not made.
$string['error:servicecharged'] = 'The LMS Labs service could not complete the request, and the credits for it may still have been charged ({$a}). This one needs checking against your LMS Labs account rather than simply generating again.';
$string['error:tariffmismatch'] = 'The LMS Labs service refused this request because the price it charges does not match the price this activity quoted. Nothing was generated and no credits were used. This is a configuration fault between the plugin and the service — generating again will not fix it, and your site administrator should raise it with LMS Labs.';
$string['error:nodetail'] = 'no reason given';
$string['error:validationdetail'] = 'What was wrong: {$a}';
$string['error:servicenoscenario'] = 'The LMS Labs service did not return a scenario.';
$string['error:servicenoimage'] = 'The LMS Labs service did not return a usable image.';
$string['error:servicenoaudio'] = 'The LMS Labs service did not return usable audio.';
$string['error:requestencode'] = 'The request could not be prepared.';
$string['error:generationfailed'] = 'Generation failed. Nothing was saved, so you can try again.';
$string['error:generationinflight'] = 'A scenario is already being generated for this activity.';
$string['error:invalidgeneratedscenario'] = 'The generated scenario did not pass validation, so it was rejected. Try generating again.';
$string['error:nosourcecontent'] = 'Add some source content or a brief before generating.';
$string['error:quotaexceeded'] = 'This would take you past your daily AI allowance for this site: {$a->quota} credits a day, of which you have used {$a->spent}. The allowance resets 24 hours after each request. A site administrator can raise it under Site administration, Plugins, Activity modules, AI Branched Scenario.';
$string['error:unknownfield'] = 'That field cannot be suggested.';
$string['error:unknownjob'] = 'That generation job does not belong to this activity.';
$string['error:noworkingcopy'] = 'There is no draft scenario to edit yet.';
$string['error:nothingtopublish'] = 'There is no draft scenario to publish yet.';
$string['error:notpublished'] = 'This scenario has not been published.';
$string['error:revisionunreadable'] = 'The published revision could not be read.';
$string['error:scenariotoolarge'] = 'The scenario is larger than this plugin will store.';
$string['error:notjson'] = 'What you pasted is not valid JSON.';
$string['error:notjsonline'] = 'What you pasted is not valid JSON. Line {$a->line} looks wrong: {$a->text} — a quotation mark inside a value has to be written as \\" or the line ends early.';
$string['error:invalidscenario'] = 'What you pasted cannot be used as a scenario: {$a}';
$string['error:missingtitle'] = 'The scenario has no title.';
$string['error:missinghook'] = 'The scenario has no opening situation.';
$string['error:nonodes'] = 'The scenario has no nodes.';
$string['error:toomanynodes'] = 'The scenario has more than {$a} nodes.';
$string['error:badnode'] = 'Node {$a} is not a valid node.';
$string['error:badnodeid'] = 'Node {$a} has an unusable identifier.';
$string['error:reservednodeid'] = 'The node id "{$a}" is a name the plugin already uses for one of its own pictures or clips, so a scene would silently take another screen\'s. Rename the node.';
$string['error:duplicatenodeid'] = 'More than one node uses the identifier {$a}.';
$string['error:nodenosituation'] = 'Node {$a} has no situation text.';
$string['error:nodenochoices'] = 'Node {$a} offers no way forward.';
$string['error:beatnonext'] = 'Node {$a} does not say what follows it.';
$string['error:choicecount'] = 'Node {$a->node} must offer exactly {$a->count} options.';
$string['error:decisioncount'] = 'A scenario contains exactly {$a->expected} decisions. This one has {$a->found}.';
$string['error:choicenotext'] = 'A choice on node {$a} has no text.';
$string['error:choicenonext'] = 'Choice {$a->letter} on node {$a->node} does not lead anywhere.';
$string['error:choicenoconsequence'] = 'Choice {$a->letter} on node {$a->node} does not say what happened as a result.';
$string['error:danglingtarget'] = 'Node {$a->node} points at {$a->target}, which does not exist.';
$string['error:unreachablenode'] = 'Node {$a} cannot be reached from the start of the scenario.';
$string['error:nostartnode'] = 'The scenario has no start node.';
$string['error:badstartnode'] = 'The declared start node does not exist.';
$string['error:nooutcomenode'] = 'The scenario has no ending.';
$string['error:nooutcomereachable'] = 'No ending can be reached from the start of the scenario.';
$string['error:cyclicgraph'] = 'The scenario loops back on itself, so an attempt could never finish.';
$string['error:noattemptsleft'] = 'You have used all your attempts at this scenario.';
$string['error:replaynotallowed'] = 'Replaying this scenario is not allowed.';
$string['error:attemptnotfound'] = 'That attempt could not be found.';
$string['error:attemptnotopen'] = 'That attempt is no longer open.';
$string['error:attemptnotfinished'] = 'That attempt has not finished yet.';
$string['error:sequenceconflict'] = 'That decision is out of step with the attempt. Reload the page to continue.';
$string['error:wrongnode'] = 'That decision does not belong to the current point in the scenario.';
$string['error:unknownnode'] = 'That part of the scenario could not be found.';
$string['error:unknownchoice'] = 'That choice is not available here.';
$string['error:mediatype'] = 'The generated media was of a type this plugin does not accept.';
$string['error:mediatoolarge'] = 'The generated media was too large to store.';
$string['error:mediasignature'] = 'The generated media did not match its declared type.';
$string['error:mediakey'] = 'The generated media had an unusable name.';
$string['privacy:metadata:aibranchedscenario_attempts'] = 'Learner attempts at a branching scenario.';
$string['privacy:metadata:aibranchedscenario_attempts:scenarioid'] = 'The scenario the attempt belongs to';
$string['privacy:metadata:aibranchedscenario_attempts:revisionid'] = 'The published revision the attempt was taken against';
$string['privacy:metadata:aibranchedscenario_attempts:userid'] = 'The user who made the attempt';
$string['privacy:metadata:aibranchedscenario_attempts:attemptno'] = 'The attempt number for this user';
$string['privacy:metadata:aibranchedscenario_attempts:tier'] = 'Which of the activity\'s three scenarios the attempt was at.';
$string['privacy:metadata:aibranchedscenario_attempts:status'] = 'Whether the attempt is in progress, finished or abandoned';
$string['privacy:metadata:aibranchedscenario_attempts:currentnode'] = 'The point in the scenario the learner has reached';
$string['privacy:metadata:aibranchedscenario_attempts:statejson'] = 'The saved state of the attempt, including which nodes were visited';
$string['privacy:metadata:aibranchedscenario_attempts:engagement'] = 'The engagement level at the current point';
$string['privacy:metadata:aibranchedscenario_attempts:trust'] = 'The trust level at the current point';
$string['privacy:metadata:aibranchedscenario_attempts:tension'] = 'The tension level at the current point';
$string['privacy:metadata:aibranchedscenario_attempts:presence'] = 'The accumulated presence score';
$string['privacy:metadata:aibranchedscenario_attempts:adaptability'] = 'The accumulated adaptability score';
$string['privacy:metadata:aibranchedscenario_attempts:empathy'] = 'The accumulated empathy score';
$string['privacy:metadata:aibranchedscenario_attempts:clarity'] = 'The accumulated clarity score';
$string['privacy:metadata:aibranchedscenario_attempts:score'] = 'The decision quality percentage for the attempt';
$string['privacy:metadata:aibranchedscenario_attempts:outcome'] = 'The outcome band the attempt reached';
$string['privacy:metadata:aibranchedscenario_attempts:timestarted'] = 'When the attempt was started';
$string['privacy:metadata:aibranchedscenario_attempts:timemodified'] = 'When the attempt was last changed';
$string['privacy:metadata:aibranchedscenario_attempts:timefinished'] = 'When the attempt was finished';
$string['privacy:metadata:aibranchedscenario_events'] = 'The individual decisions a learner made during an attempt.';
$string['privacy:metadata:aibranchedscenario_events:attemptid'] = 'The attempt the decision belongs to';
$string['privacy:metadata:aibranchedscenario_events:seq'] = 'The position of the decision in the attempt';
$string['privacy:metadata:aibranchedscenario_events:nodeid'] = 'The decision point the learner was on';
$string['privacy:metadata:aibranchedscenario_events:choiceid'] = 'The choice the learner made';
$string['privacy:metadata:aibranchedscenario_events:nextnodeid'] = 'Where that choice led';
$string['privacy:metadata:aibranchedscenario_events:signaltype'] = 'Whether the choice read as positive, neutral or negative';
$string['privacy:metadata:aibranchedscenario_events:engagement'] = 'The engagement level after the decision';
$string['privacy:metadata:aibranchedscenario_events:trust'] = 'The trust level after the decision';
$string['privacy:metadata:aibranchedscenario_events:tension'] = 'The tension level after the decision';
$string['privacy:metadata:aibranchedscenario_events:unsure'] = 'Whether the learner said they were not sure about this decision before they saw its outcome.';
$string['privacy:metadata:aibranchedscenario_events:timecreated'] = 'When the decision was made';
$string['privacy:metadata:aibranchedscenario_jobs'] = 'Records of AI generation requests made by teachers.';
$string['privacy:metadata:aibranchedscenario_jobs:scenarioid'] = 'The scenario the generation request belongs to';
$string['privacy:metadata:aibranchedscenario_jobs:userid'] = 'The user who requested generation';
$string['privacy:metadata:aibranchedscenario_jobs:status'] = 'Whether the request is queued, running, ready or failed';
$string['privacy:metadata:aibranchedscenario_jobs:jobtype'] = 'Which generation operation was requested';
$string['privacy:metadata:aibranchedscenario_jobs:requestjson'] = 'A non-identifying summary of what was requested';
$string['privacy:metadata:aibranchedscenario_jobs:payloadjson'] = 'The exact request sent to the generation service for this job, kept so a retry replays it rather than rebuilding it';
$string['privacy:metadata:aibranchedscenario_jobs:resultjson'] = 'A non-identifying summary of what was produced';
$string['privacy:metadata:aibranchedscenario_jobs:errormsg'] = 'The plugin error identifier when a request failed';
$string['privacy:metadata:aibranchedscenario_jobs:modelused'] = 'The model name reported by the service';
$string['privacy:metadata:aibranchedscenario_jobs:durationms'] = 'How long the request took';
$string['privacy:metadata:aibranchedscenario_jobs:timecreated'] = 'When the request was made';
$string['privacy:metadata:aibranchedscenario_jobs:timemodified'] = 'When the request last changed state';
$string['privacy:metadata:lmslabs'] = 'Scenario generation is performed by the LMS Labs service, which passes the request to a contracted AI provider. Learner attempt data is never sent.';
$string['privacy:metadata:lmslabs:sourcecontent'] = 'The source content and authoring inputs a teacher supplied for the scenario.';
$string['privacy:metadata:lmslabs:siteid'] = 'The site identifier used to authenticate the request and account for credit use.';
$string['privacy:revisionspath'] = 'Scenario versions you published';
$string['privacy:metadata:aibranchedscenario_revisions'] = 'Published versions of a scenario record who published them, so an author can be credited and a version traced. The scenario text itself belongs to the activity rather than to the person who published it.';
$string['privacy:metadata:aibranchedscenario_revisions:createdby'] = 'The user who published this version. Cleared when that user is erased; the version itself is kept so learners part-way through it are not disrupted.';
$string['privacy:metadata:aibranchedscenario_revisions:timecreated'] = 'When this version was published.';
$string['privacy:attemptpath'] = 'Attempt {$a}';
$string['privacy:jobspath'] = 'AI generation requests';
