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

use core_external\external_api;
use mod_aibranchedscenario\external\delete_attempt;
use mod_aibranchedscenario\external\get_debrief;
use mod_aibranchedscenario\external\publish_scenario;
use mod_aibranchedscenario\external\queue_generation;
use mod_aibranchedscenario\external\start_attempt;
use mod_aibranchedscenario\external\submit_choice;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the external API: permissions, ownership and return structures.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\external\start_attempt
 * @covers     \mod_aibranchedscenario\external\submit_choice
 * @covers     \mod_aibranchedscenario\external\get_debrief
 * @covers     \mod_aibranchedscenario\external\publish_scenario
 * @covers     \mod_aibranchedscenario\external\queue_generation
 * @covers     \mod_aibranchedscenario\external\delete_attempt
 * @covers     \mod_aibranchedscenario\external\helper
 */
final class external_test extends \externallib_advanced_testcase {
    /** @var stdClass Course record. */
    protected $course;

    /** @var stdClass Activity instance record. */
    protected $scenario;

    /** @var stdClass Course module record. */
    protected $cm;

    /** @var stdClass Teacher. */
    protected $teacher;

    /** @var stdClass Learner. */
    protected $student;

    /** @var \mod_aibranchedscenario_generator Module generator. */
    protected $generator;

    /**
     * Build a course, an activity and the two people who use it.
     *
     * @param bool $publish Whether to publish the sample scenario.
     * @return void
     */
    protected function build(bool $publish = true): void {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_aibranchedscenario');
        $instance = $this->generator->create_instance(['course' => $this->course->id]);

        if ($publish) {
            $this->generator->publish_sample($instance, (int)$this->teacher->id);
        } else {
            \mod_aibranchedscenario\local\scenario_manager::save_definition(
                $instance,
                $this->generator->create_sample_definition()
            );
        }

        $this->scenario = $DB->get_record('aibranchedscenario', ['id' => $instance->id], '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_instance(
            'aibranchedscenario',
            $this->scenario->id,
            $this->course->id,
            false,
            MUST_EXIST
        );
    }

    /**
     * A learner can start an attempt and the payload matches the declared structure.
     */
    public function test_student_can_start_attempt(): void {
        $this->resetAfterTest();
        $this->build();
        $this->setUser($this->student);

        $result = start_attempt::execute((int)$this->cm->id, false);
        $result = external_api::clean_returnvalue(start_attempt::execute_returns(), $result);

        $this->assertGreaterThan(0, $result['attemptid']);
        $this->assertSame(1, $result['attemptno']);
        $this->assertSame('inprogress', $result['status']);
        $this->assertSame(1, $result['nextseq']);
        $this->assertFalse($result['resumed']);
        $this->assertSame(['engagement' => 50, 'trust' => 50, 'tension' => 30], $result['metrics']);
        $this->assertSame('start', $result['node']['id']);
        $this->assertSame('decision', $result['node']['type']);
        $this->assertCount(2, $result['node']['choices']);

        // The player is never told where a choice leads or what it costs.
        foreach ($result['node']['choices'] as $choice) {
            $this->assertSame(['id', 'letter', 'text'], array_keys($choice));
        }

        // Resuming reports the same attempt.
        $again = start_attempt::execute((int)$this->cm->id, false);
        $again = external_api::clean_returnvalue(start_attempt::execute_returns(), $again);
        $this->assertSame($result['attemptid'], $again['attemptid']);
    }

    /**
     * A learner can record a decision and, at the end, read their own debrief.
     */
    public function test_student_can_submit_choice_and_read_debrief(): void {
        $this->resetAfterTest();
        $this->build();
        $this->setUser($this->student);

        $started = start_attempt::execute((int)$this->cm->id, false);
        $started = external_api::clean_returnvalue(start_attempt::execute_returns(), $started);
        $attemptid = $started['attemptid'];

        $nodeid = $started['node']['id'];
        $seq = 1;
        $result = null;
        while (true) {
            $raw = submit_choice::execute((int)$this->cm->id, $attemptid, $nodeid, $nodeid . '_a', $seq);
            $result = external_api::clean_returnvalue(submit_choice::execute_returns(), $raw);

            $this->assertSame($seq, $result['seq']);
            $this->assertSame('positive', $result['signal']);
            $this->assertIsArray($result['consequenceparas']);
            foreach ($result['consequenceparas'] as $paragraph) {
                $this->assertIsString($paragraph);
            }

            if ($result['finished']) {
                break;
            }
            $nodeid = $result['node']['id'];
            $seq++;
        }

        $this->assertSame('outcome', $result['node']['type']);
        $this->assertSame('strong', $result['node']['outcome']);
        $this->assertSame(0, $result['after']['tension']);

        $debrief = get_debrief::execute((int)$this->cm->id, $attemptid);
        $debrief = external_api::clean_returnvalue(get_debrief::execute_returns(), $debrief);

        $this->assertSame($attemptid, $debrief['attemptid']);
        $this->assertSame('strong', $debrief['outcome']);
        $this->assertEqualsWithDelta(100.0, $debrief['score'], 0.001);
        $this->assertSame(4, $debrief['decisions']);
        $this->assertCount(4, $debrief['journey']);
        $this->assertCount(4, $debrief['radar']);
        $this->assertNotEmpty($debrief['whatmattered']);
        $this->assertNotEmpty($debrief['takeaways']);
    }

