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
 * Learner view of a branching scenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\output\player;

$id = optional_param('id', 0, PARAM_INT);
$b = optional_param('b', 0, PARAM_INT);

if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'aibranchedscenario');
    $moduleinstance = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($b) {
    $moduleinstance = $DB->get_record('aibranchedscenario', ['id' => $b], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($moduleinstance, 'aibranchedscenario');
} else {
    throw new moodle_exception('error:missingidandcmid', 'mod_aibranchedscenario');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aibranchedscenario:view', $context);

$event = \mod_aibranchedscenario\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('aibranchedscenario', $moduleinstance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/aibranchedscenario/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($moduleinstance);

$canattempt = has_capability('mod/aibranchedscenario:attempt', $context);
$canmanage = has_capability('mod/aibranchedscenario:manage', $context);

// The activity header rendered by header() already shows the description, because
// set_activity_record() above hands it the instance. Printing an intro box here as
// well is what Moodle modules did before 4.0, and on a supported version it simply
// renders the description twice.
echo $OUTPUT->header();

if ($canmanage) {
    $buttons = html_writer::link(
        new moodle_url('/mod/aibranchedscenario/edit.php', ['id' => $cm->id]),
        get_string('editscenario', 'mod_aibranchedscenario'),
        ['class' => 'btn btn-secondary mr-1 me-1']
    );
    if (has_capability('mod/aibranchedscenario:viewreports', $context)) {
        $buttons .= html_writer::link(
            new moodle_url('/mod/aibranchedscenario/report.php', ['id' => $cm->id]),
            get_string('attemptreport', 'mod_aibranchedscenario'),
            ['class' => 'btn btn-secondary']
        );
    }
    echo html_writer::div($buttons, 'mb-3');
}

if (!scenario_manager::is_playable($moduleinstance) && !$canmanage) {
    echo $OUTPUT->notification(get_string('notpublishedyet', 'mod_aibranchedscenario'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$renderable = new player($moduleinstance, $cm, $context, $canattempt);
echo $OUTPUT->render_from_template(
    'mod_aibranchedscenario/player',
    $renderable->export_for_template($OUTPUT)
);

$PAGE->requires->js_call_amd('mod_aibranchedscenario/player', 'init', [(int)$cm->id]);

echo $OUTPUT->footer();
