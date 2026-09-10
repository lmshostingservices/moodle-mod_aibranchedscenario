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
 * Attempt report for teachers.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_aibranchedscenario\local\attempt_manager;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aibranchedscenario');
$moduleinstance = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aibranchedscenario:viewreports', $context);

$baseurl = new moodle_url('/mod/aibranchedscenario/report.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('attemptreport', 'mod_aibranchedscenario'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$candelete = has_capability('mod/aibranchedscenario:deleteattempts', $context);

if ($candelete) {
    $deleteid = optional_param('delete', 0, PARAM_INT);
    $confirmed = optional_param('confirm', 0, PARAM_BOOL);
    if ($deleteid) {
        $attempt = $DB->get_record('aibranchedscenario_attempts', ['id' => $deleteid], '*', IGNORE_MISSING);

        // Deleting an attempt takes the learner's answers, the event log behind them and
        // their grade, and it cannot be undone. It used to happen on one click of a
        // link in a table of twenty-five rows.
        if ($attempt && (int)$attempt->scenarioid === (int)$moduleinstance->id && !$confirmed) {
            $owner = core_user::get_user((int)$attempt->userid, '*', IGNORE_MISSING);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string(
                    'confirmdeleteattempt',
                    'mod_aibranchedscenario',
                    (object)[
                        'user'    => $owner ? fullname($owner) : '',
                        'attempt' => (int)$attempt->attemptno,
                    ]
                ),
                new moodle_url($baseurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
                $baseurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        require_sesskey();
        if ($attempt && (int)$attempt->scenarioid === (int)$moduleinstance->id) {
            attempt_manager::delete_attempt((int)$attempt->id);
            aibranchedscenario_update_grades($moduleinstance, (int)$attempt->userid);
            redirect(
                $baseurl,
                get_string('attemptdeleted', 'mod_aibranchedscenario'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

// One attempt, read end to end. A teacher opening a branching-scenario report wants to
// know which way a learner went and where the group went wrong; the table of scores
// answered neither, though the journey has always been assembled for the debrief.
$viewid = optional_param('attempt', 0, PARAM_INT);
if ($viewid) {
    $attempt = $DB->get_record('aibranchedscenario_attempts', ['id' => $viewid], '*', IGNORE_MISSING);
    if (!$attempt || (int)$attempt->scenarioid !== (int)$moduleinstance->id) {
        throw new moodle_exception('error:unknownattempt', 'mod_aibranchedscenario');
    }
    $revision = $DB->get_record('aibranchedscenario_revisions', ['id' => $attempt->revisionid], '*', IGNORE_MISSING);
    $owner = core_user::get_user((int)$attempt->userid, '*', IGNORE_MISSING);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($moduleinstance->name));
    echo html_writer::link(
        $baseurl,
        get_string('report:backtolist', 'mod_aibranchedscenario'),
        ['class' => 'aibs-btn aibs-btn-secondary']
    );

    if (!$revision) {
        echo $OUTPUT->notification(get_string('report:revisiongone', 'mod_aibranchedscenario'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }

    $manager = new attempt_manager($moduleinstance, $revision);
    $journey = $manager->build_journey($attempt);

    echo $OUTPUT->heading(get_string('report:attemptheading', 'mod_aibranchedscenario', (object)[
        'user'    => $owner ? fullname($owner) : '',
        'attempt' => (int)$attempt->attemptno,
    ]), 3);
    echo html_writer::tag('p', get_string(
        'decisionqualityscore',
        'mod_aibranchedscenario',
        $attempt->score === null ? '-' : round((float)$attempt->score, 1)
    ), ['class' => 'aibs-fineprint']);

    $list = '';
    foreach ($journey as $step) {
        $list .= html_writer::div(
            html_writer::tag('h4', $step['seq'] . '. ' . $step['nodetitle'])
                . html_writer::tag('p', $step['choicetext'], ['class' => 'aibs-review-choice-text'])
                . html_writer::tag(
                    'p',
                    get_string('signal:' . $step['signal'], 'mod_aibranchedscenario'),
                    ['class' => 'aibs-review-meta']
                )
                . html_writer::tag('p', $step['consequence'])
                . ($step['feedback'] !== ''
                    ? html_writer::div(html_writer::tag('p', $step['feedback']), 'aibs-journey-feedback') : ''),
            'aibs-review-choice aibs-signal-' . $step['signal']
        );
    }
    echo html_writer::div($list ?: $OUTPUT->notification(
        get_string('report:nodecisions', 'mod_aibranchedscenario'),
        'info'
    ), 'aibs-review-choices');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($moduleinstance->name));

$total = $DB->count_records('aibranchedscenario_attempts', ['scenarioid' => $moduleinstance->id]);
$finished = $DB->count_records('aibranchedscenario_attempts', [
    'scenarioid' => $moduleinstance->id,
    'status'     => attempt_manager::STATUS_FINISHED,
]);
$averagescore = (float)$DB->get_field_sql(
    'SELECT AVG(score) FROM {aibranchedscenario_attempts}
      WHERE scenarioid = :sid AND status = :status AND score IS NOT NULL',
    ['sid' => $moduleinstance->id, 'status' => attempt_manager::STATUS_FINISHED]
);
$learners = $DB->count_records_sql(
    'SELECT COUNT(DISTINCT userid) FROM {aibranchedscenario_attempts} WHERE scenarioid = :sid',
    ['sid' => $moduleinstance->id]
);

$stats = [
    ['value' => $total, 'label' => get_string('stat:attempts', 'mod_aibranchedscenario')],
    ['value' => $learners, 'label' => get_string('stat:learners', 'mod_aibranchedscenario')],
    ['value' => $finished, 'label' => get_string('stat:finished', 'mod_aibranchedscenario')],
    ['value' => $finished ? round($averagescore, 1) . '%' : '-',
        'label' => get_string('stat:averagescore', 'mod_aibranchedscenario')],
];

$summary = '';
foreach ($stats as $stat) {
    $summary .= html_writer::div(
        html_writer::span($stat['value'], 'aibs-stat-value')
            . html_writer::span($stat['label'], 'aibs-stat-label'),
        'aibs-stat'
    );
}
echo html_writer::div($summary, 'aibs-report-summary');

if (!$total) {
    echo $OUTPUT->notification(get_string('noattemptsyet', 'mod_aibranchedscenario'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
$sql = "SELECT a.id, a.userid, a.attemptno, a.status, a.score, a.outcome, a.engagement, a.trust,
               a.tension, a.timestarted, a.timefinished {$userfields->selects}
          FROM {aibranchedscenario_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.scenarioid = :sid
      ORDER BY a.timestarted DESC, a.id DESC";
$params = array_merge(['sid' => $moduleinstance->id], $userfields->params);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('learner', 'mod_aibranchedscenario'),
    get_string('attemptnumber', 'mod_aibranchedscenario'),
    get_string('status', 'mod_aibranchedscenario'),
    get_string('score', 'mod_aibranchedscenario'),
    get_string('outcome', 'mod_aibranchedscenario'),
    get_string('started', 'mod_aibranchedscenario'),
];
$table->head[] = get_string('actions', 'mod_aibranchedscenario');

$recordset = $DB->get_recordset_sql($sql, $params, $page * $perpage, $perpage);
foreach ($recordset as $record) {
    $row = [
        fullname($record),
        (int)$record->attemptno,
        get_string('attemptstatus:' . $record->status, 'mod_aibranchedscenario'),
        $record->score === null ? '-' : round((float)$record->score, 1) . '%',
        $record->outcome === '' ? '-' : get_string('outcome:' . $record->outcome, 'mod_aibranchedscenario'),
        userdate($record->timestarted),
    ];
    $actions = html_writer::link(
        new moodle_url($baseurl, ['attempt' => $record->id]),
        get_string('report:view', 'mod_aibranchedscenario'),
        ['class' => 'btn btn-sm btn-outline-secondary']
    );
    if ($candelete) {
        $actions .= ' ' . html_writer::link(
            new moodle_url($baseurl, ['delete' => $record->id, 'sesskey' => sesskey()]),
            get_string('delete'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
    $row[] = $actions;
    $table->data[] = $row;
}
$recordset->close();

echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
echo $OUTPUT->footer();
