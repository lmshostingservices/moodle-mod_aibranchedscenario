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

/**
 * Translates between the LMS Labs wire format and this plugin's own scenario shape.
 *
 * The service speaks camelCase and names a scene's prose "content", a choice's prose
 * "label", and a terminal node "end". This plugin has always used lower-case keys,
 * "situation", "text" and "outcome", and the stored definition, the validator, the
 * templates, the attempt engine and every existing revision are built on those names.
 *
 * Translating once, here, keeps that seam in a single file: the wire format can change
 * without touching the player, and the stored format can change without renegotiating
 * the API. Nothing in this class validates - it only renames and reshapes. Everything
 * it produces is handed straight to {@see \mod_aibranchedscenario\local\validator},
 * which is what decides whether the result is safe and coherent.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenario_mapper {
    /** @var array Node type on the wire, mapped to the plugin's node types. */
    const NODE_TYPES = [
        'decision' => 'decision',
        'content'  => 'beat',
        'beat'     => 'beat',
        'end'      => 'outcome',
        'outcome'  => 'outcome',
    ];

    /**
     * Convert a scenario as returned by LMS Labs into this plugin's shape.
     *
     * Missing optional fields become the plugin's documented defaults rather than
     * being dropped, so that a service response that predates a contract addition
     * still produces a scenario the validator can reason about.
     *
     * @param array $wire Scenario object from the service.
     * @return array Scenario in the plugin's own shape.
     */
    public static function from_wire(array $wire): array {
        $nodes = [];
        foreach ((array)($wire['nodes'] ?? []) as $node) {
            if (is_array($node)) {
                $nodes[] = self::node_from_wire($node);
            }
        }

        $principles = [];
        foreach ((array)($wire['principles'] ?? []) as $index => $principle) {
            if (!is_array($principle)) {
                continue;
            }
            $principles[] = [
                'id'      => (string)($principle['id'] ?? 'p' . ($index + 1)),
                'title'   => (string)($principle['title'] ?? ''),
                'summary' => (string)($principle['summary'] ?? ''),
            ];
        }

        $debrief = (array)($wire['debrief'] ?? []);
        $takeaways = [];
        foreach ((array)($wire['takeaways'] ?? []) as $takeaway) {
            if (is_array($takeaway)) {
                $takeaways[] = [
                    'heading' => (string)($takeaway['heading'] ?? ''),
                    'body'    => (string)($takeaway['body'] ?? ''),
                ];
            }
        }

        $metrics = (array)($wire['openingMetrics'] ?? []);

        return [
            'title'          => (string)($wire['title'] ?? ''),
            'subtitle'       => (string)($wire['subtitle'] ?? ''),
            'role'           => (string)($wire['role'] ?? ''),
            'setting'        => (string)($wire['setting'] ?? ''),
            'language'       => (string)($wire['language'] ?? 'en-AU'),
            'tone'           => (string)($wire['tone'] ?? 'neutral'),
            'complexity'     => (string)($wire['complexity'] ?? 'intermediate'),
            'hook'           => (string)($wire['hook'] ?? ($wire['introduction'] ?? '')),
            'openingmetrics' => [
                'engagement' => (int)($metrics['engagement'] ?? 50),
                'trust'      => (int)($metrics['trust'] ?? 50),
                'tension'    => (int)($metrics['tension'] ?? 30),
            ],
            'principles'     => $principles,
            'startnode'      => (string)($wire['startNodeId'] ?? ''),
            'nodes'          => $nodes,
            'debrief'        => [
                'whatmattered'      => self::strings($debrief['whatMattered'] ?? []),
                'criticaldecisions' => self::strings($debrief['criticalDecisions'] ?? []),
                'practice'          => self::strings($debrief['practice'] ?? []),
                'sourceconnection'  => (string)($debrief['sourceConnection'] ?? ''),
            ],
            'takeaways'      => $takeaways,
        ];
    }

    /**
     * Convert one node from the wire format.
     *
     * @param array $node Node object from the service.
     * @return array
     */
    protected static function node_from_wire(array $node): array {
        $type = (string)($node['type'] ?? 'decision');
        $mapped = self::NODE_TYPES[$type] ?? 'beat';

        $out = [
            'id'                => (string)($node['id'] ?? ''),
            'type'              => $mapped,
            'title'             => (string)($node['title'] ?? ''),
            'situation'         => (string)($node['content'] ?? ($node['situation'] ?? '')),
            'facilitatorspeech' => (string)($node['facilitatorSpeech'] ?? ''),
            'challenge'         => (string)($node['challenge'] ?? ''),
        ];

        if (isset($node['stage'])) {
            $out['stage'] = (int)$node['stage'];
        }

        if (isset($node['crisisVariant']) && is_array($node['crisisVariant'])) {
            $crisis = $node['crisisVariant'];
            $out['crisisvariant'] = [
                'situation'         => (string)($crisis['situation'] ?? ''),
                'facilitatorspeech' => (string)($crisis['facilitatorSpeech'] ?? ''),
                'challenge'         => (string)($crisis['challenge'] ?? ''),
            ];
        }

        if ($mapped === 'outcome') {
            $out['outcome'] = (string)($node['outcome'] ?? 'mixed');
            $out['summary'] = (string)($node['summary'] ?? '');
            // A terminal node carries no choices, whatever the service sent.
            return $out;
        }

        $choices = [];
        foreach ((array)($node['choices'] ?? []) as $index => $choice) {
            if (is_array($choice)) {
                $choices[] = self::choice_from_wire($choice, $index);
            }
        }
        $out['choices'] = $choices;

        return $out;
    }

    /**
     * Convert one choice from the wire format.
     *
     * @param array $choice Choice object from the service.
     * @param int $index Position within the node, used only to invent a missing id.
     * @return array
     */
    protected static function choice_from_wire(array $choice, int $index): array {
        $skills = (array)($choice['skills'] ?? []);
        $effects = (array)($choice['effects'] ?? []);

        return [
            'id'          => (string)($choice['id'] ?? 'c' . ($index + 1)),
            'text'        => (string)($choice['label'] ?? ($choice['text'] ?? '')),
            'signal'      => (string)($choice['signal'] ?? 'neutral'),
            'consequence' => (string)($choice['consequence'] ?? ''),
            'feedback'    => (string)($choice['feedback'] ?? ''),
            'principleid' => (string)($choice['principleId'] ?? ''),
            'next'        => (string)($choice['nextNodeId'] ?? ''),
            'skills'      => [
                'presence'     => (int)($skills['presence'] ?? 0),
                'adaptability' => (int)($skills['adaptability'] ?? 0),
                'empathy'      => (int)($skills['empathy'] ?? 0),
                'clarity'      => (int)($skills['clarity'] ?? 0),
            ],
            'effects'     => [
                'engagement' => (int)($effects['engagement'] ?? 0),
                'trust'      => (int)($effects['trust'] ?? 0),
                'tension'    => (int)($effects['tension'] ?? 0),
            ],
        ];
    }

    /**
     * Convert the field set returned by the populate route into wizard values.
     *
     * The route returns a deliberately small set of fields. Only those are filled in;
     * the wizard's other values are left alone rather than being invented here, which
     * is why the caller merges this over the existing source rather than replacing it.
     *
     * @param array $fields Field object from the service.
     * @return array Wizard values in this plugin's own names.
     */
    public static function fields_from_wire(array $fields): array {
        $out = [];
        if (isset($fields['title'])) {
            $out['title'] = (string)$fields['title'];
        }
        if (isset($fields['audience'])) {
            $out['audience'] = (string)$fields['audience'];
        }
        if (isset($fields['introduction'])) {
            $out['openingsituation'] = (string)$fields['introduction'];
        }
        if (isset($fields['instructions'])) {
            $out['brief'] = (string)$fields['instructions'];
        }
        if (isset($fields['learningObjectives']) && is_array($fields['learningObjectives'])) {
            $principles = [];
            foreach (array_values($fields['learningObjectives']) as $index => $objective) {
                if (is_scalar($objective) && trim((string)$objective) !== '') {
                    $principles[] = [
                        'id'      => 'p' . ($index + 1),
                        'title'   => (string)$objective,
                        'summary' => '',
                    ];
                }
            }
            if ($principles) {
                $out['principles'] = $principles;
            }
        }
        return $out;
    }

    /**
     * Cast a list to a list of strings, dropping anything that is not scalar.
     *
     * @param mixed $value Candidate list.
     * @return string[]
     */
    protected static function strings($value): array {
        $out = [];
        foreach ((array)$value as $item) {
            if (is_scalar($item)) {
                $out[] = (string)$item;
            }
        }
        return $out;
    }
}
