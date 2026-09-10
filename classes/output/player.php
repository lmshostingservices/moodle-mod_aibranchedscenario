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

namespace mod_aibranchedscenario\output;

use context_module;
use mod_aibranchedscenario\local\attempt_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use stdClass;

/**
 * Prepares the data the scenario player template and its AMD module need.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player implements \renderable, \templatable {
    /** @var stdClass Activity instance. */
    protected $scenario;

    /** @var stdClass Course module record. */
    protected $cm;

    /** @var context_module Module context. */
    protected $context;

    /** @var bool Whether the current user may attempt the scenario. */
    protected $canattempt;

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance.
     * @param stdClass $cm Course module record.
     * @param context_module $context Module context.
     * @param bool $canattempt Whether the current user may attempt the scenario.
     */
    public function __construct(stdClass $scenario, $cm, context_module $context, bool $canattempt) {
        $this->scenario = $scenario;
        $this->cm = $cm;
        $this->context = $context;
        $this->canattempt = $canattempt;
    }

    /**
     * Export the data used by the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $revision = scenario_manager::get_current_revision($this->scenario);
        $definition = $revision ? json_decode($revision->scenariojson, true) : null;

        $stages = [];
        $longest = 0;
        if (is_array($definition)) {
            $longest = (int)($definition['stats']['longestpath'] ?? 0);
            for ($i = 1; $i <= max(1, $longest); $i++) {
                $stages[] = ['index' => $i, 'last' => $i === max(1, $longest)];
            }
        }

        $metrics = [];
        foreach (schema::metrics() as $metric) {
            $metrics[] = [
                'key'   => $metric,
                'label' => get_string('metric:' . $metric, 'mod_aibranchedscenario'),
                'value' => (int)($definition['openingmetrics'][$metric] ?? 50),
            ];
        }

        $attemptsused = 0;
        $open = null;
        $finished = null;
        $canstartnew = false;
        if ($revision) {
            $manager = new attempt_manager($this->scenario, $revision);
            $userid = (int)$GLOBALS['USER']->id;
            $attemptsused = $manager->count_user_attempts($userid);
            $open = $manager->get_open_attempt($userid);
            $finished = $manager->get_last_finished_attempt($userid);
            $canstartnew = $manager->can_start_new_attempt($userid);
        }

        return [
            'cmid'         => (int)$this->cm->id,
            'instanceid'   => (int)$this->scenario->id,
            'theme'        => $this->scenario->theme,
            'themeclass'   => 'aibs-theme-' . $this->scenario->theme,
            'title'        => format_string($this->scenario->name, true, ['context' => $this->context]),
            'scenariotitle' => is_array($definition) ? $definition['title'] : '',
            'subtitle'     => is_array($definition) ? $definition['subtitle'] : '',
            'role'         => is_array($definition) ? $definition['role'] : '',
            'hookparas'     => is_array($definition)
                ? \mod_aibranchedscenario\external\helper::paragraph_list($definition['hook']) : '',
            'setting'      => is_array($definition) ? $definition['setting'] : '',
            'published'    => (bool)$revision,
            'canattempt'   => $this->canattempt,
            'showtimeline' => !empty($this->scenario->showtimeline),
            'showmetrics'  => !empty($this->scenario->showmetrics),
            'showdebrief'  => !empty($this->scenario->showdebrief),
            'allowreplay'  => !empty($this->scenario->allowreplay),
            'enableaudio'  => !empty($this->scenario->enableaudio),
            'maxattempts'  => (int)$this->scenario->maxattempts,
            'attemptsused' => $attemptsused,
            'hasopen'      => (bool)$open,
            'hasfinished'  => (bool)$finished && !$open,
            'finishedattemptid' => $finished ? (int)$finished->id : 0,
            'canstartnew'  => $canstartnew && !$open,
            'attemptsleft' => empty($this->scenario->maxattempts)
                ? -1 : max(0, (int)$this->scenario->maxattempts - $attemptsused),
            'stages'       => $stages,
            'stagecount'   => $longest,
            'metrics'      => $metrics,
            'principles'   => is_array($definition) ? array_values($definition['principles']) : [],
            'hasprinciples' => is_array($definition) && !empty($definition['principles']),
        ];
    }
}
