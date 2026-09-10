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

namespace mod_aibranchedscenario\local;

use context_module;
use moodle_exception;
use stdClass;

/**
 * Server-authoritative attempt engine.
 *
 * The browser never sends metrics, scores or node payloads. It sends the id of the
 * choice it believes it is on, and this class decides whether that choice is legal
 * for the attempt's current node, what it does to the metrics, and where it leads.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /** @var string Attempt in progress. */
    const STATUS_INPROGRESS = 'inprogress';

    /** @var string Attempt finished. */
    const STATUS_FINISHED = 'finished';

    /** @var string Attempt abandoned by a new replay. */
    const STATUS_ABANDONED = 'abandoned';

    /** @var stdClass The activity instance record. */
    protected $scenario;

    /** @var stdClass The published revision record used by this attempt. */
    protected $revision;

    /** @var array Decoded, validated scenario definition. */
    protected $definition;

    /** @var array Node lookup keyed by node id. */
    protected $nodes = [];

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance record.
     * @param stdClass $revision Published revision record.
     */
    public function __construct(stdClass $scenario, stdClass $revision) {
        $this->scenario = $scenario;
        $this->revision = $revision;
        $decoded = json_decode($revision->scenariojson, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('error:revisionunreadable', 'mod_aibranchedscenario');
        }
        $this->definition = $decoded;
        foreach ($this->definition['nodes'] as $node) {
            $this->nodes[$node['id']] = $node;
        }
    }

    /**
     * Build the manager for an activity instance, using its current published revision.
     *
     * @param stdClass $scenario Activity instance record.
     * @return self
     */
    public static function for_scenario(stdClass $scenario): self {
        global $DB;
        $revision = $DB->get_record(
            'aibranchedscenario_revisions',
            ['scenarioid' => $scenario->id, 'revision' => $scenario->revision],
            '*',
            IGNORE_MISSING
        );
        if (!$revision) {
            throw new moodle_exception('error:notpublished', 'mod_aibranchedscenario');
        }
        return new self($scenario, $revision);
    }

    /**
     * Build the manager bound to the revision a given attempt started on.
     *
     * @param stdClass $scenario Activity instance record.
     * @param stdClass $attempt Attempt record.
     * @return self
     */
    public static function for_attempt(stdClass $scenario, stdClass $attempt): self {
        global $DB;
        $revision = $DB->get_record('aibranchedscenario_revisions', ['id' => $attempt->revisionid], '*', MUST_EXIST);
        return new self($scenario, $revision);
    }

    /**
     * Get the decoded scenario definition.
     *
     * @return array
     */
    public function get_definition(): array {
        return $this->definition;
    }

    /**
     * Get the revision record this manager is bound to.
     *
     * @return stdClass
     */
    public function get_revision(): stdClass {
        return $this->revision;
    }

    /**
     * Fetch a node by id.
     *
     * @param string $nodeid Node identifier.
     * @return array|null
     */
    public function get_node(string $nodeid): ?array {
        return $this->nodes[$nodeid] ?? null;
    }

    /**
     * Load an attempt owned by the given user, or null.
     *
     * @param int $attemptid Attempt id.
     * @param int $userid Owning user id.
     * @return stdClass|null
     */
    public static function get_owned_attempt(int $attemptid, int $userid): ?stdClass {
        global $DB;
        $attempt = $DB->get_record('aibranchedscenario_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt || (int)$attempt->userid !== $userid) {
            return null;
        }
        return $attempt;
    }

    /**
     * Return the learner's in-progress attempt, if any.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public function get_open_attempt(int $userid): ?stdClass {
        global $DB;
        $records = $DB->get_records('aibranchedscenario_attempts', [
            'scenarioid' => $this->scenario->id,
            'userid'     => $userid,
            'status'     => self::STATUS_INPROGRESS,
        ], 'attemptno DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Return all attempts by a user, newest first.
     *
     * @param int $userid User id.
     * @return stdClass[]
     */
    public function get_user_attempts(int $userid): array {
        global $DB;
        return $DB->get_records('aibranchedscenario_attempts', [
            'scenarioid' => $this->scenario->id,
            'userid'     => $userid,
        ], 'attemptno DESC');
    }

    /**
     * Count attempts a user has already started.
     *
     * @param int $userid User id.
     * @return int
     */
    public function count_user_attempts(int $userid): int {
        global $DB;
        return $DB->count_records('aibranchedscenario_attempts', [
            'scenarioid' => $this->scenario->id,
            'userid'     => $userid,
        ]);
    }

    /**
     * The most recently finished attempt, if there is one.
     *
     * A learner who completes the scenario, closes the tab and comes back tomorrow to
     * re-read their debrief used to be shown one button, "Begin the scenario". Pressing
     * it spent another of a limited allowance and put the earlier result out of reach
     * for good; with one attempt allowed it threw an error and the result was simply
     * gone. Their own finished attempt is theirs to look at again.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public function get_last_finished_attempt(int $userid): ?stdClass {
        global $DB;

        $records = $DB->get_records(
            'aibranchedscenario_attempts',
            ['scenarioid' => $this->scenario->id, 'userid' => $userid, 'status' => self::STATUS_FINISHED],
            'attemptno DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Whether the user may start a further attempt.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function can_start_new_attempt(int $userid): bool {
        if (empty($this->scenario->maxattempts)) {
            return true;
        }
        return $this->count_user_attempts($userid) < (int)$this->scenario->maxattempts;
    }

    /**
     * Resume the learner's open attempt or start a new one.
     *
     * @param int $userid User id.
     * @param bool $forcenew Start a new attempt even when one is open.
     * @return stdClass Attempt record.
     */
    public function start_or_resume(int $userid, bool $forcenew = false): stdClass {
        global $DB;

        if (!$forcenew) {
            $open = $this->get_open_attempt($userid);
            if ($open) {
                return $open;
            }
        }

        if (!$this->can_start_new_attempt($userid)) {
            throw new moodle_exception('error:noattemptsleft', 'mod_aibranchedscenario');
        }

        $transaction = $DB->start_delegated_transaction();

        if ($forcenew) {
            $open = $this->get_open_attempt($userid);
            if ($open) {
                $open->status = self::STATUS_ABANDONED;
                $open->timemodified = time();
                $DB->update_record('aibranchedscenario_attempts', $open);
            }
        }

        $max = (int)$DB->get_field_sql(
            'SELECT MAX(attemptno) FROM {aibranchedscenario_attempts} WHERE scenarioid = ? AND userid = ?',
            [$this->scenario->id, $userid]
        );

        $opening = $this->definition['openingmetrics'];
        $attempt = (object)[
            'scenarioid'   => $this->scenario->id,
            'revisionid'   => $this->revision->id,
            'userid'       => $userid,
            'attemptno'    => $max + 1,
            'status'       => self::STATUS_INPROGRESS,
            'currentnode'  => $this->definition['startnode'],
            'statejson'    => json_encode(['visited' => [$this->definition['startnode']], 'decisions' => 0]),
            'engagement'   => $opening['engagement'],
            'trust'        => $opening['trust'],
            'tension'      => $opening['tension'],
            'presence'     => 0,
            'adaptability' => 0,
            'empathy'      => 0,
            'clarity'      => 0,
            'score'        => null,
            'outcome'      => '',
            'timestarted'  => time(),
            'timemodified' => time(),
            'timefinished' => 0,
        ];
        $attempt->id = $DB->insert_record('aibranchedscenario_attempts', $attempt);

        $transaction->allow_commit();
        return $attempt;
    }

    /**
     * Apply a learner's choice to an attempt.
     *
     * The submission is idempotent: replaying the same sequence number with the same
     * choice returns the state that submission produced instead of appending again.
     *
     * @param stdClass $attempt Attempt record (by reference, updated in place).
     * @param string $nodeid Node the browser believes it is on.
     * @param string $choiceid Chosen choice id.
     * @param int $seq Client sequence number, starting at 1.
     * @return array Result payload with the consequence, feedback and next node.
     */
    public function submit_choice(stdClass &$attempt, string $nodeid, string $choiceid, int $seq): array {
        global $DB;

        // Always work from the stored row. A retried request arrives in a fresh PHP
        // process, so the caller's copy of the attempt cannot be trusted to be current.
        $attempt = $DB->get_record('aibranchedscenario_attempts', ['id' => $attempt->id], '*', MUST_EXIST);

        // Idempotency: an identical replay of an already recorded step is answered from the log.
        $existing = $DB->get_record('aibranchedscenario_events', ['attemptid' => $attempt->id, 'seq' => $seq]);
        if ($existing) {
            if ($existing->nodeid !== $nodeid || $existing->choiceid !== $choiceid) {
                throw new moodle_exception('error:sequenceconflict', 'mod_aibranchedscenario');
            }
            return $this->replay_event($attempt, $existing);
        }

        if ($attempt->status !== self::STATUS_INPROGRESS) {
            throw new moodle_exception('error:attemptnotopen', 'mod_aibranchedscenario');
        }

        $expectedseq = (int)$DB->count_records('aibranchedscenario_events', ['attemptid' => $attempt->id]) + 1;
        if ($seq !== $expectedseq) {
            throw new moodle_exception('error:sequenceconflict', 'mod_aibranchedscenario');
        }

        if ($attempt->currentnode !== $nodeid) {
            throw new moodle_exception('error:wrongnode', 'mod_aibranchedscenario');
        }

        $node = $this->get_node($nodeid);
        if (!$node) {
            throw new moodle_exception('error:unknownnode', 'mod_aibranchedscenario');
        }

        $choice = null;
        foreach ($node['choices'] as $candidate) {
            if ($candidate['id'] === $choiceid) {
                $choice = $candidate;
                break;
            }
        }
        if ($choice === null) {
            throw new moodle_exception('error:unknownchoice', 'mod_aibranchedscenario');
        }

        $transaction = $DB->start_delegated_transaction();

        $before = [
            'engagement' => (int)$attempt->engagement,
            'trust'      => (int)$attempt->trust,
            'tension'    => (int)$attempt->tension,
        ];

        foreach (schema::metrics() as $metric) {
            $attempt->{$metric} = max(0, min(100, (int)$attempt->{$metric} + (int)$choice['effects'][$metric]));
        }
        foreach (schema::skills() as $skill) {
            $attempt->{$skill} = (int)$attempt->{$skill} + (int)$choice['skills'][$skill];
        }

        $state = $this->decode_state($attempt);
        if ($node['type'] === 'decision') {
            $state['decisions'] = (int)($state['decisions'] ?? 0) + 1;
        }

        $nextid = $this->resolve_target($choice['next'], $attempt, $state);
        $state['visited'][] = $nextid;
        $state['visited'] = array_values(array_unique($state['visited']));

        $attempt->currentnode = $nextid;
        $attempt->statejson = json_encode($state);
        $attempt->timemodified = time();

        $event = (object)[
            'attemptid'   => $attempt->id,
            'seq'         => $seq,
            'nodeid'      => $nodeid,
            'choiceid'    => $choiceid,
            'nextnodeid'  => $nextid,
            'signaltype'  => $choice['signal'],
            'engagement'  => (int)$attempt->engagement,
            'trust'       => (int)$attempt->trust,
            'tension'     => (int)$attempt->tension,
            'timecreated' => time(),
        ];
        $event->id = $DB->insert_record('aibranchedscenario_events', $event);

        $nextnode = $this->get_node($nextid);
        $finished = $nextnode !== null && $nextnode['type'] === 'outcome';
        if ($finished) {
            $this->mark_finished($attempt, $nextnode['outcome']);
        } else {
            $DB->update_record('aibranchedscenario_attempts', $attempt);
        }

        $transaction->allow_commit();

        return [
            'consequence' => $choice['consequence'],
            'feedback'    => $choice['feedback'],
            'signal'      => $choice['signal'],
            'principle'   => $this->principle_title($choice['principleid']),
            'before'      => $before,
            'after'       => [
                'engagement' => (int)$attempt->engagement,
                'trust'      => (int)$attempt->trust,
                'tension'    => (int)$attempt->tension,
            ],
            'nextnodeid'  => $nextid,
            'finished'    => $finished,
            'seq'         => $seq,
        ];
    }

    /**
     * Rebuild the response for an already recorded event, so a retried request is safe.
     *
     * @param stdClass $attempt Attempt record.
     * @param stdClass $event Stored event.
     * @return array
     */
    protected function replay_event(stdClass $attempt, stdClass $event): array {
        global $DB;
        $node = $this->get_node($event->nodeid);
        $choice = null;
        if ($node) {
            foreach ($node['choices'] as $candidate) {
                if ($candidate['id'] === $event->choiceid) {
                    $choice = $candidate;
                    break;
                }
            }
        }
        $previous = $DB->get_record(
            'aibranchedscenario_events',
            ['attemptid' => $attempt->id, 'seq' => $event->seq - 1]
        );
        $before = $previous
            ? ['engagement' => (int)$previous->engagement, 'trust' => (int)$previous->trust,
                'tension' => (int)$previous->tension]
            : $this->definition['openingmetrics'];
        $nextnode = $this->get_node($event->nextnodeid);
        return [
            'consequence' => $choice['consequence'] ?? '',
            'feedback'    => $choice['feedback'] ?? '',
            'signal'      => $event->signaltype,
            'principle'   => $this->principle_title($choice['principleid'] ?? ''),
            'before'      => $before,
            'after'       => ['engagement' => (int)$event->engagement, 'trust' => (int)$event->trust,
                'tension' => (int)$event->tension],
            'nextnodeid'  => $event->nextnodeid,
            'finished'    => $nextnode !== null && $nextnode['type'] === 'outcome',
            'seq'         => (int)$event->seq,
        ];
    }

    /**
     * Resolve a choice target, including the automatic outcome target.
     *
     * @param string $target Declared target.
     * @param stdClass $attempt Attempt record with current skill totals.
     * @param array $state Decoded attempt state.
     * @return string Node id.
     */
    protected function resolve_target(string $target, stdClass $attempt, array $state): string {
        if ($target !== schema::auto_target() && isset($this->nodes[$target])) {
            return $target;
        }
        $band = $this->score_band($this->calculate_score($attempt, $state));
        $fallback = '';
        foreach ($this->definition['nodes'] as $node) {
            if ($node['type'] !== 'outcome') {
                continue;
            }
            $fallback = $fallback ?: $node['id'];
            if ($node['outcome'] === $band) {
                return $node['id'];
            }
        }
        if ($fallback === '') {
            throw new moodle_exception('error:nooutcomenode', 'mod_aibranchedscenario');
        }
        return $fallback;
    }

    /**
     * Turn a percentage score into an outcome band.
     *
     * @param float $percent Score out of 100.
     * @return string One of the outcome bands.
     */
    public function score_band(float $percent): string {
        if ($percent >= 75.0) {
            return 'strong';
        }
        if ($percent >= 50.0) {
            return 'mixed';
        }
        return 'highrisk';
    }

    /**
     * Calculate the attempt's score as a percentage of the best available on its own path.
     *
     * Every decision offers each of the four skills a delta in the range -2..+2, so a
     * path of N decisions spans -8N..+8N. The score maps that span onto 0..100.
     *
     * @param stdClass $attempt Attempt record.
     * @param array|null $state Decoded state, loaded when omitted.
     * @return float Percentage 0..100.
     */
    public function calculate_score(stdClass $attempt, ?array $state = null): float {
        $state = $state ?? $this->decode_state($attempt);
        $decisions = max(1, (int)($state['decisions'] ?? 0));
        $span = $decisions * count(schema::skills()) * schema::MAX_SKILL_DELTA;
        $raw = 0;
        foreach (schema::skills() as $skill) {
            $raw += (int)$attempt->{$skill};
        }
        $raw = max(-$span, min($span, $raw));
        return round((($raw + $span) / (2 * $span)) * 100, 5);
    }

    /**
     * Mark an attempt finished, store its score and update completion and grades.
     *
     * @param stdClass $attempt Attempt record, updated in place.
     * @param string $outcome Outcome band reached.
     * @return void
     */
    public function mark_finished(stdClass &$attempt, string $outcome): void {
        global $DB;
        $attempt->status = self::STATUS_FINISHED;
        $attempt->outcome = schema::in_list($outcome, schema::outcomes()) ? $outcome : 'mixed';
        $attempt->score = $this->calculate_score($attempt);
        $attempt->timefinished = time();
        $attempt->timemodified = time();
        $DB->update_record('aibranchedscenario_attempts', $attempt);
    }

    /**
     * Decode the attempt state blob defensively.
     *
     * @param stdClass $attempt Attempt record.
     * @return array
     */
    public function decode_state(stdClass $attempt): array {
        $state = json_decode((string)$attempt->statejson, true);
        if (!is_array($state)) {
            $state = [];
        }
        if (!isset($state['visited']) || !is_array($state['visited'])) {
            $state['visited'] = [];
        }
        if (!isset($state['decisions'])) {
            $state['decisions'] = 0;
        }
        return $state;
    }

    /**
     * Look up the title of a decision principle.
     *
     * @param string $principleid Principle id.
     * @return string Empty string when unknown.
     */
    public function principle_title(string $principleid): string {
        if ($principleid === '') {
            return '';
        }
        foreach ($this->definition['principles'] as $principle) {
            if ($principle['id'] === $principleid) {
                return $principle['title'];
            }
        }
        return '';
    }

    /**
     * Ordered event log for an attempt.
     *
     * @param int $attemptid Attempt id.
     * @return stdClass[]
     */
    public static function get_events(int $attemptid): array {
        global $DB;
        return $DB->get_records('aibranchedscenario_events', ['attemptid' => $attemptid], 'seq ASC');
    }

    /**
     * Build the journey shown in the debrief: each decision, what was chosen and what followed.
     *
     * @param stdClass $attempt Attempt record.
     * @return array
     */
    public function build_journey(stdClass $attempt): array {
        $journey = [];
        foreach (self::get_events((int)$attempt->id) as $event) {
            $node = $this->get_node($event->nodeid);
            if (!$node) {
                continue;
            }
            $chosen = null;
            foreach ($node['choices'] as $choice) {
                if ($choice['id'] === $event->choiceid) {
                    $chosen = $choice;
                    break;
                }
            }
            if (!$chosen) {
                continue;
            }
            $journey[] = [
                'seq'         => (int)$event->seq,
                'nodetitle'   => $node['title'] !== '' ? $node['title'] : $node['id'],
                'choicetext'  => $chosen['text'],
                'signal'      => $chosen['signal'],
                'consequence' => $chosen['consequence'],
                'feedback'    => $chosen['feedback'],
                'principle'   => $this->principle_title($chosen['principleid']),
            ];
        }
        return $journey;
    }

    /**
     * Radar values for the four skills, each normalised into 0.08..1.
     *
     * @param stdClass $attempt Attempt record.
     * @return array skill => float
     */
    public function radar_values(stdClass $attempt): array {
        $state = $this->decode_state($attempt);
        $span = max(1, (int)($state['decisions'] ?? 1)) * schema::MAX_SKILL_DELTA;
        $values = [];
        foreach (schema::skills() as $skill) {
            $raw = max(-$span, min($span, (int)$attempt->{$skill}));
            $values[$skill] = round(max(0.08, min(1.0, ($raw + $span) / (2 * $span))), 4);
        }
        return $values;
    }

    /**
     * Delete an attempt and its events.
     *
     * @param int $attemptid Attempt id.
     * @return void
     */
    public static function delete_attempt(int $attemptid): void {
        global $DB;
        $DB->delete_records('aibranchedscenario_events', ['attemptid' => $attemptid]);
        $DB->delete_records('aibranchedscenario_attempts', ['id' => $attemptid]);
    }

    /**
     * Aggregate a user's attempts into the single grade the gradebook stores.
     *
     * @param stdClass $scenario Activity instance record.
     * @param int $userid User id.
     * @return float|null Percentage, or null when there is nothing to grade.
     */
    public static function aggregate_score(stdClass $scenario, int $userid): ?float {
        global $DB;
        $attempts = $DB->get_records_select(
            'aibranchedscenario_attempts',
            'scenarioid = :sid AND userid = :uid AND status = :status AND score IS NOT NULL',
            ['sid' => $scenario->id, 'uid' => $userid, 'status' => self::STATUS_FINISHED],
            'attemptno ASC'
        );
        if (!$attempts) {
            return null;
        }
        $scores = array_map(function ($attempt) {
            return (float)$attempt->score;
        }, $attempts);
        switch ($scenario->grademethod) {
            case 'first':
                return reset($scores);
            case 'highest':
                return max($scores);
            case 'average':
                return array_sum($scores) / count($scores);
            case 'last':
            default:
                return end($scores);
        }
    }

    /**
     * Context helper.
     *
     * @param stdClass $cm Course module record or cm_info.
     * @return context_module
     */
    public static function context($cm): context_module {
        return context_module::instance($cm->id);
    }
}
