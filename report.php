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
use mod_aibranchedscenario\local\cohort_insight;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;

$id = required_param('id', PARAM_INT);
$view = optional_param('view', 'attempts', PARAM_ALPHA);
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
// The report carries no behaviour of its own, but it does render the activity shell, and
// that shell has to know whether the page behind it is dark. Without this it was the one
// screen in the product that stayed white on a dark Moodle theme.
$PAGE->requires->js_call_amd('mod_aibranchedscenario/scheme', 'init');

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
            // Grade AND completion. See helper::recalculate_for_user().
            \mod_aibranchedscenario\external\helper::recalculate_for_user(
                $moduleinstance,
                (int)$attempt->userid
            );
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
    // Every design token in this plugin is declared on the activity shell, so the buttons
    // and decision cards below were drawing outside it: no surface, no border colour, no
    // radius, because the custom properties they ask for did not exist on this page. The
    // attempt list was given the shell; the attempt itself was not.
    echo html_writer::start_div('aibs-wizard ' . schema::theme_class((string)$moduleinstance->theme));
    echo html_writer::start_div('aibs-shell');
    echo $OUTPUT->heading(format_string($moduleinstance->name));
    echo html_writer::link(
        $baseurl,
        get_string('report:backtolist', 'mod_aibranchedscenario'),
        ['class' => 'aibs-btn aibs-btn-secondary']
    );

    if (!$revision) {
        echo $OUTPUT->notification(get_string('report:revisiongone', 'mod_aibranchedscenario'), 'warning');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $manager = new attempt_manager($moduleinstance, $revision);
    $journey = $manager->build_journey($attempt);

    echo $OUTPUT->heading(
        get_string(
            'report:attemptheading',
            'mod_aibranchedscenario',
            (object)[
                'user'    => $owner ? fullname($owner) : '',
                'attempt' => (int)$attempt->attemptno,
            ]
        ),
        3
    );
    echo html_writer::tag(
        'p',
        get_string(
            'decisionqualityscore',
            'mod_aibranchedscenario',
            $attempt->score === null ? '-' : round((float)$attempt->score, 1)
        ),
        ['class' => 'aibs-fineprint']
    );

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
    echo html_writer::div(
        $list ?: $OUTPUT->notification(
            get_string('report:nodecisions', 'mod_aibranchedscenario'),
            'info'
        ),
        'aibs-review-choices'
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();

// The report used to open with a bare Moodle heading and an unshelled row of numbers,
// so a teacher coming straight from the wizard landed on what looked like a different,
// older plugin. It now sits in the same shell, under the same masthead, as every other
// screen in the activity.
// Built by hand here, with a retired theme as its fallback, while the three renderers
// resolved the same value through schema::theme_class(). A stored theme that is no
// longer offered has one resolution, in one place.
// The shell carries the border and the canvas; the inset lives on .aibs-shell inside
// it. The report opened the one and never the other, so its masthead, its figures and
// its table all sat hard against the rounded edge - the only screen in the product
// with no gutter at all.
echo html_writer::start_div('aibs-wizard ' . schema::theme_class((string)$moduleinstance->theme));
echo html_writer::start_div('aibs-shell');
echo html_writer::start_tag('header', ['class' => 'aibs-masthead aibs-masthead-compact']);
echo html_writer::start_div('aibs-masthead-text');
echo html_writer::tag('p', get_string('report:eyebrow', 'mod_aibranchedscenario'), ['class' => 'aibs-eyebrow']);
echo html_writer::tag('h2', format_string($moduleinstance->name), ['class' => 'aibs-title']);
echo html_writer::end_div();
echo html_writer::end_tag('header');

$total = $DB->count_records('aibranchedscenario_attempts', ['scenarioid' => $moduleinstance->id]);
$finished = $DB->count_records(
    'aibranchedscenario_attempts',
    [
        'scenarioid' => $moduleinstance->id,
        'status'     => attempt_manager::STATUS_FINISHED,
    ]
);
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

// THE TWO PAGES OF THE REPORT.
//
// A list of scores tells a trainer who to worry about. It does not tell them what to teach
// on Monday. The second page does, and it is the thing a training manager is actually
// buying - so it is a tab rather than a section further down a page nobody scrolls.
$tabs = '';
foreach (['attempts', 'cohort'] as $tabname) {
    $tabs .= html_writer::link(
        new moodle_url($baseurl, $tabname === 'attempts' ? [] : ['view' => $tabname]),
        get_string('report:tab:' . $tabname, 'mod_aibranchedscenario'),
        ['class' => 'aibs-reporttab' . ($view === $tabname ? ' aibs-is-current' : ''),
            'aria-current' => $view === $tabname ? 'page' : 'false']
    );
}
echo html_writer::tag(
    'nav',
    $tabs,
    [
        'class' => 'aibs-reporttabs',
        'aria-label' => get_string('report:eyebrow', 'mod_aibranchedscenario'),
    ]
);

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
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// WHERE PEOPLE GO WRONG.
//
// The page a training manager buys. A list of scores says who to worry about; this says what
// to teach on Monday, and it is built entirely from decisions people actually made.
//
// The distinction it exists for: somebody who chose badly and said they were unsure has a
// GAP, and telling them the rule closes it. Somebody who chose badly while sure has a
// MISCONCEPTION - they will not ask, because as far as they are concerned there is nothing
// to ask about. Both score 60%, and they are not the same problem.
if ($view === 'cohort') {
    $currentrevision = scenario_manager::get_current_revision($moduleinstance);
    $definition = $currentrevision ? json_decode((string)$currentrevision->scenariojson, true) : null;
    if (!is_array($definition)) {
        echo $OUTPUT->notification(get_string('report:nodatayet', 'mod_aibranchedscenario'), 'info');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $insight = cohort_insight::build((int)$moduleinstance->id, $definition);

    echo html_writer::tag(
        'p',
        get_string('report:cohortintro', 'mod_aibranchedscenario'),
        ['class' => 'aibs-fineprint']
    );

    if (!$insight['responses']) {
        echo $OUTPUT->notification(get_string('report:nodatayet', 'mod_aibranchedscenario'), 'info');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $cohortstats = html_writer::div(
        html_writer::span($insight['responses'], 'aibs-stat-value')
            . html_writer::span(
                get_string('stat:decisionsmade', 'mod_aibranchedscenario'),
                'aibs-stat-label'
            ),
        'aibs-stat'
    ) . html_writer::div(
        html_writer::span($insight['unsurerate'] . '%', 'aibs-stat-value')
            . html_writer::span(
                get_string('stat:unsurerate', 'mod_aibranchedscenario'),
                'aibs-stat-label'
            ),
        'aibs-stat'
    );
    echo html_writer::div($cohortstats, 'aibs-report-summary');

    echo html_writer::tag(
        'h3',
        get_string('report:misconceptions', 'mod_aibranchedscenario'),
        ['class' => 'aibs-h3']
    );
    if (!$insight['misconceptions']) {
        // A REPORT THAT SAYS NOTHING IS WRONG MUST SAY WHY IT IS SURE. "No problems found"
        // reads identically whether the group is doing well or whether four people have
        // played it, so the count of decisions with enough answers to judge is part of the
        // sentence rather than something the trainer has to infer.
        $judged = 0;
        foreach ($insight['decisions'] as $decision) {
            $judged += $decision['enough'] ? 1 : 0;
        }
        echo $OUTPUT->notification(
            get_string('report:nomisconceptions', 'mod_aibranchedscenario', $judged),
            'info'
        );
    }
    foreach ($insight['misconceptions'] as $item) {
        $body = html_writer::tag('h4', $item['title'], ['class' => 'aibs-h4']);
        $body .= html_writer::span(
            get_string(
                $item['confident'] ? 'report:confidentwrong' : 'report:knewtheywereunsure',
                'mod_aibranchedscenario'
            ),
            'aibs-chip ' . ($item['confident'] ? 'aibs-chip-alert' : 'aibs-chip-quiet')
        );
        $body .= html_writer::tag(
            'p',
            get_string(
                $item['confident'] ? 'report:misconceptiondetail' : 'report:gapdetail',
                'mod_aibranchedscenario',
                (object)['share' => $item['share'], 'sureshare' => $item['sureshare']]
            )
        );
        if ($item['option'] !== '') {
            $body .= html_writer::tag(
                'p',
                get_string('report:mostcommonly', 'mod_aibranchedscenario', $item['option']),
                ['class' => 'aibs-review-choice-text']
            );
        }
        if ($item['principle'] !== '') {
            $body .= html_writer::tag(
                'p',
                get_string('report:testing', 'mod_aibranchedscenario', $item['principle']),
                ['class' => 'aibs-fineprint']
            );
        }
        echo html_writer::div(
            $body,
            'aibs-misconception' . ($item['confident'] ? ' aibs-misconception-confident' : '')
        );
    }

    echo html_writer::tag(
        'h3',
        get_string('report:everydecision', 'mod_aibranchedscenario'),
        ['class' => 'aibs-h3']
    );
    foreach ($insight['decisions'] as $decision) {
        $body = html_writer::tag('h4', $decision['title'], ['class' => 'aibs-h4']);
        $body .= html_writer::tag(
            'p',
            $decision['enough']
                ? get_string('report:answeredby', 'mod_aibranchedscenario', $decision['taken'])
                : get_string(
                    'report:notenoughyet',
                    'mod_aibranchedscenario',
                    (object)['a' => $decision['taken'], 'b' => cohort_insight::MIN_RESPONSES]
                ),
            ['class' => 'aibs-fineprint']
        );
        foreach ($decision['options'] as $option) {
            // THE BAR IS THE POINT. A column of percentages is read one number at a time;
            // three bars against a common baseline are read at a glance, which is the
            // difference between a trainer noticing the split and not.
            $bar = html_writer::div(
                '',
                'aibs-optionbar-fill',
                ['style' => 'width:' . (int)$option['share'] . '%']
            );
            $row = html_writer::span($option['letter'], 'aibs-optionbar-letter')
                . html_writer::span($option['text'], 'aibs-optionbar-text')
                . html_writer::div($bar, 'aibs-optionbar-track')
                . html_writer::span($option['share'] . '%', 'aibs-optionbar-share');
            if ($option['unsure']) {
                $row .= html_writer::span(
                    get_string('report:unsurecount', 'mod_aibranchedscenario', $option['unsure']),
                    'aibs-optionbar-unsure'
                );
            }
            $body .= html_writer::div($row, 'aibs-optionbar aibs-signal-' . $option['signal']);
        }
        echo html_writer::div($body, 'aibs-decisionbreakdown');
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
$sql = "SELECT a.id, a.userid, a.tier, a.attemptno, a.status, a.score, a.outcome, a.engagement, a.trust,
               a.tension, a.timestarted, a.timefinished {$userfields->selects}
          FROM {aibranchedscenario_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.scenarioid = :sid
      ORDER BY a.tier ASC, a.timestarted DESC, a.id DESC";
$params = array_merge(['sid' => $moduleinstance->id], $userfields->params);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('learner', 'mod_aibranchedscenario'),
    // WHICH OF THE THREE. Without this a teacher reads three "attempt 1" rows for one
    // learner and cannot tell which scenario each was - and the whole point of the ladder
    // is being able to see how somebody did as it got harder.
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
        // THE RUNG COLUMN WENT AND ITS CELL DID NOT.
        //
        // When v2.6.0 withdrew the ladder, the "which of the three" heading was removed from
        // the table head and the cell under it was left behind as a get_string() call with
        // its identifier deleted and its component left in place - so the report asked core
        // for a string called "mod_aibranchedscenario", put whatever came back in a column
        // that no longer had a heading, and handed every row to html_table with one cell
        // more than the header. The teacher's report has been a column out of alignment
        // since that release, and nothing could see it: the page renders, the query is
        // right, and the numbers above it are correct.
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

echo html_writer::div(html_writer::table($table), 'aibs-tablewrap');
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
