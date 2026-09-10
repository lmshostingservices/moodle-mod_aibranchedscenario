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
 * Plugin administration settings.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\schema;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/connectionheading',
        get_string('settings:connectionheading', 'mod_aibranchedscenario'),
        get_string('settings:connectiondesc', 'mod_aibranchedscenario')
    ));

    $settings->add(new admin_setting_description(
        'mod_aibranchedscenario/connectionstatus',
        get_string('settings:status', 'mod_aibranchedscenario'),
        \mod_aibranchedscenario\local\ai\status_report::render()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/preferlocalcredentials',
        get_string('settings:preferlocal', 'mod_aibranchedscenario'),
        get_string('settings:preferlocaldesc', 'mod_aibranchedscenario'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/centralcomponent',
        get_string('settings:centralcomponent', 'mod_aibranchedscenario'),
        get_string('settings:centralcomponentdesc', 'mod_aibranchedscenario'),
        'local_aiconfig',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/siteid',
        get_string('settings:siteid', 'mod_aibranchedscenario'),
        get_string('settings:siteiddesc', 'mod_aibranchedscenario'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aibranchedscenario/apikey',
        get_string('settings:apikey', 'mod_aibranchedscenario'),
        get_string('settings:apikeydesc', 'mod_aibranchedscenario'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/apihost',
        get_string('settings:apihost', 'mod_aibranchedscenario'),
        get_string('settings:apihostdesc', 'mod_aibranchedscenario'),
        lmslabs_provider::DEFAULT_HOST,
        PARAM_URL
    ));

    $settings->add(new admin_setting_configduration(
        'mod_aibranchedscenario/requesttimeout',
        get_string('settings:requesttimeout', 'mod_aibranchedscenario'),
        get_string('settings:requesttimeoutdesc', 'mod_aibranchedscenario'),
        180,
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/limitsheading',
        get_string('settings:limitsheading', 'mod_aibranchedscenario'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/maxsourcechars',
        get_string('settings:maxsourcechars', 'mod_aibranchedscenario'),
        get_string('settings:maxsourcecharsdesc', 'mod_aibranchedscenario'),
        schema::MAX_SOURCE_CHARS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/dailyquota',
        get_string('settings:dailyquota', 'mod_aibranchedscenario'),
        get_string('settings:dailyquotadesc', 'mod_aibranchedscenario'),
        40,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/allowimages',
        get_string('settings:allowimages', 'mod_aibranchedscenario'),
        get_string('settings:allowimagesdesc', 'mod_aibranchedscenario'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_aibranchedscenario/allowaudio',
        get_string('settings:allowaudio', 'mod_aibranchedscenario'),
        get_string('settings:allowaudiodesc', 'mod_aibranchedscenario'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_aibranchedscenario/defaultsheading',
        get_string('settings:defaultsheading', 'mod_aibranchedscenario'),
        ''
    ));

    $themeoptions = [];
    foreach (schema::theme_ids() as $themeid) {
        $themeoptions[$themeid] = get_string('theme:' . $themeid, 'mod_aibranchedscenario');
    }
    $settings->add(new admin_setting_configselect(
        'mod_aibranchedscenario/defaulttheme',
        get_string('settings:defaulttheme', 'mod_aibranchedscenario'),
        '',
        'slate',
        $themeoptions
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aibranchedscenario/jobretention',
        get_string('settings:jobretention', 'mod_aibranchedscenario'),
        get_string('settings:jobretentiondesc', 'mod_aibranchedscenario'),
        30,
        PARAM_INT
    ));
}
