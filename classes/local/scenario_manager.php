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
     * Read the working-copy definition for an activity, or null when there is none yet.
     *
     * @param stdClass $scenario Activity instance record.
     * @return array|null
     */
    public static function get_working_definition(stdClass $scenario): ?array {
        if (empty($scenario->scenariojson)) {
            return null;
        }
        $decoded = json_decode($scenario->scenariojson, true);
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
    public static function save_definition(stdClass $scenario, array $definition, ?array $generationmeta = null): array {
        global $DB;
        $clean = validator::validate($definition);
        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > schema::MAX_SCENARIO_BYTES) {
            throw new validation_exception([get_string('error:scenariotoolarge', 'mod_aibranchedscenario')]);
        }
        $update = (object)[
            'id'           => $scenario->id,
            'scenariojson' => $encoded,
            'timemodified' => time(),
        ];
        if ($generationmeta !== null) {
            $update->generationmeta = json_encode(self::sanitise_meta($generationmeta));
        }
        $DB->update_record('aibranchedscenario', $update);
        $scenario->scenariojson = $encoded;
        if ($generationmeta !== null) {
            $scenario->generationmeta = $update->generationmeta;
        }
        return $clean;
    }

    /**
     * Strip anything that could carry a credential out of generation metadata.
     *
     * @param array $meta Raw metadata.
     * @return array
     */
    protected static function sanitise_meta(array $meta): array {
        $allowed = ['model', 'provider', 'durationms', 'timegenerated', 'promptversion', 'contractversion',
            'sourcechars', 'nodecount', 'decisioncount'];
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
    public static function publish(stdClass $scenario, int $userid): stdClass {
        global $DB;

        $definition = self::get_working_definition($scenario);
        if ($definition === null) {
            throw new moodle_exception('error:nothingtopublish', 'mod_aibranchedscenario');
        }
        $clean = validator::validate($definition);

        $transaction = $DB->start_delegated_transaction();

        $revisionno = (int)$DB->get_field_sql(
            'SELECT MAX(revision) FROM {aibranchedscenario_revisions} WHERE scenarioid = ?',
            [$scenario->id]
        ) + 1;

        $revision = (object)[
            'scenarioid'    => $scenario->id,
            'revision'      => $revisionno,
            'scenariojson'  => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'theme'         => $scenario->theme,
            'nodecount'     => (int)($clean['stats']['nodecount'] ?? 0),
            'maxskillscore' => (int)($clean['stats']['maxskillscore'] ?? 0),
            'timecreated'   => time(),
            'createdby'     => $userid,
        ];
        $revision->id = $DB->insert_record('aibranchedscenario_revisions', $revision);

        $DB->update_record('aibranchedscenario', (object)[
            'id'           => $scenario->id,
            'status'       => self::STATUS_PUBLISHED,
            'revision'     => $revisionno,
            'timemodified' => time(),
        ]);
        $scenario->status = self::STATUS_PUBLISHED;
        $scenario->revision = $revisionno;

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
    public static function unpublish(stdClass $scenario): void {
        global $DB;
        $DB->update_record('aibranchedscenario', (object)[
            'id'           => $scenario->id,
            'status'       => self::STATUS_DRAFT,
            'timemodified' => time(),
        ]);
        $scenario->status = self::STATUS_DRAFT;
    }

    /**
     * Get the currently published revision, or null.
     *
     * @param stdClass $scenario Activity instance record.
     * @return stdClass|null
     */
    public static function get_current_revision(stdClass $scenario): ?stdClass {
        global $DB;
        if ($scenario->status !== self::STATUS_PUBLISHED || empty($scenario->revision)) {
            return null;
        }
        $revision = $DB->get_record(
            'aibranchedscenario_revisions',
            ['scenarioid' => $scenario->id, 'revision' => $scenario->revision],
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
    public static function is_playable(stdClass $scenario): bool {
        return self::get_current_revision($scenario) !== null;
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
