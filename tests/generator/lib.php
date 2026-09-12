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
 * Test data generator for mod_aibranchedscenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_aibranchedscenario\local\scenario_manager;

/**
 * Activity module generator.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aibranchedscenario_generator extends testing_module_generator {
    /**
     * Create a new activity instance, filling in the settings a scenario needs.
     *
     * @param array|stdClass|null $record Instance settings.
     * @param array|null $options Generator options passed through to the parent.
     * @return stdClass The created activity instance record.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        $defaults = [
            'theme'              => 'indigo',
            'scenariolang'       => 'en-AU',
            'status'             => scenario_manager::STATUS_DRAFT,
            'revision'           => 0,
            'maxattempts'        => 0,
            'allowreplay'        => 1,
            'showdebrief'        => 1,
            'showtimeline'       => 1,
            'showmetrics'        => 1,
            'enableaudio'        => 0,
            'enableimages'       => 0,
            'grademethod'        => 'last',
            'grade'              => 100,
            'completionfinish'   => 0,
            'completionminscore' => 0,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * A valid branch-and-bottleneck scenario definition.
     *
     * The graph is: a start decision that forks into two decision nodes, both of
     * which reconverge on a bottleneck, then one further decision that resolves to
     * an outcome by score. Choice A is always the best available answer (+2 on every
     * skill) and choice B always the worst (-2 on every skill), so a run of A choices
     * scores 100 and a run of B choices scores 0.
     *
     * @return array Definition ready for the validator.
     */
    public function create_sample_definition(): array {
        return [
            'title'      => 'The vibrating machine',
            'subtitle'   => 'A late shift, a fault nobody logged',
            'role'       => 'You are the afternoon shift supervisor on the packing line.',
            'setting'    => 'Packing line, main floor',
            'language'   => 'en-AU',
            'tone'       => 'direct',
            'complexity' => 'intermediate',
            'facilitator' => [
                'id'   => 'lead',
                'name' => 'Dana Okafor',
                'role' => 'Line lead',
            ],
            'characters' => [
                ['id' => 'operator', 'name' => 'Sam Ng', 'role' => 'Machine operator'],
            ],
            // A principle needs an example and a pitfall, and has since v1.20.0: a learner
            // remembers the words they can use, and needs warning about the thing that
            // sounds reasonable and is not. This fixture predated the rule and every test
            // built on it was failing validation.
            'principles' => [
                [
                    'id'      => 'speakup',
                    'title'   => 'Report faults before the next shift',
                    'summary' => 'Anything that can hurt someone is logged the moment it is seen.',
                    'example' => '"I am logging the vibration now so the night shift sees it '
                        . 'before they start."',
                    'pitfall' => 'Telling the next supervisor in the corridor and assuming that '
                        . 'counts as a record.',
                ],
            ],
            'openingmetrics' => ['engagement' => 50, 'trust' => 50, 'tension' => 30],
            'hook'       => "It is 2:40 pm and the number three packer has been shaking for an hour.\n\n"
                . 'Nobody has written it up.',
            'startnode'  => 'start',
            'nodes'      => [
                self::sample_decision(
                    'start',
                    1,
                    'Sam waves you over to the shaking packer.',
                    'pathone',
                    'pathtwo'
                ),
                self::sample_decision(
                    'pathone',
                    2,
                    'You are standing at the guard rail with Sam.',
                    'bottleneck',
                    'bottleneck'
                ),
                self::sample_decision(
                    'pathtwo',
                    2,
                    'You are back at the supervisor desk with the log open.',
                    'bottleneck',
                    'bottleneck'
                ),
                array_merge(
                    self::sample_decision(
                        'bottleneck',
                        3,
                        'The line lead asks you directly what you decided.',
                        'final',
                        'final'
                    ),
                    ['bottleneck' => true, 'title' => 'The line lead asks']
                ),
                self::sample_decision(
                    'final',
                    4,
                    'The night shift supervisor arrives for handover.',
                    '__auto__',
                    '__auto__'
                ),
                [
                    'id'      => 'outcomestrong',
                    'type'    => 'outcome',
                    'stage'   => 5,
                    'title'   => 'The fault is fixed before anyone is hurt',
                    'outcome' => 'strong',
                    'situation' => 'Maintenance isolates the packer within the hour.',
                    'summary' => 'You logged the fault, isolated the machine and handed over clearly.',
                ],
                [
                    'id'      => 'outcomerisk',
                    'type'    => 'outcome',
                    'stage'   => 5,
                    'title'   => 'The fault runs into the night shift',
                    'outcome' => 'highrisk',
                    'situation' => 'The packer is still running when the night shift starts.',
                    'summary' => 'Nothing was written down, and the next crew inherited the risk.',
                ],
            ],
            'debrief'    => [
                'whatmattered'      => ['Naming the hazard out loud changed what the team did next.'],
                'criticaldecisions' => ['Whether you stopped the line before handover.'],
                'practice'          => ['Log a fault the moment you see it.'],
                'sourceconnection'  => 'The plant safety procedure requires faults to be logged on sight.',
            ],
            'takeaways'  => [
                [
                    'heading' => 'Write it down',
                    'body'    => 'A verbal warning does not survive a shift change.',
                ],
            ],
        ];
    }

    /**
     * Save and publish the sample definition against an activity instance.
     *
     * @param stdClass $instance Activity instance record, updated in place.
     * @param int $userid The publishing user.
     * @return stdClass The new revision record.
     */
    public function publish_sample(stdClass $instance, int $userid): stdClass {
        scenario_manager::save_definition($instance, $this->create_sample_definition());
        return scenario_manager::publish($instance, $userid);
    }

    /**
     * Build a two-choice decision node where A is always the best answer.
     *
     * @param string $id Node identifier.
     * @param int $stage Narrative stage.
     * @param string $situation Situation text.
     * @param string $atarget Target of the good choice.
     * @param string $btarget Target of the poor choice.
     * @return array
     */
    protected static function sample_decision(
        string $id,
        int $stage,
        string $situation,
        string $atarget,
        string $btarget
    ): array {
        return [
            'id'        => $id,
            'type'      => 'decision',
            'stage'     => $stage,
            'title'     => ucfirst(str_replace('_', ' ', $id)),
            'situation' => $situation,
            'challenge' => 'What do you do?',
            'choices'   => [
                [
                    'id'          => $id . '_a',
                    'text'        => 'Stop the line and log the fault now.',
                    'signal'      => 'positive',
                    'consequence' => 'The line stops. Sam looks relieved.',
                    'feedback'    => 'Acting on a hazard the moment you see it is the whole point.',
                    'principleid' => 'speakup',
                    'tags'        => ['escalates-appropriately'],
                    'effects'     => ['engagement' => 40, 'trust' => 40, 'tension' => -40],
                    'skills'      => ['presence' => 2, 'adaptability' => 2, 'empathy' => 2, 'clarity' => 2],
                    'next'        => $atarget,
                ],
                [
                    'id'          => $id . '_b',
                    'text'        => 'Leave it for the next shift to deal with.',
                    'signal'      => 'negative',
                    'consequence' => 'The packer keeps shaking. Sam says nothing else.',
                    'feedback'    => 'Deferring a known hazard moves the risk, it does not remove it.',
                    'principleid' => 'speakup',
                    'tags'        => ['undermines-trust'],
                    'effects'     => ['engagement' => -40, 'trust' => -40, 'tension' => 40],
                    'skills'      => ['presence' => -2, 'adaptability' => -2, 'empathy' => -2, 'clarity' => -2],
                    'next'        => $btarget,
                ],
            ],
        ];
    }
}
