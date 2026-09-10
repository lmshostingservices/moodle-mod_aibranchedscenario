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
 * Library of interface functions and constants.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_aibranchedscenario\local\attempt_manager;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Return whether the plugin supports a Moodle feature.
 *
 * @param string $feature Constant representing the feature.
 * @return mixed True if the feature is supported, null otherwise.
 */
function aibranchedscenario_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_ADVANCED_GRADING:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the activity into the database.
 *
 * @param stdClass $moduleinstance Data from the module form.
 * @param mod_aibranchedscenario_mod_form|null $mform The form instance.
 * @return int The id of the newly inserted record.
 */
function aibranchedscenario_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();
    $moduleinstance->status = scenario_manager::STATUS_DRAFT;
    $moduleinstance->revision = 0;

    $id = $DB->insert_record('aibranchedscenario', $moduleinstance);
    $moduleinstance->id = $id;

    aibranchedscenario_grade_item_update($moduleinstance);

    return $id;
}

/**
 * Updates an instance of the activity in the database.
 *
 * @param stdClass $moduleinstance Data from the module form.
 * @param mod_aibranchedscenario_mod_form|null $mform The form instance.
 * @return bool True on success.
 */
function aibranchedscenario_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    $result = $DB->update_record('aibranchedscenario', $moduleinstance);

    $updated = $DB->get_record('aibranchedscenario', ['id' => $moduleinstance->id], '*', MUST_EXIST);
    aibranchedscenario_grade_item_update($updated);
    aibranchedscenario_update_grades($updated);

    return $result;
}

/**
 * Removes an instance of the activity from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function aibranchedscenario_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('aibranchedscenario', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    scenario_manager::delete_all_attempts($id);
    $DB->delete_records('aibranchedscenario_revisions', ['scenarioid' => $id]);
    $DB->delete_records('aibranchedscenario_jobs', ['scenarioid' => $id]);
    $DB->delete_records('aibranchedscenario', ['id' => $id]);

    aibranchedscenario_grade_item_delete($instance);

    return true;
}

/**
 * Extend the settings navigation with activity specific links.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param navigation_node $node The node to add module settings to.
 * @return void
 */
