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

namespace mod_aibranchedscenario\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\quality_review;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\source_normaliser;

/**
 * What one press of Generate will cost, and what it will replace.
 *
 * The plugin had exactly one button that spends money and less friction in front of it
 * than deleting a forum post: no balance, no estimate, no confirmation, and no warning
 * that it would overwrite a draft the teacher may have spent an hour editing.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_generation_plan extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'  => new external_value(PARAM_INT, 'Course module id'),
            'tiers' => new external_value(
                PARAM_INT,
                'How many rungs of the ladder this press will write',
                VALUE_DEFAULT,
                1
            ),
        ]);
    }

    /**
     * Estimate the run.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid, int $tiers = 1): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'tiers' => $tiers]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:generate');
        $scenario = $resolved['scenario'];
        // GENERATING THE WHOLE LADDER IS THREE RUNS AND THREE PRICES.
        //
        // One press can now write all three scenarios, and a teacher deciding whether to
        // press it is deciding about the whole bill, not a third of it. Everything counted
        // below is multiplied here rather than in the browser, because the prices are a
        // fixed product decision and multiplying them in JavaScript would be a second
        // place that decides what something costs.
        $rungs = max(1, min(schema::TIERS, (int)$params['tiers']));

        $source = scenario_manager::get_source($scenario);
        $source = $source ? source_normaliser::normalise($source) : source_normaliser::blank();
        // THE SHAPE IS FIXED, SO THE ESTIMATE CAN BE BUILT FROM IT.
        //
        // This read the teacher's chosen decision count and clamped it into three-to-eight
        // - a range that no longer exists - and then counted images and clips with
        // arithmetic that had drifted a long way from what the run actually makes. The
        // figures are shown to the teacher verbatim before they spend credits, so a wrong
        // count is a wrong promise even though the PRICE is flat and unaffected.
        $decisions = schema::DECISIONS;
        $choices = schema::CHOICES;
        $outcomes = count(schema::outcomes());
        // A branched scenario has MORE decision nodes than it has decisions: a poor choice
        // leads somewhere a good one does not, and that alternative is a node of its own
        // with a scene, reactions and clips. The content standard asks for at least two
        // branch points, so two alternatives is the floor and what the shipped worked
        // example carries. Counting only the five made the estimate a third short the day
        // branching became a requirement.
        $nodes = $decisions + quality_review::MIN_BRANCHING_DECISIONS;
        // Three is what the content standard asks for and what the debrief was built
        // around; a scenario may teach more, which makes this an estimate rather than a
        // count - stated as one, and checked against a real run by the harness.
        $principles = 3;
        $tariff = schema::tariff();

        $wantsimages = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $wantsaudio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');

        // Every frame the run commissions: the establishing shot, one per decision, one
        // per ending, the slide that teaches each principle, and a reaction for each
        // signal a decision can produce. The debrief commissions nothing of its own - its
        // slides reuse the reaction already drawn for the option the learner took.
        $scenes = 1 + $decisions + $outcomes;
        // Plus the escalated variants: the content standard asks for one or two, and each
        // is a frame of its own.
        $crisis = 2;
        $images = $wantsimages
            ? $scenes + $principles + ($nodes * count(schema::signals())) + $crisis
            : 0;
        // Every clip: the situation on each node that is not an ending, the opening, one
        // spoken line per decision, and then three per option - the consequence, the same
        // choice read again on its record, and the outcome note the debrief slide reads.
        $narrations = $wantsaudio
            ? ($nodes + 1 + $nodes + ($nodes * $choices * 3) + $outcomes)
            : 0;
        // What the run costs this site to produce, which is not what the teacher pays and
        // is not shown to them. It is kept because the daily allowance is a budget of
        // service operations rather than of sales.
        $servicecost = $tariff['scenario']
            + ($images * $tariff['image'])
            + ($narrations * $tariff['speech']);

        // The price is what is deducted, so it is the number the balance has to cover.
        // Showing the teacher two different credit figures on one dialogue - what they are
        // charged and what it costs us to make - is how a support ticket starts.
        $price = schema::price_for($wantsimages, $wantsaudio);
        $price['total'] = (int)$price['total'] * $rungs;
        $estimate = (int)$price['total'];
        $servicecost *= $rungs;
        $scenes *= $rungs;
        $images *= $rungs;
        $narrations *= $rungs;

        $provider = new lmslabs_provider();
        $status = $provider->get_status();

        $allowance = (new \mod_aibranchedscenario\local\generator())->allowance((int)$GLOBALS['USER']->id);

        return [
            'decisions'    => $decisions,
            'scenes'       => $scenes,
            'price'        => schema::price_text($price['total'], $price['currency'], $price['rate']),
            'pricetotal'   => $price['total'],
            'pricecurrency' => $price['currency'],
            'images'       => $images,
            'narrations'   => $narrations,
            'estimate'     => $estimate,
            'servicecost'  => $servicecost,
            'credits'      => (int)($status['credits'] ?? 0),
            'unlimited'    => !empty($status['unlimited']),
            'balanceknown' => !empty($status['connected']),
            'enough'       => !empty($status['unlimited'])
                || !empty($status['connected']) === false
                || (int)($status['credits'] ?? 0) >= $estimate,
            // ASKED OF THE LADDER, NOT OF THE INSTANCE.
            //
            // This read the activity's own scenariojson column, which the ladder stopped
            // writing: for any activity created since, the column is empty and the warning
            // never appeared, so the one dialogue that exists to say "this replaces work
            // you have done" stopped saying it. Every rung this press will write is asked
            // instead, because replacing any of them is worth the warning.
            'replacesdraft' => self::replaces_draft($scenario, $rungs),
            'tiers'         => $rungs,
            'allowancelimited'   => !empty($allowance['limited']),
            'allowanceremaining' => (int)$allowance['remaining'],
            'buyurl'       => (string)($status['buyurl'] ?? ''),
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    /**
     * Would this press overwrite a draft somebody has already made?
     *
     * @param \stdClass $scenario Activity instance record.
     * @param int $rungs How many rungs the press will write, counted from the first.
     * @return bool
     */
    protected static function replaces_draft(\stdClass $scenario, int $rungs): bool {
        for ($tier = 1; $tier <= $rungs; $tier++) {
            if (scenario_manager::get_working_definition($scenario, $tier) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'decisions'     => new external_value(PARAM_INT, 'Decision points the scenario will contain'),
            'scenes'        => new external_value(PARAM_INT, 'Scenes the scenario will contain'),
            'price'         => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'What this scenario costs, formatted'
            ),
            'pricetotal'    => new external_value(PARAM_INT, 'What this scenario costs, in credits'),
            'pricecurrency' => new external_value(PARAM_ALPHA, 'ISO currency code for the price'),
            'images'        => new external_value(PARAM_INT, 'Images that will be generated'),
            'narrations'    => new external_value(PARAM_INT, 'Narration clips that will be generated'),
            'estimate'      => new external_value(PARAM_INT, 'Credits the teacher is charged'),
            'servicecost'   => new external_value(PARAM_INT, 'Credits the run costs this site to produce'),
            'credits'       => new external_value(PARAM_INT, 'Credits currently available'),
            'unlimited'     => new external_value(PARAM_BOOL, 'Whether the plan is unlimited'),
            'balanceknown'  => new external_value(PARAM_BOOL, 'Whether the balance could be read'),
            'enough'        => new external_value(PARAM_BOOL, 'Whether the balance covers the estimate'),
            'replacesdraft' => new external_value(PARAM_BOOL, 'Whether a working copy would be replaced'),
            'tiers'         => new external_value(PARAM_INT, 'How many scenarios this press will write'),
            'allowancelimited'   => new external_value(PARAM_BOOL, 'Whether a daily allowance applies'),
            'allowanceremaining' => new external_value(PARAM_INT, 'How much of the daily allowance is left'),
            'buyurl'        => new external_value(PARAM_URL, 'Where more credits can be bought', VALUE_DEFAULT, ''),
        ]);
    }
}
