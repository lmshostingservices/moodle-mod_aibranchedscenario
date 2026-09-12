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
 * Site administration settings.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\schema;

if ($ADMIN->fulltree) {
    global $OUTPUT;

    // A setting's name, with Moodle's help icon beside it.
    //
    // Moodle gives an admin setting an inline description rather than the question mark a
    // teacher gets on an activity form, and a page of twenty-six inline paragraphs is a
    // wall of text nobody reads. Core appends a help icon to a setting name in exactly
    // this way on the media players page, so the control is the standard one and the
    // explanation is a click away rather than always on screen.
    //
    // The icon is added only where the help string exists, so a setting whose help has
    // not been written renders as a plain name rather than as a broken link.
    $label = function (string $key) use ($OUTPUT): string {
        $name = get_string('settings:' . $key, 'mod_aibranchedscenario');
        $helpkey = 'settings:' . $key . '_help';
        if (get_string_manager()->string_exists($helpkey, 'mod_aibranchedscenario')) {
            $icon = $OUTPUT->help_icon('settings:' . $key, 'mod_aibranchedscenario');
            $name .= '&nbsp;' . $icon;
        }
        return $name;
    };

    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/connectionheading',
        get_string('settings:connectionheading', 'mod_aibranchedscenario'),
        get_string('settings:connectiondesc', 'mod_aibranchedscenario')
    ));

    $settings->add(new admin_setting_description(
        'mod_aibranchedscenario/connectionstatus',
        $label('status'),
        \mod_aibranchedscenario\local\ai\status_report::render()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/preferlocalcredentials',
        $label('preferlocal'),
        '',
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/centralcomponent',
        $label('centralcomponent'),
        '',
        'local_aiconfig',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/siteid',
        $label('siteid'),
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aibranchedscenario/apikey',
        $label('apikey'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/apihost',
        $label('apihost'),
        '',
        lmslabs_provider::DEFAULT_HOST,
        PARAM_URL
    ));

    $settings->add(new admin_setting_configduration(
        'mod_aibranchedscenario/requesttimeout',
        $label('requesttimeout'),
        '',
        180,
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/limitsheading',
        get_string('settings:limitsheading', 'mod_aibranchedscenario'),
        get_string('settings:limitsheadingdesc', 'mod_aibranchedscenario')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/maxsourcechars',
        $label('maxsourcechars'),
        '',
        schema::MAX_SOURCE_CHARS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/dailyquota',
        $label('dailyquota'),
        '',
        400,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/allowimages',
        $label('allowimages'),
        '',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/allowaudio',
        $label('allowaudio'),
        '',
        1
    ));

    // Three voices, so a scenario does not sound like one person reading a play. The
    // narrator reads the situation; a character's own line is spoken in the voice that
    // matches the gender recorded for them, and falls back to the narrator when the
    // scenario names nobody.
    $voiceoptions = [];
    foreach (\mod_aibranchedscenario\local\media_manager::voices() as $voiceid => $kind) {
        $voiceoptions[$voiceid] = $voiceid . ' (' . get_string('voice:' . $kind, 'mod_aibranchedscenario') . ')';
    }
    foreach (['narrator' => 'Aoede', 'male' => 'Puck', 'female' => 'Kore'] as $role => $default) {
        $settings->add(new admin_setting_configselect(
            'mod_aibranchedscenario/' . $role . 'voice',
            $label($role . 'voice'),
            '',
            $default,
            $voiceoptions
        ));
    }

    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/defaultsheading',
        get_string('settings:defaultsheading', 'mod_aibranchedscenario'),
        get_string('settings:defaultsheadingdesc', 'mod_aibranchedscenario')
    ));

    $themeoptions = [];
    foreach (schema::theme_ids() as $themeid) {
        $themeoptions[$themeid] = get_string('theme:' . $themeid, 'mod_aibranchedscenario');
    }
    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaulttheme',
        $label('defaulttheme'),
        '',
        'slate',
        $themeoptions
    ));

    $languageoptions = [];
    foreach (schema::languages() as $languageid) {
        $languageoptions[$languageid] = get_string(
            'language:' . strtolower(str_replace('-', '', $languageid)),
            'mod_aibranchedscenario'
        );
    }
    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaultlanguage',
        $label('defaultlanguage'),
        '',
        'en-AU',
        $languageoptions
    ));

    $yesno = [0 => get_string('no'), 1 => get_string('yes')];
    // Each toggle with the value a new activity should start at. Holding the way forward
    // closed until a screen has been heard out is the one that is off unless asked for:
    // it is a deliberate restriction on the learner, not a presentation preference.
    $toggles = [
        'showtimeline' => 1,
        'showmetrics' => 1,
        'showdebrief' => 1,
        'enableimages' => 1,
        'enableaudio' => 1,
        'requirelisten' => 0,
    ];
    foreach ($toggles as $toggle => $starts) {
        $settings->add(new admin_setting_configselect(
            'mod_aibranchedscenario/default' . $toggle,
            $label('default' . $toggle),
            '',
            $starts,
            $yesno
        ));
    }

    // The two thresholds every reading and every skill bar is coloured against.
    foreach (['bandgreen' => 67, 'bandred' => 34] as $band => $starts) {
        $settings->add(new admin_setting_configtext(
            'mod_aibranchedscenario/default' . $band,
            $label('default' . $band),
            '',
            $starts,
            PARAM_INT,
            4
        ));
    }

    $attemptoptions = [0 => get_string('unlimited', 'mod_aibranchedscenario')];
    for ($i = 1; $i <= 10; $i++) {
        $attemptoptions[$i] = $i;
    }
    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaultmaxattempts',
        $label('defaultmaxattempts'),
        '',
        3,
        $attemptoptions
    ));

    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaultallowreplay',
        $label('defaultallowreplay'),
        '',
        1,
        $yesno
    ));

    $grademethodoptions = [];
    foreach (schema::grademethods() as $method) {
        $grademethodoptions[$method] = get_string('grademethod:' . $method, 'mod_aibranchedscenario');
    }
    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaultgrademethod',
        $label('defaultgrademethod'),
        '',
        'highest',
        $grademethodoptions
    ));

    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaultcompletionfinish',
        $label('defaultcompletionfinish'),
        '',
        1,
        $yesno
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/defaultcompletionminscore',
        $label('defaultcompletionminscore'),
        '',
        100,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/defaultgradepass',
        $label('defaultgradepass'),
        '',
        100,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/jobretention',
        $label('jobretention'),
        '',
        30,
        PARAM_INT
    ));
}