    /**
     * A learner cannot publish a revision.
     */
    public function test_student_cannot_publish(): void {
        $this->resetAfterTest();
        $this->build();
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        publish_scenario::execute((int)$this->cm->id);
    }

    /**
     * A learner cannot queue an AI generation job.
     */
    public function test_student_cannot_queue_generation(): void {
        $this->resetAfterTest();
        $this->build();
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        queue_generation::execute((int)$this->cm->id);
    }

    /**
     * A learner cannot delete an attempt, not even their own.
     */
    public function test_student_cannot_delete_attempt(): void {
        $this->resetAfterTest();
        $this->build();
        $this->setUser($this->student);

        $started = start_attempt::execute((int)$this->cm->id, false);
        $started = external_api::clean_returnvalue(start_attempt::execute_returns(), $started);

        $this->expectException(\required_capability_exception::class);
        delete_attempt::execute((int)$this->cm->id, $started['attemptid']);
    }

    /**
     * A learner cannot drive somebody else's attempt.
     */
    public function test_student_cannot_submit_against_another_users_attempt(): void {
        $this->resetAfterTest();
        $this->build();

        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        $started = start_attempt::execute((int)$this->cm->id, false);
        $started = external_api::clean_returnvalue(start_attempt::execute_returns(), $started);

        $this->setUser($this->student);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:attemptnotfound', 'mod_aibranchedscenario'));
        submit_choice::execute((int)$this->cm->id, $started['attemptid'], 'start', 'start_a', 1);
    }

    /**
     * A learner cannot read somebody else's debrief.
     */
    public function test_student_cannot_read_another_users_debrief(): void {
        $this->resetAfterTest();
        $this->build();

        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        $started = start_attempt::execute((int)$this->cm->id, false);
        $started = external_api::clean_returnvalue(start_attempt::execute_returns(), $started);

        $this->setUser($this->student);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:attemptnotfound', 'mod_aibranchedscenario'));
        get_debrief::execute((int)$this->cm->id, $started['attemptid']);
    }

    /**
     * A teacher can publish the working copy, and the return value matches its structure.
     */
    public function test_teacher_can_publish(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build(false);
        $this->setUser($this->teacher);

        $result = publish_scenario::execute((int)$this->cm->id);
        $result = external_api::clean_returnvalue(publish_scenario::execute_returns(), $result);

        $this->assertSame(1, $result['revision']);
        $this->assertSame(7, $result['nodecount']);

        $stored = $DB->get_record('aibranchedscenario', ['id' => $this->scenario->id], '*', MUST_EXIST);
        $this->assertSame('published', $stored->status);
        $this->assertSame(1, (int)$stored->revision);

        // Publishing again moves to the next revision.
        $second = publish_scenario::execute((int)$this->cm->id);
        $second = external_api::clean_returnvalue(publish_scenario::execute_returns(), $second);
        $this->assertSame(2, $second['revision']);
    }

    /**
     * A teacher can delete a learner's attempt, and the return value matches its structure.
     */
    public function test_teacher_can_delete_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $this->setUser($this->student);
        $started = start_attempt::execute((int)$this->cm->id, false);
        $started = external_api::clean_returnvalue(start_attempt::execute_returns(), $started);
        submit_choice::execute((int)$this->cm->id, $started['attemptid'], 'start', 'start_a', 1);

        $this->setUser($this->teacher);
        $result = delete_attempt::execute((int)$this->cm->id, $started['attemptid']);
        $result = external_api::clean_returnvalue(delete_attempt::execute_returns(), $result);

        $this->assertTrue($result['deleted']);
        $this->assertFalse($DB->record_exists(
            'aibranchedscenario_attempts',
            ['id' => $started['attemptid']]
        ));
        $this->assertSame(0, $DB->count_records(
            'aibranchedscenario_events',
            ['attemptid' => $started['attemptid']]
        ));
    }
}
