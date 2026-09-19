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
 * Authoring wizard for a branching scenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aibranchedscenario\output\wizard;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aibranchedscenario');
$moduleinstance = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aibranchedscenario:manage', $context);

$tier = optional_param('tier', 1, PARAM_INT);
$tier = isset(\mod_aibranchedscenario\local\schema::tiers()[$tier]) ? $tier : 1;

$PAGE->set_url('/mod/aibranchedscenario/edit.php', ['id' => $cm->id, 'tier' => $tier]);
$PAGE->set_title(get_string('editscenario', 'mod_aibranchedscenario'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($moduleinstance->name));

$renderable = new wizard($moduleinstance, $cm, $context, $tier);
echo $OUTPUT->render_from_template(
    'mod_aibranchedscenario/wizard',
    $renderable->export_for_template($OUTPUT)
);

$PAGE->requires->js_call_amd('mod_aibranchedscenario/wizard', 'init', [(int)$cm->id]);

echo $OUTPUT->footer();
