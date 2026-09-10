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

namespace mod_aibranchedscenario\local\ai;

use moodle_exception;

/**
 * Raised when an AI provider call cannot be completed.
 *
 * The message shown to the user is deliberately short and never contains request
 * bodies, prompts or credentials.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generation_exception extends moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorkey Language string key in mod_aibranchedscenario.
     * @param mixed $a Optional language string parameter.
     */
    public function __construct(string $errorkey, $a = null) {
        parent::__construct($errorkey, 'mod_aibranchedscenario', '', $a);
    }
}
