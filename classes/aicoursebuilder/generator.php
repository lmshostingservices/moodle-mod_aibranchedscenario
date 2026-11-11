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
 * AI Course Builder adapter for AI Branched Scenario.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS-Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'mod_aibranchedscenario';

    /**
     * Whether this plugin can build activities for AI Course Builder.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\local_aicoursebuilder\local\generator_runtime')
            && \local_aicoursebuilder\local\generator_runtime::component_available(self::COMPONENT);
    }

    /**
     * Credits one activity will use.
     *
     * @param array $brief
     * @return int
     */
    public static function estimate(array $brief): int {
        return \local_aicoursebuilder\local\generator_runtime::estimate(self::COMPONENT, $brief);
    }

    /**
     * Start generating one activity.
     *
     * @param int $cmid
     * @param array $brief
     * @return string
     */
    public static function generate(int $cmid, array $brief): string {
        return \local_aicoursebuilder\local\generator_runtime::generate(self::COMPONENT, $cmid, $brief);
    }

    /**
     * Where a generation job is up to.
     *
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function status(int $cmid, string $jobid): array {
        return \local_aicoursebuilder\local\generator_runtime::status(self::COMPONENT, $cmid, $jobid);
    }

    /**
     * Credits one activity will use.
     *
     * @param array $brief
     * @return int
     */
    public static function native_estimate(array $brief): int {
        return native_adapter::estimate($brief);
    }

    /**
     * Start generating one activity.
     *
     * @param int $cmid
     * @param array $brief
     * @return string
     */
    public static function native_generate(int $cmid, array $brief): string {
        return native_adapter::generate($cmid, $brief);
    }

    /**
     * Where a generation job is up to.
     *
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function native_status(int $cmid, string $jobid): array {
        return native_adapter::status($cmid, $jobid);
    }

    /**
     * Save a finished generation into the activity.
     *
     * @param int $cmid
     * @param array $result
     * @return bool
     */
    public static function native_save_result(int $cmid, array $result): bool {
        return native_adapter::save_result($cmid, $result);
    }
}
