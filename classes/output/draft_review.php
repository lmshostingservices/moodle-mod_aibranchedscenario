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

namespace mod_aibranchedscenario\output;

use context_module;
use mod_aibranchedscenario\external\helper;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use stdClass;

/**
 * The working copy, laid out so a teacher can read all of it before publishing.
 *
 * "Preview as a learner" led to a page saying nothing was published, because the player
 * can only render a published revision. The only way to see what the AI had written was
 * to publish it to every enrolled learner and then look, which inverts the whole point
 * of having a draft.
 *
 * This is deliberately not a play-through. A teacher checking AI output wants to read
 * every branch, including the ones a single run would never reach, and to see the
 * consequence and the feedback attached to each choice rather than discovering them one
 * at a time. Everything the learner would meet is here, in one page, in order.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_review implements \renderable, \templatable {
    /** @var stdClass Activity instance. */
    protected $scenario;

    /** @var stdClass Course module record. */
    protected $cm;

    /** @var context_module Module context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance.
     * @param stdClass $cm Course module record.
     * @param context_module $context Module context.
     */
    public function __construct(stdClass $scenario, $cm, context_module $context) {
        $this->scenario = $scenario;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Export the data used by the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $definition = scenario_manager::get_working_definition($this->scenario);
        if (!is_array($definition)) {
            return [
                'cmid'     => (int)$this->cm->id,
                'hasdraft' => false,
                'editurl'  => (new \moodle_url(
                    '/mod/aibranchedscenario/edit.php',
                    ['id' => $this->cm->id]
                ))->out(false),
            ];
        }

        $media = new media_manager($this->context);
        $images = $media->urls_for_working(media_manager::AREA_SCENE);

        $titles = [];
        foreach ($definition['nodes'] as $node) {
            $titles[$node['id']] = $node['title'];
        }

        $nodes = [];
        foreach ($definition['nodes'] as $node) {
            $choices = [];
            foreach ($node['choices'] as $choice) {
                $target = $choice['next'] === schema::auto_target()
                    ? get_string('review:autotarget', 'mod_aibranchedscenario')
                    : ($titles[$choice['next']] ?? $choice['next']);
                $skills = [];
                foreach (schema::skills() as $skill) {
                    $value = (int)($choice['skills'][$skill] ?? 0);
                    if ($value !== 0) {
                        $skills[] = get_string('skill:' . $skill, 'mod_aibranchedscenario')
                            . ' ' . ($value > 0 ? '+' : '') . $value;
                    }
                }
                $choices[] = [
                    'letter'          => $choice['letter'],
                    'text'            => $choice['text'],
                    'signal'          => $choice['signal'],
                    'signalclass'     => 'aibs-signal-' . $choice['signal'],
                    'signallabel'     => get_string('signal:' . $choice['signal'], 'mod_aibranchedscenario'),
                    'consequenceparas' => helper::paragraph_list($choice['consequence']),
                    'feedbackparas'   => helper::paragraph_list($choice['feedback']),
                    'hasfeedback'     => trim($choice['feedback']) !== '',
                    'target'          => $target,
                    'skills'          => implode(', ', $skills),
                    'hasskills'       => $skills !== [],
                ];
            }

            $nodes[] = [
                'id'           => $node['id'],
                'title'        => $node['title'],
                'stage'        => (int)$node['stage'],
                'isoutcome'    => $node['type'] === 'outcome',
                'outcomelabel' => $node['type'] === 'outcome'
                    ? get_string('outcome:' . $node['outcome'], 'mod_aibranchedscenario') : '',
                'situationparas' => helper::paragraph_list($node['situation']),
                'speech'       => $node['facilitatorspeech'],
                'hasspeech'    => trim($node['facilitatorspeech']) !== '',
                'challenge'    => $node['challenge'],
                'haschallenge' => trim($node['challenge']) !== '',
                'summaryparas' => helper::paragraph_list($node['summary'] ?? ''),
                'imageurl'     => $images[$node['id']] ?? '',
                'hasimage'     => isset($images[$node['id']]),
                'imagealt'     => $node['imagealt'] ?? '',
                'hascrisis'    => !empty($node['crisisvariant']['situation']),
                'crisisparas'  => helper::paragraph_list($node['crisisvariant']['situation'] ?? ''),
                'choices'      => $choices,
                'haschoices'   => $choices !== [],
            ];
        }

        return [
            'cmid'        => (int)$this->cm->id,
            'hasdraft'    => true,
            'themeclass'  => 'aibs-theme-' . $this->scenario->theme,
            'title'       => $definition['title'],
            'subtitle'    => $definition['subtitle'],
            'role'        => $definition['role'],
            'hookparas'   => helper::paragraph_list($definition['hook']),
            'nodes'       => $nodes,
            'nodecount'   => count($nodes),
            'imagecount'  => count($images),
            'principles'  => array_values($definition['principles']),
            'hasprinciples' => !empty($definition['principles']),
            'editurl'     => (new \moodle_url(
                '/mod/aibranchedscenario/edit.php',
                ['id' => $this->cm->id]
            ))->out(false),
        ];
    }
}
