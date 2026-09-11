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

namespace mod_aibranchedscenario;

use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\validation_exception;
use mod_aibranchedscenario\local\validator;

/**
 * Tests for the scenario graph validator and normaliser.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\local\validator
 */
final class validator_test extends \advanced_testcase {
    /**
     * A known-good definition straight from the module generator.
     *
     * @return array
     */
    protected function sample(): array {
        /** @var \mod_aibranchedscenario_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aibranchedscenario');
        return $generator->create_sample_definition();
    }

    /**
     * Replace one node of a definition, matched by id.
     *
     * @param array $definition Definition to modify.
     * @param string $nodeid Node to replace.
     * @param array|null $replacement New node, or null to remove it.
     * @return array
     */
    protected function replace_node(array $definition, string $nodeid, ?array $replacement): array {
        $nodes = [];
        foreach ($definition['nodes'] as $node) {
            if ($node['id'] === $nodeid) {
                if ($replacement !== null) {
                    $nodes[] = $replacement;
                }
                continue;
            }
            $nodes[] = $node;
        }
        $definition['nodes'] = $nodes;
        return $definition;
    }

    /**
     * Find one node of a definition by id.
     *
     * @param array $definition Definition to search.
     * @param string $nodeid Node id.
     * @return array
     */
    protected function find_node(array $definition, string $nodeid): array {
        foreach ($definition['nodes'] as $node) {
            if ($node['id'] === $nodeid) {
                return $node;
            }
        }
        $this->fail('Node ' . $nodeid . ' is not in the definition.');
    }

    /**
     * A valid branch-and-bottleneck graph validates and gains derived statistics.
     */
    public function test_valid_graph_normalises_with_stats(): void {
        $this->resetAfterTest();

        $clean = validator::validate($this->sample());

        $this->assertSame(schema::CONTRACT_VERSION, $clean['version']);
        $this->assertSame('The vibrating machine', $clean['title']);
        $this->assertSame('start', $clean['startnode']);
        $this->assertCount(7, $clean['nodes']);

        $this->assertSame(7, $clean['stats']['nodecount']);
        $this->assertSame(5, $clean['stats']['decisioncount']);
        $this->assertSame(4, $clean['stats']['longestpath']);
        $this->assertSame(2, $clean['stats']['outcomecount']);
        // Four decisions on the longest path, four skills, two points each.
        $this->assertSame(32, $clean['stats']['maxskillscore']);

        // The bottleneck flag survives normalisation.
        $bottleneck = null;
        foreach ($clean['nodes'] as $node) {
            if ($node['id'] === 'bottleneck') {
                $bottleneck = $node;
            }
        }
        $this->assertNotNull($bottleneck);
        $this->assertTrue($bottleneck['bottleneck']);
    }

    /**
     * A definition with no title is rejected.
     */
    public function test_missing_title_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $definition['title'] = '   ';

        try {
            validator::validate($definition);
            $this->fail('A definition without a title should not validate.');
        } catch (validation_exception $e) {
            $this->assertContains(
                get_string('error:missingtitle', 'mod_aibranchedscenario'),
                $e->get_problems()
            );
        }
    }

    /**
     * A choice whose target does not exist is rejected.
     */
    public function test_dangling_choice_target_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $node = $this->find_node($definition, 'final');
        $node['choices'][0]['next'] = 'nosuchnode';
        $definition = $this->replace_node($definition, 'final', $node);

