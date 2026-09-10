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

use core\task\scheduled_task;

/**
 * Removes generation job records once they are older than the retention period.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_jobs extends scheduled_task {
    /**
     * The task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanupjobs', 'mod_aibranchedscenario');
    }

    /**
     * Delete expired job records.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $days = (int)get_config('mod_aibranchedscenario', 'jobretention');
        if ($days <= 0) {
            $days = 30;
        }
        $cutoff = time() - ($days * DAYSECS);

        $count = $DB->count_records_select(
            'aibranchedscenario_jobs',
            'timemodified < :cutoff',
            ['cutoff' => $cutoff]
        );
        if (!$count) {
            return;
        }
        $DB->delete_records_select('aibranchedscenario_jobs', 'timemodified < :cutoff', ['cutoff' => $cutoff]);
        mtrace('Removed ' . $count . ' expired generation job records.');
    }
}
