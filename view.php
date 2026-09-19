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

use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\output\ladder;
use mod_aibranchedscenario\output\player;

$id = optional_param('id', 0, PARAM_INT);
$b = optional_param('b', 0, PARAM_INT);
// Which rung of the ladder. Absent means "show me the activity", which is the chooser on a
// ladder and the scenario itself on an activity that only has one.
$tier = optional_param('tier', 0, PARAM_INT);

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

$urlparams = ['id' => $cm->id];
if ($tier) {
    $urlparams['tier'] = $tier;
}
$PAGE->set_url('/mod/aibranchedscenario/view.php', $urlparams);
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

// THE CHOOSER, WHEN THERE IS SOMETHING TO CHOOSE.
//
// An activity holds up to three scenarios, and landing straight in the foundation one
// would hide the other two entirely: a learner would finish, be returned to the same
// screen and have no way of knowing there was anything after it. The chooser is that
// missing screen. An activity with only one scenario written still opens straight into
// it, because a chooser in front of a single card asks for a choice that does not exist.
$isladder = ladder::is_a_ladder($moduleinstance);
if ($isladder && !$tier) {
    $renderable = new ladder($moduleinstance, $cm, $context, (int)$USER->id);
    echo $OUTPUT->render_from_template(
        'mod_aibranchedscenario/ladder',
        $renderable->export_for_template($OUTPUT)
    );
    $PAGE->requires->js_call_amd('mod_aibranchedscenario/ladder', 'init', []);
    echo $OUTPUT->footer();
    exit;
}

$tier = isset(schema::tiers()[$tier]) ? $tier : 1;

if (!scenario_manager::is_playable($moduleinstance, $tier) && !$canmanage) {
    echo $OUTPUT->notification(get_string('notpublishedyet', 'mod_aibranchedscenario'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// The order is the product, so it is refused here as well as drawn on the chooser: the
// tier is a URL parameter and would otherwise be a way past it. A teacher previewing
// their own work is not a learner climbing the ladder, so manage passes through.
if ($isladder && !$canmanage && !scenario_manager::tier_open($moduleinstance, (int)$USER->id, $tier)) {
    echo $OUTPUT->notification(get_string('error:tierlocked', 'mod_aibranchedscenario'), 'info');
    echo $OUTPUT->continue_button(
        new moodle_url('/mod/aibranchedscenario/view.php', ['id' => $cm->id])
    );
    echo $OUTPUT->footer();
    exit;
}

// The way back out. Without it the only route to the other two scenarios is the browser's
// back button, and a learner who has just finished one has been taken forward by the
// player rather than back, so that button does not go where they think it does.
if ($isladder) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/mod/aibranchedscenario/view.php', ['id' => $cm->id]),
            get_string('ladder:back', 'mod_aibranchedscenario'),
            ['class' => 'aibs-backlink']
        ),
        'mb-2'
    );
}

$renderable = new player($moduleinstance, $cm, $context, $canattempt, $tier);
echo $OUTPUT->render_from_template(
    'mod_aibranchedscenario/player',
    $renderable->export_for_template($OUTPUT)
);

$PAGE->requires->js_call_amd('mod_aibranchedscenario/player', 'init', [(int)$cm->id]);

echo $OUTPUT->footer();
