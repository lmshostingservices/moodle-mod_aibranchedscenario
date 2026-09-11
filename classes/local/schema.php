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
 * Canonical option lists, limits and enumerations for the scenario data contract.
 *
 * Everything the authoring wizard offers and everything the validator accepts is
 * defined here so the PHP, the AI prompt and the JavaScript stay in step.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /** @var int Current scenario JSON contract version. */
    const CONTRACT_VERSION = 1;

    /** @var int Hard ceiling on decoded scenario JSON size in bytes. */
    const MAX_SCENARIO_BYTES = 2097152;

    /** @var int Maximum number of nodes in one scenario. */
    const MAX_NODES = 60;

    /** @var int Maximum number of choices on one decision node. */
    const MAX_CHOICES = 4;

    /** @var int Minimum number of choices on one decision node. */
    const MIN_CHOICES = 2;

    /** @var int Maximum characters for any single narrative string. */
    const MAX_TEXT = 4000;

    /** @var int Maximum characters for a short string (titles, names, labels). */
    const MAX_SHORT_TEXT = 255;

    /** @var int Maximum characters accepted for pasted source content. */
    const MAX_SOURCE_CHARS = 60000;

    /** @var int Maximum characters accepted in a quick-start brief. */
    const MAX_BRIEF_CHARS = 6000;

    /** @var int Maximum absolute value of a single skill delta. */
    const MAX_SKILL_DELTA = 2;

    /** @var int Maximum absolute value of a single dynamics delta. */
    const MAX_METRIC_DELTA = 40;

    /** @var int Tension level at or above which a crisis variant is shown. */
    const CRISIS_TENSION_THRESHOLD = 75;

    /**
     * Visual themes.
     *
     * Every theme is a light, low-chroma surface set with a single strong accent, so
     * the player reads as a calm product surface rather than as part of the site theme.
     * Only the accent trio changes between themes; the neutral scale is shared.
     *
     * @return array theme id => [accent, accentstrong, accentsoft, accentborder]
     */
    public static function themes(): array {
        return [
            'indigo'  => ['#4f46e5', '#3730a3', '#eef0fe', '#c7cbf8'],
            'slate'   => ['#2563eb', '#1d4ed8', '#eaf1fe', '#bfd4fb'],
            'emerald' => ['#059669', '#047857', '#e7f6f0', '#b4e3d0'],
            'ocean'   => ['#0891b2', '#0e7490', '#e4f4f8', '#b3e0ec'],
            'amber'   => ['#c2650a', '#9a4f08', '#fdf1e2', '#f3d3ab'],
            'violet'  => ['#7c3aed', '#6d28d9', '#f3ecfe', '#dcc9fb'],
        ];
    }

    /**
     * The shared neutral scale every theme sits on.
     *
     * @return array token name => colour
     */
    public static function neutrals(): array {
        return [
            'canvas'    => '#f5f6f8',
            'surface'   => '#ffffff',
            'surfacealt' => '#eef0f4',
            'border'    => '#e0e4ea',
            'bordersoft' => '#eceff4',
            'text'      => '#171a20',
            'muted'     => '#5b6373',
            'positive'  => '#047857',
            'negative'  => '#b42318',
            'neutral'   => '#475467',
        ];
    }

    /**
     * List of valid theme identifiers.
     *
     * @return string[]
     */
    public static function theme_ids(): array {
        return array_keys(self::themes());
    }

    /**
     * Industries / subject domains offered by the wizard.
     *
     * @return string[]
     */
    public static function industries(): array {
        return [
            'training', 'healthcare', 'agedcare', 'mining', 'construction', 'hospitality',
            'retail', 'education', 'communityservices', 'emergencyservices', 'financialservices',
            'humanresources', 'manufacturing', 'government', 'ittechnology', 'compliance',
            'cybersecurity', 'sales', 'leadership', 'other',
        ];
    }

    /**
     * Physical or virtual settings offered by the wizard.
     *
     * @return string[]
     */
    public static function settings_list(): array {
        return [
            'trainingroom', 'hospitalward', 'constructionsite', 'minesite', 'customercounter',
            'office', 'kitchen', 'vehiclefield', 'videocall', 'warehouse', 'classroom', 'other',
        ];
    }

    /**
     * Atmosphere / emotional register options.
     *
     * @return string[]
     */
    public static function atmospheres(): array {
        return ['calm', 'tension', 'highpressure', 'crisis'];
    }

    /**
     * "Why this is hard" complication options.
     *
     * @return string[]
     */
    public static function whyhard(): array {
        return [
            'timepressure', 'challengingperson', 'conflictingpriorities', 'missinginformation',
            'policyvsreality', 'emotionalstakes', 'safetyrisk', 'languagebarrier',
            'powerimbalance', 'ambiguity', 'competingloyalties', 'publicscrutiny',
        ];
    }

    /**
     * "What is at stake" options.
     *
     * @return string[]
     */
    public static function stakes(): array {
        return [
            'teamtrust', 'learnersafety', 'jobperformance', 'compliance', 'reputation',
            'psychologicalsafety', 'customeroutcome', 'operationalcontinuity', 'legalexposure',
            'wellbeing', 'learningoutcomes',
        ];
    }

    /**
     * Narrative complexity levels.
     *
     * @return string[]
     */
    public static function complexities(): array {
        return ['foundation', 'intermediate', 'advanced'];
    }

    /**
     * Writing tone options.
     *
     * @return string[]
     */
    public static function tones(): array {
        return ['neutral', 'supportive', 'direct', 'formal', 'conversational'];
    }

    /**
     * Image generation styles.
     *
     * @return string[]
     */
    public static function imagestyles(): array {
        return ['photorealistic', 'cinematic', 'illustration', 'watercolour', 'noir', 'oil'];
    }

    /**
     * Supported narration/scenario languages as BCP-47 codes.
     *
     * @return string[]
     */
    public static function languages(): array {
        return [
            'en-AU', 'en-GB', 'en-US', 'en-NZ', 'es-ES', 'fr-FR', 'de-DE', 'it-IT',
            'pt-BR', 'nl-NL', 'hi-IN', 'id-ID', 'ja-JP', 'ko-KR', 'cmn-CN', 'ar-XA', 'vi-VN', 'th-TH',
        ];
    }

    /**
     * Signal type for a choice, used for consequence colouring only.
     *
     * @return string[]
     */
    public static function signals(): array {
        return ['positive', 'neutral', 'negative'];
    }

    /**
     * Controlled vocabulary of narrative tags a choice may carry.
     *
     * @return string[]
     */
    public static function choicetags(): array {
        return [
            'delayed-consequence', 'escalates-conflict', 'builds-rapport', 'creates-opening',
            'undermines-trust', 'recovery', 'gathers-information', 'escalates-appropriately',
        ];
    }

    /**
     * The four tracked skill dimensions.
     *
     * @return string[]
     */
    public static function skills(): array {
        return ['presence', 'adaptability', 'empathy', 'clarity'];
    }

    /**
     * The three tracked room-dynamics metrics.
     *
     * @return string[]
     */
    public static function metrics(): array {
        return ['engagement', 'trust', 'tension'];
    }

    /**
     * Node types.
     *
     * @return string[]
     */
    public static function nodetypes(): array {
        return ['decision', 'beat', 'outcome'];
    }

    /**
     * Outcome bands used by terminal nodes and by automatic outcome resolution.
     *
     * @return string[]
     */
    public static function outcomes(): array {
        return ['strong', 'mixed', 'highrisk'];
    }

    /**
     * Grade aggregation methods.
     *
     * @return string[]
     */
    public static function grademethods(): array {
        return ['last', 'first', 'highest', 'average'];
    }

    /**
     * Special choice target meaning "let the engine pick an outcome node by score".
     *
     * @return string
     */
    public static function auto_target(): string {
        return '__auto__';
    }

    /**
     * What the LMS Labs service charges for each operation, in credits.
     *
     * These are the service's published tariff, not a price this plugin sets. They are
     * held here only so a teacher can be told what a run will cost before they commit
     * to it. The service remains the authority: what it reports having charged is what
     * is recorded against the job.
     *
     * @return array Operation name to credits.
     */
    public static function tariff(): array {
        return [
            'scenario' => 20,
            'populate' => 3,
            'suggest'  => 1,
            'image'    => 5,
            'speech'   => 5,
        ];
    }

    /**
     * What a teacher is charged for one generated scenario, in credits.
     *
     * Set by the product owner, not derived from the service tariff above: the tariff is
     * what each operation costs this plugin to run, and this is the published price of the
     * finished thing. The three parts add up to the four prices that were set — scenario
     * alone, scenario with pictures, scenario with narration, and both.
     *
     * The price is quoted in credits, because credits are what a teacher's balance is
     * held in and what is actually deducted. The cash equivalent is shown beside it so
     * nobody has to do the conversion in their head, and the rate that converts one to the
     * other is a setting, as are all three parts: a partner reselling this does not
     * necessarily sell it at the same price or in the same currency.
     *
     * @return array Keys: base, images, voice, rate, currency.
     */
    public static function pricing(): array {
        $number = static function (string $name, float $fallback): float {
            $value = get_config('mod_aibranchedscenario', $name);
            if ($value === false || $value === '' || !is_numeric($value) || (float)$value <= 0) {
                return $fallback;
            }
            return round((float)$value, 2);
        };
        $credits = static function (string $name, int $fallback) {
            $value = get_config('mod_aibranchedscenario', $name);
            if ($value === false || $value === '' || !is_numeric($value) || (int)$value < 0) {
                return $fallback;
            }
            return (int)$value;
        };
        $currency = (string)get_config('mod_aibranchedscenario', 'pricecurrency');
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'USD';
        }
        return [
            'base'     => $credits('pricebase', 100),
            'images'   => $credits('priceimages', 50),
            'voice'    => $credits('pricevoice', 50),
            // Credits to one unit of the currency. Ten credits to the dollar puts a
            // scenario with pictures and narration at 200 credits, which is $20.
            'rate'     => $number('creditrate', 10.0),
            'currency' => $currency,
        ];
    }

    /**
     * The price of one scenario with the options a teacher has chosen.
     *
     * @param bool $withimages Whether scene images are being generated.
     * @param bool $withvoice Whether narration is being generated.
     * @return array Keys: base, images, voice, total, money, rate, currency.
     */
    public static function price_for(bool $withimages, bool $withvoice): array {
        $pricing = self::pricing();
        $images = $withimages ? $pricing['images'] : 0;
        $voice = $withvoice ? $pricing['voice'] : 0;
        $total = $pricing['base'] + $images + $voice;
        return [
            'base'     => $pricing['base'],
            'images'   => $images,
            'voice'    => $voice,
            'total'    => $total,
            'money'    => round($total / $pricing['rate'], 2),
            'rate'     => $pricing['rate'],
            'currency' => $pricing['currency'],
        ];
    }

    /**
     * Format an amount of money for display.
     *
     * @param float $amount Amount in whole currency units.
     * @param string $currency ISO currency code.
     * @return string
     */
    public static function money(float $amount, string $currency = ''): string {
        if ($currency === '') {
            $currency = self::pricing()['currency'];
        }
        $decimals = (float)$amount === floor((float)$amount) ? 0 : 2;
        return '$' . number_format($amount, $decimals) . ' ' . $currency;
    }

    /**
     * Format a price as the credits charged and what they are worth.
     *
     * @param int $credits Credits charged.
     * @param string $currency ISO currency code.
     * @param float $rate Credits to one unit of that currency.
     * @return string
     */
    public static function price_text(int $credits, string $currency = '', float $rate = 0.0): string {
        $pricing = self::pricing();
        if ($currency === '') {
            $currency = $pricing['currency'];
        }
        if ($rate <= 0) {
            $rate = $pricing['rate'];
        }
        return get_string('pricecredits', 'mod_aibranchedscenario', (object)[
            'credits' => number_format($credits),
            'money'   => self::money(round($credits / $rate, 2), $currency),
        ]);
    }

    /**
     * Check a value against one of the option lists.
     *
     * @param string $value Value to test.
     * @param string[] $allowed Allowed values.
     * @return bool
     */
    public static function in_list($value, array $allowed): bool {
        return is_string($value) && in_array($value, $allowed, true);
    }
}
