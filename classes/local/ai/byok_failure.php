<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aibranchedscenario\local\ai;

/**
 * Why a scenario written on the site's own AI was refused: the same class, stage and code the LMS Labs
 * service reports, and never any of the prose.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok_failure extends \Exception {
    /** @var string provider, content, request or unknown. */
    public $failureclass;
    /** @var string Where it failed. */
    public $stage;
    /** @var string What failed. */
    public $rule;

    /**
     * Constructor.
     *
     * @param string $failureclass
     * @param string $stage
     * @param string $rule
     */
    public function __construct(string $failureclass, string $stage, string $rule) {
        $this->failureclass = $failureclass;
        $this->stage = $stage;
        $this->rule = $rule;
        parent::__construct($rule);
    }
}
