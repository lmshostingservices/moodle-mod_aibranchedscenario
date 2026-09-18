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

    if ($oldversion < 2026091242) {
        // Release 1.32.0 changes no schema. It fixes the generate request, which never
        // asked the service for the example and the pitfall that the validator has
        // required on every principle since 1.20.0, so every generated definition was
        // refused for a field the plugin had not requested. Nothing stored is affected:
        // the refused definitions were never written.
        upgrade_mod_savepoint(true, 2026091242, 'aibranchedscenario');
    }

    if ($oldversion < 2026091243) {
        // Release 1.33.0 changes no schema and no code. 1.32.0 had already been promoted
        // under this content, so the pipeline had nothing to write; this is the version it
        // needs to publish the same work.
        upgrade_mod_savepoint(true, 2026091243, 'aibranchedscenario');
    }

    if ($oldversion < 2026091244) {
        // Release 1.34.0 changes no schema. A principle with no example or no pitfall no
        // longer rejects the whole definition; it is raised at review instead. Nothing
        // stored is affected - the definitions this refused were never written.
        upgrade_mod_savepoint(true, 2026091244, 'aibranchedscenario');
    }

    if ($oldversion < 2026091245) {
        // Release 1.35.0 changes no schema. An example and a pitfall written into a
        // principle's summary are now pulled back into their own fields, the metrics
        // legend is a button rather than a native disclosure, options are shuffled at
        // generation, the type scale follows the fit, and the title is held to one row.
        upgrade_mod_savepoint(true, 2026091245, 'aibranchedscenario');
    }

    if ($oldversion < 2026091246) {
        // Release 1.36.0 changes no schema. It is the flow review: one decision counter
        // instead of two that disagreed, no consequence screen where there is nothing to
        // report, and a screen that leaves before the next one arrives.
        upgrade_mod_savepoint(true, 2026091246, 'aibranchedscenario');
    }

    if ($oldversion < 2026091247) {
        // Release 1.36.1 changes no schema. A hovered wizard step kept its label instead
        // of taking the site theme's white text onto its own white surface.
        upgrade_mod_savepoint(true, 2026091247, 'aibranchedscenario');
    }

    if ($oldversion < 2026091248) {
        // Release 1.37.0 changes no schema. Media generated after publishing now reaches
        // the learner, the debrief pages are laid out against the slide quality checklist,
        // and "Critical decisions" has a page of its own.
        upgrade_mod_savepoint(true, 2026091248, 'aibranchedscenario');
    }

    if ($oldversion < 2026091249) {
        // Release 1.38.0 changes no schema. The generate route now carries the content
        // standard the pasted prompt has always carried, and a screen with no picture
        // centres its column while reading its words down a left edge.
        upgrade_mod_savepoint(true, 2026091249, 'aibranchedscenario');
    }

    if ($oldversion < 2026091250) {
        // Release 1.39.0 changes no schema. The two ways of writing a scenario now read
        // their craft rules from one list, so the pasted prompt and the generate request
        // cannot state different ones.
        upgrade_mod_savepoint(true, 2026091250, 'aibranchedscenario');
    }

    if ($oldversion < 2026091251) {
        // Release 1.40.0 changes no schema. A character gains a gender, stored inside the
        // existing source JSON column; three accents outside the palette are retired but
        // still validate, so an activity saved with one keeps working and simply draws in
        // the default.
        upgrade_mod_savepoint(true, 2026091251, 'aibranchedscenario');
    }

    if ($oldversion < 2026091252) {
        // Release 1.41.0 changes no schema. The mapper now carries the cast, the scene
        // brief and its alt text off the wire; all three were being dropped, so every
        // stored scenario generated before this has no cast at all. Regenerating or
        // re-importing is what fills it in - nothing here can recover what was never read.
        upgrade_mod_savepoint(true, 2026091252, 'aibranchedscenario');
    }

    if ($oldversion < 2026091253) {
        // Release 1.42.0 changes no schema. It repairs the two teacher-facing screens: the
        // report had no gutter and its single-attempt view rendered outside the shell that
        // declares every design token, and neither the report nor the draft review ever
        // matched a dark Moodle theme because they loaded no JavaScript at all.
        upgrade_mod_savepoint(true, 2026091253, 'aibranchedscenario');
    }

    if ($oldversion < 2026091254) {
        // The idempotency handle sent with a generation now identifies the job rather than
        // the wizard inputs, so a teacher generating again on unchanged inputs gets a new
        // scenario instead of the saved one.
        //
        // That makes the job's outgoing body worth keeping. The service matches a repeated
        // handle against the body it saw the first time, so a retry that rebuilt the body
        // with an upgraded plugin's content standard would be refused as a conflict. The
        // body is stored once and replayed instead of rebuilt.
        upgrade_mod_savepoint(true, 2026091254, 'aibranchedscenario');
    }

    if ($oldversion < 2026091255) {
        // Somewhere to keep that body. The service matches a repeated handle against the
        // body it saw the first time, so a retry that rebuilt the body with an upgraded
        // plugin's content standard would be refused as a conflict rather than resumed.
        $table = new xmldb_table('aibranchedscenario_jobs');
        $field = new xmldb_field('payloadjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'requestjson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026091255, 'aibranchedscenario');
    }

    if ($oldversion < 2026091256) {
        // Release 1.44.0 changes no schema. It asks the service what it accepts from the
        // generation path rather than only from the settings page, so a site where no
        // administrator opened that page still sends the full content standard.
        upgrade_mod_savepoint(true, 2026091256, 'aibranchedscenario');
    }

    if ($oldversion < 2026091257) {
        // Release 1.45.0 changes no schema. A slide with no picture now lays its content
        // out as one column, centred in the card, with every block on the same left edge.
        upgrade_mod_savepoint(true, 2026091257, 'aibranchedscenario');
    }

    if ($oldversion < 2026091258) {
        // Release 1.46.0 changes no schema. Media that cannot be made now says why, on
        // both routes, instead of failing silently and leaving no trace anywhere.
        upgrade_mod_savepoint(true, 2026091258, 'aibranchedscenario');
    }

    if ($oldversion < 2026091259) {
        // Release 1.47.0 changes no schema. Screens made of record cards use the card again
        // rather than a reading measure, the decision records sit two across, and the
        // opening situation is narrated.
        upgrade_mod_savepoint(true, 2026091259, 'aibranchedscenario');
    }

    if ($oldversion < 2026091260) {
        // Release 1.48.0 changes no schema. The ending and the whole debrief are narrated;
        // regenerate an activity's media to pick up the clips they now need.
        upgrade_mod_savepoint(true, 2026091260, 'aibranchedscenario');
    }

    if ($oldversion < 2026091261) {
        // Release 1.49.0 changes no schema. Every scenario now closes on a card that says
        // so and carries the result, rather than stopping on a list of takeaways.
        upgrade_mod_savepoint(true, 2026091261, 'aibranchedscenario');
    }

    if ($oldversion < 2026091262) {
        // Release 1.50.0 changes no schema. The closing card celebrates: confetti in the
        // product's own palette and a short synthesised chime, both of which stay out of
        // the way of anyone who asked for less motion or turned narration off.
        upgrade_mod_savepoint(true, 2026091262, 'aibranchedscenario');
    }

    if ($oldversion < 2026091263) {
        // Release 1.51.0 changes no schema. It removes 1,197 duplicated lines from the
        // stylesheet and repairs the closing card and the decks on a phone.
        upgrade_mod_savepoint(true, 2026091263, 'aibranchedscenario');
    }

    if ($oldversion < 2026091264) {
        // Release 1.52.0 changes no schema. The opening slide plays its clip, the spoken
        // line follows the narration, muting silences rather than rewinds, and the paste
        // screen stopped calling things definitions.
        upgrade_mod_savepoint(true, 2026091264, 'aibranchedscenario');
    }

    if ($oldversion < 2026091465) {
        // Release 1.53.0 changes no schema. The debrief draws the scenario's picture once,
        // on the ending it belongs to, and its review pages use the whole card instead of
        // repeating that picture down their left half.
        upgrade_mod_savepoint(true, 2026091465, 'aibranchedscenario');
    }

    if ($oldversion < 2026091466) {
        // Release 1.54.0 changes no schema. The image briefs carry the cast, an object, a
        // lighting rig and real staging, and the debrief pages are illustrated from their
        // own words - so regenerate an activity's media to pick up the frames they now ask
        // for.
        upgrade_mod_savepoint(true, 2026091466, 'aibranchedscenario');
    }

    if ($oldversion < 2026091467) {
        // Release 1.55.0 changes no schema. The type scale now fills a screen that has room
        // to spare, the two consequence cues are told apart by ear, the options answer the
        // pointer, and the spoken line plays when its control is pressed rather than being
        // read out twice.
        upgrade_mod_savepoint(true, 2026091467, 'aibranchedscenario');
    }

    if ($oldversion < 2026091468) {
        // Release 1.56.0 changes no schema. The four list pages of the debrief are played
        // as sequences rather than shown as lists, which needs one narration clip per item
        // - regenerate an activity's media to pick them up.
        upgrade_mod_savepoint(true, 2026091468, 'aibranchedscenario');
    }

    if ($oldversion < 2026091469) {
        // Release 1.57.0 changes no schema. Each card of the decision record is read as a
        // whole card - the moment, the decision taken, what followed and why it mattered -
        // by a clip of its own, so regenerate an activity's media to pick them up. An older
        // revision without them falls back to the consequence clip it already has.
        upgrade_mod_savepoint(true, 2026091469, 'aibranchedscenario');
    }

    if ($oldversion < 2026091470) {
        // Release 1.58.0 changes no schema. Type hierarchy, the reading cards laid out as
        // rows, and the wizard no longer tripping the browser's own leave-page dialog on a
        // navigation it performed itself.
        upgrade_mod_savepoint(true, 2026091470, 'aibranchedscenario');
    }

    if ($oldversion < 2026091471) {
        // Release 1.59.0 changes no schema. A media run that finished only part of the set
        // is no longer recorded as ready, which is what hid every report of a slide with no
        // picture or no narration.
        upgrade_mod_savepoint(true, 2026091471, 'aibranchedscenario');
    }

    if ($oldversion < 2026091472) {
        // Release 1.60.0 changes no schema. The per-slide quality audit found thirteen
        // faults on screens no sweep had ever looked at, and they are fixed here.
        upgrade_mod_savepoint(true, 2026091472, 'aibranchedscenario');
    }

    if ($oldversion < 2026091473) {
        // Release 1.61.0 changes no schema. A choice that names no successor is read as
        // "carry on" rather than rejecting the scenario, and the pasted prompt no longer
        // contradicts itself about what the last decision node is.
        upgrade_mod_savepoint(true, 2026091473, 'aibranchedscenario');
    }

    if ($oldversion < 2026091474) {
        // Release 1.62.0 changes no schema. The debrief no longer draws an empty picture
        // frame on a scripted page that has no generated scene, the takeaway cards read at
        // body size, and every outcome mark is normalised so it draws in full.
        upgrade_mod_savepoint(true, 2026091474, 'aibranchedscenario');
    }

    if ($oldversion < 2026091475) {
        // Release 1.63.0 changes no schema. The media task re-reads the activity before it
        // decides where its work goes, so media finished after the teacher published is no
        // longer stranded in the working area where no learner can reach it.
        upgrade_mod_savepoint(true, 2026091475, 'aibranchedscenario');
    }

    if ($oldversion < 2026091476) {
        // Release 1.64.0 changes no schema. Media filenames are unique across a whole
        // scenario, publishing survives a clash left by one that is not, and the generation
        // route copies its media into the published revision the same way the import route
        // does.
        upgrade_mod_savepoint(true, 2026091476, 'aibranchedscenario');
    }

    if ($oldversion < 2026091477) {
        // Release 1.65.0 changes no schema. Media on the pasted-prompt route is charged
        // against the daily budget like media on the generate route, and the cost estimate
        // counts the four debrief pictures it had been leaving out.
        upgrade_mod_savepoint(true, 2026091477, 'aibranchedscenario');
    }

    if ($oldversion < 2026091478) {
        // Release 1.66.0 changes no schema. The pasted-prompt route will not start a second
        // media run beside a running one, the undo copy is read from the database rather
        // than from a record held since before a long run, and a held media run is put in
        // front of the teacher instead of being navigated past.
        upgrade_mod_savepoint(true, 2026091478, 'aibranchedscenario');
    }

    if ($oldversion < 2026091479) {
        // Release 1.67.0 changes no schema. The media budget check uses the published price
        // of the media instead of a per-item sum, which had put an ordinary scenario over
        // the whole daily budget and refused its pictures and narration outright.
        upgrade_mod_savepoint(true, 2026091479, 'aibranchedscenario');
    }

    if ($oldversion < 2026091480) {
        // Release 1.68.0 changes no schema. The scene brief is rewritten as one described
        // photograph, the lesson slides get pictures made from their own words, and no
        // frame is asked to darken itself.
        upgrade_mod_savepoint(true, 2026091480, 'aibranchedscenario');
    }

    if ($oldversion < 2026091481) {
        // Release 1.69.0 changes no schema. The cast sheet, the chosen treatment and the
        // teacher's own direction can no longer be trimmed away, and no frame is told it is
        // a photograph when the teacher asked for a painting.
        upgrade_mod_savepoint(true, 2026091481, 'aibranchedscenario');
    }

    if ($oldversion < 2026091482) {
        // Release 1.70.0 changes no schema. The remaining findings from the image audit:
        // names matched as words, props recognisable without reading them, the crisis frame
        // briefed from the crisis text, media filenames reserved across all seven families,
        // and the old composer's dead code removed.
        upgrade_mod_savepoint(true, 2026091482, 'aibranchedscenario');
    }

    if ($oldversion < 2026091483) {
        // Release 1.71.0 changes no schema. Six rules added to the content standard, which
        // both routes carry: never invent legislation, keep every concept concrete, write
        // for an adult reading at work, avoid the AI register, vary the names, and stage
        // every scene in the industry named rather than in the default meeting room.
        upgrade_mod_savepoint(true, 2026091483, 'aibranchedscenario');
    }

    if ($oldversion < 2026091484) {
        // Release 1.72.0 changes no schema. A rule the standard could not fit is now
        // recorded against the scenario instead of being dropped silently, and the cast
        // rule is ranked above the new craft rules so it is never the one dropped.
        upgrade_mod_savepoint(true, 2026091484, 'aibranchedscenario');
    }

    if ($oldversion < 2026091485) {
        // Release 1.73.0 changes no schema. Five faults on the learner-facing side: a
        // resumed attempt graded on the wrong revision, a scale grade discarded, the grade
        // maximum missing from backup, completion left behind by every delete path
        // including erasure, and abandoned runs spending the attempt allowance.
        upgrade_mod_savepoint(true, 2026091485, 'aibranchedscenario');
    }

    if ($oldversion < 2026091486) {
        // Release 1.80.0 changes no schema. A milestone number for the first release
        // intended for the Moodle Marketplace: 1.80 rather than 1.8, because 1.8 sorts
        // below the 1.73 it follows and would read as a downgrade everywhere the release
        // string is compared.
        upgrade_mod_savepoint(true, 2026091486, 'aibranchedscenario');
    }

    if ($oldversion < 2026091801) {
        // Release 1.81.0 changes no schema. It changes what gets DRAWN and what gets
        // CHECKED: the consequence stops clipping on a page, the debrief carries one entry
        // per principle, pronouns are compared against the cast record, and every image is
        // keyed to the idea it illustrates rather than to the screen it lands on.
        //
        // Existing revisions keep working: every new picture falls back to what was shown
        // before it when the scenario predates this release.
        upgrade_mod_savepoint(true, 2026091801, 'aibranchedscenario');
    }

    if ($oldversion < 2026091802) {
        // Release 1.82.0 changes no schema. It finishes what 1.81.0 started and audits what
        // 1.81.0 claimed: the picture top-up now exists rather than only being reported, no
        // screen borrows another's frame any more, and a teacher's name comes off the
        // revisions they published when they are erased.
        upgrade_mod_savepoint(true, 2026091802, 'aibranchedscenario');
    }

    if ($oldversion < 2026091900) {
        // Release 2.0.0 changes no schema.
        //
        // The number is a statement about the checking rather than about the features. It
        // follows an audit of the whole plugin against a written list of seventy-eight
        // invariants - the first time anything had counted all of them rather than the
        // handful most recently worked on - and the release is what that audit turned up:
        // four checks that could not fail, a test that agreed with its own bug, a service
        // nothing called, five picture-borrowing paths, a media run that billed after
        // failing, a stored user id outside the Privacy API, and the plugin's unit tests,
        // which had never once been run.
        upgrade_mod_savepoint(true, 2026091900, 'aibranchedscenario');
    }

    if ($oldversion < 2026091901) {
        // Release 2.0.1 changes no schema. Two faults a learner could see: a screen was
        // never re-fitted after its picture arrived, which is why the page view clipped
        // while fullscreen looked perfect; and the speaker pill's text followed a token that
        // flips with the colour scheme while its background does not, so in dark mode it was
        // near-black on near-black and read as an empty grey bar.
        upgrade_mod_savepoint(true, 2026091901, 'aibranchedscenario');
    }

    return true;
}
