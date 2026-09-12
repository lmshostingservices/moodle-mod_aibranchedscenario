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
    global $DB;

    // Once, at the top. It used to be fetched inside one of the version blocks below, which
    // meant any site whose stored version was already past that block never executed the
    // line - and the next block that needed it called field_exists() on null and the install
    // died mid-upgrade. A helper every step may need belongs to the function, not to one
    // step that happened to need it first.
    $dbman = $DB->get_manager();

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

    if ($oldversion < 2026091106) {
        // Release 1.9.0 changes no schema. A scenario stored before it may contain a
        // choice with no consequence, which is now refused: those are re-validated on
        // save and on publish, so such a scenario must be generated or imported again.
        upgrade_mod_savepoint(true, 2026091106, 'aibranchedscenario');
    }

    if ($oldversion < 2026091107) {
        // Release 1.9.1 changes no schema. A beat is now rendered differently, which is a
        // presentation change over the same stored definition.
        upgrade_mod_savepoint(true, 2026091107, 'aibranchedscenario');
    }

    if ($oldversion < 2026091108) {
        // Release 1.9.2 changes no schema. Consequence screens are now narrated, which is
        // new media rather than new storage: a scenario published before this release has
        // no clip for a branch and plays silently on that screen until it is regenerated.
        upgrade_mod_savepoint(true, 2026091108, 'aibranchedscenario');
    }

    if ($oldversion < 2026091109) {
        // Release 1.10.0 changes no schema. Nodes may now record who speaks, which is
        // stored inside the existing definition JSON, and narration is now split into a
        // narrator clip and a character clip: a scenario published before this release
        // keeps the single clip it has until it is generated again.
        upgrade_mod_savepoint(true, 2026091109, 'aibranchedscenario');
    }

    if ($oldversion < 2026091110) {
        // Release 1.11.0 changes no schema. It adds price settings, which take their
        // defaults, and reduces what is narrated: a scenario already generated keeps the
        // clips it has, and generating it again produces fewer of them.
        upgrade_mod_savepoint(true, 2026091110, 'aibranchedscenario');
    }

    if ($oldversion < 2026091111) {
        // Release 1.11.1 changes no schema. Prices are now held in credits rather than in
        // currency: a site that had already set them takes the new defaults, so a site
        // which changed them should check them again.
        upgrade_mod_savepoint(true, 2026091111, 'aibranchedscenario');
    }

    if ($oldversion < 2026091112) {
        // Release 1.12.0 changes no schema and no behaviour: it is the version number the
        // work released as 1.10.0, 1.11.0 and 1.11.1 is published under.
        upgrade_mod_savepoint(true, 2026091112, 'aibranchedscenario');
    }

    if ($oldversion < 2026091113) {
        // Release 1.12.1 changes no schema. The price settings are now typed as integers
        // rather than accepted raw, so a value that is not a number is refused at the
        // point it is entered instead of quietly falling back when it is read.
        upgrade_mod_savepoint(true, 2026091113, 'aibranchedscenario');
    }

    if ($oldversion < 2026091114) {
        // Release 1.12.2 removes six settings a site should never have been given: what it
        // is charged, and how much of a scenario is narrated. Both are LMS Labs decisions.
        // Any values a site set are removed rather than left behind to confuse an
        // administrator reading the config table.
        $gonesettings = ['pricebase', 'priceimages', 'pricevoice', 'creditrate',
            'pricecurrency', 'narrationscope'];
        foreach ($gonesettings as $gone) {
            unset_config($gone, 'mod_aibranchedscenario');
        }
        upgrade_mod_savepoint(true, 2026091114, 'aibranchedscenario');
    }

    if ($oldversion < 2026091115) {
        // Release 1.12.3 changes no schema: the cost card drops a badge.
        upgrade_mod_savepoint(true, 2026091115, 'aibranchedscenario');
    }

    if ($oldversion < 2026091116) {
        // Release 1.13.0 changes no schema. The wizard gains a first step, so a bookmarked
        // link carrying ?step=n now lands one step earlier than it used to.
        upgrade_mod_savepoint(true, 2026091116, 'aibranchedscenario');
    }

    if ($oldversion < 2026091117) {
        // Release 1.13.1 changes no schema: the debrief is paged rather than scrolled.
        upgrade_mod_savepoint(true, 2026091117, 'aibranchedscenario');
    }

    if ($oldversion < 2026091118) {
        // Release 1.14.0 changes no schema. Principles may now carry a worked example and
        // the mistake it prevents, stored inside the existing definition JSON: a scenario
        // published before this release simply has neither, and its opening lesson teaches
        // the principle without the words to use until it is generated again.
        upgrade_mod_savepoint(true, 2026091118, 'aibranchedscenario');
    }

    if ($oldversion < 2026091119) {
        // Release 1.14.1 changes no schema: the import box repairs a document whose only
        // fault is unescaped quotation marks inside its values.
        upgrade_mod_savepoint(true, 2026091119, 'aibranchedscenario');
    }

    if ($oldversion < 2026091120) {
        // Release 1.15.0 changes no schema, but it does change what the automatic target
        // does: a choice pointing at it now goes to the next stage where there is one,
        // rather than straight to an ending. A published scenario using it mid-story
        // therefore plays further than it did before, which is what its author intended.
        upgrade_mod_savepoint(true, 2026091120, 'aibranchedscenario');
    }

    if ($oldversion < 2026091121) {
        // Release 1.15.1 changes no schema: the authoring prompt now describes the
        // document this plugin actually accepts.
        upgrade_mod_savepoint(true, 2026091121, 'aibranchedscenario');
    }

    if ($oldversion < 2026091122) {
        // Release 1.15.2 changes no schema: the prompt now asks for the spoken line and
        // its speaker, which the player has been rendering since 1.10.0.
        upgrade_mod_savepoint(true, 2026091122, 'aibranchedscenario');
    }

    if ($oldversion < 2026091123) {
        // Release 1.15.3 changes no schema: the generation wait is stated plainly rather
        // than measured from the wrong half of the job.
        upgrade_mod_savepoint(true, 2026091123, 'aibranchedscenario');
    }

    if ($oldversion < 2026091124) {
        // Release 1.16.0 changes no schema: the opening lesson is laid out as a fixed
        // slide frame rather than a card that shrinks to its contents.
        upgrade_mod_savepoint(true, 2026091124, 'aibranchedscenario');
    }

    if ($oldversion < 2026091125) {
        // Release 1.16.1 changes no schema: the sticky bar measures the whole stack of
        // pinned furniture above it rather than only what covers the very top edge.
        upgrade_mod_savepoint(true, 2026091125, 'aibranchedscenario');
    }

    if ($oldversion < 2026091126) {
        // Release 1.17.0 changes no schema: the player page no longer scrolls in the
        // learner's view once everything on it fits.
        upgrade_mod_savepoint(true, 2026091126, 'aibranchedscenario');
    }

    if ($oldversion < 2026091127) {
        // Release 1.18.0 changes no schema: a design-system pass over the stylesheet.
        upgrade_mod_savepoint(true, 2026091127, 'aibranchedscenario');
    }

    if ($oldversion < 2026091128) {
        // Release 1.18.1 changes no schema: the narrow and touch layouts.
        upgrade_mod_savepoint(true, 2026091128, 'aibranchedscenario');
    }

    if ($oldversion < 2026091129) {
        // Release 1.18.2 changes no schema: the metric legend moved into the top bar.
        upgrade_mod_savepoint(true, 2026091129, 'aibranchedscenario');
    }

    if ($oldversion < 2026091130) {
        // Release 1.19.0 changes no schema: the design-system pass and the dark palette.
        upgrade_mod_savepoint(true, 2026091130, 'aibranchedscenario');
    }

    if ($oldversion < 2026091131) {
        // Release 1.20.0 changes no schema: the fixed frame, the narration and the bar.
        upgrade_mod_savepoint(true, 2026091131, 'aibranchedscenario');
    }

    if ($oldversion < 2026091132) {
        // Release 1.20.1 changes no schema: the readings became dials.
        upgrade_mod_savepoint(true, 2026091132, 'aibranchedscenario');
    }

    if ($oldversion < 2026091133) {
        // Whether the learner must hear a slide out before the way on unlocks.
        $table = new xmldb_table('aibranchedscenario');
        $field = new xmldb_field(
            'requirelisten',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'enableaudio'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026091133, 'aibranchedscenario');
    }

    if ($oldversion < 2026091134) {
        // Release 1.22.0 changes no schema: the nine screen-audit items.
        upgrade_mod_savepoint(true, 2026091134, 'aibranchedscenario');
    }

    if ($oldversion < 2026091135) {
        // Release 1.23.0 changes no schema: one shape for every screen.
        upgrade_mod_savepoint(true, 2026091135, 'aibranchedscenario');
    }

    if ($oldversion < 2026091136) {
        // Release 1.24.0 changes no schema: arrows, centring and the wizard's padding.
        upgrade_mod_savepoint(true, 2026091136, 'aibranchedscenario');
    }

    if ($oldversion < 2026091137) {
        // Where a reading stops being good and where it becomes a problem. These were two
        // numbers written into the JavaScript, which meant the same thresholds for a
        // de-escalation exercise and a sales conversation.
        $table = new xmldb_table('aibranchedscenario');
        foreach ([['bandgreen', '67', 'requirelisten'], ['bandred', '34', 'bandgreen']] as $spec) {
            $field = new xmldb_field(
                $spec[0],
                XMLDB_TYPE_INTEGER,
                '3',
                null,
                XMLDB_NOTNULL,
                null,
                $spec[1],
                $spec[2]
            );
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026091137, 'aibranchedscenario');
    }

    if ($oldversion < 2026091138) {
        // Release 1.26.0 changes no schema: the readings carry their mark everywhere.
        upgrade_mod_savepoint(true, 2026091138, 'aibranchedscenario');
    }

    if ($oldversion < 2026091239) {
        // Release 1.27.0 changes no schema: every figure counts to its value, the rings
        // are drawn thin, hover lifts instead of repainting, and a slide without a
        // picture stops reserving the column for one.
        upgrade_mod_savepoint(true, 2026091239, 'aibranchedscenario');
    }

    // Ascending, always. $oldversion is read once and never changes as the steps run, so
    // a block placed out of order still matches after a later block has already moved the
    // stored version past it - and its savepoint then tries to set the version backwards,
    // which Moodle refuses with "Cannot downgrade". A new step goes at the bottom.
    if ($oldversion < 2026091240) {
        // Release 1.30.0 changes no schema. The work it carries is the look-and-feel
        // programme that 1.27.0 was built for and never released under.
        upgrade_mod_savepoint(true, 2026091240, 'aibranchedscenario');
    }

    if ($oldversion < 2026091241) {
        // Release 1.31.0 changes no schema. The settings page gains a help icon and a
        // written explanation on every setting, and several settings are renamed; a site
        // that installed 1.30.0 needs this bump to pick the new strings up.
        upgrade_mod_savepoint(true, 2026091241, 'aibranchedscenario');
    }

    return true;
}
