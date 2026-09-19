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

/**
 * Restore structure step for mod_aibranchedscenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores one aibranchedscenario activity, its revisions and, when present, its attempts.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_aibranchedscenario_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the paths this step is able to process.
     *
     * @return array The processed paths, wrapped into standard activity structure.
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('aibranchedscenario', '/activity/aibranchedscenario');
        // The ladder's rungs, carrying the working copies. A backup made before v2.5.0 has
        // no <tiers> element at all, and simply produces no calls here - the activity then
        // restores with nothing on its ladder, which is what process_aibranchedscenario()
        // repairs from the legacy columns.
        $paths[] = new restore_path_element(
            'aibranchedscenario_tier',
            '/activity/aibranchedscenario/tiers/tier'
        );
        $paths[] = new restore_path_element(
            'aibranchedscenario_revision',
            '/activity/aibranchedscenario/revisions/revision'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'aibranchedscenario_attempt',
                '/activity/aibranchedscenario/attempts/attempt'
            );
            $paths[] = new restore_path_element(
                'aibranchedscenario_event',
                '/activity/aibranchedscenario/attempts/attempt/events/event'
            );
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the activity record itself.
     *
     * @param array $data Parsed activity data.
     * @return void
     */
    protected function process_aibranchedscenario($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Never trust the course recorded in the backup file.
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset(empty($data->timecreated) ? time() : $data->timecreated);
        $data->timemodified = time();

        // Defensive defaults for fields that may be absent in older backups.
        $data->status = isset($data->status) ? $data->status : 'draft';
        $data->revision = isset($data->revision) ? (int)$data->revision : 0;

        // Insert the record and connect it to the course module being restored.
        $newitemid = $DB->insert_record('aibranchedscenario', $data);
        $this->apply_activity_instance($newitemid);

        // A BACKUP MADE BEFORE THE LADDER HAS NO RUNGS IN IT.
        //
        // Its working copy is in the activity's own scenariojson column, which is where it
        // lived until v2.5.0. Restored without this, the activity comes back with an empty
        // ladder: the published revisions are there, so learners can still play, and the
        // teacher's unpublished draft is simply gone. Rebuilt here from the legacy columns,
        // and replaced by the real thing if the backup does turn out to carry a <tiers>
        // element - see process_aibranchedscenario_tier().
        $now = time();
        $DB->insert_record('aibranchedscenario_tiers', (object)[
            'scenarioid'     => $newitemid,
            'tier'           => 1,
            'status'         => $data->status,
            'scenariojson'   => $data->scenariojson ?? null,
            'previousjson'   => $data->previousjson ?? null,
            'generationmeta' => $data->generationmeta ?? null,
            'revision'       => (int)$data->revision,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }

    /**
     * Process one rung of the ladder.
     *
     * @param array $data Parsed rung data.
     * @return void
     */
    protected function process_aibranchedscenario_tier($data) {
        global $DB;

        $data = (object)$data;
        unset($data->id);
        $data->scenarioid = $this->get_new_parentid('aibranchedscenario');
        $data->tier = max(1, min(\mod_aibranchedscenario\local\schema::TIERS, (int)($data->tier ?? 1)));
        $data->timecreated = $this->apply_date_offset(empty($data->timecreated) ? time() : $data->timecreated);
        $data->timemodified = $this->apply_date_offset(empty($data->timemodified) ? time() : $data->timemodified);
        // The unique index is (scenarioid, tier), and process_aibranchedscenario() may have
        // already built a foundation rung from the legacy columns of an older backup. The
        // one in the backup is the better answer, so it replaces rather than collides.
        $existing = $DB->get_record(
            'aibranchedscenario_tiers',
            ['scenarioid' => $data->scenarioid, 'tier' => $data->tier],
            'id',
            IGNORE_MISSING
        );
        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('aibranchedscenario_tiers', $data);
            return;
        }
        $DB->insert_record('aibranchedscenario_tiers', $data);
    }

    /**
     * Process one published revision.
     *
     * @param array $data Parsed revision data.
     * @return void
     */
    protected function process_aibranchedscenario_revision($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->scenarioid = $this->get_new_parentid('aibranchedscenario');
        // A revision from a backup made before the ladder belongs to the foundation rung,
        // which is where its activity has been put.
        $data->tier = max(1, min(\mod_aibranchedscenario\local\schema::TIERS, (int)($data->tier ?? 1)));
        $data->timecreated = $this->apply_date_offset(empty($data->timecreated) ? time() : $data->timecreated);

        // Keep the author when that user came across in the backup, otherwise drop the reference.
        $createdby = empty($data->createdby) ? 0 : $this->get_mappingid('user', $data->createdby);
        $data->createdby = $createdby ? $createdby : 0;

        $newitemid = $DB->insert_record('aibranchedscenario_revisions', $data);

        // Mapping by revision id, used to remap attempt->revisionid.
        $this->set_mapping('aibranchedscenario_revision', $oldid, $newitemid);

        // Published media is filed under itemid = revision number, so a second mapping keyed
        // by the revision number is what add_related_files() needs for those areas.
        $this->set_mapping('aibranchedscenario_revisionfile', $data->revision, $data->revision, true);
    }

    /**
     * Process one learner attempt.
     *
     * @param array $data Parsed attempt data.
     * @return void
     */
    protected function process_aibranchedscenario_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->scenarioid = $this->get_new_parentid('aibranchedscenario');

        // Point the attempt at the revision that was just created for it.
        $revisionid = empty($data->revisionid) ? 0 : $this->get_mappingid('aibranchedscenario_revision', $data->revisionid);
        $data->revisionid = $revisionid ? $revisionid : 0;
        // Which rung it was taken at. Absent from a pre-ladder backup, where every attempt
        // was at the only scenario the activity had.
        $data->tier = max(1, min(\mod_aibranchedscenario\local\schema::TIERS, (int)($data->tier ?? 1)));

        $data->userid = $this->get_mappingid('user', $data->userid);

        $data->timestarted = $this->apply_date_offset($data->timestarted);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timefinished = empty($data->timefinished) ? 0 : $this->apply_date_offset($data->timefinished);

        $newitemid = $DB->insert_record('aibranchedscenario_attempts', $data);
        $this->set_mapping('aibranchedscenario_attempt', $oldid, $newitemid);
    }

    /**
     * Process one attempt event.
     *
     * @param array $data Parsed event data.
     * @return void
     */
    protected function process_aibranchedscenario_event($data) {
        global $DB;

        $data = (object)$data;

        $data->attemptid = $this->get_new_parentid('aibranchedscenario_attempt');
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $DB->insert_record('aibranchedscenario_events', $data);
    }

    /**
     * Add the files belonging to the restored activity and its revisions.
     *
     * @return void
     */
    protected function after_execute() {
        // Working-copy areas, all stored with itemid 0.
        $this->add_related_files('mod_aibranchedscenario', 'intro', null);
        $this->add_related_files('mod_aibranchedscenario', 'scene', null);
        $this->add_related_files('mod_aibranchedscenario', 'narration', null);

        // Published areas, keyed by revision number.
        $this->add_related_files('mod_aibranchedscenario', 'revisionscene', 'aibranchedscenario_revisionfile');
        $this->add_related_files('mod_aibranchedscenario', 'revisionnarration', 'aibranchedscenario_revisionfile');
    }
}