        $problems = [];
        $this->assertNull(validator::try_validate($definition, $problems));
        $this->assertContains(
            get_string(
                'error:danglingtarget',
                'mod_aibranchedscenario',
                (object)['node' => 'final', 'target' => 'nosuchnode']
            ),
            $problems
        );
    }

    /**
     * A node nothing points at is rejected.
     */
    public function test_unreachable_node_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $orphan = $this->find_node($definition, 'pathone');
        $orphan['id'] = 'orphan';
        $orphan['choices'][0]['id'] = 'orphan_a';
        $orphan['choices'][1]['id'] = 'orphan_b';
        $definition['nodes'][] = $orphan;

        $problems = [];
        $this->assertNull(validator::try_validate($definition, $problems));
        $this->assertContains(
            get_string('error:unreachablenode', 'mod_aibranchedscenario', 'orphan'),
            $problems
        );
    }

    /**
     * A graph that can loop back on itself is rejected, so an attempt always terminates.
     */
    public function test_cyclic_graph_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $node = $this->find_node($definition, 'bottleneck');
        $node['choices'][1]['next'] = 'start';
        $definition = $this->replace_node($definition, 'bottleneck', $node);

        $problems = [];
        $this->assertNull(validator::try_validate($definition, $problems));
        $this->assertContains(
            get_string('error:cyclicgraph', 'mod_aibranchedscenario'),
            $problems
        );
    }

    /**
     * A scenario with nowhere to finish is rejected.
     */
    public function test_no_outcome_node_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();

        // Point the last decision at the terminal nodes directly, then demote them to
        // decisions so the graph stays reachable but has no outcome band anywhere.
        $final = $this->find_node($definition, 'final');
        $final['choices'][0]['next'] = 'outcomestrong';
        $final['choices'][1]['next'] = 'outcomerisk';
        $definition = $this->replace_node($definition, 'final', $final);

        foreach (['outcomestrong', 'outcomerisk'] as $terminal) {
            $node = $this->find_node($definition, $terminal);
            $node['type'] = 'decision';
            $node['choices'] = [
                [
                    'id'   => $terminal . '_a',
                    'text' => 'Close out the shift.',
                    'next' => schema::auto_target(),
                ],
                [
                    'id'   => $terminal . '_b',
                    'text' => 'Say nothing further.',
                    'next' => schema::auto_target(),
                ],
            ];
            $definition = $this->replace_node($definition, $terminal, $node);
        }

        $problems = [];
        $this->assertNull(validator::try_validate($definition, $problems));
        $this->assertContains(
            get_string('error:nooutcomenode', 'mod_aibranchedscenario'),
            $problems
        );
    }

    /**
     * A decision offering a single choice is rejected.
     */
    public function test_decision_with_one_choice_fails(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $node = $this->find_node($definition, 'pathone');
        $node['choices'] = [$node['choices'][0]];
        $definition = $this->replace_node($definition, 'pathone', $node);

        $problems = [];
        $this->assertNull(validator::try_validate($definition, $problems));
        $this->assertContains(
            get_string('error:choicecount', 'mod_aibranchedscenario', (object)[
                'node' => 'pathone',
                'min'  => schema::MIN_CHOICES,
                'max'  => schema::MAX_CHOICES,
            ]),
            $problems
        );
    }

    /**
     * Narrative text longer than the cap is truncated rather than rejected.
     */
    public function test_long_text_is_truncated(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $node = $this->find_node($definition, 'pathone');
        $node['situation'] = str_repeat('a', schema::MAX_TEXT + 500);
        $definition = $this->replace_node($definition, 'pathone', $node);

        $clean = validator::validate($definition);

        $stored = null;
        foreach ($clean['nodes'] as $candidate) {
            if ($candidate['id'] === 'pathone') {
                $stored = $candidate;
            }
        }
        $this->assertNotNull($stored);
        $this->assertSame(schema::MAX_TEXT, \core_text::strlen($stored['situation']));
    }

    /**
     * Keys the contract does not describe never reach storage.
     */
    public function test_unknown_keys_are_dropped(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $definition['injected'] = 'top level';
        $definition['stats'] = ['nodecount' => 9999];

        $node = $this->find_node($definition, 'pathone');
        $node['injected'] = 'node level';
        $node['choices'][0]['injected'] = 'choice level';
        $definition = $this->replace_node($definition, 'pathone', $node);

        $clean = validator::validate($definition);

        $this->assertArrayNotHasKey('injected', $clean);
        $this->assertSame(7, $clean['stats']['nodecount']);

        foreach ($clean['nodes'] as $candidate) {
            $this->assertArrayNotHasKey('injected', $candidate);
            foreach ($candidate['choices'] as $choice) {
                $this->assertArrayNotHasKey('injected', $choice);
            }
        }
    }

    /**
     * Narrative text stays plain text: a script payload survives verbatim but can
     * never be emitted as markup, and control characters are stripped.
     */
    public function test_script_payload_stays_plain_text(): void {
        $this->resetAfterTest();

        $payload = "Sam says \x00\x07\"stop\"</script><script>alert(1)</script>\nand walks off.";

        $definition = $this->sample();
        $node = $this->find_node($definition, 'pathone');
        $node['situation'] = $payload;
        $definition = $this->replace_node($definition, 'pathone', $node);

        $clean = validator::validate($definition);

        $stored = null;
        foreach ($clean['nodes'] as $candidate) {
            if ($candidate['id'] === 'pathone') {
                $stored = $candidate;
            }
        }
        $this->assertNotNull($stored);

        // The words are preserved: nothing is silently mangled or dropped.
        $this->assertStringContainsString('</script>', $stored['situation']);
        $this->assertStringContainsString('alert(1)', $stored['situation']);
        $this->assertStringContainsString('and walks off.', $stored['situation']);

        // Control characters are gone, the newline is kept.
        $this->assertStringNotContainsString("\x00", $stored['situation']);
        $this->assertStringNotContainsString("\x07", $stored['situation']);
        $this->assertStringContainsString("\n", $stored['situation']);

        // The helper splits the text into paragraphs and deliberately does not escape it.
        // Escaping belongs at the point of output, not in the data, or a teacher who
        // legitimately writes "10 < 20" would be shown "10 &lt; 20". So the paragraphs
        // still carry the payload verbatim.
        $paragraphs = \mod_aibranchedscenario\external\helper::paragraph_list($stored['situation']);
        $this->assertStringContainsString('</script>', implode("\n", $paragraphs));

        // What matters is that it can only ever reach a browser as text. Every template in
        // this plugin uses escaping mustache tags, so rendering the paragraph the way the
        // player renders it neutralises the payload.
        $rendered = implode("\n", array_map('s', $paragraphs));
        $this->assertStringNotContainsString('<script', $rendered);
        $this->assertStringNotContainsString('</script>', $rendered);
        $this->assertStringContainsString('&lt;/script&gt;', $rendered);

        // And no template may opt out of that escaping with a triple mustache, which is
        // the one change that would turn the stored payload back into live markup.
        foreach (glob(__DIR__ . '/../templates/*.mustache') as $template) {
            $this->assertStringNotContainsString(
                '{{{',
                file_get_contents($template),
                basename($template) . ' renders unescaped output'
            );
        }
    }

    /**
     * Skill and dynamics deltas outside the contract's range are clamped, not rejected.
     */
    public function test_deltas_are_clamped(): void {
        $this->resetAfterTest();

        $definition = $this->sample();
        $node = $this->find_node($definition, 'pathone');
        $node['choices'][0]['skills'] = [
            'presence' => 99, 'adaptability' => -99, 'empathy' => 3, 'clarity' => 'nonsense',
        ];
        $node['choices'][0]['effects'] = ['engagement' => 999, 'trust' => -999, 'tension' => 41];
        $definition = $this->replace_node($definition, 'pathone', $node);

        $clean = validator::validate($definition);

        $choice = null;
        foreach ($clean['nodes'] as $candidate) {
            if ($candidate['id'] === 'pathone') {
                $choice = $candidate['choices'][0];
            }
        }
        $this->assertNotNull($choice);

        $this->assertSame(schema::MAX_SKILL_DELTA, $choice['skills']['presence']);
        $this->assertSame(-schema::MAX_SKILL_DELTA, $choice['skills']['adaptability']);
        $this->assertSame(schema::MAX_SKILL_DELTA, $choice['skills']['empathy']);
        $this->assertSame(0, $choice['skills']['clarity']);

        $this->assertSame(schema::MAX_METRIC_DELTA, $choice['effects']['engagement']);
        $this->assertSame(-schema::MAX_METRIC_DELTA, $choice['effects']['trust']);
        $this->assertSame(schema::MAX_METRIC_DELTA, $choice['effects']['tension']);
    }

    /**
     * The automatic outcome target is a legal destination and keeps outcomes reachable.
     */
    public function test_auto_target_is_accepted(): void {
        $this->resetAfterTest();

        $clean = validator::validate($this->sample());

        $final = null;
        foreach ($clean['nodes'] as $node) {
            if ($node['id'] === 'final') {
                $final = $node;
            }
        }
        $this->assertNotNull($final);
        foreach ($final['choices'] as $choice) {
            $this->assertSame(schema::auto_target(), $choice['next']);
        }
    }
}
