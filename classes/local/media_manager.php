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

use context_module;
use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\ai\image_prompt;
use mod_aibranchedscenario\local\ai\provider;
use stdClass;
use moodle_url;

/**
 * Creates, stores and serves the scene images and narration audio for a scenario.
 *
 * All media goes through the Moodle File API. Nothing is embedded as a data URI and
 * no remote URL is ever stored in the scenario definition.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_manager {
    /** @var string Working-copy scene images. */
    const AREA_SCENE = 'scene';

    /** @var string Working-copy narration audio. */
    const AREA_NARRATION = 'narration';

    /** @var string Published scene images, item id is the revision number. */
    const AREA_REVISION_SCENE = 'revisionscene';

    /** @var string Published narration audio, item id is the revision number. */
    const AREA_REVISION_NARRATION = 'revisionnarration';

    /** @var int Item ids for the opening lesson start here, clear of the node indexes. */
    const PRINCIPLE_ITEMID_BASE = 900;

    /** @var int Item id base for the debrief's own screens. */
    const DEBRIEF_ITEMID_BASE = 700;

    /** @var int Item ids for the pictures on the opening lesson slides start here. */
    const LESSON_ITEMID_BASE = 900;

    /** @var int Item id for the opening situation's clip. Above every node index, below the principles. */
    const OPENING_ITEMID = 800;

    /** @var string The key the opening situation's clip is stored and looked up under. */
    const OPENING_KEY = 'opening';

    /** @var int Largest media file accepted from the provider, in bytes. */
    const MAX_FILE_BYTES = 12582912;

    /** @var context_module Module context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param context_module $context Module context.
     */
    public function __construct(context_module $context) {
        $this->context = $context;
    }

    /**
     * Map a provider MIME type to a safe file extension.
     *
     * @param string $mimetype Declared MIME type.
     * @return string Extension without a dot, or an empty string when not allowed.
     */
    public static function extension_for(string $mimetype): string {
        $map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/mp3'  => 'mp3',
            'audio/wav'  => 'wav',
        ];
        return $map[strtolower($mimetype)] ?? '';
    }

    /**
     * Verify that the bytes really are the type the provider claimed.
     *
     * @param string $binary Raw file contents.
     * @param string $mimetype Declared MIME type.
     * @return bool
     */
    public static function signature_matches(string $binary, string $mimetype): bool {
        $mimetype = strtolower($mimetype);
        if (strlen($binary) < 12) {
            return false;
        }
        switch ($mimetype) {
            case 'image/png':
                return substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n";
            case 'image/jpeg':
                return substr($binary, 0, 3) === "\xFF\xD8\xFF";
            case 'image/webp':
                return substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP';
            case 'audio/wav':
                return substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WAVE';
            case 'audio/mpeg':
            case 'audio/mp3':
                return substr($binary, 0, 3) === 'ID3' || (ord($binary[0]) === 0xFF && (ord($binary[1]) & 0xE0) === 0xE0);
            default:
                return false;
        }
    }

    /**
     * Store one generated media file, replacing any existing file for that key.
     *
     * @param string $filearea File area name.
     * @param int $itemid Item id.
     * @param string $key Logical key, for example a node id.
     * @param string $binary Raw file contents.
     * @param string $mimetype Declared MIME type.
     * @return string The stored file name.
     * @throws generation_exception when the file is rejected.
     */
    public function store(string $filearea, int $itemid, string $key, string $binary, string $mimetype): string {
        $extension = self::extension_for($mimetype);
        if ($extension === '') {
            throw new generation_exception('error:mediatype');
        }
        if (strlen($binary) > self::MAX_FILE_BYTES) {
            throw new generation_exception('error:mediatoolarge');
        }
        if (!self::signature_matches($binary, $mimetype)) {
            throw new generation_exception('error:mediasignature');
        }
        $key = preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
        if ($key === '') {
            throw new generation_exception('error:mediakey');
        }
        // The old set goes when the first of the new set is ready to take its place, so a
        // run that fails at the first request leaves the activity exactly as it found it.
        //
        // Cleared PER AREA, not both at once. Clearing both meant that regenerating with
        // images turned off deleted every scene image the activity already had - assets
        // that had been generated and paid for - on the first narration clip written. The
        // run then reported success, because with images off it had asked for none and made
        // none, and the next publish produced a scenario with no pictures. A run that is
        // not making pictures has no business deleting them.
        if (empty($this->cleared[$filearea])) {
            $fs = get_file_storage();
            $fs->delete_area_files($this->context->id, 'mod_aibranchedscenario', $filearea);
            $this->cleared[$filearea] = true;
        }
        $filename = $key . '.' . $extension;

        $fs = get_file_storage();

        // Replace the file being written, and nothing else.
        //
        // This deleted every file sharing the item id, and every asset belonging to one
        // node shares one - so each write destroyed the one before it. A node's scene was
        // deleted by its crisis variant; its narration was deleted by the speaker's line,
        // which was deleted by the first choice clip, which was deleted by the second. One
        // image and one narration survived per node, and the survivor was whichever was
        // written last: a choice clip. The node's own narration never survived at all.
        //
        // Measured on a seven-node scenario: fifteen narration clips generated and paid
        // for, seven files left, and not one of them the narration a learner hears when the
        // screen opens. It looked exactly like narration that had never been generated.
        //
        // The comment on the principle clips already said storing a file clears whatever
        // shares its item id - that was known, worked around for the principles by giving
        // each its own id, and left in place for everything else.
        $existing = $fs->get_file(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            $itemid,
            '/',
            $filename
        );
        if ($existing) {
            $existing->delete();
        }

        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_aibranchedscenario',
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $binary);

        return $filename;
    }

    /** @var string[] Why each asset that failed, failed. Reasons only, never content. */
    protected $failures = [];

    /** @var bool[] Which working areas have been cleared for this run, keyed by area. */
    protected $cleared = [];

    /**
     * Record why one asset could not be made.
     *
     * Every one of these used to go to debugging() at DEVELOPER level, which writes
     * nothing at all on a production site. So a run in which the service refused every
     * single request finished "successfully", reported zero of fourteen, and left no
     * trace anywhere a site owner would ever look. The scenario simply had no pictures
     * and no voice, and nothing explained why.
     *
     * @param string $code A generation_exception error code. Never a message, never content.
     * @return void
     */
    protected function note_failure(string $code): void {
        $code = clean_param($code, PARAM_ALPHANUMEXT);
        if ($code !== '' && !in_array($code, $this->failures, true)) {
            $this->failures[] = $code;
        }
    }

    /**
     * The distinct reasons this run could not make what it was asked for.
     *
     * @return string[]
     */
    public function failures(): array {
        return $this->failures;
    }


    /**
     * Generate every scene image and narration clip a definition calls for.
     *
     * This loop used to live inside the generation task, which is why a scenario brought
     * in through the import box arrived with no pictures at all: the route a teacher
     * takes when they drafted the scenario elsewhere, or have no credits for generation
     * but plenty for images. Both routes now run the same loop.
     *
     * Individual failures are left to the per-item methods, which record them and carry
     * on. A scenario with seven of its eight pictures is worth more than none.
     *
     * @param provider $provider Generation provider.
     * @param \stdClass $scenario Activity instance.
     * @param array $definition Validated definition.
     * @return array Keys: images, imageswanted, narrations, narrationswanted. The
     *               "wanted" counts are what the definition called for; the others are
     *               what was actually produced, so a partial run is visible rather than
     *               looking identical to a complete one.
     */
    public function generate_for_definition(provider $provider, \stdClass $scenario, array $definition): array {
        $wantsimages = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $wantsaudio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');
        $blank = ['images' => 0, 'imageswanted' => 0, 'narrations' => 0, 'narrationswanted' => 0];
        if (!$wantsimages && !$wantsaudio) {
            return $blank;
        }

        // Clearing used to happen here, before a single request had been made. A run in
        // which the service refused everything therefore deleted the pictures and the
        // narration that were already there and put nothing in their place - so trying
        // again to fix an activity made it worse. The old set is cleared at the moment the
        // first new asset is ready to replace it, and not before.
        $this->cleared = [];

        $source = scenario_manager::get_source($scenario);
        $style = $source['imagestyle'] ?? 'cinematic';
        $voice = self::configured_voice('narrator');
        $counts = $blank;

        // The opening lesson is taught before the scenario starts, and is narrated for
        // the same reason every other screen is: a learner who is listening rather than
        // skim-reading arrives at the first decision knowing what they are being asked to
        // do. The principles are few - never more than eight - so this is a small bill.
        // The opening situation is the first screen a learner sees and the only one that was
        // never narrated. It is not a node, so the loop below never reached it, and the
        // deck slide had no audio attribute to play one from even if it had. A learner who
        // turned narration on was met with silence, then heard every screen after it -
        // which reads as the narration being broken rather than as one screen missing.
        if ($wantsaudio) {
            $counts['narrationswanted']++;
            if ($this->generate_opening_narration($provider, $definition, $scenario->scenariolang, $voice)) {
                $counts['narrations']++;
            }
        }

        if ($wantsaudio) {
            foreach ((array)($definition['principles'] ?? []) as $position => $principle) {
                $counts['narrationswanted']++;
                $narrated = $this->generate_principle_narration(
                    $provider,
                    $principle,
                    $scenario->scenariolang,
                    $voice,
                    $position
                );
                if ($narrated) {
                    $counts['narrations']++;
                }
            }
        }

        $index = 0;
        // Guarded. The paste route is a second entry point into this method and a
        // definition that skipped the validator would fatal here under PHP 8 rather than
        // simply making no media.
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ($wantsimages) {
                // Endings are given a frame too. The closing image is the one a learner
                // is left looking at while they read what their decisions came to.
                $counts['imageswanted']++;
                if ($this->generate_scene($provider, $definition, $node, $style, $index)) {
                    $counts['images']++;
                }
                if (!empty($node['crisisvariant']['situation'])) {
                    $counts['imageswanted']++;
                    if ($this->generate_scene($provider, $definition, $node, $style, $index, true)) {
                        $counts['images']++;
                    }
                }
            }
            if (($node['type'] ?? '') === 'outcome') {
                // The ending was the one screen in the scenario deliberately left silent:
                // narration was skipped for outcome nodes, so a learner listening the whole
                // way through arrived at the result of every decision they had made and
                // heard nothing. It is the screen the scenario exists to deliver.
                if ($wantsaudio) {
                    $counts['narrationswanted']++;
                    if ($this->generate_narration($provider, $node, $scenario->scenariolang, $voice, $index)) {
                        $counts['narrations']++;
                    }
                }
                $index++;
                continue;
            }
            if ($wantsaudio) {
                $counts['narrationswanted']++;
                if ($this->generate_narration($provider, $node, $scenario->scenariolang, $voice, $index)) {
                    $counts['narrations']++;
                }
                // A line is only worth its own clip when it is said by somebody with a
                // voice of their own. An unattributed line would be read by the narrator
                // anyway, so it stays inside the narrator's clip and costs nothing extra.
                if (self::has_own_voice($node)) {
                    $counts['narrationswanted']++;
                    if ($this->generate_speech_line($provider, $node, $scenario->scenariolang, $index)) {
                        $counts['narrations']++;
                    }
                }
                // The consequence screen is half of what a learner reads, and narrating
                // only the decisions made the voice appear to cut out every second screen.
                // Each branch is narrated separately because which one is heard is not
                // known until the learner chooses. Choice ids are unique across the
                // definition, so they sit in the same area as the node clips.
                // A single-choice beat's consequence is filler - "Continue to the next
                // decision point" - and is never worth a clip. Every branch of a real
                // decision is recorded, because the consequence screen is half of what a
                // learner reads and silence there reads as the narration being broken.
                $nodechoices = (array)($node['choices'] ?? []);
                $branches = count($nodechoices) > 1 ? $nodechoices : [];
                foreach ($branches as $choice) {
                    $counts['narrationswanted']++;
                    if (
                        $this->generate_choice_narration(
                            $provider,
                            $choice,
                            $scenario->scenariolang,
                            $voice,
                            $index
                        )
                    ) {
                        $counts['narrations']++;
                    }
                    // The same choice read a second time, as the debrief records it.
                    //
                    // The record page reuses the consequence clip, which reads what followed
                    // and why it mattered - and never says WHICH decision it followed from.
                    // A learner listening to their own record therefore heard five outcomes
                    // with no decisions attached to them, while the heading and the choice
                    // they actually took sat on screen unread. The card is read whole.
                    $counts['narrationswanted']++;
                    if (
                        $this->generate_record_narration(
                            $provider,
                            $node,
                            $choice,
                            $scenario->scenariolang,
                            $voice,
                            $index
                        )
                    ) {
                        $counts['narrations']++;
                    }
                }
            }
            $index++;
        }

        // The debrief was read, not listened to - a decision taken in the player and never
        // stated anywhere a teacher could see it. A learner who turned narration on heard
        // every screen of the scenario and then nothing at all for the half of the product
        // that explains what just happened. These screens are the same for every attempt,
        // so one clip each covers them.
        if ($wantsaudio) {
            $debrief = (array)($definition['debrief'] ?? []);
            // The three list pages are read one item at a time, so each item has a clip of
            // its own rather than the page having a single recording of the whole list. The
            // page can then pace itself: a card arrives, the picture behind it changes, the
            // clip for THAT item plays with the card marked, and the next arrives when it
            // finishes. One clip for five items can only be played at a list already
            // entirely on screen, which is a wall of text with a voice over it.
            $scripted = [
                'lesson'   => array_values((array)($debrief['whatmattered'] ?? [])),
                'critical' => array_values((array)($debrief['criticaldecisions'] ?? [])),
                'practice' => array_values((array)($debrief['practice'] ?? [])),
                // A takeaway is a heading and a body, so the clip reads both: the heading
                // alone is a label, and the body alone is advice with nothing to hang it on.
                'takeaway' => array_values(array_map(
                    static function ($takeaway) {
                        return trim(trim((string)($takeaway['heading'] ?? ''), " .") . '. '
                            . (string)($takeaway['body'] ?? ''));
                    },
                    (array)($definition['takeaways'] ?? [])
                )),
            ];
            $bucket = 0;
            foreach ($scripted as $name => $items) {
                $bucket++;
                foreach ($items as $position => $item) {
                    $text = trim((string)$item);
                    if ($text === '') {
                        continue;
                    }
                    $counts['narrationswanted']++;
                    $made = $this->generate_section_narration(
                        $provider,
                        $text,
                        $scenario->scenariolang,
                        $voice,
                        self::DEBRIEF_ITEMID_BASE + ($bucket * 20) + $position,
                        'debrief_' . $name . '_' . $position
                    );
                    if ($made) {
                        $counts['narrations']++;
                    }
                }
            }

            $sections = [
                'whatmattered' => self::lines_text($debrief['whatmattered'] ?? []),
                'practice'     => self::lines_text($debrief['practice'] ?? []),
                'takeaways'    => self::takeaways_text($definition['takeaways'] ?? []),
            ];
            $slot = 0;
            foreach ($sections as $name => $text) {
                $slot++;
                if (trim($text) === '') {
                    continue;
                }
                $counts['narrationswanted']++;
                $made = $this->generate_section_narration(
                    $provider,
                    $text,
                    $scenario->scenariolang,
                    $voice,
                    self::DEBRIEF_ITEMID_BASE + $slot,
                    'debrief_' . $name
                );
                if ($made) {
                    $counts['narrations']++;
                }
            }
        }

        // THE LESSON SLIDES GET PICTURES OF THEIR OWN.
        //
        // They had none. The player took the Nth decision node's photograph and put it
        // beside the Nth principle - principle one got node one's picture, principle two
        // got node two's, wrapping round when it ran out. So a learner met a photograph of
        // a scene they had not reached yet, next to words it had nothing to do with, and
        // then met the same photograph again a minute later when they actually got there.
        //
        // These are the first screens in the activity and they are the ones teaching the
        // principle the whole scenario is built on. A picture that does not belong to the
        // words beside it is worse than no picture: it is something else for the learner to
        // reconcile at the exact moment they are being asked to learn the rule.
        //
        // Briefed from the principle's own text, through the same builder every other
        // frame uses, exactly as the debrief pages are.
        if ($wantsimages) {
            $slot = 0;
            foreach (array_values((array)($definition['principles'] ?? [])) as $principle) {
                $slot++;
                if (!is_array($principle)) {
                    continue;
                }
                $text = trim(implode(' ', array_filter([
                    (string)($principle['summary'] ?? ''),
                    (string)($principle['example'] ?? ''),
                ])));
                if ($text === '') {
                    continue;
                }
                $counts['imageswanted']++;
                $made = $this->generate_scene(
                    $provider,
                    $definition,
                    [
                        'id'        => 'lesson_' . self::principle_key($principle, $slot),
                        'title'     => (string)($principle['title'] ?? ''),
                        'situation' => $text,
                    ],
                    $style,
                    self::LESSON_ITEMID_BASE + $slot
                );
                if ($made) {
                    $counts['images']++;
                }
            }
        }

        // Every page of the debrief used to redraw the SAME picture - whichever frame the
        // scenario opened on - so the lessons, the practice points and the takeaways were
        // all illustrated by a photograph of a room nobody was talking about any more. A
        // picture that does not reflect the words beside it is decoration, and the honest
        // fix is not to remove it but to draw the right one: each of these pages gets a
        // frame briefed from its own text, through the same prompt builder every scene
        // uses, so it sits in the scenario's own world rather than beside it.
        if ($wantsimages) {
            $debrief = (array)($definition['debrief'] ?? []);
            $pages = [
                'whatmattered' => [
                    get_string('whatmattered', 'mod_aibranchedscenario'),
                    self::lines_text($debrief['whatmattered'] ?? []),
                ],
                'criticaldecisions' => [
                    get_string('decisionsthatchanged', 'mod_aibranchedscenario'),
                    self::lines_text($debrief['criticaldecisions'] ?? []),
                ],
                'practice' => [
                    get_string('applyitinpractice', 'mod_aibranchedscenario'),
                    self::lines_text($debrief['practice'] ?? []),
                ],
                'takeaways' => [
                    get_string('takeaways', 'mod_aibranchedscenario'),
                    self::takeaways_text($definition['takeaways'] ?? []),
                ],
            ];
            $slot = 0;
            foreach ($pages as $name => $page) {
                $slot++;
                [$title, $text] = $page;
                if (trim($text) === '') {
                    continue;
                }
                $counts['imageswanted']++;
                $made = $this->generate_scene(
                    $provider,
                    $definition,
                    [
                        'id'        => 'debrief_' . $name,
                        'title'     => $title,
                        'situation' => $text,
                    ],
                    $style,
                    self::DEBRIEF_ITEMID_BASE + $slot
                );
                if ($made) {
                    $counts['images']++;
                }
            }
        }

        // The reasons travel with the counts, so both routes record them without either
        // having to know they exist. A count of zero against a want of fourteen is a
        // question; the same count with "insufficientcredits" beside it is an answer.
        $counts['refused'] = $this->failures;

        return $counts;
    }

    /**
     * Generate and store the scene image for one node.
     *
     * @param provider $provider Generation provider.
     * @param array $node Normalised node.
     * @param string $style Image style.
     * @param int $index Zero based node index, used as the file item id.
     * @return bool True when an image was stored.
     */
    public function generate_scene(
        provider $provider,
        array $definition,
        array $node,
        string $style,
        int $index,
        bool $crisis = false
    ): bool {
        $brief = image_prompt::for_node($definition, $node, $style, $crisis);
        if (trim($brief['prompt']) === '') {
            return false;
        }
        // The crisis variant of a scene is its own frame, stored beside the calm one,
        // so a learner who has driven the tension up sees the escalated moment rather
        // than the picture of the room before it went wrong.
        $key = $crisis ? $node['id'] . '_crisis' : $node['id'];
        try {
            $result = $provider->generate_image($brief['prompt'], $brief['style'], $brief['scenetitle']);
            $this->store(self::AREA_SCENE, $index, $key, $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Scene image generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Generate and store the narration for one node.
     *
     * @param provider $provider Generation provider.
     * @param array $node Normalised node.
     * @param string $language BCP-47 language code.
     * @param string $voice Voice identifier.
     * @param int $index Zero based node index, used as the file item id.
     * @return bool True when audio was stored.
     */
    public function generate_narration(
        provider $provider,
        array $node,
        string $language,
        string $voice,
        int $index
    ): bool {
        // The narrator reads the whole screen, in the order a person reads it: the scene's
        // title, the situation, the line to think about, and the question being put. It
        // used to read the situation alone, which meant a learner listening rather than
        // reading was never told what the scene was called and - worse - never heard the
        // question they were being asked to answer. The options themselves are separate
        // clips, so they are not repeated here.
        //
        // A line spoken by a named character is its own clip in that character's voice.
        // The narrator only reads it when nobody with a voice of their own says it, which
        // is why a scenario no longer sounds like one person reading a play aloud.
        $parts = [
            trim((string)($node['title'] ?? '')),
            trim((string)$node['situation']),
        ];
        if (!self::has_own_voice($node)) {
            $parts[] = trim((string)($node['facilitatorspeech'] ?? ''));
        }
        $parts[] = trim((string)($node['challenge'] ?? ''));
        $text = trim(implode("\n\n", array_filter($parts, static function ($part) {
            return $part !== '';
        })));
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(self::AREA_NARRATION, $index, $node['id'], $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Narration generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Whether this node's spoken line should be recorded separately from the narration.
     *
     * @param array $node Normalised node.
     * @return bool
     */
    public static function has_own_voice(array $node): bool {
        if (trim((string)($node['facilitatorspeech'] ?? '')) === '') {
            return false;
        }
        $gender = (string)($node['speakergender'] ?? '');
        return $gender === 'male' || $gender === 'female';
    }

    /**
     * The Moodle language code for a scenario language, for reading a label aloud in it.
     *
     * The three labels the narrator speaks - the two on a principle slide and the one on a
     * consequence - were fetched in the site language, so a French scenario had an English
     * word read out in the middle of French narration. They are fetched in the scenario's
     * language now. Where the site has no language pack for it Moodle falls back to English,
     * which is exactly where this started, so nothing is lost by trying.
     *
     * @param string $language BCP-47 code as stored on the activity, such as en-AU.
     * @return string A Moodle language code, such as en_au.
     */
    protected static function label_lang(string $language): string {
        $language = str_replace('-', '_', \core_text::strtolower(trim($language)));
        if ($language === '') {
            return 'en';
        }
        $installed = get_string_manager()->get_list_of_translations(true);
        if (isset($installed[$language])) {
            return $language;
        }
        $short = explode('_', $language)[0];
        return isset($installed[$short]) ? $short : 'en';
    }

    /**
     * Generate and store the narration for one slide of the opening lesson.
     *
     * @param provider $provider Generation provider.
     * @param array $principle Normalised principle.
     * @param string $language BCP-47 language code.
     * @param string $voice Voice identifier.
     * @param int $position Zero based position, used as the file item id.
     * @return bool True when audio was stored.
     */
    /**
     * Narrate the opening situation.
     *
     * Reads the role the learner is taking and the situation they are walking into, in that
     * order, because that is the order the screen presents them.
     *
     * @param provider $provider The generation provider.
     * @param array $definition The scenario definition.
     * @param string $language Scenario language.
     * @param string $voice Narrator voice.
     * @return bool Whether a clip was stored.
     */
    public function generate_opening_narration(
        provider $provider,
        array $definition,
        string $language,
        string $voice
    ): bool {
        $parts = [(string)($definition['role'] ?? ''), (string)($definition['hook'] ?? '')];
        $text = trim(implode("\n\n", array_filter($parts, static function ($part) {
            return trim($part) !== '';
        })));
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(
                self::AREA_NARRATION,
                self::OPENING_ITEMID,
                self::OPENING_KEY,
                $result['data'],
                $result['mimetype']
            );
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Opening situation narration skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Join a list of debrief lines into one piece of narration.
     *
     * @param array $lines Plain strings.
     * @return string
     */
    public static function lines_text(array $lines): string {
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $clean[] = rtrim($line, '.') . '.';
            }
        }
        return implode(' ', $clean);
    }

    /**
     * Join the takeaways into one piece of narration, heading then body.
     *
     * @param array $takeaways Each with a heading and a body.
     * @return string
     */
    public static function takeaways_text(array $takeaways): string {
        $parts = [];
        foreach ($takeaways as $takeaway) {
            $heading = trim((string)($takeaway['heading'] ?? ''));
            $body = trim((string)($takeaway['body'] ?? ''));
            $line = trim($heading === '' ? $body : rtrim($heading, '.') . '. ' . $body);
            if ($line !== '') {
                $parts[] = $line;
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Narrate one screen of the debrief.
     *
     * @param provider $provider The generation provider.
     * @param string $text What the screen says.
     * @param string $language Scenario language.
     * @param string $voice Narrator voice.
     * @param int $itemid Item id for this clip.
     * @param string $key The key it is stored and looked up under.
     * @return bool Whether a clip was stored.
     */
    public function generate_section_narration(
        provider $provider,
        string $text,
        string $language,
        string $voice,
        int $itemid,
        string $key
    ): bool {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(self::AREA_NARRATION, $itemid, $key, $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Debrief narration skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Narrate one principle of the opening lesson.
     *
     * @param provider $provider The generation provider.
     * @param array $principle The principle, with its title, summary, example and pitfall.
     * @param string $language Scenario language.
     * @param string $voice Narrator voice.
     * @param int $position Its position in the lesson, which gives the clip its item id.
     * @return bool Whether a clip was stored.
     */
    public function generate_principle_narration(
        provider $provider,
        array $principle,
        string $language,
        string $voice,
        int $position
    ): bool {
        // Read with the same labels the slide shows, or the example and the pitfall run
        // together into one paragraph and a listener cannot tell which is which.
        $lang = self::label_lang($language);
        $parts = [(string)($principle['title'] ?? '') . '.', (string)($principle['summary'] ?? '')];
        if (trim((string)($principle['example'] ?? '')) !== '') {
            $parts[] = get_string('lesson:saythis', 'mod_aibranchedscenario', null, $lang) . '. '
                . (string)$principle['example'];
        }
        if (trim((string)($principle['pitfall'] ?? '')) !== '') {
            $parts[] = get_string('lesson:notthis', 'mod_aibranchedscenario', null, $lang) . '. '
                . (string)$principle['pitfall'];
        }
        $text = trim(implode("\n\n", array_filter($parts, static function ($part) {
            return trim($part, ". \n") !== '';
        })));
        if (trim($text, ". \n") === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            // Each clip needs its own item id: storing a file clears whatever else shares
            // its item id, so a shared one would leave only the last principle recorded.
            $this->store(
                self::AREA_NARRATION,
                self::PRINCIPLE_ITEMID_BASE + $position,
                'lesson_' . self::principle_key($principle, $position + 1),
                $result['data'],
                $result['mimetype']
            );
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Opening lesson narration skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Generate and store the spoken line for whoever talks on this node.
     *
     * @param provider $provider Generation provider.
     * @param array $node Normalised node.
     * @param string $language BCP-47 language code.
     * @param int $index Zero based node index, used as the file item id.
     * @return bool True when audio was stored.
     */
    public function generate_speech_line(
        provider $provider,
        array $node,
        string $language,
        int $index
    ): bool {
        $text = trim((string)($node['facilitatorspeech'] ?? ''));
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(
                \core_text::substr($text, 0, 4500),
                self::voice_for_speaker($node),
                $language
            );
            $this->store(self::AREA_NARRATION, $index, $node['id'] . '_said', $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Character line generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Read one card of the decision record: the moment, the choice, and what followed.
     *
     * The record page used to play the consequence clip - which reads what followed and why
     * it mattered, and never says which decision it followed from. So a learner listening to
     * their own record heard five outcomes with no decisions attached to them, while the
     * heading and the choice they actually took sat on the screen unread.
     *
     * Its own clip, because a record card is a different thing from a consequence screen:
     * one is "here is what just happened", the other is "this is what you decided, and this
     * is what it did". The components are properties of the node and the choice, not of the
     * attempt, so it can be generated once per revision like everything else.
     *
     * @param provider $provider Generation provider.
     * @param array $node The node the choice belongs to.
     * @param array $choice Normalised choice.
     * @param string $language BCP-47 language code.
     * @param string $voice Voice identifier.
     * @param int $index Zero based node index, used as the file item id.
     * @return bool True when audio was stored.
     */
    public function generate_record_narration(
        provider $provider,
        array $node,
        array $choice,
        string $language,
        string $voice,
        int $index
    ): bool {
        $lang = self::label_lang($language);
        $parts = [];
        $title = trim((string)($node['title'] ?? ''));
        if ($title !== '') {
            $parts[] = rtrim($title, '.') . '.';
        }
        $took = trim((string)($choice['text'] ?? ''));
        if ($took !== '') {
            $parts[] = get_string('recordyouchose', 'mod_aibranchedscenario', null, $lang)
                . ' ' . $took;
        }
        $parts[] = trim((string)($choice['consequence'] ?? ''));
        if (trim((string)($choice['feedback'] ?? '')) !== '') {
            $parts[] = get_string('whythismattered', 'mod_aibranchedscenario', null, $lang)
                . '. ' . (string)$choice['feedback'];
        }
        $text = trim(implode("\n\n", array_filter($parts, static function ($part) {
            return trim((string)$part) !== '';
        })));
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(
                self::AREA_NARRATION,
                $index,
                'record_' . $choice['id'],
                $result['data'],
                $result['mimetype']
            );
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Decision record narration generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Generate and store the narration for what follows one choice.
     *
     * @param provider $provider Generation provider.
     * @param array $choice Normalised choice.
     * @param string $language BCP-47 language code.
     * @param string $voice Voice identifier.
     * @param int $index Zero based node index, used as the file item id.
     * @return bool True when audio was stored.
     */
    public function generate_choice_narration(
        provider $provider,
        array $choice,
        string $language,
        string $voice,
        int $index
    ): bool {
        // The consequence is the story and the feedback is the lesson drawn out of it, and
        // both are read out. Speaking only the first half was a decision made on the page
        // rather than for a listener: somebody with the narration on heard what happened
        // and never heard why it mattered, which is the half that teaches. The feedback is
        // introduced by the same words the screen puts above it, so the two do not run
        // together into one paragraph.
        $parts = [trim((string)($choice['consequence'] ?? ''))];
        if (trim((string)($choice['feedback'] ?? '')) !== '') {
            $label = get_string(
                'whythismattered',
                'mod_aibranchedscenario',
                null,
                self::label_lang($language)
            );
            $parts[] = $label . '. ' . (string)$choice['feedback'];
        }
        $text = trim(implode("\n\n", array_filter($parts, static function ($part) {
            return $part !== '';
        })));
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(self::AREA_NARRATION, $index, $choice['id'], $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Consequence narration generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * How many narration clips a definition will ask for.
     *
     * The paste route needed this to know what a run will cost BEFORE it starts, which is
     * what a quota check is. It mirrors the loop in generate_for_definition() exactly, and
     * mirroring is a thing that drifts - so the harness runs a real generation and asserts
     * this number equals the narrationswanted that run reports. If the two ever disagree,
     * the build fails rather than the billing.
     *
     * @param array $definition The validated scenario definition.
     * @return int
     */
    public static function count_narrations(array $definition): int {
        // The opening situation, then one for each principle taught before the scenario.
        $count = 1 + count((array)($definition['principles'] ?? []));

        foreach ((array)($definition['nodes'] ?? []) as $node) {
            $count++;
            if (($node['type'] ?? '') === 'outcome') {
                continue;
            }
            if (self::has_own_voice($node)) {
                $count++;
            }
            // A single-choice beat's consequence is filler and never gets a clip. Every
            // branch of a real decision gets two: the consequence, and the same choice read
            // again on the decision record.
            $choices = (array)($node['choices'] ?? []);
            if (count($choices) > 1) {
                $count += count($choices) * 2;
            }
        }

        $debrief = (array)($definition['debrief'] ?? []);
        // The three list pages and the takeaways are read an item at a time.
        $items = array_merge(
            array_values((array)($debrief['whatmattered'] ?? [])),
            array_values((array)($debrief['criticaldecisions'] ?? [])),
            array_values((array)($debrief['practice'] ?? [])),
            array_values((array)($definition['takeaways'] ?? []))
        );
        foreach ($items as $item) {
            $text = is_array($item)
                ? trim(trim((string)($item['heading'] ?? ''), " .") . '. ' . (string)($item['body'] ?? ''))
                : trim((string)$item);
            if ($text !== '') {
                $count++;
            }
        }

        // And one clip for each whole section that has anything in it.
        $sections = [
            self::lines_text($debrief['whatmattered'] ?? []),
            self::lines_text($debrief['practice'] ?? []),
            self::takeaways_text($definition['takeaways'] ?? []),
        ];
        foreach ($sections as $text) {
            if (trim($text) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The media key for one principle's picture and clip.
     *
     * The picture used its id with a fallback to its position and the clip used the id with
     * no fallback at all, so a principle without an id stored a picture under one name,
     * threw on the narration, and left the player looking for a third. One function, both
     * callers.
     *
     * @param array $principle The principle.
     * @param int $position Its one-based position, used when it has no id.
     * @return string
     */
    public static function principle_key(array $principle, int $position): string {
        $id = trim((string)($principle['id'] ?? ''));
        return $id !== '' ? $id : (string)$position;
    }

    /**
     * Copy the working-copy media into the immutable areas for a published revision.
     *
     * Returns the number of files that reached the revision, because that is the only
     * count that means anything to a learner. It used to return nothing, so the one step
     * that decides whether a picture is ever seen was also the one step that reported
     * nothing about itself.
     *
     * @param int $revisionnumber The revision number being published.
     * @return int Files copied into the revision areas.
     */
    public function publish_media(int $revisionnumber): int {
        $fs = get_file_storage();
        $copied = 0;
        $pairs = [
            self::AREA_SCENE     => self::AREA_REVISION_SCENE,
            self::AREA_NARRATION => self::AREA_REVISION_NARRATION,
        ];
        foreach ($pairs as $from => $to) {
            $fs->delete_area_files($this->context->id, 'mod_aibranchedscenario', $to, $revisionnumber);
            $files = $fs->get_area_files(
                $this->context->id,
                'mod_aibranchedscenario',
                $from,
                false,
                'itemid, filepath, filename',
                false
            );
            // A clash here used to throw, and the throw was the worst part of it.
            //
            // The working areas are keyed by node position, so two files can legitimately
            // share a name at different positions; this copy flattens them onto one item
            // id. The validator now stops that being created, but scenarios published
            // before it did still hold the clash, and on those a raw exception took out
            // the teacher's publish and - on the import route - escaped the ad-hoc task,
            // which Moodle retried forever, regenerating and re-billing the whole set on
            // every attempt.
            //
            // A file that cannot be copied is skipped and left out of the count, so the
            // caller's "did this reach the learner" check reports it instead. Losing one
            // clip is a fault worth reporting. Looping on a paid API is not a fault, it is
            // a bill.
            foreach ($files as $file) {
                $target = [
                    'contextid' => $this->context->id,
                    'component' => 'mod_aibranchedscenario',
                    'filearea'  => $to,
                    'itemid'    => $revisionnumber,
                    'filepath'  => '/',
                    'filename'  => $file->get_filename(),
                ];
                // Not reported through debugging(): at DEVELOPER level that writes nothing
                // on a production site, which is how media failures vanished for days. The
                // count this method returns is the report, and the caller turns a shortfall
                // into a recorded job failure.
                $exists = $fs->file_exists(
                    $this->context->id,
                    'mod_aibranchedscenario',
                    $to,
                    $revisionnumber,
                    '/',
                    $file->get_filename()
                );
                if ($exists) {
                    continue;
                }
                $fs->create_file_from_storedfile($target, $file);
                $copied++;
            }
        }
        return $copied;
    }

    /**
     * Build a map of node id to media URL for a published revision.
     *
     * @param string $filearea Published file area.
     * @param int $revisionnumber Revision number.
     * @return array Node id => URL string.
     */
    public function urls_for_revision(string $filearea, int $revisionnumber): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            $revisionnumber,
            'filename',
            false
        );
        $out = [];
        foreach ($files as $file) {
            $name = $file->get_filename();
            $nodeid = pathinfo($name, PATHINFO_FILENAME);
            $out[$nodeid] = moodle_url::make_pluginfile_url(
                $this->context->id,
                'mod_aibranchedscenario',
                $filearea,
                $revisionnumber,
                '/',
                $name
            )->out(false);
        }
        return $out;
    }

    /**
     * URLs for the working copy's media, keyed by node id.
     *
     * The working areas are itemised by node index rather than by revision number, so
     * this walks them and keys the result the way the draft review page needs it.
     *
     * @param string $filearea Working file area.
     * @return array Node id to URL.
     */
    public function urls_for_working(string $filearea): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            false,
            'filename',
            false
        );
        $out = [];
        foreach ($files as $file) {
            $name = $file->get_filename();
            $nodeid = pathinfo($name, PATHINFO_FILENAME);
            $out[$nodeid] = moodle_url::make_pluginfile_url(
                $this->context->id,
                'mod_aibranchedscenario',
                $filearea,
                $file->get_itemid(),
                '/',
                $name
            )->out(false);
        }
        return $out;
    }

    /**
     * Remove all working-copy media, used when a scenario is regenerated.
     *
     * @return void
     */
    public function clear_working_media(): void {
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_aibranchedscenario', self::AREA_SCENE);
        $fs->delete_area_files($this->context->id, 'mod_aibranchedscenario', self::AREA_NARRATION);
    }

    /**
     * The voices the service offers, and which kind of part each one suits.
     *
     * The list is fixed here rather than fetched, because a site administrator has to be
     * able to choose one while the service is unreachable, and because a voice that has
     * been removed upstream should fall back to a known good name rather than to silence.
     *
     * @return array Voice identifier mapped to 'female', 'male' or 'neutral'.
     */
    public static function voices(): array {
        return [
            'Aoede'  => 'female',
            'Kore'   => 'female',
            'Leda'   => 'female',
            'Zephyr' => 'female',
            'Puck'   => 'male',
            'Charon' => 'male',
            'Fenrir' => 'male',
            'Orus'   => 'male',
        ];
    }

    /**
     * The configured voice for one part, validated against a safe shape.
     *
     * @param string $role narrator, male or female.
     * @return string
     */
    public static function configured_voice(string $role = 'narrator'): string {
        $defaults = ['narrator' => 'Aoede', 'male' => 'Puck', 'female' => 'Kore'];
        if (!isset($defaults[$role])) {
            $role = 'narrator';
        }
        // The old single setting is still read for the narrator, so a site that set a
        // voice before there were three keeps the voice it chose.
        $voice = (string)get_config('mod_aibranchedscenario', $role . 'voice');
        if ($voice === '' && $role === 'narrator') {
            $voice = (string)get_config('mod_aibranchedscenario', 'defaultvoice');
        }
        if ($voice === '' || !preg_match('/^[A-Za-z][A-Za-z0-9\-]{1,32}$/', $voice)) {
            $voice = $defaults[$role];
        }
        return $voice;
    }

    /**
     * The voice a named speaker should be given.
     *
     * @param array $node Normalised node.
     * @return string Voice identifier.
     */
    public static function voice_for_speaker(array $node): string {
        $gender = (string)($node['speakergender'] ?? '');
        if ($gender === 'male' || $gender === 'female') {
            return self::configured_voice($gender);
        }
        // Nobody named, or somebody who is not in the cast: the narrator reads the line,
        // which is exactly what happened before characters had voices of their own.
        return self::configured_voice('narrator');
    }
}
