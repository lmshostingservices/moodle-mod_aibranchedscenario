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
 * What a group of people got wrong, and whether they knew.
 *
 * THE THING A TRAINING MANAGER ACTUALLY BUYS.
 *
 * A list of scores tells a trainer who to worry about. It does not tell them what to teach
 * on Monday. This does: for every decision in the scenario, which option the group took,
 * where the group went wrong, and - the part no score can show - whether the people who
 * went wrong knew they were unsure.
 *
 * THE ONE DISTINCTION EVERYTHING HERE IS BUILT ON. Somebody who chose badly and said they
 * were not sure has a GAP: they know the edge of what they know, and telling them the rule
 * closes it. Somebody who chose badly while sure has a MISCONCEPTION: they will not ask,
 * because as far as they are concerned there is nothing to ask about, and the only thing
 * that shifts it is being shown the consequence. A score of 60% is the same number in both
 * cases and they are not the same problem.
 *
 * DERIVED FROM THE EVENT LOG, which has recorded every decision since the first release.
 * The only thing added for any of this was one column saying whether the learner volunteered
 * that they were unsure, because that is the only fact here the product cannot work out for
 * itself. Nothing is profiled, nothing is predicted, and every number on the page is a count
 * of things that happened.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_insight {
    /**
     * @var int How many people have to have answered a decision before it is worth reading.
     *
     * Below this the percentages are noise - two people out of three is 67% and means
     * nothing - and a report that draws confident conclusions from three learners is worse
     * than one that says it does not know yet.
     */
    const MIN_RESPONSES = 5;

    /**
     * @var int The share of a group taking one poor option that makes it a common answer.
     *
     * Not a rule about learners. A wrong answer that a third of a trained group chooses is
     * evidence about the TEACHING or about the option: either the material never addressed
     * the thing that makes it tempting, or the option is written so attractively that it is
     * the reasonable read. Both are the trainer's to fix, which is why this is reported
     * against the decision rather than against anybody's name.
     */
    const COMMON_SHARE = 30;

    /**
     * @var int The share of the people who chose a poor option while sure that makes it a
     *          misconception rather than a gap.
     */
    const MISCONCEPTION_SHARE = 60;

    /**
     * Everything the cohort report shows, for one scenario.
     *
     * @param int $scenarioid The activity.
     * @param array $definition The published definition, for the wording of each option.
     * @return array Keys: decisions, misconceptions, responses, unsurerate.
     */
    public static function build(int $scenarioid, array $definition): array {
        global $DB;

        // One query. The report is a page a trainer opens between classes, not a report
        // builder, and a query per decision would be five to twenty round trips for a
        // screen that is read in thirty seconds.
        // A RECORDSET, NOT get_records_sql().
        //
        // get_records_sql() keys its result by the FIRST COLUMN and silently drops every
        // later row that repeats it. This query returns one row per OPTION, so every row
        // carries the same node id as its neighbours - and the first cut of this method,
        // which used get_records_sql(), kept one option per decision and threw the rest
        // away. The report then showed whichever option happened to come last as if the
        // whole group had taken it, at 100%, which is a wrong number presented with total
        // confidence on the page a trainer acts on.
        //
        // It survived review because the query is correct, the loop is correct and the
        // arithmetic is correct. The harness caught it by walking eight people through one
        // decision and asking what share took each option.
        $rows = $DB->get_recordset_sql(
            "SELECT e.nodeid, e.choiceid, e.signaltype,
                    COUNT(e.id) AS taken,
                    SUM(e.unsure) AS unsure
               FROM {aibranchedscenario_events} e
               JOIN {aibranchedscenario_attempts} a ON a.id = e.attemptid
              WHERE a.scenarioid = :sid
           GROUP BY e.nodeid, e.choiceid, e.signaltype
           ORDER BY e.nodeid ASC, e.choiceid ASC",
            ['sid' => $scenarioid]
        );

        $counts = [];
        $responses = 0;
        $unsuretotal = 0;
        foreach ($rows as $row) {
            $taken = (int)$row->taken;
            $counts[$row->nodeid][$row->choiceid] = [
                'taken'  => $taken,
                'unsure' => (int)$row->unsure,
                'signal' => (string)$row->signaltype,
            ];
            $responses += $taken;
            $unsuretotal += (int)$row->unsure;
        }
        $rows->close();

        $decisions = [];
        $misconceptions = [];
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            $nodeid = (string)($node['id'] ?? '');
            $taken = array_sum(array_column($counts[$nodeid] ?? [], 'taken'));
            $options = [];
            $poortaken = 0;
            $poorsure = 0;
            foreach ((array)($node['choices'] ?? []) as $position => $choice) {
                $choiceid = (string)($choice['id'] ?? '');
                $seen = $counts[$nodeid][$choiceid] ?? ['taken' => 0, 'unsure' => 0];
                $count = (int)$seen['taken'];
                // The SIGNAL COMES FROM THE DEFINITION, not from the event log. The log
                // records what the scenario said at the time; a revision that changed an
                // option from poor to reasonable would otherwise make the report disagree
                // with the scenario a trainer is looking at.
                $signal = (string)($choice['signal'] ?? 'neutral');
                if ($signal === 'negative') {
                    $poortaken += $count;
                    $poorsure += $count - (int)$seen['unsure'];
                }
                $options[] = [
                    'letter'   => chr(65 + $position),
                    'text'     => (string)($choice['text'] ?? ''),
                    'signal'   => $signal,
                    'taken'    => $count,
                    'share'    => $taken ? (int)round($count * 100 / $taken) : 0,
                    'unsure'   => (int)$seen['unsure'],
                ];
            }

            $enough = $taken >= self::MIN_RESPONSES;
            $decisions[] = [
                'nodeid'    => $nodeid,
                'title'     => (string)($node['title'] ?? $nodeid),
                'stage'     => (int)($node['stage'] ?? 0),
                'taken'     => $taken,
                'enough'    => $enough,
                'options'   => $options,
                'poorshare' => $taken ? (int)round($poortaken * 100 / $taken) : 0,
            ];

            // A MISCONCEPTION IS A DECISION, NOT A PERSON. Reported here as "this decision
            // catches people out, and they do not know it is catching them" - which is
            // something a trainer can do something about on Monday. Naming learners would
            // turn a teaching signal into a list of who to talk to, which is the same data
            // put to a use nobody asked for.
            if (!$enough || !$poortaken) {
                continue;
            }
            $share = (int)round($poortaken * 100 / $taken);
            if ($share < self::COMMON_SHARE) {
                continue;
            }
            $sureshare = (int)round($poorsure * 100 / $poortaken);
            $misconceptions[] = [
                'nodeid'        => $nodeid,
                'title'         => (string)($node['title'] ?? $nodeid),
                'share'         => $share,
                'sureshare'     => $sureshare,
                'confident'     => $sureshare >= self::MISCONCEPTION_SHARE,
                // The option they actually take, because "people get this one wrong" sends
                // a trainer back to read five options and guess which one.
                'option'        => self::popular_poor_option($node, $counts[$nodeid] ?? []),
                'principle'     => self::principle_title($definition, $node),
            ];
        }

        // Worst first. A trainer reading this has one class and twenty minutes, and the
        // decision a third of the group got wrong while sure is what that time is for.
        usort($misconceptions, static function ($a, $b) {
            return ($b['share'] * ($b['confident'] ? 2 : 1)) <=> ($a['share'] * ($a['confident'] ? 2 : 1));
        });

        return [
            'decisions'      => $decisions,
            'misconceptions' => $misconceptions,
            'responses'      => $responses,
            'unsurerate'     => $responses ? (int)round($unsuretotal * 100 / $responses) : 0,
        ];
    }

    /**
     * The poor option this group takes most often at one decision.
     *
     * @param array $node The node from the definition.
     * @param array $counts What was taken there, keyed by choice id.
     * @return string The option's own words, or empty.
     */
    protected static function popular_poor_option(array $node, array $counts): string {
        $best = '';
        $most = 0;
        foreach ((array)($node['choices'] ?? []) as $choice) {
            if ((string)($choice['signal'] ?? '') !== 'negative') {
                continue;
            }
            $count = (int)($counts[(string)($choice['id'] ?? '')]['taken'] ?? 0);
            if ($count > $most) {
                $most = $count;
                $best = (string)($choice['text'] ?? '');
            }
        }
        return $best;
    }

    /**
     * The principle a decision was testing, read off its options.
     *
     * @param array $definition The whole definition.
     * @param array $node The node.
     * @return string
     */
    protected static function principle_title(array $definition, array $node): string {
        $wanted = '';
        foreach ((array)($node['choices'] ?? []) as $choice) {
            $wanted = (string)($choice['principleid'] ?? '');
            if ($wanted !== '') {
                break;
            }
        }
        foreach ((array)($definition['principles'] ?? []) as $principle) {
            if ((string)($principle['id'] ?? '') === $wanted) {
                return (string)($principle['title'] ?? '');
            }
        }
        return '';
    }
}
