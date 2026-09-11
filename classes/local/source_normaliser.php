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

namespace mod_aibranchedscenario\local;

/**
 * Normaliser for the five-step authoring wizard's inputs.
 *
 * These values are what a teacher typed, so they are stored verbatim as text but
 * clamped in length, and every enumerated value must match a canonical option.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_normaliser {
    /**
     * Return a fully populated set of wizard inputs with sensible defaults.
     *
     * @return array
     */
    public static function blank(): array {
        return [
            'brief'            => '',
            'sourcecontent'    => '',
            'title'            => '',
            'industry'         => 'training',
            'industryother'    => '',
            'audience'         => '',
            'setting'          => 'trainingroom',
            'settingother'     => '',
            'atmosphere'       => 'tension',
            'openingsituation' => '',
            'centralproblem'   => '',
            'whyhard'          => [],
            'stakes'           => [],
            'participantrole'  => '',
            'characters'       => [],
            'principles'       => [],
            'decisions'        => 5,
            'tone'             => 'neutral',
            'complexity'       => 'intermediate',
            // Photorealistic is the default: a cinematic still is graded dark by every
            // model that draws one, and a workplace scenario reads better as a room the
            // learner could walk into than as a film frame.
            'imagestyle'       => 'photorealistic',
            'imageprompt'      => '',
            'openingmetrics'   => ['engagement' => 50, 'trust' => 50, 'tension' => 30],
        ];
    }

    /**
     * Normalise a raw set of wizard inputs.
     *
     * @param array $raw Raw inputs.
     * @return array
     */
    public static function normalise(array $raw): array {
        $out = self::blank();

        $out['brief'] = self::text($raw['brief'] ?? '', schema::MAX_BRIEF_CHARS);
        $out['sourcecontent'] = self::text($raw['sourcecontent'] ?? '', schema::MAX_SOURCE_CHARS);
        $out['title'] = self::line($raw['title'] ?? '', 255);
        $out['audience'] = self::line($raw['audience'] ?? '', 255);
        $out['openingsituation'] = self::text($raw['openingsituation'] ?? '', 3000);
        $out['centralproblem'] = self::text($raw['centralproblem'] ?? '', 3000);
        $out['participantrole'] = self::line($raw['participantrole'] ?? '', 255);
        $out['imageprompt'] = self::line($raw['imageprompt'] ?? '', 600);

        $out['industry'] = schema::in_list($raw['industry'] ?? '', schema::industries())
            ? $raw['industry'] : $out['industry'];
        $out['setting'] = schema::in_list($raw['setting'] ?? '', schema::settings_list())
            ? $raw['setting'] : $out['setting'];

        // The detail box that belongs to "Other" is hidden rather than emptied when a
        // listed option is chosen, and a hidden input is still submitted. Keeping the
        // description only while its picker says "other" stops text the teacher believes
        // they replaced from being stored and sent to the service.
        $out['industryother'] = $out['industry'] === 'other'
            ? self::line($raw['industryother'] ?? '', 120) : '';
        $out['settingother'] = $out['setting'] === 'other'
            ? self::line($raw['settingother'] ?? '', 255) : '';
        $out['atmosphere'] = schema::in_list($raw['atmosphere'] ?? '', schema::atmospheres())
            ? $raw['atmosphere'] : $out['atmosphere'];
        $out['tone'] = schema::in_list($raw['tone'] ?? '', schema::tones()) ? $raw['tone'] : $out['tone'];
        $out['complexity'] = schema::in_list($raw['complexity'] ?? '', schema::complexities())
            ? $raw['complexity'] : $out['complexity'];
        $out['imagestyle'] = schema::in_list($raw['imagestyle'] ?? '', schema::imagestyles())
            ? $raw['imagestyle'] : $out['imagestyle'];

        $out['whyhard'] = self::filtered_list($raw['whyhard'] ?? [], schema::whyhard());
        $out['stakes'] = self::filtered_list($raw['stakes'] ?? [], schema::stakes());

        $out['decisions'] = self::intrange($raw['decisions'] ?? 5, 3, 8, 5);

        foreach (schema::metrics() as $metric) {
            $default = $out['openingmetrics'][$metric];
            $out['openingmetrics'][$metric] = self::intrange($raw['openingmetrics'][$metric] ?? $default, 0, 100, $default);
        }

        if (isset($raw['characters']) && is_array($raw['characters'])) {
            foreach (array_slice($raw['characters'], 0, 4) as $character) {
                if (!is_array($character)) {
                    continue;
                }
                $name = self::line($character['name'] ?? '', 80);
                if ($name === '') {
                    continue;
                }
                $out['characters'][] = [
                    'name'       => $name,
                    'role'       => self::line($character['role'] ?? '', 120),
                    'trait'      => self::line($character['trait'] ?? '', 200),
                    'appearance' => self::line($character['appearance'] ?? '', 400),
                ];
            }
        }

        if (isset($raw['principles']) && is_array($raw['principles'])) {
            foreach (array_slice($raw['principles'], 0, 8) as $principle) {
                if (!is_array($principle)) {
                    continue;
                }
                $title = self::line($principle['title'] ?? '', 160);
                if ($title === '') {
                    continue;
                }
                $out['principles'][] = [
                    'title'   => $title,
                    'summary' => self::text($principle['summary'] ?? '', 800),
                ];
            }
        }

        return $out;
    }

    /**
     * Normalise a single line of text.
     *
     * @param mixed $value Raw value.
     * @param int $max Maximum length.
     * @return string
     */
    protected static function line($value, int $max): string {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
        return \core_text::substr($value, 0, $max);
    }

    /**
     * Normalise a block of text.
     *
     * @param mixed $value Raw value.
     * @param int $max Maximum length.
     * @return string
     */
    protected static function text($value, int $max): string {
        if (!is_string($value)) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/\n{3,}/u', "\n\n", (string)$value);
        return \core_text::substr(trim($value), 0, $max);
    }

    /**
     * Keep only values present in an allowed list.
     *
     * @param mixed $value Raw list.
     * @param string[] $allowed Allowed values.
     * @return string[]
     */
    protected static function filtered_list($value, array $allowed): array {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (schema::in_list($item, $allowed) && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Clamp an integer.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @param int $default Fallback.
     * @return int
     */
    protected static function intrange($value, int $min, int $max, int $default): int {
        if (!is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int)round((float)$value)));
    }
}
