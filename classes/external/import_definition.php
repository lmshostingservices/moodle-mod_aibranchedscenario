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
use mod_aibranchedscenario\local\ai\credentials;
use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\ai\image_prompt;
use mod_aibranchedscenario\local\generator;
use mod_aibranchedscenario\local\media_manager;
use mod_aibranchedscenario\local\scenario_manager;
use mod_aibranchedscenario\local\source_normaliser;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\validation_exception;
use mod_aibranchedscenario\local\validator;

/**
 * Imports a scenario definition supplied as JSON, without using the AI service.
 *
 * This is the route that lets a teacher author or reuse a scenario when no AI
 * credentials are configured, and it is what the automated tests use.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_definition extends external_api {
    /**
     * Describe the parameters.
     *
     * The definition is a JSON document, transported base64 encoded. Its narrative
     * fields legitimately contain angle brackets, quotation marks and non-ASCII text,
     * so passing the JSON as a cleaned string parameter would silently corrupt it,
     * and passing it uncleaned would place an unvalidated string in the parameter
     * layer. Base64 gives the parameter a shape that can be strictly validated on
     * arrival while leaving the document itself byte-exact. Moodle's PARAM_BASE64
     * follows the PEM convention of 64 character lines, so callers wrap the encoded
     * document accordingly; base64_decode() skips that whitespace.
     *
     * Nothing is trusted once decoded: the bytes are length checked, JSON decoded and
     * passed through the plugin's whitelist validator, which drops every key it does
     * not know and stores every string as plain text.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module id'),
            'definition' => new external_value(
                PARAM_BASE64,
                'Scenario definition: a JSON document, base64 encoded'
            ),
            // LAST, BECAUSE THE ORDER HERE IS THE CALLING ORDER.
            //
            // Moodle validates the arguments by name and then calls execute() with them
            // POSITIONALLY, in the order this list declares - "this also sorts the params
            // properly, we need the correct order in the next part", as external_api puts
            // it. A key added in the middle of this list is therefore handed to whichever
            // argument sits in that position, so a rung number arrived where the scenario
            // was expected and every paste-in was refused with "Invalid parameter value
            // detected". A new optional key goes at the end, matching the signature.
            'tier'       => new external_value(PARAM_INT, 'Which rung of the ladder', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Validate and store the supplied definition as the working copy.
     *
     * @param int $cmid Course module id.
     * @param string $definition Scenario definition: a JSON document, base64 encoded.
     * @param int $tier Which rung of the ladder this definition is for.
     * @return array
     */
    public static function execute(int $cmid, string $definition, int $tier = 1): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'definition' => $definition, 'tier' => $tier]
        );
        $resolved = helper::resolve($params['cmid'], 'mod/aibranchedscenario:manage');
        $tier = helper::tier($params['tier']);

        $json = base64_decode($params['definition'], true);
        if ($json === false) {
            return [
                'imported'  => false,
                'nodecount' => 0,
                'mediaqueued' => 0,
                'problems'  => get_string('error:notjson', 'mod_aibranchedscenario'),
            ];
        }

        try {
            $clean = validator::validate_json($json);
        } catch (validation_exception $e) {
            return [
                'imported'  => false,
                'nodecount' => 0,
                'mediaqueued' => 0,
                'problems'  => implode("\n", array_slice($e->get_problems(), 0, 20)),
            ];
        }

        // THE AI'S CHOICES BECOME THE TEACHER'S EDITABLE ANSWERS.
        //
        // A pasted scenario arrived complete - its own setting, its own cast, its own title,
        // its own role and tone - and none of it reached the wizard. The teacher was left
        // looking at empty fields beside a finished scenario, with the AI's answers readable
        // only inside the definition, and the next generate request was sent with the blanks
        // rather than with what was actually built. The point of the paste route is that the
        // assistant fills these in, so what it filled in is what is stored.
        //
        // Nothing the teacher typed is overwritten: a field they answered themselves wins,
        // because they answered it on purpose.
        self::backfill_source($resolved['scenario'], $clean);

        scenario_manager::save_definition($resolved['scenario'], $clean, [
            'provider'        => 'manual',
            'timegenerated'   => time(),
            'contractversion' => $clean['version'],
            'nodecount'       => (int)($clean['stats']['nodecount'] ?? 0),
            'decisioncount'   => (int)($clean['stats']['decisioncount'] ?? 0),
            'tier'            => $tier,
        ], true, $tier);

        // An imported scenario used to arrive with no pictures, because the media loop
        // ran only inside the generation task. A teacher who drafted the scenario
        // elsewhere and pasted it in still wants the illustrations, and the definition
        // they pasted carries an imageprompt for every node.
        // THE MEDIA A PASTED SCENARIO ASKS FOR IS CHARGED LIKE ANY OTHER MEDIA.
        //
        // It was not. queue_scenario() checks the daily budget before it spends anything;
        // this route checked nothing, and the one job row it wrote was weighed at a single
        // credit however much it made. So the generate button was budgeted and the paste
        // box was free, and a teacher could paste scenarios all day without ever reaching
        // the limit their site had set. The cost is known before the run starts, because
        // it is the number of pictures and clips the definition asks for.
        //
        // The scenario itself still imports: pasting costs nothing, validating costs
        // nothing, and refusing the whole import over the media would throw away work the
        // teacher did elsewhere. Only the media is held, and the teacher is told why
        // rather than left with a scenario that quietly never illustrates itself.
        $media = 0;
        $problems = '';
        if (self::wants_media($resolved['scenario']) && credentials::are_configured()) {
            $wantsimages = !empty($resolved['scenario']->enableimages)
                && get_config('mod_aibranchedscenario', 'allowimages');
            $wantsaudio = !empty($resolved['scenario']->enableaudio)
                && get_config('mod_aibranchedscenario', 'allowaudio');
            $images = $wantsimages ? image_prompt::count_images($clean) : 0;
            $clips = $wantsaudio ? media_manager::count_narrations(
                $clean,
                media_manager::voice_parts($resolved['scenario'])
            ) : 0;
            // THE SAME PRICE HOWEVER THE SCENARIO WAS MADE, which is still the rule - it is
            // just that the price is now counted rather than published. A pasted scenario
            // and a generated one with the same pictures and clips cost the same, because
            // both are the same number of pieces. The words are not charged on this route:
            // the teacher wrote them somewhere else, and LMS Labs made nothing.
            $cost = (int)schema::price_for_units(0, $images, $clips)['total'];

            try {
                // ONE MEDIA RUN AT A TIME, which the generate route has always enforced and
                // this one never did.
                //
                // queue_adhoc_task() de-duplicates only while a task is still QUEUED. Once
                // the first one is running it has left the queue, so pasting a corrected
                // version a few minutes later starts a second run alongside it. The second
                // run's first write clears the working area the first is still filling,
                // both then copy into the same revision, and the teacher is charged twice
                // for a result that is neither version.
                //
                // A run is only treated as in flight for an hour. Blocking on a job record
                // forever would mean one crashed run locks a teacher out of their own
                // pictures with no way back except the database.
                if (self::media_in_flight((int)$resolved['scenario']->id, $tier)) {
                    throw new generation_exception('error:mediainflight');
                }
                (new generator())->check_credits((int)$USER->id, $cost);
                $task = new \mod_aibranchedscenario\task\generate_media();
                $task->set_custom_data((object)['cmid' => (int)$params['cmid'], 'tier' => $tier]);
                $task->set_userid((int)$USER->id);
                \core\task\manager::queue_adhoc_task($task, true);
                $media = $images;
            } catch (generation_exception $e) {
                $problems = $e->errorcode === 'error:mediainflight'
                    ? get_string('media:inflight', 'mod_aibranchedscenario')
                    : get_string('media:overbudget', 'mod_aibranchedscenario', (object)[
                        'images' => $images,
                        'clips'  => $clips,
                    ]);
            }
        }

        return [
            'imported'  => true,
            'nodecount' => (int)($clean['stats']['nodecount'] ?? 0),
            'mediaqueued' => $media,
            'problems'  => $problems,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'imported'  => new external_value(PARAM_BOOL, 'Whether the definition was stored'),
            'nodecount' => new external_value(PARAM_INT, 'Nodes in the stored definition'),
            'mediaqueued' => new external_value(
                PARAM_INT,
                'Scene images queued for background generation',
                VALUE_DEFAULT,
                0
            ),
            'problems'  => new external_value(
                PARAM_RAW, // pipeline-ignore: PARAM_RAW — prose, escaped at render, never cleaned.
                'Validation problems when the import was rejected'
            ),
        ]);
    }

    /**
     * Is a media run for this activity already under way?
     *
     * @param int $scenarioid Activity instance id.
     * @param int $tier Which rung of the ladder.
     * @return bool
     */
    protected static function media_in_flight(int $scenarioid, int $tier = 1): bool {
        global $DB;
        // Per rung, for the same reason generation is: illustrating the intermediate
        // scenario while the foundation one's pictures are still being drawn is two
        // separate runs writing two separate sets of files.
        return $DB->record_exists_select(
            'aibranchedscenario_jobs',
            'scenarioid = :sid AND tier = :tier AND jobtype = :type AND status IN (:queued, :running)
               AND timecreated > :since',
            [
                'sid'     => $scenarioid,
                'tier'    => $tier,
                'type'    => 'media',
                'queued'  => generator::JOB_QUEUED,
                'running' => generator::JOB_RUNNING,
                'since'   => time() - HOURSECS,
            ]
        );
    }

    /**
     * Whether this activity is set up to want images or narration.
     *
     * @param \stdClass $scenario Activity instance.
     * @return bool
     */
    protected static function wants_media(\stdClass $scenario): bool {
        $images = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $audio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');
        return $images || $audio;
    }

    /**
     * Copy what the assistant decided into the wizard's own fields.
     *
     * @param \stdClass $scenario The activity instance.
     * @param array $definition The validated definition just stored.
     * @return void
     */
    protected static function backfill_source(\stdClass $scenario, array $definition): void {
        $source = scenario_manager::get_source($scenario);
        $source = is_array($source) ? $source : [];
        $source = source_normaliser::normalise($source);
        $blank = source_normaliser::blank();

        // A value counts as unanswered when it is empty or still the shipped default: a
        // picker nobody touched is silence, not a choice, and silence is what this fills.
        $empty = static function (string $key) use ($source, $blank): bool {
            $value = $source[$key] ?? '';
            if (is_array($value)) {
                return $value === [];
            }
            $value = trim((string)$value);
            return $value === '' || (array_key_exists($key, $blank) && $value === (string)$blank[$key]);
        };

        $take = static function (string $key, $value) use (&$source, $empty): void {
            $value = is_string($value) ? trim($value) : $value;
            if ($value !== '' && $value !== null && $empty($key)) {
                $source[$key] = $value;
            }
        };

        $take('title', (string)($definition['title'] ?? ''));
        $take('participantrole', (string)($definition['role'] ?? ''));
        $take('tone', (string)($definition['tone'] ?? ''));
        $take('complexity', (string)($definition['complexity'] ?? ''));

        // The setting arrives as prose - "the cold room at the back of the kitchen" - and the
        // wizard's field is a key from a list. Where the prose names one of the places the
        // plugin knows, it is stored as that key so the picker shows it; where it does not,
        // it is stored under "other" with the words the assistant used, which is exactly
        // what the free-text box is for.
        $place = trim((string)($definition['setting'] ?? ''));
        if ($place !== '' && $empty('setting')) {
            $matched = '';
            foreach (schema::settings_list() as $candidate) {
                $label = get_string_manager()->string_exists('setting:' . $candidate, 'mod_aibranchedscenario')
                    ? get_string('setting:' . $candidate, 'mod_aibranchedscenario') : '';
                if ($label !== '' && stripos($place, $label) !== false) {
                    $matched = $candidate;
                    break;
                }
            }
            if ($matched !== '') {
                $source['setting'] = $matched;
            } else {
                $source['setting'] = 'other';
                $source['settingother'] = \core_text::substr($place, 0, 120);
            }
        }

        // THE CAST, which is the part a teacher most wants to correct and the part that was
        // most completely hidden. The facilitator is one of the people, so it goes in the
        // list with the rest rather than being dropped for having its own key.
        if (($source['characters'] ?? []) === []) {
            $cast = [];
            $facilitator = (array)($definition['facilitator'] ?? []);
            foreach (array_merge([$facilitator], (array)($definition['characters'] ?? [])) as $person) {
                if (!is_array($person) || trim((string)($person['name'] ?? '')) === '') {
                    continue;
                }
                $cast[] = [
                    'name'       => (string)$person['name'],
                    'role'       => (string)($person['role'] ?? ''),
                    'trait'      => (string)($person['trait'] ?? ''),
                    'appearance' => (string)($person['appearance'] ?? ''),
                    'gender'     => (string)($person['gender'] ?? ''),
                ];
            }
            if ($cast !== []) {
                $source['characters'] = array_slice($cast, 0, 4);
            }
        }

        // How it opens, taken from the scenario's own first screen rather than invented.
        if ($empty('openingsituation')) {
            foreach ((array)($definition['nodes'] ?? []) as $node) {
                if ((string)($node['id'] ?? '') === (string)($definition['startnode'] ?? '')) {
                    $source['openingsituation'] = (string)($node['situation'] ?? '');
                    break;
                }
            }
        }

        scenario_manager::save_source($scenario, $source);
    }
}
