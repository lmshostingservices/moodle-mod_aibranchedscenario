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
use mod_aibranchedscenario\local\ai\credentials;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\source_normaliser;
use stdClass;

/**
 * Prepares the data the authoring wizard template and its AMD module need.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard implements \renderable, \templatable {
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
     * Build a list of selectable options.
     *
     * @param string[] $values Option keys.
     * @param string $prefix Language string prefix.
     * @param string|string[] $selected Currently selected key or keys.
     * @return array
     */
    protected function options(array $values, string $prefix, $selected): array {
        $selected = is_array($selected) ? $selected : [$selected];
        $out = [];
        foreach ($values as $value) {
            $out[] = [
                'key'      => $value,
                'label'    => get_string($prefix . ':' . $value, 'mod_aibranchedscenario'),
                'selected' => in_array($value, $selected, true),
            ];
        }
        return $out;
    }

    /**
     * Where a choice may lead, as a list a teacher can choose from.
     *
     * A choice's target was shown as a raw node identifier and could not be changed at
     * all, which made the branching of a branching-scenario authoring tool read only.
     * Only later stages and endings are offered, because the validator rejects a graph
     * that loops back.
     *
     * @param array $definition The working copy.
     * @param array $node The node the choice belongs to.
     * @param string $selected The current target.
     * @return array
     */
    protected function targets(array $definition, array $node, string $selected): array {
        $out = [[
            'key'      => schema::auto_target(),
            'label'    => get_string('review:autotarget', 'mod_aibranchedscenario'),
            'selected' => $selected === schema::auto_target(),
        ]];
        foreach ($definition['nodes'] as $candidate) {
            $later = (int)$candidate['stage'] > (int)$node['stage'];
            if (!$later && $candidate['type'] !== 'outcome') {
                continue;
            }
            $out[] = [
                'key'      => $candidate['id'],
                'label'    => $candidate['title'] !== '' ? $candidate['title'] : $candidate['id'],
                'selected' => $selected === $candidate['id'],
            ];
        }
        return $out;
    }

    /**
     * Export the data used by the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $source = scenario_manager::get_source($this->scenario);
        $source = $source ? source_normaliser::normalise($source) : source_normaliser::blank();

        $definition = scenario_manager::get_working_definition($this->scenario);
        $nodes = [];
        if (is_array($definition)) {
            foreach ($definition['nodes'] as $node) {
                $choices = [];
                foreach ($node['choices'] as $choice) {
                    $choices[] = [
                        'id'          => $choice['id'],
                        'letter'      => $choice['letter'],
                        'text'        => $choice['text'],
                        'consequence' => $choice['consequence'],
                        'feedback'    => $choice['feedback'],
                        'signal'      => $choice['signal'],
                        'next'        => $choice['next'],
                        'signaloptions' => $this->options(schema::signals(), 'signal', $choice['signal']),
                        'targetoptions' => $this->targets($definition, $node, $choice['next']),
                    ];
                }
                $nodes[] = [
                    'id'                => $node['id'],
                    'type'              => $node['type'],
                    'isoutcome'         => $node['type'] === 'outcome',
                    'title'             => $node['title'],
                    'stage'             => $node['stage'],
                    'situation'         => $node['situation'],
                    'facilitatorspeech' => $node['facilitatorspeech'],
                    'challenge'         => $node['challenge'],
                    'summary'           => $node['summary'],
                    'choices'           => $choices,
                    'haschoices'        => !empty($choices),
                ];
            }
        }

        $characters = [];
        for ($i = 0; $i < 4; $i++) {
            $characters[] = [
                'index'      => $i,
                'position'   => $i + 1,
                'name'       => $source['characters'][$i]['name'] ?? '',
                'role'       => $source['characters'][$i]['role'] ?? '',
                'trait'      => $source['characters'][$i]['trait'] ?? '',
                'appearance' => $source['characters'][$i]['appearance'] ?? '',
            ];
        }

        $decisionoptions = [];
        for ($i = 3; $i <= 8; $i++) {
            $decisionoptions[] = ['value' => $i, 'selected' => (int)$source['decisions'] === $i];
        }

        $credentials = credentials::resolve();
        $configuredmax = get_config('mod_aibranchedscenario', 'maxsourcechars');
        $maxsourcechars = (int)($configuredmax ?: schema::MAX_SOURCE_CHARS);

        return [
            'cmid'         => (int)$this->cm->id,
            'sesskey'      => sesskey(),
            'themeclass'   => 'aibs-theme-' . $this->scenario->theme,
            'viewurl'      => (new \moodle_url(
                '/mod/aibranchedscenario/view.php',
                ['id' => $this->cm->id]
            ))->out(false),
            'reviewurl'    => (new \moodle_url(
                '/mod/aibranchedscenario/review.php',
                ['id' => $this->cm->id]
            ))->out(false),
            'aiavailable'  => $credentials['source'] !== credentials::SOURCE_NONE,
            'published'    => $this->scenario->status === scenario_manager::STATUS_PUBLISHED,
            'revision'     => (int)$this->scenario->revision,
            'hasdraft'     => is_array($definition),
            'hasprevious'  => !empty($this->scenario->previousjson),
            'source'       => $source,
            'characters'   => $characters,
            'principles'   => array_values($source['principles']),
            'hasprinciples' => !empty($source['principles']),
            'nodes'        => $nodes,
            'nodecount'    => count($nodes),
            'decisionoptions' => $decisionoptions,
            'industries'   => $this->options(schema::industries(), 'industry', $source['industry']),
            'settings'     => $this->options(schema::settings_list(), 'setting', $source['setting']),
            'atmospheres'  => $this->options(schema::atmospheres(), 'atmosphere', $source['atmosphere']),
            'whyhard'      => $this->options(schema::whyhard(), 'whyhard', $source['whyhard']),
            'stakes'       => $this->options(schema::stakes(), 'stakes', $source['stakes']),
            'tones'        => $this->options(schema::tones(), 'tone', $source['tone']),
            'complexities' => $this->options(schema::complexities(), 'complexity', $source['complexity']),
            'imagestyles'  => $this->options(schema::imagestyles(), 'imagestyle', $source['imagestyle']),
            'openingmetrics' => [
                ['key' => 'engagement', 'label' => get_string('metric:engagement', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['engagement']],
                ['key' => 'trust', 'label' => get_string('metric:trust', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['trust']],
                ['key' => 'tension', 'label' => get_string('metric:tension', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['tension']],
            ],
            // The wizard has to be able to tell "the teacher chose Training room" from
            // "nobody has chosen anything yet", because the pickers are rendered with a
            // schema default already pressed. Without this, autofill treats every
            // picker as answered and never fills one.
            'defaults'       => [
                'industry'   => source_normaliser::blank()['industry'],
                'setting'    => source_normaliser::blank()['setting'],
                'atmosphere' => source_normaliser::blank()['atmosphere'],
            ],
            'maxsourcechars' => $maxsourcechars,
            'importprompt'   => \mod_aibranchedscenario\local\import_prompt::text((int)$source['decisions']),
        ];
    }
}
