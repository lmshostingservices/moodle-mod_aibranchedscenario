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
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The screen a learner lands on: three scenarios, one after another.
 *
 * An activity used to be one scenario, which a learner opened straight into. It holds
 * three now - foundation, intermediate, advanced - testing the same principles in harder
 * situations, and each one opens when the one below it has been passed.
 *
 * The cards are deliberately not a menu. The order is the point: a learner does not get to
 * start with the hardest one, because the ladder is developing their competence rather
 * than sampling it.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ladder implements renderable, templatable {
    /** @var int How much of the hook a card can hold without needing to scroll. */
    const BLURB_LIMIT = 240;

    /** @var stdClass The activity instance. */
    protected $scenario;

    /** @var stdClass|\cm_info The course module. */
    protected $cm;

    /** @var context_module The module context. */
    protected $context;

    /** @var int The learner. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance record.
     * @param stdClass|\cm_info $cm Course module.
     * @param context_module $context Module context.
     * @param int $userid The learner whose progress is being shown.
     */
    public function __construct(stdClass $scenario, $cm, context_module $context, int $userid) {
        $this->scenario = $scenario;
        $this->cm = $cm;
        $this->context = $context;
        $this->userid = $userid;
    }

    /**
     * Is there more than one rung worth showing a chooser for?
     *
     * An activity with only its foundation scenario written is the product as it was
     * before the ladder, and putting a chooser in front of one card is a screen that asks
     * a learner to make a choice they do not have.
     *
     * @param stdClass $scenario Activity instance record.
     * @return bool
     */
    public static function is_a_ladder(stdClass $scenario): bool {
        $written = 0;
        foreach (array_keys(schema::tiers()) as $tier) {
            if (scenario_manager::is_playable($scenario, $tier)) {
                $written++;
            }
        }
        return $written > 1;
    }

    /**
     * The part of a scenario's hook that fits on a card, cut at a sentence.
     *
     * A card is a fixed height with two other cards beside it, so the text has to stop
     * somewhere. It stops at the end of a sentence rather than mid-word with an ellipsis,
     * because a card is the last thing a learner reads before choosing and a severed
     * sentence reads as a fault in the product.
     *
     * @param string $hook The scenario's hook, which may be several paragraphs.
     * @return string
     */
    protected static function excerpt(string $hook): string {
        $hook = trim($hook);
        if ($hook === '') {
            return '';
        }

        // A hook opens with the idea and then puts the learner in the situation. The
        // situation is the half that belongs on a card, so it is preferred when it fits.
        $paragraphs = preg_split('/\R\s*\R/', $hook) ?: [$hook];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), 'strlen'));
        $text = (string)end($paragraphs);
        if (\core_text::strlen($text) > self::BLURB_LIMIT) {
            $text = (string)reset($paragraphs);
        }
        if (\core_text::strlen($text) <= self::BLURB_LIMIT) {
            return $text;
        }

        // Keep whole sentences. If even the first one is longer than the card, the card
        // shows that sentence rather than a fragment of it.
        $kept = '';
        if (preg_match_all('/.*?[.!?](?:\s|$)/u', $text . ' ', $sentences)) {
            foreach ($sentences[0] as $sentence) {
                if ($kept !== '' && \core_text::strlen($kept . $sentence) > self::BLURB_LIMIT) {
                    break;
                }
                $kept .= $sentence;
            }
        }
        return trim($kept) !== '' ? trim($kept) : trim($text);
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $progress = scenario_manager::ladder_progress($this->scenario, $this->userid);
        $names = schema::tiers();

        $cards = [];
        $passed = 0;
        foreach ($names as $tier => $complexity) {
            $state = $progress[$tier];
            if ($state['passed']) {
                $passed++;
            }

            // The blurb and the picture come from the scenario itself where there is one,
            // so a card describes the situation a learner is about to walk into rather
            // than the difficulty label above it. A rung nobody has written yet falls back
            // to the generic description of what that rung is for.
            $definition = null;
            $image = '';
            if ($state['published']) {
                $revision = scenario_manager::get_current_revision($this->scenario, $tier);
                if ($revision) {
                    $decoded = json_decode($revision->scenariojson, true);
                    $definition = is_array($decoded) ? $decoded : null;
                    // One manager per rung: the rung is what tells it which scenario's
                    // pictures it is looking at.
                    $media = new media_manager($this->context, $tier);
                    $urls = $media->urls_for_revision(
                        media_manager::AREA_REVISION_SCENE,
                        (int)$revision->revision
                    );
                    $image = (string)($urls[media_manager::OPENING_KEY] ?? '');
                }
            }

            $subtitle = $definition
                ? trim((string)($definition['subtitle'] ?? ''))
                : '';
            if ($subtitle === '') {
                $subtitle = get_string('tier:' . $complexity . 'blurb', 'mod_aibranchedscenario');
            }

            // The hook is the short piece of context a teacher already wrote for the
            // opening screen: the situation a learner is about to be standing in. It is
            // what makes a card a choice rather than a difficulty setting, so the card
            // borrows it rather than inventing a second description of the same scenario.
            $blurb = $definition ? self::excerpt((string)($definition['hook'] ?? '')) : '';

            // What the button says, which is three different sentences and not one with a
            // state class on it: "Start" and "Try again" are different promises.
            $label = get_string('ladder:start', 'mod_aibranchedscenario');
            if ($state['best'] !== null) {
                $label = $state['passed']
                    ? get_string('ladder:again', 'mod_aibranchedscenario')
                    : get_string('ladder:continue', 'mod_aibranchedscenario');
            }

            // Why a locked card is locked, named rather than implied. "Locked" with no
            // reason is the thing a learner takes to their trainer.
            $reason = '';
            if (!$state['published']) {
                $reason = get_string('ladder:notwritten', 'mod_aibranchedscenario');
            } else if (!$state['unlocked']) {
                $reason = $tier > 1
                    ? get_string(
                        'ladder:locked',
                        'mod_aibranchedscenario',
                        get_string('tier:' . $names[$tier - 1], 'mod_aibranchedscenario')
                    )
                    : get_string('ladder:lockedfirst', 'mod_aibranchedscenario');
            }

            $cards[] = [
                'tier'       => $tier,
                'number'     => $tier,
                'name'       => get_string('tier:' . $complexity, 'mod_aibranchedscenario'),
                'title'      => $definition ? (string)($definition['title'] ?? '') : '',
                'hastitle'   => $definition && trim((string)($definition['title'] ?? '')) !== '',
                'subtitle'   => $subtitle,
                'blurb'      => $blurb,
                'hasblurb'   => $blurb !== '',
                'hasimage'   => $image !== '',
                'imageurl'   => $image,
                'unlocked'   => (bool)$state['unlocked'],
                'locked'     => !$state['unlocked'],
                'lockreason' => $reason,
                'passed'     => (bool)$state['passed'],
                'attempted'  => $state['best'] !== null,
                'best'       => $state['best'] === null ? '' : (string)round((float)$state['best']),
                'label'      => $label,
                'url'        => (new moodle_url(
                    '/mod/aibranchedscenario/view.php',
                    ['id' => $this->cm->id, 'tier' => $tier]
                ))->out(false),
            ];
        }

        return [
            'title'       => get_string('ladder:title', 'mod_aibranchedscenario'),
            'lead'        => get_string('ladder:lead', 'mod_aibranchedscenario'),
            'cards'       => $cards,
            'themeclass'  => schema::theme_class($this->scenario->theme ?? ''),
            'progress'    => get_string('ladder:progress', 'mod_aibranchedscenario', (object)[
                'done'  => $passed,
                'total' => schema::TIERS,
            ]),
            'allpassed'   => $passed === schema::TIERS,
            'allpassedtext' => get_string('ladder:allpassed', 'mod_aibranchedscenario'),
        ];
    }
}
