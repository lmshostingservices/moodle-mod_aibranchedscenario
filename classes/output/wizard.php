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
use mod_aibranchedscenario\local\ai\credentials;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\source_normaliser;
use stdClass;

/**
 * Prepares the data the authoring wizard template and its AMD module need.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard implements \renderable, \templatable {
    /** @var stdClass Activity instance. */
    protected $scenario;

    /** @var stdClass Course module record. */
    protected $cm;

    /** @var context_module Module context. */
    protected $context;

    /** @var int Which stored rung this writes. Always one - see schema::TIERS. */
    protected $tier;

    /**
     * Constructor.
     *
     * @param stdClass $scenario Activity instance.
     * @param stdClass $cm Course module record.
     * @param context_module $context Module context.
     * @param int $tier Which rung of the ladder is being written.
     */
    public function __construct(stdClass $scenario, $cm, context_module $context, int $tier = 1) {
        $this->scenario = $scenario;
        $this->cm = $cm;
        $this->context = $context;
        $this->tier = isset(schema::tiers()[$tier]) ? $tier : 1;
    }

    /**
     * Build a list of selectable options.
     *
     * @param string[] $values Option keys.
     * @param string $prefix Language string prefix.
     * @param string|string[] $selected Currently selected key or keys.
     * @return array
     */
    protected function options(array $values, string $prefix, $selected): array {
        $selected = is_array($selected) ? $selected : [$selected];
        $out = [];
        foreach ($values as $value) {
            $out[] = [
                'key'      => $value,
                'label'    => get_string($prefix . ':' . $value, 'mod_aibranchedscenario'),
                'selected' => in_array($value, $selected, true),
            ];
        }
        return $out;
    }

    /**
     * The setting pickers, each tagged with the industries it belongs to.
     *
     * @param string $selected The chosen setting.
     * @return array Template context.
     */
    protected function settings_options(string $selected, string $industry = ''): array {
        $belongs = [];
        foreach (schema::industries() as $sector) {
            foreach (schema::settings_for_industry($sector) as $setting) {
                $belongs[$setting][] = $sector;
            }
        }
        // Filtered here as well as in the browser, so the first paint is already the short
        // list. Rendering all sixty-four and hiding sixty of them a moment later is a page
        // that visibly changes its mind while the teacher is reading it.
        $allowed = ($industry === '' || $industry === 'other')
            ? schema::settings_list() : schema::settings_for_industry($industry);
        $out = [];
        foreach (schema::settings_list() as $setting) {
            $out[] = [
                'key'        => $setting,
                'label'      => get_string('setting:' . $setting, 'mod_aibranchedscenario'),
                'selected'   => $setting === $selected,
                'hide'       => !in_array($setting, $allowed, true) && $setting !== $selected,
                // NOT 'industries': inside the settings loop that name resolves to the
                // template's own industry list one context up, and the picker renders an
                // array into an attribute.
                'industrykeys' => implode(' ', $belongs[$setting] ?? []),
            ];
        }
        return $out;
    }

    /**
     * Where a choice may lead, as a list a teacher can choose from.
     *
     * A choice's target was shown as a raw node identifier and could not be changed at
     * all, which made the branching of a branching-scenario authoring tool read only.
     * Only later stages and endings are offered, because the validator rejects a graph
     * that loops back.
     *
     * @param array $definition The working copy.
     * @param array $node The node the choice belongs to.
     * @param string $selected The current target.
     * @return array
     */
    protected function targets(array $definition, array $node, string $selected): array {
        $out = [[
            'key'      => schema::auto_target(),
            'label'    => get_string('review:autotarget', 'mod_aibranchedscenario'),
            'selected' => $selected === schema::auto_target(),
        ]];
        foreach ($definition['nodes'] as $candidate) {
            $later = (int)$candidate['stage'] > (int)$node['stage'];
            if (!$later && $candidate['type'] !== 'outcome') {
                continue;
            }
            $out[] = [
                'key'      => $candidate['id'],
                'label'    => $candidate['title'] !== '' ? $candidate['title'] : $candidate['id'],
                'selected' => $selected === $candidate['id'],
            ];
        }
        return $out;
    }

    /**
     * Export the data used by the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $source = scenario_manager::get_source($this->scenario);
        $source = $source ? source_normaliser::normalise($source) : source_normaliser::blank();

        $definition = scenario_manager::get_working_definition($this->scenario, $this->tier);
        $tierrow = scenario_manager::tier_row((int)$this->scenario->id, $this->tier);
        $nodes = [];
        if (is_array($definition)) {
            foreach ($definition['nodes'] as $node) {
                $choices = [];
                foreach ($node['choices'] as $choice) {
                    $choices[] = [
                        'id'          => $choice['id'],
                        'letter'      => $choice['letter'],
                        'text'        => $choice['text'],
                        'consequence' => $choice['consequence'],
                        'feedback'    => $choice['feedback'],
                        // Read with a fallback: a draft written before v2.3.0 has no note,
                        // and the editor is where a teacher writes the missing one.
                        'outcomenote' => (string)($choice['outcomenote'] ?? ''),
                        'signal'      => $choice['signal'],
                        'next'        => $choice['next'],
                        'signaloptions' => $this->options(schema::signals(), 'signal', $choice['signal']),
                        'targetoptions' => $this->targets($definition, $node, $choice['next']),
                    ];
                }
                $nodes[] = [
                    'id'                => $node['id'],
                    'type'              => $node['type'],
                    'isoutcome'         => $node['type'] === 'outcome',
                    'title'             => $node['title'],
                    'stage'             => $node['stage'],
                    'situation'         => $node['situation'],
                    'facilitatorspeech' => $node['facilitatorspeech'],
                    'challenge'         => $node['challenge'],
                    'summary'           => $node['summary'],
                    'choices'           => $choices,
                    'haschoices'        => !empty($choices),
                ];
            }
        }

        $characters = [];
        for ($i = 0; $i < 4; $i++) {
            $characters[] = [
                'index'      => $i,
                'position'   => $i + 1,
                'name'       => $source['characters'][$i]['name'] ?? '',
                'role'       => $source['characters'][$i]['role'] ?? '',
                'trait'      => $source['characters'][$i]['trait'] ?? '',
                'appearance' => $source['characters'][$i]['appearance'] ?? '',
                'ismale'     => ($source['characters'][$i]['gender'] ?? '') === 'male',
                'isfemale'   => ($source['characters'][$i]['gender'] ?? '') === 'female',
                'isnonbinary' => ($source['characters'][$i]['gender'] ?? '') === 'non-binary',
                // FOUR EMPTY SLOTS READ AS FOUR PEOPLE TO INVENT, and two is what the
                // format can carry. The third and fourth are hidden until they hold
                // somebody, or until the teacher asks for them.
                'extra'      => $i >= 2 && trim((string)($source['characters'][$i]['name'] ?? '')) === '',
            ];
        }

        // WHAT IS STILL BEING MADE. A scenario's text arrives minutes before its pictures
        // and narration on both routes in, and until they are there it cannot be published.
        $mediapending = false;
        $mediastatus = '';
        if (is_array($definition) && $definition) {
            $status = (new \mod_aibranchedscenario\local\media_manager($this->context, $this->tier))
                ->working_media_status($this->scenario, $definition);
            $mediapending = empty($status['complete']);
            if ($mediapending) {
                $mediastatus = get_string('mediapending', 'mod_aibranchedscenario', (object)[
                    'images'           => (int)$status['images'],
                    'imageswanted'     => (int)$status['imageswanted'],
                    'narrations'       => (int)$status['narrations'],
                    'narrationswanted' => (int)$status['narrationswanted'],
                ]);
            }
        }

        $credentials = credentials::resolve();
        $configuredmax = get_config('mod_aibranchedscenario', 'maxsourcechars');
        $maxsourcechars = (int)($configuredmax ?: schema::MAX_SOURCE_CHARS);

        return [
            'cmid'         => (int)$this->cm->id,
            'sesskey'      => sesskey(),
            'themeclass'   => schema::theme_class((string)$this->scenario->theme),
            'viewurl'      => (new \moodle_url(
                '/mod/aibranchedscenario/view.php',
                ['id' => $this->cm->id]
            ))->out(false),
            'reviewurl'    => (new \moodle_url(
                '/mod/aibranchedscenario/review.php',
                ['id' => $this->cm->id, 'tier' => $this->tier]
            ))->out(false),
            // WHETHER THE SERVICE IS CONFIGURED AND WHETHER THIS PERSON MAY USE IT ARE
            // TWO DIFFERENT QUESTIONS, and only the first was ever asked.
            //
            // Every control that spends credits or publishes was shown whenever the site
            // had credentials, whatever the person looking at it was allowed to do. A role
            // with manage but not generate - an ordinary split in a training organisation,
            // where authoring and spending are different jobs - got a Generate button that
            // answered "you do not currently have permissions to do that". An offered
            // control that refuses the person it was offered to is worse than no control:
            // it reads as the product being broken rather than as a permission they do
            // not have.
            'aiavailable'  => $credentials['source'] !== credentials::SOURCE_NONE
                && has_capability('mod/aibranchedscenario:generate', $this->context),
            'canpublish'   => has_capability('mod/aibranchedscenario:publish', $this->context),
            // Said out loud rather than left as an absence, because a teacher who cannot
            // find the Generate button needs to know it is a permission and not a fault.
            'cannotgenerate' => $credentials['source'] !== credentials::SOURCE_NONE
                && !has_capability('mod/aibranchedscenario:generate', $this->context),
            // Per RUNG, not per activity. Read from the instance, the wizard for the
            // unwritten advanced scenario announced itself as published at revision 3,
            // because the foundation scenario was - and offered Restore draft on a rung
            // that had never had one.
            'published'    => $tierrow->status === scenario_manager::STATUS_PUBLISHED
                && (int)$tierrow->revision > 0,
            'revision'     => (int)$tierrow->revision,
            'hasdraft'     => is_array($definition),
            // Publishing is refused while the pictures and narration are still being made,
            // on both routes in. The button says so rather than failing when pressed.
            'mediapending' => $mediapending,
            'mediastatus'  => $mediastatus,
            'hasprevious'  => !empty($tierrow->previousjson),
            'tier'         => $this->tier,
            'source'       => $source,
            'characters'   => $characters,
            'principles'   => array_values($source['principles']),
            'hasprinciples' => !empty($source['principles']),
            'nodes'        => $nodes,
            'nodecount'    => count($nodes),
            // Not a list of options any more - the length is fixed. Passed so the screen
            // can name the number rather than hard-coding it in the template.
            'decisions'    => schema::DECISIONS,
            'maxoutcomenote' => schema::MAX_OUTCOME_NOTE,
            // Three worked examples for the extra-direction box, built here rather than
            // written into the template so each one is a translatable string with its
            // label beside it.
            'briefexamples' => array_map(static function ($key) {
                return [
                    'label' => get_string('briefexample:' . $key, 'mod_aibranchedscenario'),
                    'text'  => get_string('briefexample:' . $key . 'text', 'mod_aibranchedscenario'),
                ];
            }, ['audience', 'focus', 'situation']),
            'industries'   => $this->options(schema::industries(), 'industry', $source['industry']),
            // Each setting carries the industries it belongs to, so the wizard can show a
            // hospitality teacher a kitchen, a bar and a function room instead of a list
            // that opens with "Mine site". Filtered in the browser rather than on a reload,
            // because choosing an industry should not cost a page load.
            'settings'     => $this->settings_options($source['setting'], (string)$source['industry']),
            'atmospheres'  => $this->options(schema::atmospheres(), 'atmosphere', $source['atmosphere']),
            'whyhard'      => $this->options(schema::whyhard(), 'whyhard', $source['whyhard']),
            'stakes'       => $this->options(schema::stakes(), 'stakes', $source['stakes']),
            'tones'        => $this->options(schema::tones(), 'tone', $source['tone']),
            // HOW HARD THIS SCENARIO IS, AND THE TEACHER DECIDES.
            //
            // The control was removed when an activity briefly held three scenarios and
            // the rung decided the level. An activity is one scenario again, so the choice
            // comes back to the person making it - and a teacher who wants a progression
            // builds three activities, one at each level, sequenced with Moodle's own
            // availability restrictions.
            'complexities' => $this->options(schema::complexities(), 'complexity', $source['complexity']),
            'imagestyles'  => $this->options(schema::imagestyles(), 'imagestyle', $source['imagestyle']),
            'openingmetrics' => [
                ['key' => 'engagement', 'label' => get_string('metric:engagement', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['engagement']],
                ['key' => 'trust', 'label' => get_string('metric:trust', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['trust']],
                ['key' => 'tension', 'label' => get_string('metric:tension', 'mod_aibranchedscenario'),
                    'value' => (int)$source['openingmetrics']['tension']],
            ],
            // The wizard has to be able to tell "the teacher chose Training room" from
            // "nobody has chosen anything yet", because the pickers are rendered with a
            // schema default already pressed. Without this, autofill treats every
            // picker as answered and never fills one.
            'defaults'       => [
                'industry'   => source_normaliser::blank()['industry'],
                'setting'    => source_normaliser::blank()['setting'],
                'atmosphere' => source_normaliser::blank()['atmosphere'],
            ],
            'topicgroups'    => self::topic_options($source['topics'] ?? []),
            'choices'        => self::choices_summary($source),
            'maxsourcechars' => $maxsourcechars,
            'generationeta'  => get_string('generationeta', 'mod_aibranchedscenario'),
            // Built from the SAVED answers, so the prompt a teacher copies describes
            // the workplace, the people and the problem they have already chosen
            // rather than leaving an assistant to guess at all three.
            'importprompt'   => \mod_aibranchedscenario\local\import_prompt::text(schema::DECISIONS, $source),
            'pricing'        => self::pricing_card($this->scenario),
            'quote'          => self::quote($this->scenario, is_array($definition) ? $definition : null),
            'howto'          => self::how_to_use(),
            'reading'        => self::further_reading(),
        ];
    }

    /**
     * The three pages a teacher asks for next, on the service's own site.
     *
     * Built from the configured API host rather than written out, so a site pointed at a
     * staging service does not send its teachers to the live one.
     *
     * @return array Template context.
     */
    protected static function further_reading(): array {
        $links = [
            'docs'    => '/docs/ai-branched-scenario',
            'pricing' => '/pricing',
            'voices'  => '/voices',
        ];
        $out = [];
        foreach ($links as $key => $path) {
            $out[] = [
                'url'   => \mod_aibranchedscenario\local\ai\lmslabs_provider::public_link($path),
                'title' => get_string('reading:' . $key, 'mod_aibranchedscenario'),
                'body'  => get_string('reading:' . $key . 'body', 'mod_aibranchedscenario'),
            ];
        }
        return $out;
    }

    /**
     * What the teacher has already chosen, printed above the content box.
     *
     * The content step used to come first, so an assistant was handed the format rules and
     * a block of policy text and had to guess the workplace - and guessed a meeting room.
     * The context steps come first now, and this prints their answers where the prompt is
     * copied, so what the assistant is about to be told is visible rather than implied.
     *
     * An unanswered row is left out rather than shown blank: a list of empty values reads
     * as a broken page, and the one thing this block exists to do is show that the context
     * is there.
     *
     * @param array $source Normalised wizard values.
     * @return array Template context: rows of label and value.
     */
    /**
     * The situations a teacher can pick from, grouped.
     *
     * @param array $chosen Topic keys already ticked.
     * @return array Template context.
     */
    protected static function topic_options(array $chosen): array {
        $out = [];
        foreach (schema::topic_groups() as $group => $topics) {
            $items = [];
            foreach ($topics as $topic) {
                $items[] = [
                    'key'     => $topic,
                    'label'   => get_string('topic:' . $topic, 'mod_aibranchedscenario'),
                    'checked' => in_array($topic, $chosen, true),
                ];
            }
            $out[] = [
                'key'    => $group,
                'label'  => get_string('topicgroup:' . $group, 'mod_aibranchedscenario'),
                'topics' => $items,
            ];
        }
        return $out;
    }

    /**
     * What the teacher has already chosen, printed above the content box.
     *
     * @param array $source Normalised wizard values.
     * @return array Template context: rows of label and value, and whether there are any.
     */
    protected static function choices_summary(array $source): array {
        // A PICKER RENDERED WITH A DEFAULT ALREADY SELECTED IS NOT AN ANSWER.
        //
        // This printed "Training and assessment", "Training room" and "Neutral" under a
        // heading saying they were the teacher's answers, to a teacher who had answered
        // nothing. lmslabs_provider::current_values() already guards against exactly this
        // before sending values to the service - a default sent as a current value told the
        // service the teacher had chosen a training room whatever the content was about -
        // and the summary was written without the same guard. An untouched default is
        // silence, and silence is left out rather than dressed up as a choice.
        $blank = source_normaliser::blank();
        $answered = static function (string $key) use ($source, $blank): bool {
            $value = trim((string)($source[$key] ?? ''));
            if ($value === '') {
                return false;
            }
            return !array_key_exists($key, $blank) || $value !== (string)$blank[$key];
        };
        $label = static function (string $family, string $key): string {
            $id = $family . ':' . $key;
            return get_string_manager()->string_exists($id, 'mod_aibranchedscenario')
                ? get_string($id, 'mod_aibranchedscenario') : $key;
        };
        $pick = static function (string $key, string $family) use ($source, $label, $answered): string {
            if (!$answered($key)) {
                return '';
            }
            $value = trim((string)($source[$key] ?? ''));
            if ($value === 'other') {
                return trim((string)($source[$key . 'other'] ?? ''));
            }
            return $label($family, $value);
        };
        $list = static function (string $key, string $family) use ($source, $label): string {
            $out = [];
            foreach ((array)($source[$key] ?? []) as $one) {
                if (trim((string)$one) !== '') {
                    $out[] = $label($family, (string)$one);
                }
            }
            return implode(', ', $out);
        };
        $said = static function (string $key) use ($source): string {
            return trim((string)($source[$key] ?? ''));
        };

        $cast = [];
        foreach ((array)($source['characters'] ?? []) as $character) {
            $name = trim((string)($character['name'] ?? ''));
            if ($name !== '') {
                $cast[] = $name;
            }
        }

        // What it is about is one of the three things the teacher is now asked for, and the
        // one the assistant most needs, so it is named here beside the other two rather
        // than left for the prompt to carry silently.
        $topics = $list('topics', 'topic');
        $own = $said('topicown');
        if ($own !== '') {
            $topics = $topics === '' ? $own : $topics . ', ' . $own;
        }

        $rows = [
            ['field:industry', $pick('industry', 'industry')],
            ['field:setting', $pick('setting', 'setting')],
            ['field:topic', $topics],
            ['field:audience', $said('audience')],
            ['field:participantrole', $said('participantrole')],
            ['field:centralproblem', $said('centralproblem')],
            ['field:whyhard', $list('whyhard', 'whyhard')],
            ['field:stakes', $list('stakes', 'stakes')],
            ['field:characters', implode(', ', $cast)],
            ['field:tone', $pick('tone', 'tone')],
        ];
        $out = [];
        foreach ($rows as [$stringid, $value]) {
            if ($value === '') {
                continue;
            }
            $out[] = [
                'label' => get_string_manager()->string_exists($stringid, 'mod_aibranchedscenario')
                    ? get_string($stringid, 'mod_aibranchedscenario') : $stringid,
                'value' => $value,
            ];
        }
        return ['rows' => $out, 'any' => $out !== []];
    }

    /**
     * The order a teacher does this in, including the part everybody misses.
     *
     * Publishing does not show you the scenario: this page is the author's view, and the
     * activity only becomes the thing a learner sees when you look at it as one. Teachers
     * who did not know that concluded that publishing had done nothing.
     *
     * @return array Template context.
     */
    protected static function how_to_use(): array {
        $steps = [];
        // Five, and they are the five steps the wizard itself has: "six steps" sat above a
        // rail numbered differently, and its last two both happened on one screen. Looking
        // at it as a student is folded into publishing, which is where a teacher is standing
        // when they need to hear it.
        foreach (['paste', 'shape', 'generate', 'review', 'publish'] as $index => $key) {
            $steps[] = [
                'number' => $index + 1,
                'title'  => get_string('howto:' . $key, 'mod_aibranchedscenario'),
                'body'   => get_string('howto:' . $key . 'body', 'mod_aibranchedscenario'),
            ];
        }
        return $steps;
    }

    /**
     * What the four ways of generating a scenario cost, with the current one marked.
     *
     * A teacher should not have to press the button that spends money to find out what
     * it spends. The card is shown before anything is filled in, and the same price is
     * repeated in the confirmation, so there is no point at which the cost is a surprise.
     *
     * @param \stdClass $scenario Activity instance.
     * @return array Template context.
     */
    protected static function pricing_card(\stdClass $scenario): array {
        // NO MORE TABLE OF FOUR PRICES. The four-row card existed because a scenario was one
        // flat price with two switches on it; it is charged by the piece now, so the only
        // honest thing to show before a scenario exists is the rate and an estimate, and the
        // real number belongs on the button that spends it.
        $pricing = schema::pricing();
        $withimages = !empty($scenario->enableimages)
            && get_config('mod_aibranchedscenario', 'allowimages');
        $withvoice = !empty($scenario->enableaudio)
            && get_config('mod_aibranchedscenario', 'allowaudio');
        $estimate = schema::price_for($withimages, $withvoice);
        return [
            'rates'    => (object)[
                'node'  => (int)$pricing['node'],
                'image' => (int)$pricing['image'],
                'clip'  => (int)$pricing['clip'],
            ],
            'amount'   => get_string('quote:estimate', 'mod_aibranchedscenario', $estimate['total']),
            'credits'  => $estimate['total'],
            'currency' => $estimate['currency'],
        ];
    }

    /**
     * What a run will cost, counted from the scenario in hand.
     *
     * Shown beside the button that spends it, which is where the sister activity puts it and
     * where a teacher is actually looking when the money moves.
     *
     * @param \stdClass $scenario The activity instance.
     * @param array|null $definition The working definition, when there is one.
     * @return array Template context: line, total, and whether there is anything to charge.
     */
    protected static function quote(\stdClass $scenario, ?array $definition): array {
        $withimages = !empty($scenario->enableimages)
            && get_config('mod_aibranchedscenario', 'allowimages');
        $withvoice = !empty($scenario->enableaudio)
            && get_config('mod_aibranchedscenario', 'allowaudio');
        if (!is_array($definition) || empty($definition['nodes'])) {
            $estimate = schema::price_for($withimages, $withvoice);
            return [
                'line'  => get_string('quote:estimate', 'mod_aibranchedscenario', $estimate['total']),
                'total' => $estimate['total'],
                'any'   => true,
            ];
        }
        $nodes = count((array)$definition['nodes']);
        $images = $withimages ? count(media_manager::expected_image_map($definition)) : 0;
        $clips = $withvoice
            ? media_manager::count_narrations($definition, media_manager::voice_parts($scenario)) : 0;
        $units = schema::price_for_units($nodes, $images, $clips);
        return [
            'line'  => get_string('quote:line', 'mod_aibranchedscenario', (object)[
                'total'  => $units['total'],
                'nodes'  => $units['nodes'],
                'images' => $units['images'],
                'clips'  => $units['clips'],
            ]),
            'total' => $units['total'],
            'any'   => $units['total'] > 0,
        ];
    }
}
