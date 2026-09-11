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
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;

/**
 * Generates the images and narration for a scenario that already has its text.
 *
 * Generation produces both in one task, but a scenario can also arrive through the
 * import box, drafted in another assistant and pasted in. Those definitions carry an
 * image brief for every node and used to be shown with no pictures at all, because the
 * media loop only ever ran inside the generation task.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_media extends \core\task\adhoc_task {
    /**
     * Generate the media for the working copy.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $cmid = (int)($data->cmid ?? 0);
        if (!$cmid) {
            return;
        }

        try {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'aibranchedscenario');
            $context = context_module::instance($cm->id);
        } catch (\moodle_exception $e) {
            mtrace('Course module ' . $cmid . ' is no longer available; media skipped.');
            return;
        }

        $scenario = $DB->get_record('aibranchedscenario', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$scenario) {
            return;
        }

        $definition = scenario_manager::get_working_definition($scenario);
        if (!is_array($definition)) {
            mtrace('Activity ' . $scenario->id . ' has no working copy; media skipped.');
            return;
        }

        $generator = new generator();
        $media = new media_manager($context);
        $counts = $media->generate_for_definition($generator->get_provider(), $scenario, $definition);

        mtrace(
            'Activity ' . $scenario->id . ' media: '
                . $counts['images'] . ' images, ' . $counts['narrations'] . ' narrations.'
        );
    }
}
