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

use moodle_exception;
use stdClass;

/**
 * Storage and lifecycle operations for scenario definitions.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenario_manager {
    /** @var string Working copy only. */
    const STATUS_DRAFT = 'draft';

    /** @var string At least one revision published. */
    const STATUS_PUBLISHED = 'published';

    /**
     * The row for one rung of an activity's ladder, created empty if it does not exist.
     *
     * THE LADDER IS THE SINGLE DESCRIPTION OF WHAT AN ACTIVITY HOLDS.
     *
     * An activity used to be one scenario, stored in columns on the instance itself. It
     * holds three now - foundation, intermediate, advanced - and every one of them is a row
     * here. The instance's own scenariojson, previousjson and revision columns are legacy:
     * the upgrade copied them into tier 1 and nothing reads them any more.
     *
     * Created on demand rather than at install time, because a teacher who never generates
     * the advanced scenario should not have an empty row implying they abandoned one.
     *
     * @param int $scenarioid Activity instance id.
     * @param int $tier Which rung, 1 to schema::TIERS.
     * @return stdClass The rung.
     */
    public static function tier_row(int $scenarioid, int $tier = 1): stdClass {
        global $DB;
        $tier = max(1, min(schema::TIERS, $tier));
        $row = $DB->get_record(
            'aibranchedscenario_tiers',
            ['scenarioid' => $scenarioid, 'tier' => $tier],
            '*',
            IGNORE_MISSING
        );
        if ($row) {
            return $row;
        }
        $now = time();
        $row = (object)[
            'scenarioid'     => $scenarioid,
            'tier'           => $tier,
            'status'         => self::STATUS_DRAFT,
            'scenariojson'   => null,
            'previousjson'   => null,
            'generationmeta' => null,
            'revision'       => 0,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ];
        $row->id = $DB->insert_record('aibranchedscenario_tiers', $row);
        return $row;
    }

    /**
     * Every rung of an activity's ladder, lowest first, whether or not it has content.
     *
     * @param int $scenarioid Activity instance id.
     * @return stdClass[] Tier number to row.
     */
    public static function ladder(int $scenarioid): array {
        $out = [];
        foreach (array_keys(schema::tiers()) as $tier) {
            $out[$tier] = self::tier_row($scenarioid, $tier);
        }
        return $out;
    }

    /**
     * Read the working-copy definition for one rung, or null when there is none yet.
     *
     * @param stdClass $scenario Activity instance record.
     * @param int $tier Which rung.
     * @return array|null
     */
    public static function get_working_definition(stdClass $scenario, int $tier = 1): ?array {
        $row = self::tier_row((int)$scenario->id, $tier);
        if (empty($row->scenariojson)) {
            return null;
        }
        $decoded = json_decode($row->scenariojson, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Read the stored wizard inputs for an activity.
     *
     * @param stdClass $scenario Activity instance record.
     * @return array
     */
    public static function get_source(stdClass $scenario): array {
        if (empty($scenario->sourcejson)) {
            return [];
        }
        $decoded = json_decode($scenario->sourcejson, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Store the wizard inputs. The inputs are normalised against the option lists first.
     *
     * @param stdClass $scenario Activity instance record.
     * @param array $source Raw wizard inputs.
     * @return array The normalised inputs as stored.
     */
    public static function save_source(stdClass $scenario, array $source): array {
        global $DB;
        $clean = source_normaliser::normalise($source);
        $DB->update_record('aibranchedscenario', (object)[
            'id'           => $scenario->id,
            'sourcejson'   => json_encode($clean),
            'timemodified' => time(),
        ]);
        $scenario->sourcejson = json_encode($clean);
        return $clean;
    }

    /**
     * Validate and store a working-copy scenario definition.
     *
     * @param stdClass $scenario Activity instance record.
     * @param array $definition Raw definition.
     * @param array|null $generationmeta Non-secret metadata about how it was produced.
     * @return array The validated definition as stored.
     */
    public static function save_definition(
        stdClass $scenario,
        array $definition,
        ?array $generationmeta = null,
        bool $strictshape = true,
        int $tier = 1
    ): array {
        global $DB;
        // Strict by default, because most callers are saving something newly made. An edit
        // to a draft that already exists passes false - see publish() for why an upgrade
        // must not make a teacher's own work unsaveable.
        $clean = validator::validate($definition, $strictshape);
        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > schema::MAX_SCENARIO_BYTES) {
            throw new validation_exception([get_string('error:scenariotoolarge', 'mod_aibranchedscenario')]);
        }
        // Keep the copy this replaces, so one generation or import can be undone. An
        // hour of hand editing used to disappear on a single click of Generate with no
        // warning and nothing to go back to.
        //
        // WHAT IT REPLACES IS READ FROM THE DATABASE, not from the record handed in.
        //
        // Generation calls this at the end of a run that takes minutes, holding an activity
        // record fetched before the run started. A teacher who fixed a typo while waiting
        // had that edit overwritten by the finishing generation - normal enough - but
        // previousjson was then written from the pre-edit copy the caller was holding, so
        // the edit was gone from BOTH slots and Restore draft put back a version that had
        // never existed. The undo has to preserve what was actually there a moment ago.
        $row = self::tier_row((int)$scenario->id, $tier);
        $update = (object)[
            'id'           => $row->id,
            'previousjson' => $row->scenariojson,
            'scenariojson' => $encoded,
            'timemodified' => time(),
        ];
        if ($generationmeta !== null) {
            $update->generationmeta = json_encode(self::sanitise_meta($generationmeta));
        }
        $DB->update_record('aibranchedscenario_tiers', $update);
        // The instance record the caller is holding is updated too, so code that has not
        // been moved onto the ladder yet still sees the foundation scenario where it
        // expects it. The DATABASE columns are not written: the ladder is the single
        // description of what an activity holds, and two places holding the same answer is
        // how they come to disagree.
        if ($tier === 1) {
            $scenario->previousjson = $update->previousjson;
            $scenario->scenariojson = $encoded;
            if ($generationmeta !== null) {
                $scenario->generationmeta = $update->generationmeta;
            }
        }
        return $clean;
    }

    /**
     * Put the previous working copy back.
     *
     * Exactly one step is kept. Restoring swaps the two, so a teacher who undoes by
     * mistake can redo, and cannot dig further back than that.
     *
     * @param stdClass $scenario Activity instance.
     * @return bool True when something was restored.
     */
    public static function restore_previous(stdClass $scenario, int $tier = 1): bool {
        global $DB;

        $row = self::tier_row((int)$scenario->id, $tier);
        $previous = $row->previousjson;
        if (empty($previous)) {
            return false;
        }
        $decoded = json_decode($previous, true);
        if (!is_array($decoded)) {
            return false;
        }

        $DB->update_record('aibranchedscenario_tiers', (object)[
            'id'           => $row->id,
            'scenariojson' => $previous,
            'previousjson' => $row->scenariojson,
            'timemodified' => time(),
        ]);
        if ($tier === 1) {
            $swap = $row->scenariojson;
            $scenario->scenariojson = $previous;
            $scenario->previousjson = $swap;
        }
        return true;
    }

    /**
     * Strip anything that could carry a credential out of generation metadata.
     *
     * @param array $meta Raw metadata.
     * @return array
     */
    protected static function sanitise_meta(array $meta): array {
        // The standardnotstated key holds the rules the content standard could not fit into
        // the narrow instructions field on this request. Kept with the scenario because it
        // is part of what this scenario was asked to be, and a site owner comparing two
        // scenarios of different quality should be able to see that one of them was written
        // to fewer rules than the other.
        $allowed = ['model', 'provider', 'durationms', 'timegenerated', 'promptversion', 'contractversion',
            'sourcechars', 'nodecount', 'decisioncount', 'standardnotstated', 'tier'];
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $meta)) {
                $value = $meta[$key];
                $out[$key] = is_scalar($value) ? $value : null;
            }
        }
        return $out;
    }

    /**
     * Publish the current working copy as a new immutable revision.
     *
     * In-progress attempts stay bound to the revision they started on, so publishing
     * never changes a scenario underneath a learner.
     *
     * @param stdClass $scenario Activity instance record.
     * @param int $userid Publishing user.
     * @return stdClass The new revision record.
     */
    public static function publish(stdClass $scenario, int $userid, int $tier = 1): stdClass {
        global $DB;

        $definition = self::get_working_definition($scenario, $tier);
        if ($definition === null) {
            throw new moodle_exception('error:nothingtopublish', 'mod_aibranchedscenario');
        }
        // The STORED validator: this draft is already ours. Publishing re-validates, and
        // re-validating a draft written in August against a rule introduced in September
        // made every pre-v2.3.0 scenario on a site unpublishable - for a shape the teacher
        // could not have written and cannot now fix, because the editor has no control for
        // adding the third option the rule demands. The shape is enforced where a scenario
        // is MADE; here it is reported by the review panel and left to the teacher.
        $clean = validator::validate_stored($definition);

        $transaction = $DB->start_delegated_transaction();

        // Revision numbers run per RUNG. Counting them across the whole activity would
        // make the intermediate scenario's first publish "revision 4" because the
        // foundation one had been through three - a number that means nothing to the
        // teacher looking at it, and one that changes depending on work they did elsewhere.
        $revisionno = (int)$DB->get_field_sql(
            'SELECT MAX(revision) FROM {aibranchedscenario_revisions}
                WHERE scenarioid = ? AND tier = ?',
            [$scenario->id, $tier]
        ) + 1;

        $revision = (object)[
            'scenarioid'    => $scenario->id,
            'tier'          => $tier,
            'revision'      => $revisionno,
            'scenariojson'  => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'theme'         => $scenario->theme,
            'nodecount'     => (int)($clean['stats']['nodecount'] ?? 0),
            'maxskillscore' => (int)($clean['stats']['maxskillscore'] ?? 0),
            'timecreated'   => time(),
            'createdby'     => $userid,
        ];
        $revision->id = $DB->insert_record('aibranchedscenario_revisions', $revision);

        $row = self::tier_row((int)$scenario->id, $tier);
        $DB->update_record('aibranchedscenario_tiers', (object)[
            'id'           => $row->id,
            'status'       => self::STATUS_PUBLISHED,
            'revision'     => $revisionno,
            'timemodified' => time(),
        ]);
        // The ACTIVITY is published once any rung is: a learner can start the ladder as
        // soon as its first scenario exists, and waiting for all three would mean a
        // teacher who has only written the foundation one has an activity nobody can open.
        $DB->update_record('aibranchedscenario', (object)[
            'id'           => $scenario->id,
            'status'       => self::STATUS_PUBLISHED,
            'timemodified' => time(),
        ]);
        $scenario->status = self::STATUS_PUBLISHED;
        if ($tier === 1) {
            $scenario->revision = $revisionno;
        }

        $transaction->allow_commit();

        return $revision;
    }

    /**
     * Unpublish an activity, returning it to draft. Existing revisions are kept so
     * finished attempts stay interpretable.
     *
     * @param stdClass $scenario Activity instance record.
     * @return void
     */
    public static function unpublish(stdClass $scenario, int $tier = 1): void {
        global $DB;
        $row = self::tier_row((int)$scenario->id, $tier);
        $DB->update_record('aibranchedscenario_tiers', (object)[
            'id'           => $row->id,
            'status'       => self::STATUS_DRAFT,
            'timemodified' => time(),
        ]);
        // The activity goes back to draft only when NO rung is published any more.
        // Unpublishing the advanced scenario must not take the foundation one away from
        // the learners who are part way up the ladder.
        $stillpublished = $DB->record_exists(
            'aibranchedscenario_tiers',
            ['scenarioid' => $scenario->id, 'status' => self::STATUS_PUBLISHED]
        );
        if (!$stillpublished) {
            $DB->update_record('aibranchedscenario', (object)[
                'id'           => $scenario->id,
                'status'       => self::STATUS_DRAFT,
                'timemodified' => time(),
            ]);
            $scenario->status = self::STATUS_DRAFT;
        }
    }

    /**
     * Get the currently published revision, or null.
     *
     * @param stdClass $scenario Activity instance record.
     * @return stdClass|null
     */
    public static function get_current_revision(stdClass $scenario, int $tier = 1): ?stdClass {
        global $DB;
        $row = self::tier_row((int)$scenario->id, $tier);
        if ($row->status !== self::STATUS_PUBLISHED || empty($row->revision)) {
            return null;
        }
        $revision = $DB->get_record(
            'aibranchedscenario_revisions',
            ['scenarioid' => $scenario->id, 'tier' => $tier, 'revision' => $row->revision],
            '*',
            IGNORE_MISSING
        );
        return $revision ?: null;
    }

    /**
     * Whether the activity is ready for learners.
     *
     * @param stdClass $scenario Activity instance record.
     * @return bool
     */
    public static function is_playable(stdClass $scenario, int $tier = 1): bool {
        return self::get_current_revision($scenario, $tier) !== null;
    }

    /**
     * May this learner open this rung?
     *
     * The chooser already draws a locked card as locked, but a card is a picture of the
     * rule and not the rule itself: the tier arrives as a request parameter, so a learner
     * who changes the number in the address bar would otherwise walk straight into the
     * advanced scenario without having passed anything. This is the same question asked
     * where it is answerable.
     *
     * @param stdClass $scenario Activity instance record.
     * @param int $userid The learner.
     * @param int $tier Which rung.
     * @return bool
     */
    public static function tier_open(stdClass $scenario, int $userid, int $tier): bool {
        $progress = self::ladder_progress($scenario, $userid);
        return !empty($progress[$tier]['unlocked']);
    }

    /**
     * Which rungs of the ladder a learner can actually open, and why not where they cannot.
     *
     * The gate is the pass mark the teacher sets. A rung is available when the one below it
     * has been passed; the foundation rung is always available once it is published.
     *
     * Deliberately NOT a full score: with five decisions and three options, demanding every
     * best choice locks out a learner who reasoned well and took one defensible middle
     * option, and what they do about it is replay until they find the green path - at which
     * point they have stopped reasoning about the job and started memorising a sequence.
     *
     * @param stdClass $scenario Activity instance record.
     * @param int $userid The learner.
     * @return array Tier number to ['published','unlocked','passed','best'].
     */
    public static function ladder_progress(stdClass $scenario, int $userid): array {
        $pass = (int)($scenario->completionminscore ?? 0);
        $out = [];
        $unlocked = true;
        foreach (array_keys(schema::tiers()) as $tier) {
            $published = self::is_playable($scenario, $tier);
            $best = attempt_manager::best_score($scenario, $userid, $tier);
            // A pass mark of zero means "finishing it is enough", which is what a teacher
            // who never touched the setting gets.
            $passed = $best !== null && ($pass <= 0 || $best >= $pass);
            $out[$tier] = [
                'published' => $published,
                'unlocked'  => $published && $unlocked,
                'passed'    => $passed,
                'best'      => $best,
            ];
            // The next rung opens only if this one was passed. A rung that is not published
            // closes the ladder behind it rather than being skipped over, or a teacher who
            // has written the foundation and advanced scenarios would have learners
            // jumping the missing middle.
            $unlocked = $unlocked && $passed;
        }
        return $out;
    }

    /**
     * Count of attempts recorded for an activity.
     *
     * @param int $scenarioid Activity instance id.
     * @return int
     */
    public static function count_attempts(int $scenarioid): int {
        global $DB;
        return $DB->count_records('aibranchedscenario_attempts', ['scenarioid' => $scenarioid]);
    }

    /**
     * Delete every attempt and event for an activity.
     *
     * @param int $scenarioid Activity instance id.
     * @return void
     */
    public static function delete_all_attempts(int $scenarioid): void {
        global $DB;
        $attemptids = $DB->get_fieldset_select('aibranchedscenario_attempts', 'id', 'scenarioid = ?', [$scenarioid]);
        if ($attemptids) {
            [$insql, $params] = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('aibranchedscenario_events', "attemptid $insql", $params);
        }
        $DB->delete_records('aibranchedscenario_attempts', ['scenarioid' => $scenarioid]);
    }
}
