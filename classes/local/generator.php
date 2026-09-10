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
     * Check the per-user daily generation quota and throw when it is exhausted.
     *
     * @param int $userid User id.
     * @return void
     * @throws generation_exception
     */
    public function check_quota(int $userid, string $operation = provider::OP_SCENARIO): void {
        global $DB;

        $quota = (int)get_config('mod_aibranchedscenario', 'dailyquota');
        if ($quota <= 0) {
            return;
        }

        // The limit is a credit budget, not a request count. Counting every operation
        // the same made it meaningless: filling the wizard costs one populate and up to
        // ten suggestions, so a teacher hit a limit of forty after three or four presses
        // of a button that spends about thirty credits in total. Each job is now
        // weighted by what the service charges for it.
        $tariff = schema::tariff();
        $since = time() - DAYSECS;
        $spent = 0;
        $counts = $DB->get_records_sql(
            'SELECT jobtype, COUNT(id) AS total
               FROM {aibranchedscenario_jobs}
              WHERE userid = :userid AND timecreated > :since
           GROUP BY jobtype',
            ['userid' => $userid, 'since' => $since]
        );
        foreach ($counts as $row) {
            $spent += (int)$row->total * (int)($tariff[$row->jobtype] ?? 1);
        }

        $cost = (int)($tariff[$operation] ?? 1);
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

        $tariff = schema::tariff();
        $spent = 0;
        $counts = $DB->get_records_sql(
            'SELECT jobtype, COUNT(id) AS total
               FROM {aibranchedscenario_jobs}
              WHERE userid = :userid AND timecreated > :since
           GROUP BY jobtype',
            ['userid' => $userid, 'since' => time() - DAYSECS]
        );
        foreach ($counts as $row) {
            $spent += (int)$row->total * (int)($tariff[$row->jobtype] ?? 1);
        }

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
    public function queue_scenario(stdClass $scenario, int $userid, int $cmid): int {
        global $DB;

        $this->check_quota($userid);

        $open = $DB->get_records_select(
            'aibranchedscenario_jobs',
            'scenarioid = :sid AND jobtype = :type AND status IN (:queued, :running)',
            ['sid' => $scenario->id, 'type' => provider::OP_SCENARIO,
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
        ]);

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

        $source = scenario_manager::get_source($scenario);
        $source['sourcecontent'] = $this->clamp_source($source['sourcecontent'] ?? '');
        $source['language'] = $scenario->scenariolang;
        $source['theme'] = $scenario->theme;
        $source['contractversion'] = schema::CONTRACT_VERSION;

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

        scenario_manager::save_definition($scenario, $definition, $meta);

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
    protected function create_job(int $scenarioid, int $userid, string $jobtype, array $request): int {
        global $DB;
        return (int)$DB->insert_record('aibranchedscenario_jobs', (object)[
            'scenarioid'   => $scenarioid,
            'userid'       => $userid,
            'status'       => self::JOB_QUEUED,
            'jobtype'      => $jobtype,
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
