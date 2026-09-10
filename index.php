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
 * Lists every branching scenario in a course.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);

// This page only lists activities the user can already see, and
// get_all_instances_in_course() applies visibility and availability itself. The
// explicit check is here so that a role denied the activity at course level does
// not reach a listing page for it at all.
require_capability('mod/aibranchedscenario:view', $context);

$PAGE->set_url('/mod/aibranchedscenario/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \mod_aibranchedscenario\event\course_module_instance_list_viewed::create(['context' => $context]);
$event->add_record_snapshot('course', $course);
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_aibranchedscenario'));

$instances = get_all_instances_in_course('aibranchedscenario', $course);
if (!$instances) {
    echo $OUTPUT->notification(get_string('noinstances', 'mod_aibranchedscenario'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $table->head = [
        get_string('sectionname', 'format_' . $course->format),
        get_string('activityname', 'mod_aibranchedscenario'),
        get_string('status', 'mod_aibranchedscenario'),
    ];
} else {
    $table->head = [
        get_string('activityname', 'mod_aibranchedscenario'),
        get_string('status', 'mod_aibranchedscenario'),
    ];
}

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/aibranchedscenario/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name, true, ['context' => context_module::instance($instance->coursemodule)]),
        $instance->visible ? [] : ['class' => 'dimmed']
    );
    $status = get_string('status:' . $instance->status, 'mod_aibranchedscenario');
    if ($usesections) {
        $table->data[] = [get_section_name($course, $instance->section), $link, $status];
    } else {
        $table->data[] = [$link, $status];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
