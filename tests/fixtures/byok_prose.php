<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A well-written prose answer for any scenario prose schema, as a capable model would return it.
 *
 * Used by the Bring your own AI tests to stand in for the site's AI provider.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Write prose that fills every slot of a prose schema.
 *
 * @param array $schema The prose JSON schema scenario_engine sends.
 * @return array
 */
function mod_aibranchedscenario_byok_prose(array $schema): array {
    $speakers =
        array_values(array_filter($schema['properties']['nodes']['properties']['n1']['anyOf'][0]['properties']['speaker']['enum']));
    $facilitator = $speakers[0];
    $other = $speakers[1] ?? $speakers[0];
    $profile = function (string $role, string $gender, string $trait, string $look): array {
        return ['role' => $role, 'gender' => $gender, 'trait' => $trait, 'appearance' => $look];
    };
    $people = [];
    for ($i = 0; $i < $schema['properties']['characters']['minItems']; $i++) {
        $people[] = $profile(
            'Crusher operator finishing night shift',
            'female',
            'Goes quiet and clipped when tired, then over-explains once she feels heard.',
            'Medium build, dark hair tied back, hi-vis orange shirt, dusty steel-capped boots, radio on her belt.'
        );
    }
    $stage = 0;
    $nodes = [];
    foreach ($schema['properties']['nodes']['properties'] as $id => $node) {
        $stage++;
        $props = $node['anyOf'][0]['properties'];
        $choiceids = $props['choices']['required'];
        $isdecision = count($choiceids) === 3;
        $text = [
            'title' => "Handover at the primary crusher, part {$stage}",
            'content' =>
                "It is 5:40 am at the Kestrel Ridge primary crusher. {$other} has the night log open on the tablet, and "
                . "the isolation board shows two locks still on conveyor CV-0{$stage}. "
                . "The day crew is already waiting at the gate.",
            'facilitatorSpeech' => "Two locks are still on CV-0{$stage}, and the day crew wants to start in ten minutes.",
            'speaker' => $facilitator,
            'imageAlt' =>
                "{$other} stands beside the isolation board at the crusher, holding a tablet, while the day crew waits.",
            'imagePrompt' =>
                "A wide shot of a mine crusher control room at dawn with a long window onto a conveyor gantry. {$other} "
                . "stands at a wall-mounted isolation board holding a tablet, two red personal locks still hanging on the "
                . "conveyor isolator. Three workers in orange hi-vis wait by the doorway with hard hats under their arms. "
                . "Coffee cups and a paper permit book sit on the bench beside a flickering radio. The light outside is pale "
                . "orange and dust hangs in the air above the belt.",
            'challenge' => $isdecision ? 'What do you say to the night operator before the day crew starts?' : '',
            'crisisVariant' => null,
            'summary' => $isdecision ? '' : 'The handover sets how safely the day shift starts.',
            'choices' => [],
        ];
        if (($props['crisisVariant']['type'] ?? '') === 'object') {
            $text['crisisVariant'] = ['situation' => 'The control room radio crackles: a fitter reports the belt jolted ' .
                'while a lock '
                . 'was still on.', 'facilitatorSpeech' => 'The belt just moved with a lock on it.',
                'challenge' => 'What is the first thing you do now?'];
        }
        $labels = ['Walk the isolation board with her and check each lock against the log',
            'Ask her to sign the handover sheet and brief the day crew yourself',
            'Tell the day crew to start on the other conveyors while you sort it out later'];
        foreach ($choiceids as $index => $choiceid) {
            $text['choices'][$choiceid] = [
                'label' => $isdecision ? $labels[$index] . " ({$stage})" : 'Continue to the next part of the shift',
                'feedback' => $isdecision ?
                    "{$other} slows down when someone checks the board beside her, and the two locks get "
                    . "an owner within a minute. Step {$index}." : 'The shift moves on with the handover settled.',
                'consequence' => $isdecision ?
                    "The day crew sees who holds each lock before anyone touches the belt. Path {$index}."
                    : 'The crew moves to the next job.',
                'outcomeNote' => "Taking this option at stage {$stage} leaves the crew with a clearer picture of who owns each "
                    . "isolation, and {$other} with a different view of how handovers work here. Variant {$index}.",
            ];
        }
        // An outcome has no choices: an empty JSON object, as the schema asks.
        $text['choices'] = (object) $text['choices'];
        $nodes[$id] = $text;
    }
    return [
        'title' => 'Handover at the primary crusher',
        'subtitle' => 'A night-to-day handover with two locks unaccounted for',
        'hook' => 'Ten minutes before the day crew starts, the isolation board does not match the log.',
        'introduction' => 'You are the shift supervisor at the Kestrel Ridge primary crusher. Your job is to hand the plant ' .
            'over '
            . 'safely from the night crew to the day crew.',
        'facilitator' => $profile(
            'Senior shift supervisor',
            'male',
            'Stays calm and asks short questions when the pressure ' .
            'rises.',
            'Tall, grey beard, navy work shirt with reflective tape, white hard hat with a supervisor stripe.'
        ),
        'characters' => $people,
        'principles' => [['title' => 'Check the board, not the log', 'summary' => 'A handover is only complete when every '
            . 'lock on the board has a named owner.', 'example' => 'Walk the board together and read each tag aloud.',
            'pitfall' => 'Signing the log first and promising to sort the locks out later.']],
        'debrief' => ['whatMattered' => ['Who owned each lock before the belt restarted.'],
            'criticalDecisions' => ['Whether you checked the board in person.'],
            'practice' => ['Read every tag aloud at your next handover.'],
            'sourceConnection' => 'The site isolation procedure requires every personal lock to be accounted for at handover.'],
        'takeaways' => [['heading' => 'Locks have owners', 'body' => 'Match every lock to a person before the shift changes.']],
        'nodes' => $nodes,
    ];
}
