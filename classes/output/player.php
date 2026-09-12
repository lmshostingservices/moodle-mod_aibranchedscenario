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
use mod_aibranchedscenario\external\helper;
use mod_aibranchedscenario\local\media_manager;
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
     * The opening lesson, one principle to a slide.
     *
     * A learner who is told the principles in a bulleted list has read them and learned
     * nothing. Each one gets its own screen with the words to actually use and the
     * plausible-sounding mistake it prevents, a picture from the scenario itself so the
     * lesson is set where the story is, and its own narration.
     *
     * The pictures are the scenes already generated for the scenario, taken in order and
     * cycled if there are more principles than scenes. Nothing extra is generated.
     *
     * @param array|null $definition Published definition.
     * @param \stdClass|null $revision Published revision.
     * @return array Template context.
     */
    protected function lesson_slides($definition, $revision): array {
        if (!is_array($definition) || empty($definition['principles']) || !$revision) {
            return [];
        }
        $media = new media_manager($this->context);
        $scenes = array_values($media->urls_for_revision(
            media_manager::AREA_REVISION_SCENE,
            (int)$revision->revision
        ));
        $narration = $media->urls_for_revision(
            media_manager::AREA_REVISION_NARRATION,
            (int)$revision->revision
        );

        $slides = [];
        foreach (array_values($definition['principles']) as $position => $principle) {
            $image = $scenes ? (string)$scenes[$position % count($scenes)] : '';
            $slides[] = [
                'number'      => $position + 1,
                'title'       => (string)$principle['title'],
                'summaryparas' => helper::paragraph_list((string)($principle['summary'] ?? '')),
                'example'     => (string)($principle['example'] ?? ''),
                'hasexample'  => trim((string)($principle['example'] ?? '')) !== '',
                'pitfall'     => (string)($principle['pitfall'] ?? ''),
                'haspitfall'  => trim((string)($principle['pitfall'] ?? '')) !== '',
                'imageurl'    => $image,
                'hasimage'    => $image !== '',
                'audiourl'    => (string)($narration['lesson_' . $principle['id']] ?? ''),
            ];
        }
        return $slides;
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

        $lessonslides = $this->lesson_slides($definition, $revision);

        $stages = [];
        $longest = 0;
        if (is_array($definition)) {
            $longest = (int)($definition['stats']['longestpath'] ?? 0);
            for ($i = 1; $i <= max(1, $longest); $i++) {
                $stages[] = ['index' => $i, 'last' => $i === max(1, $longest)];
            }
        }

        // Where a reading stops being good and where it becomes a problem, as the teacher
        // set them. The same two numbers go to the template for JavaScript to band by.
        $green = (int)($this->scenario->bandgreen ?? 67);
        $red = (int)($this->scenario->bandred ?? 34);

        $metrics = [];
        foreach (schema::metrics() as $metric) {
            $value = (int)($definition['openingmetrics'][$metric] ?? 50);
            // Tension reads the other way up: a low tension is a good one.
            $standing = $metric === 'tension' ? 100 - $value : $value;
            if ($standing >= $green) {
                $tone = 'aibs-tone-good';
            } else if ($standing < $red) {
                $tone = 'aibs-tone-bad';
            } else {
                $tone = 'aibs-tone-warn';
            }
            // The template draws a different mark for each reading, and mustache cannot
            // switch on a value, so each row says which one it is.
            //
            // The band is written here as well as by the JavaScript that updates it after
            // every decision, so the opening reading is already coloured when the page
            // paints. Left to the script, the three marks arrived grey and stayed grey
            // until the first decision - which is what "cant see the icons" was looking
            // at: a thin grey stroke on a white bar.
            $metrics[] = [
                'key'   => $metric,
                'label' => get_string('metric:' . $metric, 'mod_aibranchedscenario'),
                'value' => $value,
                'tone'  => $tone,
                'isengagement' => $metric === 'engagement',
                'istrust'      => $metric === 'trust',
                'istension'    => $metric === 'tension',
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
            'requirelisten' => !empty($this->scenario->enableaudio)
                && !empty($this->scenario->requirelisten),
            'bandgreen'    => (int)($this->scenario->bandgreen ?? 67),
            'bandred'      => (int)($this->scenario->bandred ?? 34),
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
            'lessonslides' => $lessonslides,
            'openingimage' => $lessonslides ? (string)$lessonslides[0]['imageurl'] : '',
        ];
    }
}
