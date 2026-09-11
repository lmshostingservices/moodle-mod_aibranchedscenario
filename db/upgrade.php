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

    if ($oldversion < 2026091004) {
        // Release 1.1.0 changes no schema. It rebuilds scene image briefing and aligns
        // the media routes with the service contract, in PHP only.
        upgrade_mod_savepoint(true, 2026091004, 'aibranchedscenario');
    }

    if ($oldversion < 2026091005) {
        // Release 1.1.1 changes no schema and no behaviour. It is a packaging bump so a
        // site that has already seen the v1.1.0 number installs cleanly.
        upgrade_mod_savepoint(true, 2026091005, 'aibranchedscenario');
    }

    if ($oldversion < 2026091006) {
        // Release 1.2.0 changes no schema and no behaviour. It is a packaging bump past
        // a version number the release pipeline had already recorded.
        upgrade_mod_savepoint(true, 2026091006, 'aibranchedscenario');
    }

    if ($oldversion < 2026091007) {
        // Release 1.3.0 changes no schema. Two new wizard values, the described setting
        // and industry, live inside the existing source JSON column.
        upgrade_mod_savepoint(true, 2026091007, 'aibranchedscenario');
    }

    if ($oldversion < 2026091008) {
        // Release 1.3.1 changes no schema. It corrects nine faults found by reviewing
        // the 1.3.0 changes before they were installed anywhere.
        upgrade_mod_savepoint(true, 2026091008, 'aibranchedscenario');
    }

    if ($oldversion < 2026091009) {
        // Release 1.4.0 changes no schema. Media generation gains its own ad-hoc task,
        // which needs no table of its own.
        upgrade_mod_savepoint(true, 2026091009, 'aibranchedscenario');
    }

    if ($oldversion < 2026091010) {
        global $DB;
        $dbman = $DB->get_manager();

        // Generating again used to overwrite the working copy in place, so an hour of
        // hand editing disappeared on one click with no way back. The previous copy is
        // now kept so that exactly one step can be undone.
        $table = new xmldb_table('aibranchedscenario');
        $field = new xmldb_field('previousjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'scenariojson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026091010, 'aibranchedscenario');
    }

    if ($oldversion < 2026091011) {
        // Release 1.5.1 changes no schema. The daily allowance becomes a credit budget
        // rather than a request count, which needs no migration: it is read from the
        // job records already there.
        upgrade_mod_savepoint(true, 2026091011, 'aibranchedscenario');
    }

    if ($oldversion < 2026091012) {
        // Release 1.5.2 changes no schema. The generic course introduction is no longer
        // written into the opening situation.
        upgrade_mod_savepoint(true, 2026091012, 'aibranchedscenario');
    }

    if ($oldversion < 2026091013) {
        // Release 1.5.3 changes no schema. The service's own failure explanation is no
        // longer discarded on its way to the teacher.
        upgrade_mod_savepoint(true, 2026091013, 'aibranchedscenario');
    }

    if ($oldversion < 2026091014) {
        // Release 1.6.0 changes no schema. It brings every request the plugin sends
        // inside the service's published validation rules.
        upgrade_mod_savepoint(true, 2026091014, 'aibranchedscenario');
    }

    if ($oldversion < 2026091015) {
        // Release 1.6.1 changes no schema. It restores a character the previous release
        // was wrongly leaving out of the request.
        upgrade_mod_savepoint(true, 2026091015, 'aibranchedscenario');
    }

    if ($oldversion < 2026091016) {
        // Release 1.6.2 changes no schema. It shows the field the service objected to
        // when a request is rejected, instead of an unexplained failure.
        upgrade_mod_savepoint(true, 2026091016, 'aibranchedscenario');
    }

    if ($oldversion < 2026091017) {
        // Release 1.6.3 changes no schema. The draft review page gains advisory notes
        // about decisions that change little, which are computed when the page is built.
        upgrade_mod_savepoint(true, 2026091017, 'aibranchedscenario');
    }

    if ($oldversion < 2026091100) {
        // Release 1.6.4 changes no schema. It corrects the mapping of the service's
        // content nodes, which no stored scenario can be holding: the scenarios affected
        // were refused at validation and never written.
        upgrade_mod_savepoint(true, 2026091100, 'aibranchedscenario');
    }

    if ($oldversion < 2026091101) {
        // Release 1.7.0 changes no schema. The activity defaults become site settings, and
        // an unsaved setting falls back to the shipped value, so nothing needs seeding.
        upgrade_mod_savepoint(true, 2026091101, 'aibranchedscenario');
    }

    if ($oldversion < 2026091102) {
        // Release 1.7.1 changes no schema. A stored working copy containing a beat is
        // repaired the next time it is validated, which happens on save and on publish,
        // so nothing needs migrating here.
        upgrade_mod_savepoint(true, 2026091102, 'aibranchedscenario');
    }

    if ($oldversion < 2026091103) {
        // Release 1.7.2 changes no schema. It corrects the order of the permission check
        // in the external API and the tests that were never reaching it.
        upgrade_mod_savepoint(true, 2026091103, 'aibranchedscenario');
    }

    if ($oldversion < 2026091104) {
        // Release 1.8.0 changes no schema. It is the first release whose packaged files
        // all agree on what version they belong to.
        upgrade_mod_savepoint(true, 2026091104, 'aibranchedscenario');
    }

    if ($oldversion < 2026091105) {
        // Release 1.8.1 changes no schema. A stored scenario is re-validated on save and
        // on publish, so a working copy rejected before this release simply has to be
        // generated or imported again; there is nothing to migrate.
        upgrade_mod_savepoint(true, 2026091105, 'aibranchedscenario');
    }

    return true;
}
