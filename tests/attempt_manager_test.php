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
use stdClass;

/**
 * Tests for the server-authoritative attempt engine.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\local\attempt_manager
 */
final class attempt_manager_test extends \advanced_testcase {
    /** @var stdClass Course record. */
    protected $course;

    /** @var stdClass Activity instance record. */
    protected $scenario;

    /** @var stdClass Learner. */
    protected $learner;

    /**
     * Build a published scenario with a learner enrolled.
     *
     * @param array $overrides Extra activity instance settings.
     * @return void
     */
    protected function build_scenario(array $overrides = []): void {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->learner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        /** @var \mod_aibranchedscenario_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aibranchedscenario');
        $instance = $generator->create_instance(array_merge(['course' => $this->course->id], $overrides));
        $generator->publish_sample($instance, (int)$teacher->id);

        $this->scenario = $DB->get_record('aibranchedscenario', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Manager bound to the published revision.
     *
     * @return attempt_manager
     */
    protected function manager(): attempt_manager {
        return attempt_manager::for_scenario($this->scenario);
    }

    /**
     * Walk an attempt through the graph, taking the given choice letter at each node.
     *
     * @param attempt_manager $manager Bound manager.
     * @param stdClass $attempt Attempt record, updated in place.
     * @param string $letter Either 'a' or 'b'.
     * @return array The result of the final submission.
     */
    protected function walk(attempt_manager $manager, stdClass &$attempt, string $letter): array {
        $seq = 1;
        $result = [];
        while (true) {
            $node = $manager->get_node($attempt->currentnode);
            if ($node['type'] === 'outcome') {
                break;
            }
            $result = $manager->submit_choice($attempt, $node['id'], $node['id'] . '_' . $letter, $seq);
            $seq++;
        }
        return $result;
    }

    /**
     * A first call creates an attempt sitting on the start node with the opening metrics.
     */
    public function test_start_creates_attempt_at_start_node(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);

        $this->assertSame(attempt_manager::STATUS_INPROGRESS, $attempt->status);
        $this->assertSame('start', $attempt->currentnode);
        $this->assertSame(1, (int)$attempt->attemptno);
        $this->assertSame(50, (int)$attempt->engagement);
        $this->assertSame(50, (int)$attempt->trust);
        $this->assertSame(30, (int)$attempt->tension);
        $this->assertSame(0, (int)$attempt->presence);
        $this->assertNull($attempt->score);

        $state = $manager->decode_state($attempt);
        $this->assertSame(['start'], $state['visited']);
        $this->assertSame(0, (int)$state['decisions']);
    }

    /**
     * Calling again while an attempt is open resumes it rather than starting another.
     */
    public function test_resume_returns_the_same_attempt(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $first = $manager->start_or_resume((int)$this->learner->id);
        $second = $manager->start_or_resume((int)$this->learner->id);

        $this->assertSame((int)$first->id, (int)$second->id);
        $this->assertSame(1, $manager->count_user_attempts((int)$this->learner->id));
    }

    /**
     * A submission applies clamped effects, accumulates skills, logs an event and advances.
     */
    public function test_submit_choice_applies_effects_and_advances(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);

        $result = $manager->submit_choice($attempt, 'start', 'start_a', 1);

        $this->assertSame('pathone', $result['nextnodeid']);
        $this->assertFalse($result['finished']);
        $this->assertSame('positive', $result['signal']);
        $this->assertSame('Report faults before the next shift', $result['principle']);
        $this->assertSame(['engagement' => 50, 'trust' => 50, 'tension' => 30], $result['before']);

        // Engagement and trust rise by 40; tension would drop to -10 and clamps at 0.
        $this->assertSame(90, (int)$attempt->engagement);
        $this->assertSame(90, (int)$attempt->trust);
        $this->assertSame(0, (int)$attempt->tension);
        $this->assertSame(['engagement' => 90, 'trust' => 90, 'tension' => 0], $result['after']);

