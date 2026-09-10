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
 * What each wizard field is actually asking for.
 *
 * The suggest route was being sent the name of the field and the teacher's source
 * content, and nothing else. A name alone does not say what good looks like, so a
 * request to suggest an opening situation against a page about active listening came
 * back as "Enhancing workplace communication through active listening" — a course
 * objective, correctly summarising the source and useless in the field it landed in.
 *
 * Each field below carries three things: what the field is, the form the answer must
 * take, and one worked example in that form. The example is the part that does the
 * work; a rule can be read past, a sample of the target shape cannot.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_brief {
    /** @var int Ceiling for any one brief, so the payload stays inside the route limits. */
    const MAX_BRIEF = 1200;

    /**
     * The instruction sent with every suggestion, whatever the field.
     *
     * @return string
     */
    public static function common(): string {
        return 'Write only the value for this one field, with no preamble, no label, no '
            . 'quotation marks and no explanation. Use the source content as subject '
            . 'matter to draw on, never as text to summarise or restate. Do not define '
            . 'the topic, list its benefits, or describe what the learner will learn: '
            . 'this is a dramatised workplace scenario, not a course description. The '
            . 'example in the field specification shows the shape of a good answer and '
            . 'nothing else: never reuse its people, place, sector or wording. Take '
            . 'those from the source content and from the values already set on the '
            . 'other fields, and stay consistent with them, so every field describes one '
            . 'workplace on one occasion rather than several. Match the language and '
            . 'spelling of the source content.';
    }

    /**
     * The specification for one field.
     *
     * @param string $field Field name as the wizard knows it.
     * @return string Empty when the field has no brief.
     */
    public static function for_field(string $field): string {
        $briefs = self::briefs();
        if (!isset($briefs[$field])) {
            return '';
        }
        return \core_text::substr($briefs[$field], 0, self::MAX_BRIEF);
    }

    /**
     * Every field specification.
     *
     * @return array Field name to specification.
     */
    protected static function briefs(): array {
        return [
            'title' =>
                'A title for the scenario. Name the moment the learner is walking into, '
                . 'not the topic being taught. Six words or fewer, no colon, no subtitle, '
                . 'no wording like "Module", "Training" or "Introduction to", no question '
                . 'mark, and no gerund topic label such as "Managing difficult '
                . 'conversations". No full stop at the end. '
                . 'Example: The handover nobody finished',
            'audience' =>
                'Who this scenario is for, as a job role and the experience level that '
                . 'changes how they would read it. One phrase, under fifteen words. '
                . 'Example: Newly promoted shift supervisors in their first six months.',
            'industry' =>
                'This field is a picker, not free text. Answer with exactly one key from '
                . 'this list and nothing else, spelled exactly as shown: '
                . implode(', ', schema::industries()) . '. Choose the sector the work in '
                . 'the scenario happens in, not the topic being taught. Use "other" only '
                . 'when no key is close. Example answer: agedcare',
            'setting' =>
                'This field is a picker, not free text. Answer with exactly one key from '
                . 'this list and nothing else, spelled exactly as shown: '
                . implode(', ', schema::settings_list()) . '. Choose the physical place '
                . 'the opening scene happens in, the place a camera would be pointed at '
                . 'rather than an abstraction such as "the workplace". Use "other" only '
                . 'when no key is close. Example answer: hospitalward',
            'atmosphere' =>
                'This field is a picker, not free text. Answer with exactly one key from '
                . 'this list and nothing else, spelled exactly as shown: '
                . implode(', ', schema::atmospheres()) . '. Judge how the room feels as '
                . 'the scenario opens by the pressure in the air, not by how serious the '
                . 'subject matter is. Example answer: highpressure',
            'openingsituation' =>
                'The opening situation: where the learner is standing and what is '
                . 'happening to them as the scenario begins. Two or three sentences, '
                . 'second person, present tense. Put the learner in the moment, name who '
                . 'else is there, and end on the pressure that forces them to act. It '
                . 'must read as the first shot of a story, not as an explanation of a '
                . 'subject. Do not begin with "Imagine", "In this scenario", "You will" '
                . 'or "This scenario explores". Do not ask a question, do not offer '
                . 'options, and do not name the skill being practised. '
                . 'Example: It is ten minutes past the end of your shift and '
                . 'Priya is still waiting for the handover. She has asked you twice about '
                . 'the bed four fluid chart and both times you have answered a different '
                . 'question. She stops writing and looks up at you.',
            'centralproblem' =>
                'The central problem the learner must deal with, stated as the difficulty '
                . 'in front of them rather than the skill being assessed. One or two '
                . 'sentences. Example: The information Priya needs is in your head, the '
                . 'shift is over, and she is too tired to ask a third time.',
            'whyhard' =>
                'This field is a picker, not free text. Answer with one to three keys '
                . 'from this list, separated by commas, and nothing else, each spelled '
                . 'exactly as shown: ' . implode(', ', schema::whyhard()) . '. Choose the '
                . 'pulls that make a sensible person get this wrong. Do not explain the '
                . 'choice and do not add any other words. '
                . 'Example answer: timepressure, conflictingpriorities',
            'stakes' =>
                'This field is a picker, not free text. Answer with one to three keys '
                . 'from this list, separated by commas, and nothing else, each spelled '
                . 'exactly as shown: ' . implode(', ', schema::stakes()) . '. Choose what '
                . 'is actually at risk if the learner handles this badly, not what the '
                . 'topic is about. Do not explain the choice and do not add any other '
                . 'words. Example answer: teamtrust, customeroutcome',
            'participantrole' =>
                'Who the learner is playing, written to them in second person, starting '
                . '"You are". One sentence naming the role and what they are responsible '
                . 'for. Example: You are the registered nurse handing over bay four at '
                . 'the end of a night shift.',
            'characterfull' =>
                'One other person in the scenario. Answer as exactly four parts on one '
                . 'line separated by vertical bars, in this order and nothing else: '
                . 'given name | their role | one trait shown as behaviour under pressure '
                . '| how they look, for illustration only. They must have their own '
                . 'reason for being difficult rather than existing to be corrected, and '
                . 'they are not the person the learner is playing. Give no age and no '
                . 'ethnicity, do not name a real person, and do not use a vertical bar '
                . 'inside any of the four parts. '
                . 'Example: Priya | Incoming nurse taking on four bays | Asks the same '
                . 'question a second time, more quietly | Tall, dark hair tied back, '
                . 'navy scrubs and a lanyard.',
            'charactertrait' =>
                'One trait that shapes how this person behaves under pressure, as '
                . 'behaviour rather than a label. Under twelve words. '
                . 'Example: Asks the same question a second time, more quietly.',
            'characterappearance' =>
                'How this person looks, for scene illustration only: build, hair, and '
                . 'what they are wearing for this setting. One sentence, no names of real '
                . 'people, no age, no ethnicity. '
                . 'Example: Tall, dark hair tied back, navy scrubs and a lanyard.',
            'principles' =>
                'The handful of principles good practice in this scenario turns on, drawn '
                . 'from the source content. Three to five short lines, one principle each, '
                . 'each phrased as something a person does. '
                . 'Example: Say back what you heard before you answer it.',
            'imageprompt' =>
                'One extra instruction for the illustrator, added on top of a brief that '
                . 'already fixes the location, the people and the visual style, so do not '
                . 'restate any of those and do not ask for a lens, a palette or a mood. '
                . 'Name only the thing in the frame that the scene prose would not make '
                . 'obvious. One sentence, under sixty words, on one line. No text, '
                . 'lettering, logos, identifiable real people or visible injury. '
                . 'Example: The trolley is still loaded and blocking the bay entrance.',
        ];
    }
}
