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
                // The service may send these under either name. Both are optional.
                'example' => (string)($principle['example'] ?? ($principle['goodExample'] ?? '')),
                'pitfall' => (string)($principle['pitfall'] ?? ($principle['commonMistake'] ?? '')),
            ];
        }

        // The cast was not mapped at all, and everything that depends on knowing who is in
        // the scenario had been quietly running on the fallback ever since.
        //
        // A node's speaker is matched against this list to find the voice their line is
        // read in, so with the list empty every spoken line was read by the narrator. The
        // illustrator brief names the people in frame from this list, so with it empty
        // every scene image in the product was drawn to "one worker, seen from behind or in
        // three-quarter view, face not the subject of the image". And the avatar the
        // learner clicks to hear someone speak had nobody to show.
        //
        // None of that looked like a fault. It looked like the pictures being a bit
        // impersonal and the narration being a bit flat.
        $facilitator = is_array($wire['facilitator'] ?? null)
            ? self::person_from_wire($wire['facilitator'])
            : null;
        $characters = [];
        foreach ((array)($wire['characters'] ?? []) as $character) {
            if (!is_array($character)) {
                continue;
            }
            $person = self::person_from_wire($character);
            if (trim($person['name']) !== '') {
                $characters[] = $person;
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
            'facilitator'    => $facilitator,
            'characters'     => $characters,
            'principles'     => $principles,
            'startnode'      => (string)($wire['startNodeId'] ?? ''),
            'nodes'          => $nodes,
            // The service's "debrief" block and "takeaways" are no longer mapped. Those
            // four lists of closing advice have been replaced by one paragraph per option,
            // carried on the choice itself, so anything still arriving under the old keys
            // is left on the wire rather than stored against a contract that has no place
            // for it.
        ];
    }

    /**
     * Convert one person from the wire format.
     *
     * @param array $person Person object from the service.
     * @return array
     */
    protected static function person_from_wire(array $person): array {
        // The same three values the validator stores, matched in the spellings the service
        // actually sends. This used to accept "male" and "female" and nothing else, so
        // "Female", "woman" and "non-binary" all arrived here and left as an empty string -
        // the person's gender then never reached the picture brief and the image model
        // chose for itself.
        $gender = \core_text::strtolower(trim((string)($person['gender'] ?? '')));
        $known = [
            'male'       => ['male', 'm', 'man', 'boy'],
            'female'     => ['female', 'f', 'woman', 'girl'],
            'non-binary' => ['non-binary', 'nonbinary', 'non binary', 'nb', 'enby'],
        ];
        $canonical = '';
        foreach ($known as $value => $spellings) {
            if (in_array($gender, $spellings, true)) {
                $canonical = $value;
                break;
            }
        }
        return [
            'name'       => (string)($person['name'] ?? ''),
            'role'       => (string)($person['role'] ?? ''),
            'trait'      => (string)($person['trait'] ?? ''),
            'appearance' => (string)($person['appearance'] ?? ''),
            'gender'     => $canonical,
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
            // Who says that line. The service may name them on the node or inside the
            // speech object; either way the name is matched against the cast so the line
            // can be spoken in that person's voice.
            'speaker'           => (string)($node['speaker'] ?? ($node['speakerName'] ?? '')),
            'challenge'         => (string)($node['challenge'] ?? ''),
            // The service's own description of the scene, and the same scene written for a
            // screen reader. Neither was mapped, so the illustrator was briefed without the
            // one line written specifically for it, and every picture in the product fell
            // back to alt text condensed from the situation prose.
            'imageprompt'       => (string)($node['imagePrompt'] ?? ($node['imageprompt'] ?? '')),
            'imagealt'          => (string)($node['imageAlt'] ?? ($node['imagealt'] ?? '')),
        ];

        // A node the generator marks as a point every path passes through.
        if (isset($node['bottleneck'])) {
            $out['bottleneck'] = !empty($node['bottleneck']);
        }

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

        // The conditional variants: this moment, written for a learner carrying a
        // particular fact. Mapped by hand rather than passed through so that the condition
        // keys arrive in the shape the validator normalises, whichever case the service
        // sends them in.
        $out['variants'] = [];
        foreach ((array)($node['variants'] ?? []) as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $when = (array)($variant['when'] ?? []);
            $out['variants'][] = [
                'when' => [
                    'flag'    => (string)($when['flag'] ?? ''),
                    'is'      => !empty($when['is']),
                    'metric'  => (string)($when['metric'] ?? ''),
                    'signals' => (string)($when['signals'] ?? ''),
                    'atleast' => isset($when['atLeast']) ? (int)$when['atLeast']
                        : (isset($when['atleast']) ? (int)$when['atleast'] : null),
                    'atmost'  => isset($when['atMost']) ? (int)$when['atMost']
                        : (isset($when['atmost']) ? (int)$when['atmost'] : null),
                ],
                'situation'         => (string)($variant['situation'] ?? ''),
                'facilitatorspeech' => (string)($variant['facilitatorSpeech']
                    ?? ($variant['facilitatorspeech'] ?? '')),
                'challenge'         => (string)($variant['challenge'] ?? ''),
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

        // A beat is validated on a node-level `next` and its choices are discarded, but
        // nothing on the wire carries that field: the service puts every onward link in
        // the choices. So every `content` node the service sent arrived as a beat with
        // nowhere to go, failed with "does not say what follows it", and — because the
        // node it happened to be was the first one — took the reachability of the whole
        // scenario down with it. That is the failure seen on 10 September, and it was
        // this plugin's doing rather than the generator's.
        //
        // A node the service called content but gave real alternatives to is a decision
        // whatever it was labelled, so it is mapped as one rather than losing the
        // branching. A genuine one-way beat keeps its single onward link.
        // TWO, not schema::MIN_CHOICES. The minimum is three now, and reading the minimum
        // here meant a node the service gave two real alternatives to was demoted to a
        // one-way beat - the branching thrown away silently, which is the exact fault this
        // branch was written to stop. What makes something a decision is having more than
        // one way out; whether it has ENOUGH ways out is the validator's question, and it
        // says so with an error a teacher can act on rather than by deleting the choices.
        if ($mapped === 'beat' && count($choices) >= 2) {
            $out['type'] = 'decision';
            $out['choices'] = $choices;
            return $out;
        }

        if ($mapped === 'beat') {
            $next = trim((string)($node['nextNodeId'] ?? ($node['next'] ?? '')));
            if ($next === '' && isset($choices[0]['next'])) {
                $next = (string)$choices[0]['next'];
            }
            $out['next'] = $next;
            $label = trim((string)($node['continueLabel'] ?? ($node['continuelabel'] ?? '')));
            if ($label === '' && isset($choices[0]['text'])) {
                $label = (string)$choices[0]['text'];
            }
            if ($label !== '') {
                $out['continuelabel'] = $label;
            }
            $out['choices'] = [];
            return $out;
        }

        $out['choices'] = self::shuffled($choices, (string)($out['id'] ?? ''));

        return $out;
    }

    /**
     * Put the options in an order that gives nothing away.
     *
     * The generator writes the best option first. It does this consistently, so A was the
     * strongest answer at every decision in the scenario - and a learner notices that
     * within two screens and stops reading the options at all. The scenario then measures
     * whether they spotted the pattern rather than whether they know the material.
     *
     * The order is settled once, here, where the service's answer becomes ours: the
     * teacher edits the same order the learner sees, which they could not do if it were
     * shuffled again at each attempt. It is seeded from the node's own id rather than left
     * to chance, so regenerating the same scenario gives the same order and a support
     * question about "option B" still means something a week later.
     *
     * @param array $choices Mapped choices, in the order the service sent them.
     * @param string $seed Node identifier.
     * @return array
     */
    protected static function shuffled(array $choices, string $seed): array {
        if (count($choices) < 2) {
            return $choices;
        }
        $order = array_keys($choices);
        // A Fisher-Yates walk driven by a hash of the node id, so it is deterministic
        // without depending on the state of any shared random number generator.
        $digest = md5($seed === '' ? 'aibs' : $seed);
        for ($i = count($order) - 1; $i > 0; $i--) {
            $byte = hexdec(substr($digest, ($i * 2) % 30, 2));
            $j = $byte % ($i + 1);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }
        $out = [];
        foreach ($order as $position) {
            $out[] = $choices[$position];
        }
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
            // The paragraph the debrief slide shows against this option. Accepted under
            // either spelling, like every other field the service may camel-case.
            'outcomenote' => (string)($choice['outcomeNote'] ?? ($choice['outcomenote'] ?? '')),
            // What this choice leaves behind. Accepted under either spelling, like every
            // other field the service may camel-case, and simply absent on a service that
            // has not shipped it - which is every service today.
            'setflags'    => (array)($choice['setFlags'] ?? ($choice['setflags'] ?? [])),
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
        // The `introduction` field is deliberately not read. It is the generic content route's
        // course introduction — the field that produces "Active listening is a crucial
        // communication skill…" — and writing it into the opening situation was why
        // that field kept arriving as a course description. Worse, it arrived first:
        // the wizard only asks for the fields populate left empty, so an opening
        // situation filled with a course introduction meant the properly briefed
        // request for a real one never ran. The scenario-specific `openingSituation`
        // below is read when the service sends it; otherwise the field is left empty
        // for that request to fill.

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

        // The union operator keeps its left operand on a collision, so array_merge is
        // used instead: a response carrying both the generic `introduction` and a real
        // `openingSituation` would otherwise have kept the introduction, which is exactly
        // the fault the scenario-specific mapping exists to correct.
        return array_merge($out, self::scenario_fields_from_wire($fields));
    }

    /**
     * The scenario-specific half of a populate response.
     *
     * The populate route is shared with the other LMS Labs plugins, so its documented
     * shape is the generic one handled above: a title, an introduction, instructions
     * and learning objectives. Those five keys were all the plugin read, which is why a
     * teacher who pressed Autofill still had to choose a setting, an atmosphere, the
     * complications, the stakes, the role and every character by hand, and why the
     * Opening situation arrived reading like a course introduction — it was one.
     *
     * Everything below is read when the service sends it and ignored when it does not,
     * so the richer response can be turned on server-side without a plugin release.
     * Option keys are checked against the wizard's own lists; a value the wizard cannot
     * display is dropped rather than stored.
     *
     * @param array $fields Decoded populate response.
     * @return array Wizard values.
     */
    protected static function scenario_fields_from_wire(array $fields): array {
        $out = [];

        $text = [
            'settingother'     => 'settingDescription',
            'openingsituation' => 'openingSituation',
            'centralproblem'   => 'centralProblem',
            'participantrole'  => 'participantRole',
            'imageprompt'      => 'imagePrompt',
        ];
        foreach ($text as $local => $wire) {
            if (isset($fields[$wire]) && is_scalar($fields[$wire]) && trim((string)$fields[$wire]) !== '') {
                $out[$local] = (string)$fields[$wire];
            }
        }

        $lists = [
            'industry'   => [schema::industries(), false],
            'setting'    => [schema::settings_list(), false],
            'atmosphere' => [schema::atmospheres(), false],
            'tone'       => [schema::tones(), false],
            'complexity' => [schema::complexities(), false],
            'whyhard'    => [schema::whyhard(), true],
            'stakes'     => [schema::stakes(), true],
        ];
        foreach ($lists as $local => [$allowed, $multiple]) {
            if (!isset($fields[$local])) {
                continue;
            }
            $chosen = [];
            foreach ((array)$fields[$local] as $value) {
                if (is_scalar($value) && in_array((string)$value, $allowed, true)) {
                    $chosen[] = (string)$value;
                }
            }
            if (!$chosen) {
                continue;
            }
            $out[$local] = $multiple ? array_values(array_unique($chosen)) : $chosen[0];
        }

        if (isset($fields['characters']) && is_array($fields['characters'])) {
            $characters = [];
            foreach (array_slice($fields['characters'], 0, 4) as $character) {
                if (!is_array($character) || trim((string)($character['name'] ?? '')) === '') {
                    continue;
                }
                $characters[] = [
                    'name'       => (string)$character['name'],
                    'role'       => (string)($character['role'] ?? ''),
                    'trait'      => (string)($character['trait'] ?? ''),
                    'appearance' => (string)($character['appearance'] ?? ''),
                ];
            }
            if ($characters) {
                $out['characters'] = $characters;
            }
        }

        return $out;
    }
}
