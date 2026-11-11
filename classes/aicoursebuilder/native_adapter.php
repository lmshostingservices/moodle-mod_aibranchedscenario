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

namespace mod_aibranchedscenario\aicoursebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Uses Branched Scenario's own tariff, job queue, generator, and draft storage.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS-Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class native_adapter {
    /**
     * Credits one activity will use.
     *
     * @param array $brief
     * @return int
     */
    public static function estimate(array $brief): int {
        $price = \mod_aibranchedscenario\local\schema::price_for(false, false);
        return (int)($price['total'] ?? 0);
    }

    /**
     * Start generating one activity.
     *
     * @param int $cmid
     * @param array $brief
     * @return string
     */
    public static function generate(int $cmid, array $brief): string {
        global $DB;
        $cm = get_coursemodule_from_id('aibranchedscenario', $cmid, 0, false, MUST_EXIST);
        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);
        $programid = (int)($brief['programid'] ?? 0);
        $userid = (int)$DB->get_field('local_aicoursebuilder_program', 'userid', ['id' => $programid]);
        if ($userid < 1) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder');
        }

        $lesson = $brief['lesson'] ?? [];
        $title = clean_param((string)($lesson['title'] ?? $scenario->name), PARAM_TEXT);
        $sourcecontent = trim(clean_param((string)($brief['sourcecontent'] ?? ''), PARAM_TEXT));
        if (\core_text::strlen($sourcecontent) < \mod_aibranchedscenario\local\ai\lmslabs_provider::MIN_GENERATE_CHARS) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder');
        }
        $program = $brief['program'] ?? [];
        $source = \mod_aibranchedscenario\local\source_normaliser::normalise([
            'title' => $title,
            'brief' => 'Teach the lesson outcomes through one branching decision scenario.',
            'sourcecontent' => $sourcecontent,
            'audience' => trim(implode(', ', array_filter([
                (string)($program['level'] ?? ''),
                (string)($program['sector'] ?? ''),
            ]))),
            'industry' => 'training',
            'setting' => 'trainingroom',
        ]);
        \mod_aibranchedscenario\local\scenario_manager::save_source($scenario, $source);

        // This adapter quotes text-only generation. Prevent the module's media pipeline
        // from adding unquoted image or narration operations after scenario generation.
        $DB->update_record('aibranchedscenario', (object)[
            'id' => (int)$scenario->id,
            'enableimages' => 0,
            'enableaudio' => 0,
            'timemodified' => time(),
        ]);
        return (string)(new \mod_aibranchedscenario\local\generator())->queue_scenario(
            $scenario,
            $userid,
            $cmid,
            1
        );
    }

    /**
     * Where a generation job is up to.
     *
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function status(int $cmid, string $jobid): array {
        global $DB;
        if (!ctype_digit($jobid)) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The Branched Scenario job reference is invalid.'];
        }
        $cm = get_coursemodule_from_id('aibranchedscenario', $cmid, 0, false, MUST_EXIST);
        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);
        $job = $DB->get_record('aibranchedscenario_jobs', [
            'id' => (int)$jobid,
            'scenarioid' => (int)$scenario->id,
            'tier' => 1,
            'jobtype' => \mod_aibranchedscenario\local\ai\provider::OP_SCENARIO,
        ]);
        if (!$job) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The Branched Scenario job was not found for this activity.'];
        }
        if ($job->status === \mod_aibranchedscenario\local\generator::JOB_READY) {
            $definition = \mod_aibranchedscenario\local\scenario_manager::get_working_definition($scenario, 1);
            if (!$definition) {
                return ['status' => 'failed', 'retryable' => false, 'error' => 'The generated scenario draft is missing.'];
            }
            // Learners only ever see a PUBLISHED scenario, and the plugin will not publish one
            // whose pictures or narration are still being made. Wait for the media, exactly as
            // the teacher's Publish button would, before reporting the activity as built.
            $media = new \mod_aibranchedscenario\local\media_manager(\context_module::instance($cmid), 1);
            $mediastatus = $media->working_media_status($scenario, $definition);
            if (empty($mediastatus['complete'])) {
                return ['status' => 'running'];
            }
            return ['status' => 'complete', 'result' => ['jobId' => (int)$job->id, 'definition' => $definition]];
        }
        if ($job->status === \mod_aibranchedscenario\local\generator::JOB_ERROR) {
            // A busy or unavailable service charges nothing, so AI Course Builder may try again.
            $reason = clean_param((string)($job->errormsg ?? ''), PARAM_TEXT);
            $transient = (bool)preg_match('/serviceunavailable|unavailable|timeout|timed out|rate|overloaded|busy/i', $reason);
            return ['status' => 'failed', 'retryable' => $transient,
                'error' => 'Branched Scenario generation failed' . ($reason !== '' ? ' (' . $reason . ')' : '') . '.'];
        }
        return ['status' => 'running'];
    }

    /**
     * Save a finished generation into the activity.
     *
     * @param int $cmid
     * @param array $result
     * @return bool
     */
    public static function save_result(int $cmid, array $result): bool {
        global $DB;
        if (empty($result['jobId']) || empty($result['definition'])) {
            return false;
        }
        $cm = get_coursemodule_from_id('aibranchedscenario', $cmid, 0, false, MUST_EXIST);
        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', MUST_EXIST);
        $job = $DB->get_record('aibranchedscenario_jobs', [
            'id' => (int)$result['jobId'],
            'scenarioid' => (int)$scenario->id,
            'status' => \mod_aibranchedscenario\local\generator::JOB_READY,
        ]);
        if (!$job) {
            return false;
        }
        $saved = \mod_aibranchedscenario\local\scenario_manager::get_working_definition($scenario, 1);
        if (!is_array($saved) || $saved !== $result['definition']) {
            return false;
        }
        // Publish the generated scenario so learners can play it. The course itself stays
        // hidden until the teacher has reviewed it and makes it visible.
        if ($scenario->status !== \mod_aibranchedscenario\local\scenario_manager::STATUS_PUBLISHED) {
            global $USER;
            $revision = \mod_aibranchedscenario\local\scenario_manager::publish($scenario, (int)$USER->id, 1);
            $media = new \mod_aibranchedscenario\local\media_manager(\context_module::instance($cmid), 1);
            $media->publish_media((int)$revision->revision);
        }
        return true;
    }
}
