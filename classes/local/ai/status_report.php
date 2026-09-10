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

use html_writer;

/**
 * Renders the connection and credit status shown on the settings page.
 *
 * The full API key is never rendered; only the source of the credentials, the site
 * identifier and the balance are shown.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_report {
    /**
     * Render the status block as HTML.
     *
     * @return string
     */
    public static function render(): string {
        $resolved = credentials::resolve();
        $diagnostics = credentials::diagnostics();

        if ($resolved['source'] === credentials::SOURCE_NONE) {
            // Say why, not just that. A bare "no credentials" sends an administrator
            // looking in the wrong place when the real cause is a half-filled source
            // or Central Config being deliberately ignored.
            $component = credentials::central_component();
            $reasons = [get_string('status:nocredentials', 'mod_aibranchedscenario')];
            if ($diagnostics['ignoring']) {
                $reasons[] = get_string('status:reasonignoring', 'mod_aibranchedscenario');
            } else if (!$diagnostics['centralinstalled']) {
                $reasons[] = get_string('status:reasonnocentral', 'mod_aibranchedscenario', $component);
            } else if ($diagnostics['centralpartial']) {
                $reasons[] = get_string('status:reasoncentralpartial', 'mod_aibranchedscenario', $component);
            } else {
                $reasons[] = get_string('status:reasoncentralempty', 'mod_aibranchedscenario', $component);
            }
            if ($diagnostics['localpartial']) {
                $reasons[] = get_string('status:reasonlocalpartial', 'mod_aibranchedscenario');
            }
            return html_writer::div(implode(' ', $reasons), 'alert alert-warning');
        }

        $rows = [];
        $rows[] = self::row(
            get_string('status:credentialsource', 'mod_aibranchedscenario'),
            $resolved['source'] === credentials::SOURCE_CENTRAL
                ? get_string('status:fromcentral', 'mod_aibranchedscenario', $resolved['component'])
                : get_string('status:fromlocal', 'mod_aibranchedscenario')
        );
        $rows[] = self::row(
            get_string('status:siteid', 'mod_aibranchedscenario'),
            s($resolved['siteid'])
        );
        $rows[] = self::row(
            get_string('status:apikey', 'mod_aibranchedscenario'),
            s(credentials::mask($resolved['apikey']))
        );

        if ($diagnostics['unusualkeyformat']) {
            // A note, not a block. The key is still sent; only LMS Labs can say
            // whether it is valid.
            $rows[] = self::row(
                get_string('status:keyformat', 'mod_aibranchedscenario'),
                html_writer::span(get_string('status:usualkeyformat', 'mod_aibranchedscenario'), 'text-warning')
            );
        }

        $provider = new lmslabs_provider();
        $status = $provider->get_status();

        if (!$status['connected']) {
            $rows[] = self::row(
                get_string('status:connection', 'mod_aibranchedscenario'),
                html_writer::span(s($status['message']), 'text-danger')
            );
            return html_writer::div(implode('', $rows), 'aibs-status');
        }

        $rows[] = self::row(
            get_string('status:connection', 'mod_aibranchedscenario'),
            html_writer::span(get_string('status:connected', 'mod_aibranchedscenario'), 'text-success')
        );
        $rows[] = self::row(
            get_string('status:credits', 'mod_aibranchedscenario'),
            $status['unlimited']
                ? get_string('status:unlimitedcredits', 'mod_aibranchedscenario')
                : (int)$status['credits']
        );

        return html_writer::div(implode('', $rows), 'aibs-status');
    }

    /**
     * Render one label and value pair.
     *
     * @param string $label Already translated label.
     * @param string|int $value Already escaped value.
     * @return string
     */
    protected static function row(string $label, $value): string {
        return html_writer::div(
            html_writer::tag('strong', s($label) . ': ') . $value,
            'aibs-status-row'
        );
    }
}
