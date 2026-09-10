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

namespace mod_aibranchedscenario\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy API implementation for mod_aibranchedscenario.
 *
 * The plugin stores learner attempts at a branching scenario, the immutable
 * per-decision event log behind each attempt, and the authoring generation jobs
 * a teacher queued. It also sends pasted source content and scenario prompts to
 * the external LMS Labs generation service.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe every place this plugin stores or transmits personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('aibranchedscenario_attempts', [
            'scenarioid'   => 'privacy:metadata:aibranchedscenario_attempts:scenarioid',
            'revisionid'   => 'privacy:metadata:aibranchedscenario_attempts:revisionid',
            'userid'       => 'privacy:metadata:aibranchedscenario_attempts:userid',
            'attemptno'    => 'privacy:metadata:aibranchedscenario_attempts:attemptno',
            'status'       => 'privacy:metadata:aibranchedscenario_attempts:status',
            'currentnode'  => 'privacy:metadata:aibranchedscenario_attempts:currentnode',
            'statejson'    => 'privacy:metadata:aibranchedscenario_attempts:statejson',
            'engagement'   => 'privacy:metadata:aibranchedscenario_attempts:engagement',
            'trust'        => 'privacy:metadata:aibranchedscenario_attempts:trust',
            'tension'      => 'privacy:metadata:aibranchedscenario_attempts:tension',
            'presence'     => 'privacy:metadata:aibranchedscenario_attempts:presence',
            'adaptability' => 'privacy:metadata:aibranchedscenario_attempts:adaptability',
            'empathy'      => 'privacy:metadata:aibranchedscenario_attempts:empathy',
            'clarity'      => 'privacy:metadata:aibranchedscenario_attempts:clarity',
            'score'        => 'privacy:metadata:aibranchedscenario_attempts:score',
            'outcome'      => 'privacy:metadata:aibranchedscenario_attempts:outcome',
            'timestarted'  => 'privacy:metadata:aibranchedscenario_attempts:timestarted',
            'timemodified' => 'privacy:metadata:aibranchedscenario_attempts:timemodified',
            'timefinished' => 'privacy:metadata:aibranchedscenario_attempts:timefinished',
        ], 'privacy:metadata:aibranchedscenario_attempts');

        $collection->add_database_table('aibranchedscenario_events', [
            'attemptid'   => 'privacy:metadata:aibranchedscenario_events:attemptid',
            'seq'         => 'privacy:metadata:aibranchedscenario_events:seq',
            'nodeid'      => 'privacy:metadata:aibranchedscenario_events:nodeid',
            'choiceid'    => 'privacy:metadata:aibranchedscenario_events:choiceid',
            'nextnodeid'  => 'privacy:metadata:aibranchedscenario_events:nextnodeid',
            'signaltype'  => 'privacy:metadata:aibranchedscenario_events:signaltype',
            'engagement'  => 'privacy:metadata:aibranchedscenario_events:engagement',
            'trust'       => 'privacy:metadata:aibranchedscenario_events:trust',
            'tension'     => 'privacy:metadata:aibranchedscenario_events:tension',
            'timecreated' => 'privacy:metadata:aibranchedscenario_events:timecreated',
        ], 'privacy:metadata:aibranchedscenario_events');

        $collection->add_database_table('aibranchedscenario_jobs', [
            'scenarioid'   => 'privacy:metadata:aibranchedscenario_jobs:scenarioid',
            'userid'       => 'privacy:metadata:aibranchedscenario_jobs:userid',
            'status'       => 'privacy:metadata:aibranchedscenario_jobs:status',
            'jobtype'      => 'privacy:metadata:aibranchedscenario_jobs:jobtype',
            'requestjson'  => 'privacy:metadata:aibranchedscenario_jobs:requestjson',
            'resultjson'   => 'privacy:metadata:aibranchedscenario_jobs:resultjson',
            'errormsg'     => 'privacy:metadata:aibranchedscenario_jobs:errormsg',
            'modelused'    => 'privacy:metadata:aibranchedscenario_jobs:modelused',
            'durationms'   => 'privacy:metadata:aibranchedscenario_jobs:durationms',
            'timecreated'  => 'privacy:metadata:aibranchedscenario_jobs:timecreated',
            'timemodified' => 'privacy:metadata:aibranchedscenario_jobs:timemodified',
        ], 'privacy:metadata:aibranchedscenario_jobs');

        $collection->add_external_location_link('lmslabs', [
            'sourcecontent' => 'privacy:metadata:lmslabs:sourcecontent',
            'siteid'        => 'privacy:metadata:lmslabs:siteid',
        ], 'privacy:metadata:lmslabs');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts containing user information for this user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aibranchedscenario} a ON a.id = cm.instance
                  JOIN {aibranchedscenario_attempts} att ON att.scenarioid = a.id
                 WHERE att.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname'      => 'aibranchedscenario',
            'userid'       => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aibranchedscenario} a ON a.id = cm.instance
                  JOIN {aibranchedscenario_jobs} j ON j.scenarioid = a.id
                 WHERE j.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname'      => 'aibranchedscenario',
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modname'    => 'aibranchedscenario',
        ];

        $sql = "SELECT att.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aibranchedscenario} a ON a.id = cm.instance
                  JOIN {aibranchedscenario_attempts} att ON att.scenarioid = a.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT j.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aibranchedscenario} a ON a.id = cm.instance
                  JOIN {aibranchedscenario_jobs} j ON j.scenarioid = a.id
                 WHERE cm.id = :instanceid AND j.userid > 0";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = (int)$user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('aibranchedscenario', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            // Write the activity's own record so the export has a readable parent node.
            $contextdata = helper::get_context_data($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
            helper::export_context_files($context, $user);

            $attempts = $DB->get_records('aibranchedscenario_attempts', [
                'scenarioid' => $cm->instance,
                'userid'     => $userid,
            ], 'attemptno ASC');

            foreach ($attempts as $attempt) {
                $subcontext = [get_string('privacy:attemptpath', 'mod_aibranchedscenario', $attempt->attemptno)];
                writer::with_context($context)->export_data($subcontext, self::prepare_attempt($attempt));
            }

            self::export_jobs($context, $cm->instance, $userid);
        }
    }

    /**
     * Build the exportable representation of one attempt, including its decision journey.
     *
     * @param stdClass $attempt Attempt record.
     * @return stdClass Data ready to hand to the writer.
     */
    protected static function prepare_attempt(stdClass $attempt): stdClass {
        global $DB;

        $data = (object)[
            'attemptno'    => (int)$attempt->attemptno,
            'status'       => $attempt->status,
            'currentnode'  => $attempt->currentnode,
            'outcome'      => $attempt->outcome,
            'score'        => $attempt->score === null ? null : (float)$attempt->score,
            'finished'     => transform::yesno($attempt->status === 'finished'),
            'metrics'      => (object)[
                'engagement' => (int)$attempt->engagement,
                'trust'      => (int)$attempt->trust,
                'tension'    => (int)$attempt->tension,
            ],
            'skills'       => (object)[
                'presence'     => (int)$attempt->presence,
                'adaptability' => (int)$attempt->adaptability,
                'empathy'      => (int)$attempt->empathy,
                'clarity'      => (int)$attempt->clarity,
            ],
            'state'        => $attempt->statejson,
            'timestarted'  => transform::datetime($attempt->timestarted),
            'timemodified' => transform::datetime($attempt->timemodified),
            'timefinished' => empty($attempt->timefinished) ? null : transform::datetime($attempt->timefinished),
            'journey'      => [],
        ];

        $events = $DB->get_records('aibranchedscenario_events', ['attemptid' => $attempt->id], 'seq ASC');
        foreach ($events as $event) {
            $data->journey[] = (object)[
                'seq'         => (int)$event->seq,
                'nodeid'      => $event->nodeid,
                'choiceid'    => $event->choiceid,
                'nextnodeid'  => $event->nextnodeid,
                'signal'      => $event->signaltype,
                'engagement'  => (int)$event->engagement,
                'trust'       => (int)$event->trust,
                'tension'     => (int)$event->tension,
                'timecreated' => transform::datetime($event->timecreated),
            ];
        }

        return $data;
    }

    /**
     * Export the authoring generation jobs a user queued in one activity.
     *
     * @param context $context Module context.
     * @param int $scenarioid Activity instance id.
     * @param int $userid User id.
     * @return void
     */
    protected static function export_jobs(context $context, int $scenarioid, int $userid): void {
        global $DB;

        $jobs = $DB->get_records('aibranchedscenario_jobs', [
            'scenarioid' => $scenarioid,
            'userid'     => $userid,
        ], 'timecreated ASC');

        if (!$jobs) {
            return;
        }

        $data = [];
        foreach ($jobs as $job) {
            $data[] = (object)[
                'jobtype'      => $job->jobtype,
                'status'       => $job->status,
                'request'      => $job->requestjson,
                'result'       => $job->resultjson,
                'errormsg'     => $job->errormsg,
                'modelused'    => $job->modelused,
                'durationms'   => (int)$job->durationms,
                'failed'       => transform::yesno($job->status === 'failed'),
                'timecreated'  => transform::datetime($job->timecreated),
                'timemodified' => transform::datetime($job->timemodified),
            ];
        }

        $subcontext = [get_string('privacy:jobspath', 'mod_aibranchedscenario')];
        writer::with_context($context)->export_data($subcontext, (object)['jobs' => $data]);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('aibranchedscenario', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        self::delete_attempts_select('scenarioid = :scenarioid', ['scenarioid' => $cm->instance]);
        $DB->delete_records('aibranchedscenario_attempts', ['scenarioid' => $cm->instance]);
        $DB->delete_records('aibranchedscenario_jobs', ['scenarioid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('aibranchedscenario', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $params = ['scenarioid' => $cm->instance, 'userid' => $userid];
            self::delete_attempts_select('scenarioid = :scenarioid AND userid = :userid', $params);
            $DB->delete_records('aibranchedscenario_attempts', $params);
            $DB->delete_records('aibranchedscenario_jobs', $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('aibranchedscenario', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge($inparams, ['scenarioid' => $cm->instance]);

        self::delete_attempts_select("scenarioid = :scenarioid AND userid $insql", $params);
        $DB->delete_records_select(
            'aibranchedscenario_attempts',
            "scenarioid = :scenarioid AND userid $insql",
            $params
        );
        $DB->delete_records_select(
            'aibranchedscenario_jobs',
            "scenarioid = :scenarioid AND userid $insql",
            $params
        );
    }

    /**
     * Delete the event log of every attempt matching an attempt-table WHERE clause.
     *
     * The attempts themselves are left for the caller to remove, so that the caller
     * can choose the matching clause without the events being orphaned.
     *
     * @param string $select WHERE clause evaluated against {aibranchedscenario_attempts}.
     * @param array $params Named parameters for the clause.
     * @return void
     */
    protected static function delete_attempts_select(string $select, array $params): void {
        global $DB;

        $attemptids = $DB->get_fieldset_select('aibranchedscenario_attempts', 'id', $select, $params);
        if (!$attemptids) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'att');
        $DB->delete_records_select('aibranchedscenario_events', "attemptid $insql", $inparams);
    }
}
