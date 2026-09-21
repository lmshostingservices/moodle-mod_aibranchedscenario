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

    /** @var int Which rung of the ladder is being played. */
    protected $tier;

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance.
     * @param stdClass $cm Course module record.
     * @param context_module $context Module context.
     * @param bool $canattempt Whether the current user may attempt the scenario.
     * @param int $tier Which rung of the ladder is being played.
     */
    public function __construct(
        stdClass $scenario,
        $cm,
        context_module $context,
        bool $canattempt,
        int $tier = 1
    ) {
        $this->scenario = $scenario;
        $this->cm = $cm;
        $this->context = $context;
        $this->canattempt = $canattempt;
        $this->tier = $tier;
    }

    /**
     * The frame the scenario opens on.
     *
     * It used to borrow the FIRST LESSON SLIDE'S picture, so the screen a learner meets
     * first and the screen immediately after it showed the same photograph - the same fault
     * the debrief had, on the two slides where it is most obvious because they are
     * consecutive. It takes the frame of the node the scenario actually opens on, which is
     * what the opening situation describes.
     *
     * @param array|mixed $definition The validated scenario definition.
     * @param stdClass|null $revision The published revision.
     * @return string The scene URL, or an empty string.
     */
    protected function opening_scene($definition, $revision): string {
        if (!is_array($definition) || !$revision) {
            return '';
        }
        $media = new media_manager($this->context);
        $scenes = $media->urls_for_revision(
            media_manager::AREA_REVISION_SCENE,
            (int)$revision->revision
        );

        // ITS OWN ESTABLISHING FRAME FIRST.
        //
        // This used to return the START NODE's photograph. The start node IS the first
        // decision, so a learner's first three screens - the opening situation, decision
        // one, and the consequence of decision one - were the same picture three times
        // before they had made a second choice. Three identical frames inside ten seconds
        // is the product introducing itself as cheap.
        //
        // Since v1.81.0 the opening has a wide frame of its own, briefed from the
        // scenario's setting and hook rather than from a node: the place, before anyone
        // has done anything. The start node's picture stays as the fallback for scenarios
        // published before that release.
        // NO FALLBACK. Its own frame, or none.
        //
        // This fell back to the START NODE's photograph, and the start node IS the first
        // decision - so the opening, decision one and the consequence of decision one were
        // one picture three times before a learner had made a second choice. v1.81.0 gave
        // the opening a frame of its own and left the fallback in place "for scenarios
        // published before it", which meant the fault was still one refused image away on
        // every scenario, and no check could fail on it because every key still resolved to
        // a picture.
        //
        // A borrowed picture is not a cheaper version of the right one. It is the fault,
        // arriving quietly. An empty column says "this frame is missing" - which the
        // missing-picture panel on the review page then names and offers to fix.
        return (string)($scenes[media_manager::OPENING_KEY] ?? '');
    }

    /**
     * The URL of the opening situation's narration, if it was made.
     *
     * @param stdClass $revision The published revision.
     * @return string
     */
    protected function opening_narration($revision): string {
        $media = new media_manager($this->context);
        $narration = $media->urls_for_revision(
            media_manager::AREA_REVISION_NARRATION,
            (int)$revision->revision
        );
        return (string)($narration[media_manager::OPENING_KEY] ?? '');
    }

    /**
     * Build the lesson slides that open the scenario, one per principle.
     *
     * @param array|mixed $definition The validated scenario definition.
     * @param stdClass|null $revision The published revision, or null if there is none.
     * @return array Slide rows for the template; empty when there is nothing to teach.
     */
    protected function lesson_slides($definition, $revision): array {
        if (!is_array($definition) || empty($definition['principles']) || !$revision) {
            return [];
        }
        $media = new media_manager($this->context);
        // Keyed, not positional.
        //
        // This used to take array_values() of the scene map and hand principle N the Nth
        // decision node's photograph, wrapping round when it ran out. Every lesson slide
        // therefore showed a scene the learner had not reached yet, and showed it again
        // properly a minute later. Each principle now has a frame of its own, briefed from
        // its own words, and it is looked up by name.
        $scenes = $media->urls_for_revision(
            media_manager::AREA_REVISION_SCENE,
            (int)$revision->revision
        );
        // The borrowing list that used to live here is gone. Every screen shows its own
        // picture or no picture: a frame borrowed from elsewhere in the set is how two
        // consecutive screens end up identical, and it is invisible to every check, because
        // a borrowed picture still resolves to a picture. What a missing frame gets now is
        // an empty column, which the missing-picture panel on the review page names and
        // offers to generate on its own.
        $narration = $media->urls_for_revision(
            media_manager::AREA_REVISION_NARRATION,
            (int)$revision->revision
        );

        $slides = [];
        foreach (array_values($definition['principles']) as $position => $principle) {
            // Its own picture, or none. The rotation that used to stand in for a missing
            // lesson frame put a photograph of a scene the learner had not reached yet
            // beside words it had nothing to do with - and then showed it to them again a
            // minute later when they actually got there.
            $key = media_manager::principle_key($principle, $position + 1);
            $image = (string)($scenes['lesson_' . $key] ?? '');
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
                'audiourl'    => (string)($narration['lesson_' . $key] ?? ''),
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
        $revision = scenario_manager::get_current_revision($this->scenario, $this->tier);
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
            'tier'         => (int)$this->tier,
            'instanceid'   => (int)$this->scenario->id,
            // Whether this site lets the product make a sound at all. Nothing to do with
            // narration, which is about paying for a generated voice.
            'cues'         => (bool)get_config('mod_aibranchedscenario', 'allowcues'),
            // Whether a learner may say they are not sure before they decide. Site-level,
            // because what it feeds is a picture of a cohort rather than of one activity.
            'askconfidence' => (bool)get_config('mod_aibranchedscenario', 'askconfidence'),
            'theme'        => $this->scenario->theme,
            'themeclass'   => schema::theme_class((string)$this->scenario->theme),
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
            // The same sentence the narrator speaks over this slide, in the page language.
            'openinggoal'  => \mod_aibranchedscenario\local\media_manager::opening_goal(
                (int)($this->scenario->bandgreen ?? 67)
            ),
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
            // The opening slide used to borrow the FIRST LESSON SLIDE'S picture, so the
            // screen a learner meets first and the screen immediately after it showed the
            // same photograph - the same fault the debrief had, on the two slides where it
            // is most obvious because they are consecutive. It takes the frame of the node
            // the scenario actually opens on, which is what the opening situation
            // describes; the lesson slides keep theirs.
            // And no second fallback to the first lesson slide's picture, which was the
            // same fault one step further along: the opening and the slide immediately
            // after it showing one photograph.
            'openingimage' => $this->opening_scene($definition, $revision),
            // The opening situation has a clip of its own. It was being generated and
            // published and then never asked for: this slide is not a node, so nothing in
            // the node payload covered it, and its article carried no audio attribute to
            // play one from. A learner met silence, then heard every screen after it.
            'openingaudio' => $revision ? (string)($this->opening_narration($revision) ?? '') : '',
            // THE BRIEF: what a learner is walking into, and what they are working towards.
            //
            // The opening screen carried a chip, a title, the role and the hook - four short
            // things in a card sized for a scene, with most of it empty. Everything below is
            // already in the definition and was shown nowhere: a learner started a scenario
            // without being told where they were, who they would meet, how many decisions
            // were ahead, or what the three readings in the bar were for.
            //
            // The readings in particular were the gap. They move on every decision and the
            // whole scoreboard is built on them, and until now the first time a learner
            // learned what they meant was when one of them dropped.
            // A PRINTED RECORD WITH NO NAME AND NO DATE ON IT IS NOT A RECORD.
            //
            // Printing gave the debrief deck and nothing else: no learner, no date, and -
            // because the readings were explicitly hidden for print - not even the final
            // scores. For an RTO keeping evidence of what a learner did, that is the one
            // thing the page is for.
            'learnername' => fullname($GLOBALS['USER']),
            'printdate'   => userdate(time(), get_string('strftimedatetime', 'core_langconfig')),
        ];
    }
}
