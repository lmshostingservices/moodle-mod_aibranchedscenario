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

use mod_aibranchedscenario\local\attempt_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\validation_exception;
use stdClass;

/**
 * Tests for scenario storage, publishing and revision binding.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\local\scenario_manager
 */
final class scenario_manager_test extends \advanced_testcase {
    /** @var stdClass Course record. */
    protected $course;

    /** @var stdClass Teacher. */
    protected $teacher;

    /** @var stdClass Activity instance record. */
    protected $scenario;

    /** @var \mod_aibranchedscenario_generator Module generator. */
    protected $generator;

    /**
     * Build an unpublished activity instance.
     *
     * @return void
     */
    protected function build(): void {
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_aibranchedscenario');
        $this->scenario = $this->generator->create_instance(['course' => $this->course->id]);
    }

    /**
     * Wizard inputs are normalised on the way in and read back unchanged.
     */
    public function test_save_source_normalises_and_round_trips(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $clean = scenario_manager::save_source($this->scenario, [
            'title'      => "  A   shaking\tpacker  ",
            'industry'   => 'not-a-real-industry',
            'setting'    => 'minesite',
            'whyhard'    => ['timepressure', 'timepressure', 'bogus'],
            'stakes'     => ['learnersafety'],
            'decisions'  => 99,
            'openingmetrics' => ['engagement' => 500, 'trust' => -5, 'tension' => 'nope'],
            'injected'   => 'should never be stored',
        ]);

        $this->assertSame('A shaking packer', $clean['title']);
        $this->assertSame('training', $clean['industry']);
        $this->assertSame('minesite', $clean['setting']);
        $this->assertSame(['timepressure'], $clean['whyhard']);
        $this->assertSame(['learnersafety'], $clean['stakes']);
        $this->assertSame(8, $clean['decisions']);
        $this->assertSame(100, $clean['openingmetrics']['engagement']);
        $this->assertSame(0, $clean['openingmetrics']['trust']);
        $this->assertSame(30, $clean['openingmetrics']['tension']);
        $this->assertArrayNotHasKey('injected', $clean);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertSame($clean, scenario_manager::get_source($stored));
        $this->assertSame($clean, scenario_manager::get_source($this->scenario));
    }

    /**
     * An invalid definition never reaches storage.
     */
    public function test_save_definition_rejects_invalid_definition(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $definition = $this->generator->create_sample_definition();
        unset($definition['title']);

        try {
            scenario_manager::save_definition($this->scenario, $definition);
            $this->fail('An invalid definition should not be stored.');
        } catch (validation_exception $e) {
            $this->assertContains(
                get_string('error:missingtitle', 'mod_aibranchedscenario'),
                $e->get_problems()
            );
        }

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertEmpty($stored->scenariojson);
        $this->assertNull(scenario_manager::get_working_definition($stored));
    }

    /**
     * Publishing produces numbered immutable revisions and bumps the instance.
     */
    public function test_publish_creates_sequential_revisions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $this->assertFalse(scenario_manager::is_playable($this->scenario));

        scenario_manager::save_definition($this->scenario, $this->generator->create_sample_definition());
        $first = scenario_manager::publish($this->scenario, (int)$this->teacher->id);

        $this->assertSame(1, (int)$first->revision);
        $this->assertSame(7, (int)$first->nodecount);
        $this->assertSame(32, (int)$first->maxskillscore);
        $this->assertSame('indigo', $first->theme);
        $this->assertSame((int)$this->teacher->id, (int)$first->createdby);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertSame(scenario_manager::STATUS_PUBLISHED, $stored->status);
        $this->assertSame(1, (int)$stored->revision);
        $this->assertTrue(scenario_manager::is_playable($stored));

        $second = scenario_manager::publish($this->scenario, (int)$this->teacher->id);
        $this->assertSame(2, (int)$second->revision);
        $this->assertNotEquals((int)$first->id, (int)$second->id);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertSame(2, (int)$stored->revision);
        $this->assertSame(2, $DB->count_records(
            'aibranchedscenario_revisions',
            ['scenarioid' => $this->scenario->id]
        ));

