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

/**
 * Resolves the LMS Labs site credentials this plugin should use.
 *
 * Credentials come from the shared Central Config plugin when one is installed, and
 * fall back to this plugin's own settings. The API key is never returned to a browser,
 * written to a log, or included in generated content; only the masked form is
 * displayable.
 *
 * Precedence, unless the administrator has turned on "Ignore Central Config":
 *
 *   1. A complete pair from Central Config.
 *   2. A complete pair from this plugin's own settings.
 *   3. Nothing, which is what makes the activity report AI generation unavailable.
 *
 * A pair is complete only when both the site identifier and the API key are present
 * and come from the same source. An empty local field therefore cannot mask a valid
 * central credential, and a half-filled source is skipped rather than being combined
 * with the other one.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /** @var string Credentials came from the Central Config plugin. */
    const SOURCE_CENTRAL = 'central';

    /** @var string Credentials came from this plugin's own settings. */
    const SOURCE_LOCAL = 'local';

    /** @var string No usable credentials were found. */
    const SOURCE_NONE = 'none';

    /** @var string The default Central Config component. */
    const DEFAULT_COMPONENT = 'local_aiconfig';

    /**
     * The shape LMS Labs API keys have used to date.
     *
     * This is only ever used to decorate a diagnostic message. It is deliberately not
     * a gate on resolution: a key that does not match this pattern may still be a
     * perfectly good key issued under a newer scheme, and only the LMS Labs server can
     * say whether a key is valid. Refusing to send an unfamiliar key would strand a
     * correctly configured site with no way to tell what was wrong.
     *
     * @var string
     */
    const KEY_PATTERN = '/^aigr_[0-9a-f]{64}$/';

    /**
     * Components that are tried, in order, when reading shared credentials by setting
     * name. The administrator can name a different component in this plugin's
     * settings; that name is tried first.
     *
     * @return string[]
     */
    protected static function candidate_components(): array {
        return [
            self::DEFAULT_COMPONENT,
            'local_lmslabs_config',
            'local_lmslabsconfig',
            'local_lmslabs',
        ];
    }

    /**
     * Setting names that are tried, in order, for the site identifier.
     *
     * @return string[]
     */
    protected static function siteid_keys(): array {
        return ['siteid', 'site_id', 'lmslabssiteid'];
    }

    /**
     * Setting names that are tried, in order, for the API key.
     *
     * @return string[]
     */
    protected static function apikey_keys(): array {
        return ['apikey', 'api_key', 'lmslabsapikey'];
    }

    /**
     * Whether the administrator has asked this plugin to ignore Central Config.
     *
     * @return bool
     */
    public static function ignoring_central(): bool {
        return (bool)get_config('mod_aibranchedscenario', 'preferlocalcredentials');
    }

    /**
     * The Central Config component this plugin should read.
     *
     * @return string
     */
    public static function central_component(): string {
        $configured = trim((string)get_config('mod_aibranchedscenario', 'centralcomponent'));
        if ($configured !== '' && preg_match('/^[a-z][a-z0-9_]{2,60}$/', $configured)) {
            return $configured;
        }
        return self::DEFAULT_COMPONENT;
    }

    /**
     * Resolve the credentials to use.
     *
     * @return array Keys: siteid, apikey, source, component, method.
     */
    public static function resolve(): array {
        if (!self::ignoring_central()) {
            $central = self::read_central();
            if ($central !== null) {
                return $central;
            }
        }

        $local = self::read_local();
        if ($local !== null) {
            return $local;
        }

        return [
            'siteid'    => '',
            'apikey'    => '',
            'source'    => self::SOURCE_NONE,
            'component' => '',
            'method'    => '',
        ];
    }

    /**
     * Read a complete credential pair from Central Config, if there is one.
     *
     * Three access routes are tried in the order of how well documented they are. The
     * first that yields both values wins; a route that yields only one is skipped, so
     * a half-configured Central Config cannot contribute half a pair.
     *
     * @return array|null Resolved credentials, or null when Central Config has no complete pair.
     */
    protected static function read_central(): ?array {
        $component = self::central_component();

        foreach (['class_pair', 'class_accessors', 'legacy_functions'] as $method) {
            $pair = self::{'via_' . $method}($component);
            if ($pair !== null) {
                return $pair + ['source' => self::SOURCE_CENTRAL, 'component' => $component, 'method' => $method];
            }
        }

        // Finally, read the stored settings directly. The documented names are
        // local_aiconfig/siteid and local_aiconfig/apikey.
        $components = [$component];
        foreach (self::candidate_components() as $candidate) {
            if (!in_array($candidate, $components, true)) {
                $components[] = $candidate;
            }
        }
        foreach ($components as $candidate) {
            $siteid = self::first_setting($candidate, self::siteid_keys());
            $apikey = self::first_setting($candidate, self::apikey_keys());
            if ($siteid !== '' && $apikey !== '') {
                return [
                    'siteid'    => $siteid,
                    'apikey'    => $apikey,
                    'source'    => self::SOURCE_CENTRAL,
                    'component' => $candidate,
                    'method'    => 'settings',
                ];
            }
        }

        return null;
    }

    /**
     * Try Central Config's combined accessor, \local_aiconfig\config::get_credentials().
     *
     * The return value is accepted as an array or an object, under any of the key
     * spellings the ecosystem has used, so that a change of shape on the Central
     * Config side does not silently strand this plugin.
     *
     * @param string $component Central Config component name.
     * @return array|null Keys siteid and apikey, or null.
     */
    protected static function via_class_pair(string $component): ?array {
        $class = '\\' . $component . '\\config';
        if (!class_exists($class) || !method_exists($class, 'get_credentials')) {
            return null;
        }

        try {
            $result = $class::get_credentials();
        } catch (\Throwable $e) {
            // Central Config is a third-party plugin. If its accessor throws, fall
            // through to the next route rather than taking the whole page down.
            return null;
        }

        $siteid = self::pluck($result, ['siteid', 'site_id', 'siteId', 'lmslabssiteid']);
        $apikey = self::pluck($result, ['apikey', 'api_key', 'apiKey', 'lmslabsapikey']);
        if ($siteid !== '' && $apikey !== '') {
            return ['siteid' => $siteid, 'apikey' => $apikey];
        }
        return null;
    }

    /**
     * Try Central Config's separate accessors, get_site_id() and get_api_key().
     *
     * @param string $component Central Config component name.
     * @return array|null Keys siteid and apikey, or null.
     */
    protected static function via_class_accessors(string $component): ?array {
        $class = '\\' . $component . '\\config';
        if (!class_exists($class)) {
            return null;
        }

        $siteid = self::call_first($class, ['get_site_id', 'get_siteid']);
        $apikey = self::call_first($class, ['get_api_key', 'get_apikey']);
        if ($siteid !== '' && $apikey !== '') {
            return ['siteid' => $siteid, 'apikey' => $apikey];
        }
        return null;
    }

    /**
     * Try the global helper functions some releases of Central Config define.
     *
     * @param string $component Central Config component name.
     * @return array|null Keys siteid and apikey, or null.
     */
    protected static function via_legacy_functions(string $component): ?array {
        $siteidfn = $component . '_get_siteid';
        $apikeyfn = $component . '_get_apikey';
        if (!function_exists($siteidfn) || !function_exists($apikeyfn)) {
            return null;
        }

        try {
            $siteid = trim((string)$siteidfn());
            $apikey = trim((string)$apikeyfn());
        } catch (\Throwable $e) {
            return null;
        }

        if ($siteid !== '' && $apikey !== '') {
            return ['siteid' => $siteid, 'apikey' => $apikey];
        }
        return null;
    }

    /**
     * Read a complete credential pair from this plugin's own settings, if there is one.
     *
     * @return array|null Resolved credentials, or null when either field is empty.
     */
    protected static function read_local(): ?array {
        $siteid = trim((string)get_config('mod_aibranchedscenario', 'siteid'));
        $apikey = trim((string)get_config('mod_aibranchedscenario', 'apikey'));
        if ($siteid === '' || $apikey === '') {
            return null;
        }
        return [
            'siteid'    => $siteid,
            'apikey'    => $apikey,
            'source'    => self::SOURCE_LOCAL,
            'component' => 'mod_aibranchedscenario',
            'method'    => 'settings',
        ];
    }

    /**
     * Call the first of several static methods that exists, and return its value as a
     * trimmed string.
     *
     * @param string $class Fully qualified class name.
     * @param string[] $methods Candidate method names, in order of preference.
     * @return string Trimmed value, or the empty string.
     */
    protected static function call_first(string $class, array $methods): string {
        foreach ($methods as $method) {
            if (!method_exists($class, $method)) {
                continue;
            }
            try {
                $value = $class::$method();
            } catch (\Throwable $e) {
                continue;
            }
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * Read a value out of an array or object under any of several key spellings.
     *
     * @param mixed $source Array or object returned by Central Config.
     * @param string[] $keys Candidate keys, in order of preference.
     * @return string Trimmed value, or the empty string.
     */
    protected static function pluck($source, array $keys): string {
        foreach ($keys as $key) {
            $value = null;
            if (is_array($source) && array_key_exists($key, $source)) {
                $value = $source[$key];
            } else if (is_object($source) && isset($source->{$key})) {
                $value = $source->{$key};
            }
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * Read the first non-empty setting from a component.
     *
     * @param string $component Frankenstyle component name.
     * @param string[] $names Candidate setting names.
     * @return string
     */
    protected static function first_setting(string $component, array $names): string {
        foreach ($names as $name) {
            $value = get_config($component, $name);
            if ($value !== false && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * Whether a string has the API key shape LMS Labs has used to date.
     *
     * This is a diagnostic only. It never decides whether credentials are used; see
     * the note on {@see self::KEY_PATTERN}.
     *
     * @param string $value Candidate key.
     * @return bool
     */
    public static function looks_like_key(string $value): bool {
        return (bool)preg_match(self::KEY_PATTERN, trim($value));
    }

    /**
     * Whether usable credentials exist.
     *
     * @return bool
     */
    public static function are_configured(): bool {
        return self::resolve()['source'] !== self::SOURCE_NONE;
    }

    /**
     * A description of what was found, for the settings page and for support.
     *
     * It names the source and the route used, reports whether the Central Config
     * plugin is present at all, and never reveals the key.
     *
     * @return array Keys: source, component, method, siteid, maskedkey, centralinstalled,
     *               centralpartial, localpartial, ignoring, unusualkeyformat.
     */
    public static function diagnostics(): array {
        $resolved = self::resolve();
        $component = self::central_component();
        $centralinstalled = class_exists('\\' . $component . '\\config')
            || function_exists($component . '_get_siteid')
            || get_config($component, 'siteid') !== false;

        $centralsiteid = self::first_setting($component, self::siteid_keys());
        $centralapikey = self::first_setting($component, self::apikey_keys());
        $localsiteid = trim((string)get_config('mod_aibranchedscenario', 'siteid'));
        $localapikey = trim((string)get_config('mod_aibranchedscenario', 'apikey'));

        return [
            'source'           => $resolved['source'],
            'component'        => $resolved['component'],
            'method'           => $resolved['method'],
            'siteid'           => $resolved['siteid'],
            'maskedkey'        => self::mask($resolved['apikey']),
            'centralinstalled' => $centralinstalled,
            'centralpartial'   => ($centralsiteid === '') !== ($centralapikey === ''),
            'localpartial'     => ($localsiteid === '') !== ($localapikey === ''),
            'ignoring'         => self::ignoring_central(),
            'unusualkeyformat' => $resolved['apikey'] !== '' && !self::looks_like_key($resolved['apikey']),
        ];
    }

    /**
     * A display form of the API key that never reveals it.
     *
     * @param string $apikey Full key.
     * @return string
     */
    public static function mask(string $apikey): string {
        $apikey = trim($apikey);
        if ($apikey === '') {
            return '';
        }
        if (\core_text::strlen($apikey) <= 4) {
            return str_repeat('•', 8);
        }
        return str_repeat('•', 8) . \core_text::substr($apikey, -4);
    }
}
