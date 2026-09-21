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

namespace mod_aibranchedscenario\local;

use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\ai\provider;
use stdClass;

/**
 * Orchestrates AI generation: quotas, job records, provider calls and validation.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var string Job waiting for cron. */
    const JOB_QUEUED = 'queued';

    /** @var string Job in progress. */
    const JOB_RUNNING = 'running';

    /** @var string Job finished successfully. */
    const JOB_READY = 'ready';

    /** @var string Job failed. */
    const JOB_ERROR = 'error';

    /** @var provider The generation provider. */
    protected $provider;

    /**
     * Constructor.
     *
     * @param provider|null $provider Provider to use, or null for the configured one.
     */
    public function __construct(?provider $provider = null) {
        $this->provider = $provider ?? new lmslabs_provider();
    }

    /**
     * Get the provider in use.
     *
     * @return provider
     */
    public function get_provider(): provider {
        return $this->provider;
    }

    /**
     * The bundle id for the authoring run a job started.
     *
     * Derived, not stored: a prefix and a digest of the site, the activity, the rung and the
     * job that began the run. So it is stable for as long as that job exists, distinct for
     * every new run, and carries no content, learner data or credential.
     *
     * @param stdClass $job The generation job, or the media job of an imported scenario.
     * @return string
     */
    public static function bundle_id(stdClass $job): string {
        return 'bundle_' . sha1('bundle|' . get_site_identifier() . '|' . (int)$job->scenarioid
            . '|' . (int)($job->tier ?? 1) . '|' . (int)$job->id);
    }

    /**
     * The bundle a top-up belongs to: the latest authoring run of this activity and rung.
     *
     * A top-up fills in what is missing from a definition that already exists, so nothing
     * new was authored and it stays inside the run that authored it. An authoring run is a
     * generation job, or the media job of an imported scenario; a top-up's own media job is
     * neither, and is recognised by the request summary it writes.
     *
     * @param int $scenarioid Activity instance id.
     * @param int $tier Rung.
     * @return string The bundle id, or empty when no authoring run is on record.
     */
    public static function bundle_for_scenario(int $scenarioid, int $tier): string {
        global $DB;
        $jobs = $DB->get_records_select(
            'aibranchedscenario_jobs',
            'scenarioid = :scenarioid AND tier = :tier AND jobtype IN (:scenario, :media)',
            ['scenarioid' => $scenarioid, 'tier' => $tier, 'scenario' => 'scenario', 'media' => 'media'],
            'id DESC',
            'id, scenarioid, tier, jobtype, requestjson'
        );
        foreach ($jobs as $job) {
            $request = json_decode((string)$job->requestjson, true);
            if ($job->jobtype === 'media' && is_array($request) && array_key_exists('topup', $request)) {
                continue;
            }
            return self::bundle_id($job);
        }
        return '';
    }

    /**
     * Name the authoring run every request this generator makes belongs to.
     *
     * @param string $bundleid Bundle id, or empty for none.
     * @return void
     */
    public function use_bundle(string $bundleid): void {
        if ($this->provider instanceof lmslabs_provider) {
            $this->provider->set_bundle($bundleid);
        }
    }

    /**
     * The failures that genuinely cost the teacher nothing.
     *
     * THE TEST FOR MEMBERSHIP IS WHAT THE TEACHER WAS TOLD.
     *
     * Each of these shows a message that states in so many words that no credits were
     * used, so the daily allowance has to agree with it. The plugin does not get to
     * promise somebody they were not charged and then spend their budget anyway.
     *
     * Everything NOT on this list counts, and that is the safe direction. A timeout where
     * no reply ever arrived, a run the service completed and this plugin then refused, and
     * the two credit codes that say the debit stands or is unresolved - none of those
     * establish a zero debit, so none of them hand the allowance back.
     *
     * The stored value is the error identifier, sometimes followed by a detail, so each is
     * matched exactly or as itself followed by a space - never as a bare prefix. A bare
     * prefix is how the two uncertain codes came to read as refunded in the first place.
     *
     * @return string[] Stored error identifiers that mean nothing was charged.
     */
    public static function no_charge_errors(): array {
        return [
            // The service could not do the work and reversed its own debit.
            'error:servicefailed',
            // Refused before any work, and before any charge.
            'error:insufficientcredits',
            'error:generationconflict',
            'error:tariffmismatch',
            'error:serviceunauthorised',
            'error:serviceratelimited',
            'error:nocredentials',
        ];
    }

    /**
     * The spend counted against a user's daily allowance in the last day.
     *
     * A call the service refused without charging must not be counted. Three scenario
     * generations that the service failed and refunded were costing a teacher sixty
     * credits of their own allowance for work nobody was billed for, which is how a
     * teacher whose generations were all failing reached the daily limit fastest of all.
     *
     * A generation the service completed and charged for still counts, even when this
     * plugin then rejected the scenario: the credits were spent whatever happened next.
     * The two are told apart by the error recorded against the job, because the service
     * reports its own refusal with a distinct identifier.
     *
     * @param int $userid User id.
     * @return int Credits spent.
     */
    protected function spend_since(int $userid): int {
        global $DB;

        $tariff = schema::tariff();
        // WHICH FAILURES COST NOTHING, NAMED RATHER THAN MATCHED ON A PREFIX.
        //
        // This used to be a LIKE on 'error:servicefailed%', on the reasoning that the
        // service refunds what it could not deliver. The reasoning is right for an
        // ordinary failure and wrong for two of them: CREDIT_REFUND_FAILED says the debit
        // stands and needs putting right by hand, and CREDIT_DEBIT_UNCERTAIN says nobody
        // knows yet. Under a prefix match both would have read as refunded, handing the
        // teacher their allowance back while their credits were gone - the one direction
        // of this that costs real money and is invisible from the screen.
        //
        // So the list is explicit, and the test it has to pass is simple: a code belongs
        // here only if the message shown to the teacher PROMISES no credits were used.
        // The plugin must not tell somebody they were not charged and then quietly bill
        // their budget as though they were, and it must not do the reverse either.
        // THE STORED VALUE IS THE CODE, SOMETIMES FOLLOWED BY A DETAIL.
        //
        // A refused scenario is stored as "error:servicefailed GENERATION_FAILED: ..." and
        // a bare refusal as "error:insufficientcredits" with nothing after it. So each code
        // is matched exactly OR as a prefix followed by a space - never as a bare prefix,
        // which is what let error:servicecharged read as error:servicefailed and started
        // this.
        $nochargeparams = [];
        $clauses = [];
        foreach (array_values(self::no_charge_errors()) as $index => $errorcode) {
            $exact = 'nochargeexact' . $index;
            $prefix = 'nochargeprefix' . $index;
            $clauses[] = '(errormsg = :' . $exact . ' OR '
                . $DB->sql_like('errormsg', ':' . $prefix, false, false) . ')';
            $nochargeparams[$exact] = $errorcode;
            $nochargeparams[$prefix] = $DB->sql_like_escape($errorcode) . ' %';
        }
        $refunded = '(' . implode(' OR ', $clauses) . ')';
        // A SCENARIO THIS PLUGIN REFUSED STILL SPENDS THE ALLOWANCE, AND THAT IS RIGHT.
        //
        // Briefly changed, and changed back. The argument for excluding it is that the
        // rejection is ours, so the teacher is charged twice for a decision they had no
        // part in. The argument against is the one that wins: this allowance is a budget
        // of SERVICE OPERATIONS, not of successes. The service did the work and billed for
        // it, and a run that did not count would let one teacher with a source the service
        // keeps choking on spend the site's budget all day without ever reaching a limit.
        //
        // The teacher's real loss is credits, and this plugin cannot refund what LMS Labs
        // charged - only the service can. The answer to a rule of ours that is wrong is
        // not to move its cost onto the allowance; it is to stop the rule refusing what it
        // could repair. See validator::derive_outcome_note() and trim_choices().

        // A media run is ONE job row covering every picture and every clip in a scenario.
        //
        // Weighted per row like everything else it came to the '?? 1' fallback below - one
        // credit for a run that makes sixty-eight speech calls and fourteen images. On the
        // paste route, where the media run is the whole of the spend, that made the daily
        // quota meaningless: a teacher could paste scenarios all day and never reach it.
        // These rows are weighed by what they actually made, recorded in resultjson, and
        // excluded from the count below so they are not also counted at one apiece.
        $spent = 0;
        // A REFUNDED MEDIA RUN SPENDS NOTHING, same as every other job type.
        //
        // This clause was missing. The non-media branch below excludes a run the service
        // refunded; the media branch had no status filter at all, so a media run that
        // failed - including one the service refused for insufficient credits and refunded
        // in full - was still billed the whole 100 credits of a teacher's daily allowance,
        // on the strength of whatever partial counts its resultjson happened to carry.
        // Found by auditing the two branches against each other, which nothing did because
        // the refund checks only ever exercised scenario, suggest and populate jobs.
        $mediajobs = $DB->get_records_select(
            'aibranchedscenario_jobs',
            'userid = :userid AND timecreated > :since AND jobtype = :media
                AND NOT (status = :errored AND ' . $refunded . ')',
            array_merge([
                'userid'   => $userid,
                'since'    => time() - DAYSECS,
                'media'    => 'media',
                'errored'  => self::JOB_ERROR,
            ], $nochargeparams),
            '',
            'id, resultjson, requestjson'
        );
        foreach ($mediajobs as $mediajob) {
            $result = json_decode((string)$mediajob->resultjson, true);
            $made = is_array($result) ? (array)($result['media'] ?? []) : [];
            $request = json_decode((string)$mediajob->requestjson, true);
            $withimages = (int)($made['images'] ?? 0) > 0;
            $withvoice = (int)($made['narrations'] ?? 0) > 0;
            // The PUBLISHED price, not a per-item sum. A top-up fills in what a run already
            // paid for, so it counts at the media price as before. A media job that is not a
            // top-up is an imported scenario being illustrated, and an imported scenario costs
            // what a generated one with the same media costs - unless it made nothing at all.
            $istopup = is_array($request) && array_key_exists('topup', $request);
            if ($istopup) {
                $spent += schema::media_price($withimages, $withvoice);
            } else if ($withimages || $withvoice) {
                $spent += (int)schema::price_for($withimages, $withvoice)['total'];
            }
        }

        $counts = $DB->get_records_sql(
            'SELECT jobtype, COUNT(id) AS total
               FROM {aibranchedscenario_jobs}
              WHERE userid = :userid AND timecreated > :since
                AND jobtype <> :media
                AND NOT (status = :errored AND ' . $refunded . ')
           GROUP BY jobtype',
            array_merge([
                'userid'   => $userid,
                'since'    => time() - DAYSECS,
                'media'    => 'media',
                'errored'  => self::JOB_ERROR,
            ], $nochargeparams)
        );
        foreach ($counts as $row) {
            $spent += (int)$row->total * (int)($tariff[$row->jobtype] ?? 1);
        }
        return $spent;
    }

    /**
     * Check the per-user daily generation quota and throw when it is exhausted.
     *
     * @param int $userid User id.
     * @return void
     * @throws generation_exception
     */
    public function check_quota(int $userid, string $operation = provider::OP_SCENARIO): void {
        $this->check_credits($userid, (int)(schema::tariff()[$operation] ?? 1));
    }

    /**
     * Check the per-user daily budget against a cost in credits.
     *
     * Most operations are one job at a fixed price, so check_quota() names the operation
     * and looks the price up. A media run is priced by what it will make, which is known
     * from the definition before it starts, so it says the number instead.
     *
     * @param int $userid User id.
     * @param int $cost Credits this piece of work will spend.
     * @return void
     * @throws generation_exception
     */
    public function check_credits(int $userid, int $cost): void {
        $quota = (int)get_config('mod_aibranchedscenario', 'dailyquota');
        if ($quota <= 0) {
            return;
        }

        // The limit is a credit budget, not a request count. Counting every operation
        // the same made it meaningless: filling the wizard costs one populate and up to
        // ten suggestions, so a teacher hit a limit of forty after three or four presses
        // of a button that spends about thirty credits in total. Each job is now
        // weighted by what the service charges for it.
        $spent = $this->spend_since($userid);

        if ($spent + $cost > $quota) {
            throw new generation_exception('error:quotaexceeded', (object)[
                'quota' => $quota,
                'spent' => $spent,
            ]);
        }
    }

    /**
     * How much of a user's daily allowance is left.
     *
     * @param int $userid User id.
     * @return array Keys: quota, spent, remaining, limited.
     */
    public function allowance(int $userid): array {
        global $DB;

        $quota = (int)get_config('mod_aibranchedscenario', 'dailyquota');
        if ($quota <= 0) {
            return ['quota' => 0, 'spent' => 0, 'remaining' => 0, 'limited' => false];
        }

        $spent = $this->spend_since($userid);

        return [
            'quota'     => $quota,
            'spent'     => $spent,
            'remaining' => max(0, $quota - $spent),
            'limited'   => true,
        ];
    }

    /**
     * Fill the wizard from everything the teacher has entered so far.
     *
     * @param stdClass $scenario Activity instance.
     * @param int $userid Requesting user.
     * @param array $source Current wizard values, including the pasted source content.
     * @return array Normalised wizard values.
     * @throws generation_exception
     */
    public function populate(stdClass $scenario, int $userid, array $source): array {
        $this->check_quota($userid, provider::OP_POPULATE);

        $brief = (string)($source['brief'] ?? '');
        $sourcecontent = $this->clamp_source((string)($source['sourcecontent'] ?? ''));
        $job = $this->create_job($scenario->id, $userid, provider::OP_POPULATE, ['chars' => strlen($sourcecontent)]);

        try {
            $fields = $this->provider->populate(array_merge($source, [
                'brief'         => $brief,
                'sourcecontent' => $sourcecontent,
                'language'      => $scenario->scenariolang,
            ]));
        } catch (generation_exception $e) {
            $this->fail_job($job, $e);
            throw $e;
        }

        // What the teacher has already answered wins. The service is told those values
        // so it can work around them, but a suggestion must never quietly replace a
        // choice someone made on purpose.
        //
        // A value equal to the schema default is not such a choice. Every picker is
        // rendered with a default already selected, so treating those as answers meant
        // the service's industry, setting, atmosphere, tone and complexity were thrown
        // away on arrival and the wizard came back saying "Training room" whatever the
        // source content was about.
        $existing = scenario_manager::get_source($scenario);
        $blank = source_normaliser::blank();
        $merged = array_merge($blank, $existing, is_array($fields) ? $fields : []);
        foreach ($source as $name => $value) {
            $filled = is_array($value) ? $value !== [] : trim((string)$value) !== '';
            $isdefault = array_key_exists($name, $blank) && $value === $blank[$name];
            if ($filled && !$isdefault) {
                $merged[$name] = $value;
            }
        }
        $merged['brief'] = $brief;
        $merged['sourcecontent'] = $sourcecontent;
        $clean = source_normaliser::normalise($merged);

        $this->finish_job($job, ['fields' => $clean]);
        return $clean;
    }

    /**
     * Suggest a value for one wizard field.
     *
     * @param stdClass $scenario Activity instance.
     * @param int $userid Requesting user.
     * @param string $field Field name.
     * @param array $context Current wizard values.
     * @return array Keys: suggestion, values.
     * @throws generation_exception
     */
    public function suggest(stdClass $scenario, int $userid, string $field, array $context): array {
        $this->check_quota($userid, provider::OP_SUGGEST);
        $job = $this->create_job($scenario->id, $userid, provider::OP_SUGGEST, ['field' => $field]);

        try {
            $result = $this->provider->suggest($field, source_normaliser::normalise($context));
        } catch (generation_exception $e) {
            $this->fail_job($job, $e);
            throw $e;
        }

        $this->finish_job($job, $result);
        return $result;
    }

    /**
     * Queue a full scenario generation as an ad-hoc task.
     *
     * @param stdClass $scenario Activity instance.
     * @param int $userid Requesting user.
     * @param int $cmid Course module id, for the task context.
     * @return int Job id.
     * @throws generation_exception
     */
    public function queue_scenario(stdClass $scenario, int $userid, int $cmid, int $tier = 1): int {
        global $DB;

        $this->check_quota($userid);

        $tier = isset(schema::tiers()[$tier]) ? $tier : 1;

        // IN FLIGHT PER RUNG, not per activity. An activity holds three scenarios, and a
        // teacher writing the intermediate one while the advanced one is still being made
        // is doing something perfectly ordinary. Asked of the whole activity, the second
        // request was refused with a message about a generation already running, which is
        // true and useless.
        $open = $DB->get_records_select(
            'aibranchedscenario_jobs',
            'scenarioid = :sid AND tier = :tier AND jobtype = :type AND status IN (:queued, :running)',
            ['sid' => $scenario->id, 'tier' => $tier, 'type' => provider::OP_SCENARIO,
            'queued' => self::JOB_QUEUED,
            'running' => self::JOB_RUNNING]
        );
        if ($open) {
            throw new generation_exception('error:generationinflight');
        }

        $source = scenario_manager::get_source($scenario);
        if (empty($source['sourcecontent']) && empty($source['brief']) && empty($source['centralproblem'])) {
            throw new generation_exception('error:nosourcecontent');
        }

        $jobid = $this->create_job($scenario->id, $userid, provider::OP_SCENARIO, [
            'cmid'     => $cmid,
            'language' => $scenario->scenariolang,
            'tier'     => $tier,
        ], $tier);

        $task = new \mod_aibranchedscenario\task\generate_scenario();
        $task->set_custom_data((object)['jobid' => $jobid, 'cmid' => $cmid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);

        return $jobid;
    }

    /**
     * Run a queued scenario generation job.
     *
     * @param stdClass $job Job record.
     * @param stdClass $scenario Activity instance.
     * @return array The validated scenario definition.
     * @throws generation_exception
     */
    public function run_scenario_job(stdClass $job, stdClass $scenario): array {
        global $DB;

        $job->status = self::JOB_RUNNING;
        $job->timemodified = time();
        $DB->update_record('aibranchedscenario_jobs', $job);

        $tier = isset(schema::tiers()[(int)($job->tier ?? 1)]) ? (int)$job->tier : 1;

        $source = scenario_manager::get_source($scenario);
        $source['sourcecontent'] = $this->clamp_source($source['sourcecontent'] ?? '');
        // THE COMPLEXITY IS THE TEACHER'S CHOICE.
        //
        // It was briefly taken from the rung, back when an activity held three scenarios
        // and the rung decided how hard each one was. An activity is one scenario now and
        // the teacher picks its level in the wizard, so the stored source carries it - and
        // source_normaliser has already checked it against schema::complexities().
        $source['language'] = $scenario->scenariolang;
        $source['theme'] = $scenario->theme;
        $source['contractversion'] = schema::CONTRACT_VERSION;
        // Identifies this generation to the service so a retried task is recognised as the
        // same one and not charged twice, while a teacher generating again deliberately is
        // recognised as a new one and gets a new scenario. Never reaches the request body -
        // the payload is built from an allow-list of named fields.
        $source['idempotencykey'] = (int)$job->id;

        // The body is built once, stored, and replayed on every later attempt at this job.
        // The service matches a repeated handle against the body it saw the first time, so
        // a retry that rebuilt the body with an upgraded plugin's content standard would be
        // refused as a conflict rather than resumed. Stored before the first call, not
        // after it, so an attempt that dies mid-flight still has a body to replay.
        $stored = $job->payloadjson !== null && $job->payloadjson !== ''
            ? json_decode($job->payloadjson, true) : null;
        if (!is_array($stored) || $stored === []) {
            $stored = $this->provider->generate_payload($source);
            $DB->set_field('aibranchedscenario_jobs', 'payloadjson', json_encode($stored), ['id' => $job->id]);
        }
        $source['replaypayload'] = $stored;

        try {
            $result = $this->provider->generate_scenario($source);
            $definition = validator::validate($result['scenario']);
        } catch (validation_exception $e) {
            $wrapped = new generation_exception('error:invalidgeneratedscenario');
            $this->fail_job($job, $wrapped, $e->getMessage());
            throw $wrapped;
        } catch (generation_exception $e) {
            $this->fail_job($job, $e);
            throw $e;
        }

        $meta = $result['meta'] ?? [];
        $meta['contractversion'] = schema::CONTRACT_VERSION;
        $meta['timegenerated'] = time();
        $meta['sourcechars'] = strlen((string)$source['sourcecontent']);
        $meta['nodecount'] = (int)($definition['stats']['nodecount'] ?? 0);
        $meta['decisioncount'] = (int)($definition['stats']['decisioncount'] ?? 0);

        $meta['tier'] = $tier;
        scenario_manager::save_definition($scenario, $definition, $meta, true, $tier);

        $job->modelused = (string)($meta['model'] ?? '');
        $job->durationms = (int)($meta['durationms'] ?? 0);

        // The job is deliberately left running. Images and narration are generated
        // after this returns, and marking the job ready here told the wizard to reload
        // and offered a Publish button while cron was still minutes from finishing the
        // artwork - so a teacher could publish a revision with three of eight images
        // and never be told.
        $DB->update_record('aibranchedscenario_jobs', (object)[
            'id'           => $job->id,
            'modelused'    => $job->modelused,
            'durationms'   => $job->durationms,
            'timemodified' => time(),
        ]);

        return $definition;
    }

    /**
     * Mark a scenario job finished, once its media has been produced too.
     *
     * @param stdClass $job Job record.
     * @param array $definition The stored definition.
     * @param array $media Counts from media_manager::generate_for_definition().
     * @return void
     */
    public function finish_scenario_job(stdClass $job, array $definition, array $media = []): void {
        // The scenario itself succeeded, so the job is ready either way - a teacher can
        // publish a scenario whose pictures did not come. But the reasons the media was
        // refused are recorded rather than dropped: this used to report "3 of 14
        // illustrations" and nothing whatsoever about why the other eleven were missing,
        // which is a fact without an explanation and sends the next person guessing.
        $refused = array_values(array_filter((array)($media['refused'] ?? []), 'is_string'));
        if ($refused) {
            $job->errormsg = implode(', ', array_slice($refused, 0, 6));
        }
        $this->finish_job($job, [
            'stats' => $definition['stats'],
            'media' => $media,
        ]);
    }

    /**
     * Clamp pasted source content to the configured maximum.
     *
     * @param string $content Raw content.
     * @return string
     */
    public function clamp_source(string $content): string {
        $max = (int)get_config('mod_aibranchedscenario', 'maxsourcechars');
        if ($max <= 0 || $max > schema::MAX_SOURCE_CHARS) {
            $max = schema::MAX_SOURCE_CHARS;
        }
        return \core_text::substr($content, 0, $max);
    }

    /**
     * Create a job record.
     *
     * @param int $scenarioid Activity instance id.
     * @param int $userid Requesting user.
     * @param string $jobtype Operation name.
     * @param array $request Non-sensitive request summary.
     * @return int Job id.
     */
    protected function create_job(
        int $scenarioid,
        int $userid,
        string $jobtype,
        array $request,
        int $tier = 1
    ): int {
        global $DB;
        return (int)$DB->insert_record('aibranchedscenario_jobs', (object)[
            'scenarioid'   => $scenarioid,
            'userid'       => $userid,
            'status'       => self::JOB_QUEUED,
            'jobtype'      => $jobtype,
            'tier'         => $tier,
            'requestjson'  => json_encode($request),
            'resultjson'   => null,
            'errormsg'     => null,
            'modelused'    => '',
            'durationms'   => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Mark a job successful.
     *
     * @param int|stdClass $job Job id or record.
     * @param array $result Non-sensitive result summary.
     * @return void
     */
    protected function finish_job($job, array $result): void {
        global $DB;
        $record = is_object($job) ? $job : (object)['id' => $job];
        $record->status = self::JOB_READY;
        $record->resultjson = json_encode($result);
        $record->timemodified = time();
        if (!isset($record->modelused)) {
            $meta = $this->provider->get_last_meta();
            $record->modelused = (string)($meta['model'] ?? '');
            $record->durationms = (int)($meta['durationms'] ?? 0);
        }
        $DB->update_record('aibranchedscenario_jobs', $record);
    }

    /**
     * Mark a job failed, recording a safe error identifier only.
     *
     * @param int|stdClass $job Job id or record.
     * @param generation_exception $exception The failure.
     * @param string $detail Optional extra non-sensitive detail.
     * @return void
     */
    protected function fail_job($job, generation_exception $exception, string $detail = ''): void {
        global $DB;
        $record = is_object($job) ? $job : (object)['id' => $job];
        $record->status = self::JOB_ERROR;
        // The identifier the service reported travels on the exception, and was being
        // dropped here, which is why the teacher was shown the message with no reason in
        // it. Only a bare upper-case identifier is kept; a provider sentence never is.
        if (
            $detail === '' && is_string($exception->a ?? null)
                && preg_match('/^[A-Z][A-Z0-9_]{1,40}(: [^\r\n]{1,200})?$/u', $exception->a)
        ) {
            $detail = $exception->a;
        }
        $record->errormsg = \core_text::substr(trim($exception->errorcode . ' ' . $detail), 0, 500);
        $record->timemodified = time();

        // How long a failed call ran is the fact that separates a rejected payload from
        // a call the service gave up on, and it was being recorded only for successes,
        // so the jobs table showed every failure as having taken no time at all.
        $meta = $this->provider->get_last_meta();
        if (!isset($record->modelused)) {
            $record->modelused = (string)($meta['model'] ?? '');
        }
        $record->durationms = (int)($meta['durationms'] ?? 0);

        // Record what was actually sent and what came back. The service reports a
        // rejected request without naming the field at fault, so without this the only
        // way to find out is to reason about code that may not be the code that ran.
        // The provider has already replaced the API key with its shape and shortened
        // long values, so nothing here reveals a credential.
        $exchange = $this->provider->get_last_exchange();
        if ($exchange) {
            $record->requestjson = \core_text::substr(
                (string)json_encode($exchange, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                0,
                4000
            );
        }

        $DB->update_record('aibranchedscenario_jobs', $record);
    }

    /**
     * Load a job owned by the given activity.
     *
     * @param int $jobid Job id.
     * @param int $scenarioid Activity instance id.
     * @return stdClass|null
     */
    public static function get_job(int $jobid, int $scenarioid): ?stdClass {
        global $DB;
        $job = $DB->get_record('aibranchedscenario_jobs', ['id' => $jobid], '*', IGNORE_MISSING);
        if (!$job || (int)$job->scenarioid !== $scenarioid) {
            return null;
        }
        return $job;
    }
}
