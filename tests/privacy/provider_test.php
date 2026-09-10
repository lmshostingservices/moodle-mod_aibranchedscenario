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

namespace mod_aibranchedscenario\privacy;

use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use mod_aibranchedscenario\local\attempt_manager;
use stdClass;

/**
 * Tests for the privacy provider.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var stdClass Course record. */
    protected $course;

    /** @var stdClass Activity instance record. */
    protected $scenario;

    /** @var context_module Module context. */
    protected $context;

    /** @var stdClass First learner. */
    protected $learner;

    /** @var stdClass Second learner. */
    protected $otherlearner;

    /**
     * Build a published activity with two learners who have each played it once.
     *
     * @return void
     */
    protected function build(): void {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->learner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->otherlearner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        /** @var \mod_aibranchedscenario_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aibranchedscenario');
        $instance = $generator->create_instance(['course' => $this->course->id]);
        $generator->publish_sample($instance, (int)$teacher->id);

        $this->scenario = $DB->get_record('aibranchedscenario', ['id' => $instance->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(
            'aibranchedscenario',
            $this->scenario->id,
            $this->course->id,
            false,
            MUST_EXIST
        );
        $this->context = context_module::instance($cm->id);

        $this->play((int)$this->learner->id, 'a');
        $this->play((int)$this->otherlearner->id, 'b');
    }

    /**
     * Play the scenario through to an outcome for one user.
     *
     * @param int $userid The learner.
     * @param string $letter Either 'a' or 'b'.
     * @return stdClass The finished attempt.
     */
    protected function play(int $userid, string $letter): stdClass {
        $manager = attempt_manager::for_scenario($this->scenario);
        $attempt = $manager->start_or_resume($userid);
        $seq = 1;
        while (true) {
            $node = $manager->get_node($attempt->currentnode);
            if ($node['type'] === 'outcome') {
                break;
            }
            $manager->submit_choice($attempt, $node['id'], $node['id'] . '_' . $letter, $seq);
            $seq++;
        }
        return $attempt;
    }

    /**
     * Every table and external transmission is declared.
     */
    public function test_get_metadata_is_not_empty(): void {
        $this->resetAfterTest();

        $collection = provider::get_metadata(new collection('mod_aibranchedscenario'));
        $items = $collection->get_collection();

        $this->assertNotEmpty($items);

        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
        }
        $this->assertContains('aibranchedscenario_attempts', $names);
        $this->assertContains('aibranchedscenario_events', $names);
        $this->assertContains('aibranchedscenario_jobs', $names);
        $this->assertContains('lmslabs', $names);
    }

    /**
     * A learner with an attempt is found in the module context.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $this->build();

        $contextlist = provider::get_contexts_for_userid((int)$this->learner->id);
        $this->assertCount(1, $contextlist);
        $this->assertSame($this->context->id, (int)$contextlist->get_contextids()[0]);

        $stranger = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertCount(0, provider::get_contexts_for_userid((int)$stranger->id));
    }

    /**
     * The export contains the learner's own attempt and decision journey.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $this->build();

        $this->assertFalse(writer::with_context($this->context)->has_any_data());

        $this->export_context_data_for_user(
            (int)$this->learner->id,
            $this->context,
            'mod_aibranchedscenario'
        );

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $subcontext = [get_string('privacy:attemptpath', 'mod_aibranchedscenario', 1)];
        $data = $writer->get_data($subcontext);

        $this->assertNotEmpty($data);
        $this->assertSame(1, (int)$data->attemptno);
        $this->assertSame(attempt_manager::STATUS_FINISHED, $data->status);
        $this->assertSame('strong', $data->outcome);
        $this->assertEqualsWithDelta(100.0, (float)$data->score, 0.001);
        $this->assertCount(4, $data->journey);
        $this->assertSame('start', $data->journey[0]->nodeid);
        $this->assertSame('start_a', $data->journey[0]->choiceid);
    }

    /**
     * Deleting one user's data leaves everybody else's alone.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $mine = $DB->get_fieldset_select(
            'aibranchedscenario_attempts',
            'id',
            'scenarioid = ? AND userid = ?',
            [$this->scenario->id, $this->learner->id]
        );
        $theirs = $DB->get_fieldset_select(
            'aibranchedscenario_attempts',
            'id',
            'scenarioid = ? AND userid = ?',
            [$this->scenario->id, $this->otherlearner->id]
        );
        $this->assertCount(1, $mine);
        $this->assertCount(1, $theirs);

        $contextlist = new approved_contextlist(
            $this->learner,
            'mod_aibranchedscenario',
            [$this->context->id]
        );
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records(
            'aibranchedscenario_attempts',
            ['scenarioid' => $this->scenario->id, 'userid' => $this->learner->id]
        ));
        $this->assertSame(0, $DB->count_records_select(
            'aibranchedscenario_events',
            'attemptid = ?',
            [reset($mine)]
        ));

        $this->assertSame(1, $DB->count_records(
            'aibranchedscenario_attempts',
            ['scenarioid' => $this->scenario->id, 'userid' => $this->otherlearner->id]
        ));
        $this->assertGreaterThan(0, $DB->count_records_select(
            'aibranchedscenario_events',
            'attemptid = ?',
            [reset($theirs)]
        ));
    }

    /**
     * Deleting the context clears every learner's data in it.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build();

        $attemptids = $DB->get_fieldset_select(
            'aibranchedscenario_attempts',
            'id',
            'scenarioid = ?',
            [$this->scenario->id]
        );
        $this->assertCount(2, $attemptids);

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertSame(0, $DB->count_records(
            'aibranchedscenario_attempts',
            ['scenarioid' => $this->scenario->id]
        ));
        [$insql, $params] = $DB->get_in_or_equal($attemptids);
        $this->assertSame(0, $DB->count_records_select(
            'aibranchedscenario_events',
            "attemptid $insql",
            $params
        ));
        $this->assertSame(0, $DB->count_records(
            'aibranchedscenario_jobs',
            ['scenarioid' => $this->scenario->id]
        ));

        // The scenario itself and its published revision survive.
        $this->assertTrue($DB->record_exists('aibranchedscenario', ['id' => $this->scenario->id]));
        $this->assertSame(1, $DB->count_records(
            'aibranchedscenario_revisions',
            ['scenarioid' => $this->scenario->id]
        ));
    }
}
