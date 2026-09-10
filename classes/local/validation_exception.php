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

use moodle_exception;

/**
 * Raised when a scenario definition fails validation.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validation_exception extends moodle_exception {
    /** @var string[] Individual validation problems. */
    protected $problems = [];

    /**
     * Constructor.
     *
     * @param string[] $problems List of human readable validation problems.
     */
    public function __construct(array $problems) {
        $this->problems = array_values($problems);
        $summary = implode(' | ', array_slice($this->problems, 0, 8));
        if (count($this->problems) > 8) {
            $summary .= ' | ...';
        }
        parent::__construct('error:invalidscenario', 'mod_aibranchedscenario', '', $summary);
    }

    /**
     * Get the list of validation problems.
     *
     * @return string[]
     */
    public function get_problems(): array {
        return $this->problems;
    }
}
