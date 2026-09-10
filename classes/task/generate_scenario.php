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

namespace mod_aibranchedscenario\task;

use context_module;
use core\task\adhoc_task;
use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Ad-hoc task that generates a scenario, then its optional media.
 *
 * Generation happens outside the web request so a slow provider cannot time out a
 * teacher's browser, and so a failure leaves a recoverable job record behind.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_scenario extends adhoc_task {
    /**
     * Run the generation job.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $jobid = (int)($data->jobid ?? 0);
        $cmid = (int)($data->cmid ?? 0);
        if (!$jobid || !$cmid) {
            mtrace('Missing job or course module id; nothing to do.');
            return;
        }

        $job = $DB->get_record('aibranchedscenario_jobs', ['id' => $jobid], '*', IGNORE_MISSING);
        if (!$job) {
            mtrace('Job ' . $jobid . ' no longer exists.');
            return;
        }
        if ($job->status === generator::JOB_READY) {
            mtrace('Job ' . $jobid . ' already completed.');
            return;
        }

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $job->scenarioid], '*', IGNORE_MISSING);
        if (!$scenario) {
            mtrace('Activity ' . $job->scenarioid . ' no longer exists.');
            return;
        }

        $generator = new generator();
        try {
            $definition = $generator->run_scenario_job($job, $scenario);
        } catch (generation_exception $e) {
            mtrace('Scenario generation failed: ' . $e->errorcode);
            return;
        }

        $wantsimages = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $wantsaudio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');
        if (!$wantsimages && !$wantsaudio) {
            mtrace('Scenario ' . $scenario->id . ' generated with ' . count($definition['nodes']) . ' nodes.');
            return;
        }

        try {
            $context = context_module::instance($cmid);
        } catch (\moodle_exception $e) {
            mtrace('Course module context unavailable; media skipped.');
            return;
        }

        $media = new media_manager($context);
        $media->clear_working_media();

        $source = scenario_manager::get_source($scenario);
        $style = $source['imagestyle'] ?? 'cinematic';
        $voice = media_manager::configured_voice();

        $index = 0;
        foreach ($definition['nodes'] as $node) {
            if ($node['type'] === 'outcome') {
                $index++;
                continue;
            }
            if ($wantsimages) {
                $media->generate_scene($generator->get_provider(), $node, $style, $index);
            }
            if ($wantsaudio) {
                $media->generate_narration(
                    $generator->get_provider(),
                    $node,
                    $scenario->scenariolang,
                    $voice,
                    $index
                );
            }
            $index++;
        }

        mtrace('Scenario ' . $scenario->id . ' generated with media.');
    }
}
