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

/**
 * Database upgrade steps.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the upgrade steps from the given old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true.
 */
function xmldb_aibranchedscenario_upgrade($oldversion) {
    if ($oldversion < 2026090900) {
        // First released version; nothing to upgrade from.
        upgrade_mod_savepoint(true, 2026090900, 'aibranchedscenario');
    }

    if ($oldversion < 2026090901) {
        // Release 1.0.1 changes no schema. The 1.0.0 archive it supersedes was never
        // promoted, so no site can hold the pre-release column layout through an
        // upgrade; a site whose 1.0.0 install aborted has no version row at all and
        // is recovered by dropping the orphaned tables, not by this step.
        upgrade_mod_savepoint(true, 2026090901, 'aibranchedscenario');
    }

    if ($oldversion < 2026090902) {
        // Release 1.0.2 changes no schema. It corrects two Moodle 4.4 compatibility
        // faults in PHP code only, so there is nothing to migrate.
        upgrade_mod_savepoint(true, 2026090902, 'aibranchedscenario');
    }

    if ($oldversion < 2026090903) {
        // Release 1.0.3 changes no schema. It answers the release pipeline's security
        // and style findings in PHP code only, so there is nothing to migrate.
        upgrade_mod_savepoint(true, 2026090903, 'aibranchedscenario');
    }

    if ($oldversion < 2026090904) {
        // Release 1.0.4 changes no schema. It corrects two faults found by the first
        // real browser pass, in PHP and CSS only, so there is nothing to migrate.
        upgrade_mod_savepoint(true, 2026090904, 'aibranchedscenario');
    }

    if ($oldversion < 2026091000) {
        // Release 1.0.5 changes no schema. It corrects the Central Config credential
        // resolver in PHP only, so there is nothing to migrate.
        upgrade_mod_savepoint(true, 2026091000, 'aibranchedscenario');
    }

    if ($oldversion < 2026091001) {
        // Release 1.0.6 changes no schema. It aligns the LMS Labs request and response
        // handling with the service's published contract, in PHP only.
        upgrade_mod_savepoint(true, 2026091001, 'aibranchedscenario');
    }

    if ($oldversion < 2026091002) {
        // Release 1.0.7 changes no schema. It cuts request values to the service's
        // documented ceilings, in PHP only.
        upgrade_mod_savepoint(true, 2026091002, 'aibranchedscenario');
    }

    if ($oldversion < 2026091003) {
        // Release 1.0.8 changes no schema. It adds failure diagnostics, corrects an
        // assumed API key format and defends button colour against site themes.
        upgrade_mod_savepoint(true, 2026091003, 'aibranchedscenario');
    }

    return true;
}