function aibranchedscenario_extend_settings_navigation($settingsnav, $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = context_module::instance($cm->id);

    if (has_capability('mod/aibranchedscenario:manage', $context)) {
        $node->add(
            get_string('editscenario', 'mod_aibranchedscenario'),
            new moodle_url('/mod/aibranchedscenario/edit.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'aibranchedscenarioedit',
            new pix_icon('t/edit', '')
        );
    }
    if (has_capability('mod/aibranchedscenario:viewreports', $context)) {
        $node->add(
            get_string('attemptreport', 'mod_aibranchedscenario'),
            new moodle_url('/mod/aibranchedscenario/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'aibranchedscenarioreport',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Serve the files from the plugin's file areas.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments, the first of which is the item id.
 * @param bool $forcedownload Whether or not to force download.
 * @param array $options Additional options affecting file serving.
 * @return bool False when the file was not found.
 */
function aibranchedscenario_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $allowed = ['scene', 'narration', 'revisionscene', 'revisionnarration'];
    if (!in_array($filearea, $allowed, true)) {
        return false;
    }

    require_login($course, true, $cm);
    if (!has_capability('mod/aibranchedscenario:view', $context)) {
        return false;
    }
    if (
        ($filearea === 'scene' || $filearea === 'narration')
            && !has_capability('mod/aibranchedscenario:manage', $context)
    ) {
        // Working-copy media belongs to the unpublished draft and is for authors only.
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_aibranchedscenario', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Create or update the grade item for the activity.
 *
 * @param stdClass $moduleinstance The activity instance.
 * @param mixed $grades Grades to push, 'reset' to reset, or null.
 * @return int GRADE_UPDATE_OK or similar.
 */
function aibranchedscenario_grade_item_update($moduleinstance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($moduleinstance->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];

    if (empty($moduleinstance->grade) || (int)$moduleinstance->grade === 0) {
        $item['gradetype'] = GRADE_TYPE_NONE;
    } else if ((int)$moduleinstance->grade > 0) {
        $item['grademax'] = (int)$moduleinstance->grade;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/aibranchedscenario',
        $moduleinstance->course,
        'mod',
        'aibranchedscenario',
        $moduleinstance->id,
        0,
        $grades,
        $item
    );
}

/**
 * Delete the grade item for the activity.
 *
 * @param stdClass $moduleinstance The activity instance.
 * @return int GRADE_UPDATE_OK or similar.
 */
function aibranchedscenario_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/aibranchedscenario',
        $moduleinstance->course,
        'mod',
        'aibranchedscenario',
        $moduleinstance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Build the grade object for one user.
 *
 * @param stdClass $moduleinstance The activity instance.
 * @param int $userid User id, or 0 for all users.
 * @return array|null Grades keyed by user id.
 */
function aibranchedscenario_get_user_grades($moduleinstance, $userid = 0) {
    global $DB;

    if (empty($moduleinstance->grade)) {
        return null;
    }
    $maxgrade = (float)$moduleinstance->grade;

    $params = ['scenarioid' => $moduleinstance->id];
    $where = 'scenarioid = :scenarioid';
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $userids = $DB->get_fieldset_select('aibranchedscenario_attempts', 'DISTINCT userid', $where, $params);

    $grades = [];
    foreach ($userids as $id) {
        $percent = attempt_manager::aggregate_score($moduleinstance, (int)$id);
        if ($percent === null) {
            continue;
        }
        $grades[$id] = (object)[
            'userid'   => (int)$id,
            'rawgrade' => round($percent / 100 * $maxgrade, 5),
        ];
    }

    return $grades ?: null;
}

/**
 * Push grades to the gradebook.
 *
 * @param stdClass $moduleinstance The activity instance.
 * @param int $userid User id, or 0 for all users.
 * @param bool $nullifnone Insert a null grade when the user has no attempts.
 * @return void
 */
function aibranchedscenario_update_grades($moduleinstance, $userid = 0, $nullifnone = true) {
    $grades = aibranchedscenario_get_user_grades($moduleinstance, $userid);

    if ($grades) {
        aibranchedscenario_grade_item_update($moduleinstance, $grades);
    } else if ($userid && $nullifnone) {
        aibranchedscenario_grade_item_update($moduleinstance, (object)['userid' => $userid, 'rawgrade' => null]);
    } else {
        aibranchedscenario_grade_item_update($moduleinstance);
    }
}

/**
 * Reset user data when a course is reset.
 *
 * @param stdClass $data Reset form data.
 * @return array Status report rows.
 */
function aibranchedscenario_reset_userdata($data) {
    global $DB;

    $status = [];
    $component = get_string('modulenameplural', 'mod_aibranchedscenario');

    if (!empty($data->reset_aibranchedscenario_attempts)) {
        $instances = $DB->get_records('aibranchedscenario', ['course' => $data->courseid], '', 'id');
        foreach ($instances as $instance) {
            scenario_manager::delete_all_attempts((int)$instance->id);
        }
        $status[] = [
            'component' => $component,
            'item'      => get_string('resetattempts', 'mod_aibranchedscenario'),
            'error'     => false,
        ];
        if (empty($data->reset_gradebook_grades)) {
            foreach ($instances as $instance) {
                $full = $DB->get_record('aibranchedscenario', ['id' => $instance->id], '*', MUST_EXIST);
                aibranchedscenario_grade_item_update($full, 'reset');
            }
        }
    }

    return $status;
}

/**
 * Add the reset options to the course reset form.
 *
 * @param MoodleQuickForm $mform The form.
 * @return void
 */
function aibranchedscenario_reset_course_form_definition($mform) {
    $mform->addElement(
        'header',
        'aibranchedscenarioheader',
        get_string('modulenameplural', 'mod_aibranchedscenario')
    );
    $mform->addElement(
        'checkbox',
        'reset_aibranchedscenario_attempts',
        get_string('resetattempts', 'mod_aibranchedscenario')
    );
}

/**
 * Default reset form values.
 *
 * @param stdClass $course The course.
 * @return array
 */
function aibranchedscenario_reset_course_form_defaults($course) {
    return ['reset_aibranchedscenario_attempts' => 1];
}

/**
 * Return a list of view actions for the legacy log reports.
 *
 * @return string[]
 */
function aibranchedscenario_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * Return a list of post actions for the legacy log reports.
 *
 * @return string[]
 */
function aibranchedscenario_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Provide the course module information Moodle caches, including the custom
 * completion rules this activity defines.
 *
 * Without the customcompletionrules entry, Moodle never evaluates the activity's
 * own completion rule, so this callback is required rather than optional.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|bool The cached information, or false when the instance is gone.
 */
function aibranchedscenario_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionfinish, completionminscore';
    $instance = $DB->get_record('aibranchedscenario', ['id' => $coursemodule->instance], $fields);
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('aibranchedscenario', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionfinish'] = $instance->completionfinish;
    }

    return $info;
}