        foreach (['presence', 'adaptability', 'empathy', 'clarity'] as $skill) {
            $this->assertSame(2, (int)$attempt->{$skill});
        }

        $this->assertSame('pathone', $attempt->currentnode);
        $this->assertSame(1, (int)$manager->decode_state($attempt)['decisions']);

        $events = attempt_manager::get_events((int)$attempt->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame('start', $event->nodeid);
        $this->assertSame('start_a', $event->choiceid);
        $this->assertSame('pathone', $event->nextnodeid);

        // A second decision keeps accumulating and clamps at the top of the range.
        $manager->submit_choice($attempt, 'pathone', 'pathone_a', 2);
        $this->assertSame(100, (int)$attempt->engagement);
        $this->assertSame(100, (int)$attempt->trust);
        $this->assertSame(0, (int)$attempt->tension);
        $this->assertSame(4, (int)$attempt->presence);
        $this->assertSame(2, $DB->count_records('aibranchedscenario_events', ['attemptid' => $attempt->id]));
    }

    /**
     * Replaying the same sequence number with the same choice is answered from the log.
     */
    public function test_repeat_submission_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);

        $first = $manager->submit_choice($attempt, 'start', 'start_a', 1);
        $again = $manager->submit_choice($attempt, 'start', 'start_a', 1);

        $this->assertSame(1, $DB->count_records('aibranchedscenario_events', ['attemptid' => $attempt->id]));
        $this->assertSame($first['nextnodeid'], $again['nextnodeid']);
        $this->assertSame($first['consequence'], $again['consequence']);
        $this->assertSame($first['after'], $again['after']);
        $this->assertSame($first['before'], $again['before']);
        $this->assertSame($first['finished'], $again['finished']);
        $this->assertSame(90, (int)$attempt->engagement);
    }

    /**
     * Replaying a sequence number with a different choice is a conflict, not a rewrite.
     */
    public function test_conflicting_replay_throws(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);
        $manager->submit_choice($attempt, 'start', 'start_a', 1);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:sequenceconflict', 'mod_aibranchedscenario'));
        $manager->submit_choice($attempt, 'start', 'start_b', 1);
    }

    /**
     * A submission out of sequence is rejected.
     */
    public function test_wrong_sequence_throws(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:sequenceconflict', 'mod_aibranchedscenario'));
        $manager->submit_choice($attempt, 'start', 'start_a', 7);
    }

    /**
     * A choice that belongs to a different node is rejected.
     */
    public function test_choice_from_another_node_throws(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:unknownchoice', 'mod_aibranchedscenario'));
        $manager->submit_choice($attempt, 'start', 'bottleneck_a', 1);
    }

    /**
     * Reaching an outcome node finishes the attempt, scores it and records the band.
     */
    public function test_reaching_outcome_finishes_the_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);
        $result = $this->walk($manager, $attempt, 'a');

        $this->assertTrue($result['finished']);
        $this->assertSame(attempt_manager::STATUS_FINISHED, $attempt->status);
        $this->assertSame('strong', $attempt->outcome);
        $this->assertGreaterThan(0, (int)$attempt->timefinished);

        $stored = $DB->get_record('aibranchedscenario_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(attempt_manager::STATUS_FINISHED, $stored->status);
        $this->assertEqualsWithDelta(100.0, (float)$stored->score, 0.001);
        $this->assertSame('outcomestrong', $stored->currentnode);
    }

    /**
     * The score maps the best path to 100 and the worst path to 0.
     */
    public function test_calculate_score_spans_the_full_range(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();

        $best = $manager->start_or_resume((int)$this->learner->id);
        $this->walk($manager, $best, 'a');
        $this->assertEqualsWithDelta(100.0, $manager->calculate_score($best), 0.001);
        $this->assertSame('strong', $manager->score_band($manager->calculate_score($best)));

        $worst = $manager->start_or_resume((int)$this->learner->id, true);
        $this->walk($manager, $worst, 'b');
        $this->assertEqualsWithDelta(0.0, $manager->calculate_score($worst), 0.001);
        $this->assertSame('highrisk', $manager->score_band($manager->calculate_score($worst)));
    }

    /**
     * The automatic target lands on the outcome node matching the attempt's band.
     */
    public function test_auto_target_resolves_by_band(): void {
        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();

        $best = $manager->start_or_resume((int)$this->learner->id);
        $this->walk($manager, $best, 'a');
        $this->assertSame('outcomestrong', $best->currentnode);
        $this->assertSame('strong', $best->outcome);

        $worst = $manager->start_or_resume((int)$this->learner->id, true);
        $this->walk($manager, $worst, 'b');
        $this->assertSame('outcomerisk', $worst->currentnode);
        $this->assertSame('highrisk', $worst->outcome);
    }

    /**
     * The attempt ceiling is enforced.
     */
    public function test_maxattempts_is_enforced(): void {
        $this->resetAfterTest();
        $this->build_scenario(['maxattempts' => 1]);

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);
        $this->walk($manager, $attempt, 'a');

        $this->assertFalse($manager->can_start_new_attempt((int)$this->learner->id));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:noattemptsleft', 'mod_aibranchedscenario'));
        $manager->start_or_resume((int)$this->learner->id, true);
    }

    /**
     * Deleting an attempt takes its decision log with it.
     */
    public function test_delete_attempt_removes_events(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build_scenario();

        $manager = $this->manager();
        $attempt = $manager->start_or_resume((int)$this->learner->id);
        $this->walk($manager, $attempt, 'a');

        $this->assertGreaterThan(0, $DB->count_records(
            'aibranchedscenario_events',
            ['attemptid' => $attempt->id]
        ));

        attempt_manager::delete_attempt((int)$attempt->id);

        $this->assertSame(0, $DB->count_records(
            'aibranchedscenario_events',
            ['attemptid' => $attempt->id]
        ));
        $this->assertFalse($DB->record_exists('aibranchedscenario_attempts', ['id' => $attempt->id]));
    }

    /**
     * Every aggregation method is honoured, and an unscored user aggregates to null.
     */
    public function test_aggregate_score_honours_each_grademethod(): void {
        global $DB;

        $this->resetAfterTest();
        $this->build_scenario();

        $revision = $DB->get_record(
            'aibranchedscenario_revisions',
            ['scenarioid' => $this->scenario->id, 'revision' => 1],
            '*',
            MUST_EXIST
        );

        $scores = [20.0, 90.0, 40.0];
        foreach ($scores as $index => $score) {
            $DB->insert_record('aibranchedscenario_attempts', (object)[
                'scenarioid'   => $this->scenario->id,
                'revisionid'   => $revision->id,
                'userid'       => $this->learner->id,
                'attemptno'    => $index + 1,
                'status'       => attempt_manager::STATUS_FINISHED,
                'currentnode'  => 'outcomestrong',
                'statejson'    => json_encode(['visited' => [], 'decisions' => 4]),
                'engagement'   => 50, 'trust' => 50, 'tension' => 30,
                'presence'     => 0, 'adaptability' => 0, 'empathy' => 0, 'clarity' => 0,
                'score'        => $score,
                'outcome'      => 'mixed',
                'timestarted'  => time(),
                'timemodified' => time(),
                'timefinished' => time(),
            ]);
        }

        $expected = ['first' => 20.0, 'last' => 40.0, 'highest' => 90.0, 'average' => 50.0];
        foreach ($expected as $method => $value) {
            $this->scenario->grademethod = $method;
            $this->assertEqualsWithDelta(
                $value,
                attempt_manager::aggregate_score($this->scenario, (int)$this->learner->id),
                0.001,
                'Aggregation method ' . $method . ' returned the wrong value.'
            );
        }

        $stranger = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertNull(attempt_manager::aggregate_score($this->scenario, (int)$stranger->id));
    }
}
