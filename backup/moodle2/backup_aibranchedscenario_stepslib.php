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
 * Backup structure step for mod_aibranchedscenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete aibranchedscenario structure for backup, with file and id annotations.
 *
 * The activity record and every published revision (plus their images and audio) are always
 * included. Attempts and their event logs are only included when user information is requested.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_aibranchedscenario_activity_structure_step extends backup_activity_structure_step {
    /**
     * Build the backup structure.
     *
     * @return backup_nested_element The activity structure wrapped for the module.
     */
    protected function define_structure() {

        // Are we including user information in this backup?
        $userinfo = $this->get_setting_value('userinfo');

        // Root activity element.
        $aibranchedscenario = new backup_nested_element('aibranchedscenario', ['id'], [
            'name', 'intro', 'introformat', 'status', 'theme', 'scenariolang',
            'scenariojson', 'sourcejson', 'generationmeta', 'revision',
            'maxattempts', 'allowreplay', 'showdebrief', 'showtimeline', 'showmetrics',
            'enableaudio', 'requirelisten', 'bandgreen', 'bandred',
            'enableimages', 'grademethod',
            'completionfinish', 'completionminscore',
            'timecreated', 'timemodified',
        ]);

        // Published revisions.
        $revisions = new backup_nested_element('revisions');

        $revision = new backup_nested_element('revision', ['id'], [
            'revision', 'scenariojson', 'theme', 'nodecount', 'maxskillscore',
            'timecreated', 'createdby',
        ]);

        // Learner attempts.
        $attempts = new backup_nested_element('attempts');

        $attempt = new backup_nested_element('attempt', ['id'], [
            'revisionid', 'userid', 'attemptno', 'status', 'currentnode', 'statejson',
            'engagement', 'trust', 'tension', 'presence', 'adaptability', 'empathy', 'clarity',
            'score', 'outcome', 'timestarted', 'timemodified', 'timefinished',
        ]);

        // Per-decision event log.
        $events = new backup_nested_element('events');

        $event = new backup_nested_element('event', ['id'], [
            'seq', 'nodeid', 'choiceid', 'nextnodeid', 'signaltype',
            'engagement', 'trust', 'tension', 'timecreated',
        ]);

        // Build the tree.
        $aibranchedscenario->add_child($revisions);
        $revisions->add_child($revision);

        $aibranchedscenario->add_child($attempts);
        $attempts->add_child($attempt);

        $attempt->add_child($events);
        $events->add_child($event);

        // Define sources.
        $aibranchedscenario->set_source_table('aibranchedscenario', ['id' => backup::VAR_ACTIVITYID]);

        // Revisions are structural content, so they travel with every backup.
        $revision->set_source_table(
            'aibranchedscenario_revisions',
            ['scenarioid' => backup::VAR_PARENTID],
            'revision ASC'
        );

        if ($userinfo) {
            $attempt->set_source_table(
                'aibranchedscenario_attempts',
                ['scenarioid' => backup::VAR_PARENTID],
                'id ASC'
            );

            $event->set_source_table(
                'aibranchedscenario_events',
                ['attemptid' => backup::VAR_PARENTID],
                'seq ASC'
            );
        }

        // Define id annotations.
        $revision->annotate_ids('user', 'createdby');
        if ($userinfo) {
            $attempt->annotate_ids('user', 'userid');
        }

        // Define file annotations.
        // Standard activity intro, plus the working-copy media, all stored with itemid 0.
        $aibranchedscenario->annotate_files('mod_aibranchedscenario', 'intro', null);
        $aibranchedscenario->annotate_files('mod_aibranchedscenario', 'scene', null);
        $aibranchedscenario->annotate_files('mod_aibranchedscenario', 'narration', null);

        // Published media is stored with itemid = the revision number, not the revision id.
        $revision->annotate_files('mod_aibranchedscenario', 'revisionscene', 'revision');
        $revision->annotate_files('mod_aibranchedscenario', 'revisionnarration', 'revision');

        // Return the root element, wrapped into standard activity structure.
        return $this->prepare_activity_structure($aibranchedscenario);
    }
}
