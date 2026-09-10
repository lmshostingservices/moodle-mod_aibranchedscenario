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