        $current = scenario_manager::get_current_revision($stored);
        $this->assertNotNull($current);
        $this->assertSame((int)$second->id, (int)$current->id);
    }

    /**
     * Publishing again never changes a scenario underneath a learner who is mid-attempt.
     */
    public function test_inprogress_attempt_stays_on_its_revision(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $learner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        scenario_manager::save_definition($this->scenario, $this->generator->create_sample_definition());
        $firstrevision = scenario_manager::publish($this->scenario, (int)$this->teacher->id);

        $manager = attempt_manager::for_scenario($this->scenario);
        $attempt = $manager->start_or_resume((int)$learner->id);
        $manager->submit_choice($attempt, 'start', 'start_a', 1);
        $this->assertSame('pathone', $attempt->currentnode);

        // Rewrite the working copy and publish it as revision 2.
        $definition = $this->generator->create_sample_definition();
        foreach ($definition['nodes'] as $index => $node) {
            if ($node['id'] === 'pathone') {
                $definition['nodes'][$index]['situation'] = 'Rewritten after the learner had already started.';
            }
        }
        scenario_manager::save_definition($this->scenario, $definition);
        $secondrevision = scenario_manager::publish($this->scenario, (int)$this->teacher->id);
        $this->assertSame(2, (int)$secondrevision->revision);

        $reloaded = $DB->get_record('aibranchedscenario_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame((int)$firstrevision->id, (int)$reloaded->revisionid);
        $this->assertNotEquals((int)$secondrevision->id, (int)$reloaded->revisionid);

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $bound = attempt_manager::for_attempt($scenario, $reloaded);
        $this->assertSame((int)$firstrevision->id, (int)$bound->get_revision()->id);
        $this->assertSame(
            'You are standing at the guard rail with Sam.',
            $bound->get_node('pathone')['situation']
        );

        // A learner starting now does get the new text.
        $fresh = attempt_manager::for_scenario($scenario);
        $this->assertSame(
            'Rewritten after the learner had already started.',
            $fresh->get_node('pathone')['situation']
        );
    }

    /**
     * Generation metadata is reduced to a fixed allowlist, so no credential can be stored.
     */
    public function test_generation_metadata_is_sanitised(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        scenario_manager::save_definition($this->scenario, $this->generator->create_sample_definition(), [
            'model'         => 'test-model-1',
            'provider'      => 'stub',
            'durationms'    => 1234,
            'apikey'        => 'sk-live-should-never-be-stored',
            'tokenkey'      => 'tok-should-never-be-stored',
            'authorization' => 'Bearer nope',
            'siteid'        => 'site-123',
            'nested'        => ['still' => 'not scalar'],
        ]);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $meta = json_decode($stored->generationmeta, true);

        $this->assertIsArray($meta);
        $this->assertSame('test-model-1', $meta['model']);
        $this->assertSame('stub', $meta['provider']);
        $this->assertSame(1234, $meta['durationms']);

        foreach (['apikey', 'tokenkey', 'authorization', 'siteid', 'nested'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $meta);
        }
        $this->assertStringNotContainsString('sk-live', $stored->generationmeta);
        $this->assertStringNotContainsString('tok-should-never', $stored->generationmeta);
        $this->assertStringNotContainsString('Bearer', $stored->generationmeta);
    }

    /**
     * Unpublishing returns the activity to draft but keeps its revisions readable.
     */
    public function test_unpublish_keeps_revisions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $this->generator->publish_sample($this->scenario, (int)$this->teacher->id);
        scenario_manager::unpublish($this->scenario);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertSame(scenario_manager::STATUS_DRAFT, $stored->status);
        $this->assertFalse(scenario_manager::is_playable($stored));
        $this->assertSame(1, $DB->count_records(
            'aibranchedscenario_revisions',
            ['scenarioid' => $this->scenario->id]
        ));
    }
}
