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

    /**
     * @var string[] The outcome signals that earn a reaction frame of their own.
     *
     * The number of reaction frames per decision node is decided HERE and nowhere else, so
     * trimming the picture bill is one edit rather than a hunt. All three is the film
     * version: the consequence cuts to the face of whoever the decision landed on, and a
     * relieved face, an unresolved one and a face wearing the cost are three different
     * pictures. Reducing this to ['negative'] would keep the cut away from the decision -
     * still a change of image - at a third of the cost.
     */
    const REACTION_SIGNALS = ['positive', 'neutral', 'negative'];

    /** @var int Largest media file accepted from the provider, in bytes. */
    const MAX_FILE_BYTES = 12582912;

    /** @var context_module Module context. */
    protected $context;

    /**
     * @var array|null Image keys this run is limited to, or null for every one.
     *
     * Set only by generate_missing_images(). Null means an ordinary full run.
     */
    protected $onlyimages = null;

    /** @var int Which rung of the ladder this manager's files belong to. */
    protected $tier;

    /**
     * How far apart the rungs sit in a file area's item ids.
     *
     * A REVISION NUMBER IS NO LONGER UNIQUE WITHIN AN ACTIVITY.
     *
     * Media used to be itemised by revision number, which was safe while an activity held
     * one scenario. It holds three now and revision numbers run per rung, so the
     * intermediate scenario's first publish is revision 1 and so is the foundation
     * scenario's. Left alone, publishing the second rung would delete the first rung's
     * pictures and put its own in their place - every learner part-way through the
     * foundation scenario would have been looking at the wrong scenario's photographs,
     * and the originals would be gone.
     *
     * The rung is folded into the item id instead. The arithmetic is deliberately chosen
     * so that rung one is unchanged: an activity that existed before the ladder keeps
     * every file exactly where it already is, and nothing has to be migrated. A hundred
     * thousand revisions of one rung is a ceiling nobody will meet.
     */
    const TIER_SPAN = 100000;

    /**
     * Constructor.
     *
     * @param context_module $context Module context.
     * @param int $tier Which rung of the ladder these files belong to.
     */
    public function __construct(context_module $context, int $tier = 1) {
        $this->context = $context;
        $this->tier = max(1, min(schema::TIERS, $tier));
    }

    /**
     * The item id this rung's copy of an item is stored under.
     *
     * @param int $item Revision number, or node index in a working area.
     * @return int
     */
    protected function slot(int $item): int {
        return ($this->tier - 1) * self::TIER_SPAN + $item;
    }

    /**
     * Does this item id belong to this rung?
     *
     * @param int $itemid A stored file's item id.
     * @return bool
     */
    protected function mine(int $itemid): bool {
        $base = ($this->tier - 1) * self::TIER_SPAN;
        return $itemid >= $base && $itemid < $base + self::TIER_SPAN;
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
        //
        // Cleared PER RUNG as well. The working areas are shared by all three scenarios in
        // an activity, so wiping the whole area would throw away the other two rungs'
        // draft artwork every time a teacher generated one of them.
        if (empty($this->cleared[$filearea])) {
            $fs = get_file_storage();
            foreach (
                $fs->get_area_files(
                    $this->context->id,
                    'mod_aibranchedscenario',
                    $filearea,
                    false,
                    'itemid, filepath, filename',
                    false
                ) as $old
            ) {
                if ($this->mine((int)$old->get_itemid())) {
                    $old->delete();
                }
            }
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
        $slot = $this->slot($itemid);
        $existing = $fs->get_file(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            $slot,
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
            'itemid'    => $slot,
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
            if (
                $this->generate_opening_narration(
                    $provider,
                    $definition,
                    $scenario->scenariolang,
                    $voice,
                    (int)($scenario->bandgreen ?? 67)
                )
            ) {
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
                //
                // THE COUNTER IS GATED ON THE SAME ANSWER AS THE WORK.
                //
                // Every "wanted" increment in this walk used to happen before the gate, so a
                // top-up that made four pictures reported twenty-seven wanted and four made
                // - which the job record then filed as a failure, and the teacher was told
                // their four-picture run had fallen twenty-three short. Found by running the
                // top-up end to end rather than by reading it. A count of what was asked for
                // has to be a count of what was asked for.
                if ($this->wanted((string)$node['id'])) {
                    $counts['imageswanted']++;
                }
                if ($this->generate_scene($provider, $definition, $node, $style, $index)) {
                    $counts['images']++;
                }
                if (!empty($node['crisisvariant']['situation'])) {
                    if ($this->wanted($node['id'] . '_crisis')) {
                            $counts['imageswanted']++;
                    }
                    if ($this->generate_scene($provider, $definition, $node, $style, $index, true)) {
                        $counts['images']++;
                    }
                }

                // THE REACTION SHOT.
                //
                // The consequence screen used to show the decision's own photograph again.
                // The reasoning written into the player was that the room has not changed
                // because the learner chose something in it - which is true, and beside the
                // point. The PEOPLE have changed, and the people are what the screen is
                // about. A film does not hold on the wide shot while someone reacts; it
                // cuts to the face.
                //
                // One frame per outcome signal the node actually uses, not one per choice:
                // three choices usually resolve to positive, neutral and negative, and what
                // a learner needs to see is the reaction to THAT rather than to the exact
                // wording they picked.
                foreach (self::signals_used($node) as $signal) {
                    if ($this->wanted(self::reaction_key((string)$node['id'], $signal))) {
                            $counts['imageswanted']++;
                    }
                    if ($this->generate_reaction($provider, $definition, $node, $style, $index, $signal)) {
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
        // that explains what just happened.
        //
        // It used to be four families of clips for four pages of closing advice. Those
        // pages are gone. What the debrief reads now is the outcome note on each option of
        // each decision - the paragraph saying where that option leads and why - so the
        // clip for an option is keyed to the option itself and the slide can read them in
        // the order it shows them.
        if ($wantsaudio) {
            $slot = 0;
            foreach ((array)($definition['nodes'] ?? []) as $node) {
                if (($node['type'] ?? '') !== 'decision') {
                    continue;
                }
                foreach ((array)($node['choices'] ?? []) as $choice) {
                    $slot++;
                    $text = trim((string)($choice['outcomenote'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $counts['narrationswanted']++;
                    $made = $this->generate_section_narration(
                        $provider,
                        $text,
                        $scenario->scenariolang,
                        $voice,
                        self::DEBRIEF_ITEMID_BASE + $slot,
                        self::outcome_key((string)($choice['id'] ?? ''))
                    );
                    if ($made) {
                        $counts['narrations']++;
                    }
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
                if ($this->wanted('lesson_' . self::principle_key($principle, $slot))) {
                    $counts['imageswanted']++;
                }
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

        // THE DEBRIEF DRAWS NOTHING OF ITS OWN ANY MORE.
        //
        // It used to commission a frame for every entry on four pages of closing advice -
        // lessons learnt, critical decisions, practice points, takeaways - which at the
        // contract's ceiling was thirty-two pictures for the last two minutes of the
        // activity, all of them illustrating a sentence rather than a moment.
        //
        // Those pages are gone. Each decision now gets one slide, and the picture on it is
        // the reaction frame already drawn for the option the learner actually took - a
        // face that has just received the consequence, which is the most relevant image the
        // scenario owns for that slide and one it has already paid for. Nothing new is
        // commissioned, and thirty-two frames come off the bill.

        // THE OPENING GETS AN ESTABLISHING FRAME OF ITS OWN.
        //
        // It used to show the start node's photograph. The start node IS the first
        // decision, so a learner's first three screens - the opening situation, decision
        // one, and the consequence of decision one - were the same photograph three times
        // before they had made a second choice. Three identical frames in the first ten
        // seconds is the product introducing itself as cheap.
        //
        // This frame is the place before anyone has done anything, briefed from the
        // scenario's own setting and hook rather than from a node.
        if ($wantsimages) {
            $hook = trim((string)($definition['hook'] ?? ''));
            $setting = trim((string)($definition['setting'] ?? ''));
            if ($hook !== '' || $setting !== '') {
                if ($this->wanted(self::OPENING_KEY)) {
                    $counts['imageswanted']++;
                }
                $made = $this->generate_scene(
                    $provider,
                    $definition,
                    [
                        'id'        => self::OPENING_KEY,
                        'title'     => trim((string)($definition['title'] ?? '')),
                        'situation' => trim($setting . ($setting !== '' ? '. ' : '') . $hook),
                    ],
                    $style,
                    self::OPENING_ITEMID
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
     * The key under which one option's outcome note is recorded.
     *
     * THE KEY CONTRACT.
     *
     * Until v1.81.0 images were filenames, and a filename is not a link. A key named a
     * screen rather than an idea, so a picture could not follow its idea from the slide
     * that taught it to the page that looked back at it - and nothing knew how many
     * pictures a scenario was supposed to have. "You are four pictures short" was an
     * unanswerable question. Everything is keyed by what it is about now:
     *
     *   opening                  the place, before anyone has done anything
     *   lesson_<principlekey>    where the idea is taught
     *   <nodeid>                 the decision that tests it
     *   <nodeid>_after_<signal>  the reaction, per outcome the node can produce
     *   <nodeid>_crisis          the escalated variant
     *   <choiceid>               the consequence, read aloud
     *   record_<choiceid>        the same choice read again on its record
     *   outcome_<choiceid>       the outcome note, read aloud on the debrief slide
     *
     * The debrief draws no pictures of its own. Its slides show the reaction frame
     * belonging to the option the learner took, which the scenario has already paid for.
     *
     * @param string $choiceid The choice this note belongs to.
     * @return string
     */
    public static function outcome_key(string $choiceid): string {
        return 'outcome_' . $choiceid;
    }

    /**
     * Every image key a definition SHOULD have, and what each one illustrates.
     *
     * THE MAP.
     *
     * Until v1.81.0 images were filenames, and a filename is not a link. A key named a
     * screen rather than an idea, so a picture could not follow its idea from the slide
     * that taught it to the page that looked back at it - and nothing, anywhere, knew how
     * many pictures a scenario was supposed to have. "You are four pictures short" was an
     * unanswerable question.
     *
     * See outcome_key() above for the full key contract.
     *
     * @param array $definition A validated definition.
     * @return array Key to a short human description of what it illustrates.
     */
    public static function expected_image_map(array $definition): array {
        $map = [];

        if (
            trim((string)($definition['hook'] ?? '')) !== ''
                || trim((string)($definition['setting'] ?? '')) !== ''
        ) {
            $map[self::OPENING_KEY] = get_string('map:opening', 'mod_aibranchedscenario');
        }

        // One-based, like every other caller of principle_key(): the generator, the
        // narration and the player all pass position + 1. This passed a zero-based index,
        // which is masked today only because the validator always supplies a principle id
        // so the positional fallback is never reached - an unvalidated definition would
        // have made the map and the generator disagree on every lesson key, and no check
        // would have failed.
        $slot = 0;
        foreach (array_values((array)($definition['principles'] ?? [])) as $principle) {
            $slot++;
            $text = trim((string)($principle['summary'] ?? '') . ' ' . (string)($principle['example'] ?? ''));
            if ($text === '') {
                continue;
            }
            $map['lesson_' . self::principle_key($principle, $slot)] = get_string(
                'map:lesson',
                'mod_aibranchedscenario',
                trim((string)($principle['title'] ?? '')) ?: $slot
            );
        }

        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (!is_array($node) || trim((string)($node['id'] ?? '')) === '') {
                continue;
            }
            $id = (string)$node['id'];
            $title = trim((string)($node['title'] ?? '')) ?: $id;
            if (trim((string)($node['situation'] ?? '')) !== '') {
                $map[$id] = get_string('map:scene', 'mod_aibranchedscenario', $title);
            }
            if (!empty($node['crisisvariant']['situation'])) {
                $map[$id . '_crisis'] = get_string('map:crisis', 'mod_aibranchedscenario', $title);
            }
            // The signals_used() helper is the single answer to "which reactions does this node
            // earn": it already excludes a signal with nothing to brief a frame from, so
            // the map, the cost estimate and the run cannot disagree.
            foreach (self::signals_used($node) as $signal) {
                $map[self::reaction_key($id, $signal)] = get_string(
                    'map:reaction' . $signal,
                    'mod_aibranchedscenario',
                    $title
                );
            }
        }

        // No debrief entries. The debrief's five slides show the reaction frame of the
        // option the learner took, which is already in this map under its node, so a
        // scenario is never reported as short of a picture the debrief will not draw.
        return $map;
    }

    /**
     * Is this image key one this run is making?
     *
     * Ordinarily yes: a full run makes every frame the definition calls for. A top-up run
     * sets a list first, and then only the frames on that list are generated - which is what
     * makes "you are four pictures short" a four-picture job rather than a rerun of the
     * whole set at the whole price.
     *
     * The gate lives here, at the single point every image write passes through, rather than
     * in the walk. A second walk that decided for itself which frames to make would be a
     * second description of the map, and two descriptions drift the first time one is
     * edited - which is the fault this whole exercise exists to stop repeating.
     *
     * @param string $key The image key about to be generated.
     * @return bool True when this run should make it.
     */
    protected function wanted(string $key): bool {
        if ($this->onlyimages === null) {
            return true;
        }
        return isset($this->onlyimages[$key]);
    }

    /**
     * Generate only the pictures a published revision is missing, and bill only for those.
     *
     * THE OTHER HALF OF THE RECONCILIATION.
     *
     * reconcile_images() answers "which slots are unfilled". Until this existed, that answer
     * had nowhere to go: the only way to obtain a missing picture was
     * generate_for_definition(), which regenerates every frame and charges the full media
     * price. A teacher four pictures short paid for thirty.
     *
     * Three things make this safe to run against a live revision:
     *
     *   - the file area is NOT cleared, so the twenty-six pictures that are already there
     *     are untouched. `cleared` is pre-marked for exactly that reason;
     *   - narration is not touched at all, whatever the activity's settings say;
     *   - the gate above means a frame absent from the list is never even briefed, so a
     *     top-up cannot quietly redraw the set and change its look.
     *
     * Composition is deterministic - the same node always yields the same brief - so a
     * frame regenerated on its own belongs to the same set as the ones around it.
     *
     * @param provider $provider Generation provider.
     * @param \stdClass $scenario Activity instance.
     * @param array $definition The published definition.
     * @param string[] $keys The image keys to make, as reconcile_images() reported them.
     * @return array Keys: images, imageswanted, narrations, narrationswanted.
     */
    public function generate_missing_images(
        provider $provider,
        \stdClass $scenario,
        array $definition,
        array $keys
    ): array {
        $blank = ['images' => 0, 'imageswanted' => 0, 'narrations' => 0, 'narrationswanted' => 0];
        $keys = array_values(array_filter(array_map('strval', $keys), static function ($key) {
            return trim($key) !== '';
        }));
        if (!$keys) {
            return $blank;
        }

        // Only keys the map actually expects. A caller handing this a key the definition
        // has no slot for would otherwise generate a file nothing can ever show, and bill
        // for it - and the list arrives from a web service, so it is not the plugin's to
        // trust.
        $expected = self::expected_image_map($definition);
        $keys = array_values(array_intersect($keys, array_keys($expected)));
        if (!$keys) {
            return $blank;
        }

        $topup = clone $scenario;
        $topup->enableimages = 1;
        $topup->enableaudio = 0;

        $this->onlyimages = array_flip($keys);
        // Pre-marked as already cleared, so store() leaves every existing picture alone.
        // Without this the first top-up write would delete the whole area and a run meant
        // to add four pictures would end with four.
        $this->cleared = [self::AREA_SCENE => true, self::AREA_NARRATION => true];
        try {
            $counts = $this->generate_for_definition($provider, $topup, $definition);
        } finally {
            // Whatever happened, this manager goes back to being an ordinary one. A filter
            // left set would silently make the next full run produce four pictures.
            $this->onlyimages = null;
        }
        return $counts;
    }

    /**
     * Compare the pictures a revision SHOULD have against the ones it HAS.
     *
     * The thing that makes "regenerate the missing ones" a lookup rather than a rerun.
     * Before this existed there was no way to say a scenario was four pictures short,
     * because nothing knew how many there should have been: a missing picture and a slot
     * that never wanted one were indistinguishable, and the only remedy was to generate
     * the whole set again and pay for it again.
     *
     * Three answers, and the third is the one nobody was looking for:
     *
     *   missing  - expected by the map, not in the file area. Regenerate exactly these.
     *   orphaned - in the file area, not in the map. Left over from a definition that has
     *              since been edited; costs nothing but tells you the map moved.
     *   shared   - two keys resolving to the same stored file. This is the repetition
     *              Jamie kept reporting, and it is the one state that looks fine in every
     *              other check because every key resolves to a picture.
     *
     * @param array $definition A validated definition.
     * @param int $revisionnumber The published revision.
     * @return array Keys: missing (key => description), orphaned (string[]), shared (array).
     */
    public function reconcile_images(array $definition, int $revisionnumber): array {
        $expected = self::expected_image_map($definition);
        $have = $this->urls_for_revision(self::AREA_REVISION_SCENE, $revisionnumber);

        $missing = [];
        foreach ($expected as $key => $what) {
            if (!isset($have[$key]) || trim((string)$have[$key]) === '') {
                $missing[$key] = $what;
            }
        }

        $orphaned = array_values(array_diff(array_keys($have), array_keys($expected)));

        // Two keys pointing at one file. urls_for_revision keys by filename stem so this
        // cannot happen through it today - but the player and the debrief both fall back to
        // BORROWING another screen's frame when their own is missing, and that fallback is
        // exactly how three consecutive screens ended up showing one photograph. Asked here
        // so the answer exists rather than being inferred from a screenshot.
        $byurl = [];
        foreach ($have as $key => $url) {
            $url = trim((string)$url);
            if ($url === '') {
                continue;
            }
            $byurl[$url][] = $key;
        }
        $shared = [];
        foreach ($byurl as $keys) {
            if (count($keys) > 1) {
                $shared[] = $keys;
            }
        }

        return ['missing' => $missing, 'orphaned' => $orphaned, 'shared' => $shared];
    }

    /**
     * The outcome signals a node's choices actually lead to.
     *
     * A node with three choices that are all negative needs one reaction frame, not three,
     * and a node whose choices span all three needs all three. Asked of the node rather
     * than assumed, so the count is what the scenario earns rather than a flat multiplier
     * on the bill.
     *
     * @param array $node A normalised node.
     * @return string[] Distinct signals, in a stable order.
     */
    public static function signals_used(array $node): array {
        if (($node['type'] ?? '') !== 'decision') {
            return [];
        }
        $found = [];
        foreach ((array)($node['choices'] ?? []) as $choice) {
            $signal = (string)($choice['signal'] ?? '');
            if (!in_array($signal, self::REACTION_SIGNALS, true) || in_array($signal, $found, true)) {
                continue;
            }
            // A reaction frame is briefed FROM the consequence text, so a signal whose
            // choices say nothing about what happened cannot produce one. Found by
            // self-audit: this used to return the signal anyway, so a run would count a
            // frame it was about to decline to make, while count_images - which does check
            // the text - counted none. The estimate and the run would then disagree by one
            // on any node with a wordless branch, and the harness check that compares them
            // only passed because the fixture has consequence text everywhere.
            //
            // Asked once, here, so the map, the estimate and the run all read the same
            // answer rather than three functions each deciding for themselves.
            foreach ((array)($node['choices'] ?? []) as $sibling) {
                if (
                    (string)($sibling['signal'] ?? '') === $signal
                        && trim((string)($sibling['consequence'] ?? '')) !== ''
                ) {
                    $found[] = $signal;
                    break;
                }
            }
        }
        // A stable order, so the same node produces the same key set on every run and a
        // reconciliation pass can compare two generations without sorting first.
        return array_values(array_filter(
            self::REACTION_SIGNALS,
            static function ($signal) use ($found) {
                return in_array($signal, $found, true);
            }
        ));
    }

    /**
     * The key a node's reaction frame is stored under.
     *
     * @param string $nodeid The node.
     * @param string $signal positive, neutral or negative.
     * @return string
     */
    public static function reaction_key(string $nodeid, string $signal): string {
        return $nodeid . '_after_' . $signal;
    }

    /**
     * Generate and store one reaction frame for a node.
     *
     * @param provider $provider Generation provider.
     * @param array $definition The whole scenario.
     * @param array $node The decision node.
     * @param string $style Treatment.
     * @param int $index The node's item id.
     * @param string $signal positive, neutral or negative.
     * @return bool True when an image was stored.
     */
    public function generate_reaction(
        provider $provider,
        array $definition,
        array $node,
        string $style,
        int $index,
        string $signal
    ): bool {
        if (!$this->wanted(self::reaction_key((string)$node['id'], $signal))) {
            return false;
        }
        $brief = image_prompt::for_reaction($definition, $node, $style, $signal);
        if (trim($brief['prompt']) === '') {
            return false;
        }
        try {
            $result = $provider->generate_image($brief['prompt'], $brief['style'], $brief['scenetitle']);
            $this->store(
                self::AREA_SCENE,
                $index,
                self::reaction_key((string)$node['id'], $signal),
                $result['data'],
                $result['mimetype']
            );
            return true;
        } catch (generation_exception $e) {
            $this->note_failure((string)$e->errorcode);
            mtrace('Reaction image generation skipped: ' . $e->errorcode);
            return false;
        }
    }

    /**
     * Generate and store the scene image for one node.
     *
     * @param provider $provider Generation provider.
     * @param array $definition The whole scenario.
     * @param array $node Normalised node.
     * @param string $style Image style.
     * @param int $index Zero based node index, used as the file item id.
     * @param bool $crisis Whether this is the crisis variant.
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
        $key = $crisis ? $node['id'] . '_crisis' : $node['id'];
        if (!$this->wanted((string)$key)) {
            return false;
        }
        $brief = image_prompt::for_node($definition, $node, $style, $crisis);
        if (trim($brief['prompt']) === '') {
            return false;
        }
        // The crisis variant of a scene is its own frame, stored beside the calm one,
        // so a learner who has driven the tension up sees the escalated moment rather
        // than the picture of the room before it went wrong. The key is worked out above,
        // because the top-up gate has to know it before anything is generated.
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
        $text = self::narration_script($node);
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
     * Every word the narrator says on a decision screen, in the order a person reads them.
     *
     * A SEPARATE METHOD BECAUSE IT IS THE THING THAT GOES WRONG.
     *
     * Narration completeness has failed twice, silently, in ways no check could see: the
     * options were left out of the clip for a release, and the question being asked was left
     * out for longer than that. Both times the code that built the words was buried inside
     * the method that calls the speech provider, so the only way to test it was to read the
     * source with a regular expression - which tests that a line exists, not that the right
     * words come out. This can be called with a node and compared against what a learner
     * would hear.
     *
     * THE NARRATOR READS THE WHOLE SCREEN. The scene's title, the situation, the document in
     * the learner's hands, the question being put, AND THE OPTIONS. The options were once
     * left out on the grounds that they get clips of their own. They do - but those play on
     * the decision RECORD, after the choice has been made, so a learner listening to this
     * screen heard a question and then silence where the three answers should have been.
     * They had to stop listening and start reading at the one moment the product asks them
     * to decide something, which is a hole in the narration and an accessibility fault
     * besides. It costs nothing: more words in a clip that was already being made, not a new
     * clip.
     *
     * A line spoken by a named character is its own clip in that character's voice, so the
     * narrator only reads it when nobody with a voice of their own says it - which is why a
     * scenario no longer sounds like one person reading a play aloud.
     *
     * @param array $node Normalised node.
     * @return string The script, or empty when there is nothing to say.
     */
    public static function narration_script(array $node): string {
        // The narrator reads the whole screen, in the order a person reads it: the scene's
        // title, the situation, the line to think about, the question being put, AND THE
        // OPTIONS. It used to read the situation alone, which meant a learner listening
        // rather than reading was never told what the scene was called and - worse - never
        // heard the question they were being asked to answer.
        //
        // The options were left out on the grounds that they get clips of their own. They
        // do - but those play on the decision RECORD, after the choice has been made, so a
        // learner listening to this screen heard a question and then silence where the
        // three answers should have been. They had to stop listening and start reading at
        // the one moment the product asks them to decide something, which is a hole in the
        // narration and an accessibility fault besides.
        //
        // Costs nothing: it is more words in a clip that was already being made, not a new
        // clip. Hearing the chosen option again on the record is not a repeat - by then it
        // is the one they took, read back to them, which is the point of that screen.
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
        // THE DOCUMENT, READ OUT.
        //
        // Placed between the situation and the question because that is where it is on the
        // screen and where it happens in the room: you are told what is going on, you pick
        // the paper up, then somebody asks you what you want to do. A learner listening who
        // never heard the permit would be asked to judge a permit they were never shown,
        // which is the same hole the lettered options used to leave.
        //
        // Read straight, with no mention of which line is the wrong one. The flag is for
        // the debrief; saying it here would be the narrator answering the question.
        $artefact = (array)($node['artefact'] ?? []);
        if ($artefact) {
            $sheet = [trim((string)($artefact['title'] ?? ''))];
            $subtitle = trim((string)($artefact['subtitle'] ?? ''));
            if ($subtitle !== '') {
                $sheet[] = $subtitle;
            }
            foreach ((array)($artefact['fields'] ?? []) as $field) {
                $sheet[] = trim((string)$field['label']) . ': ' . trim((string)$field['value']);
            }
            foreach ((array)($artefact['lines'] ?? []) as $line) {
                $sheet[] = trim((string)$line);
            }
            $footer = trim((string)($artefact['footer'] ?? ''));
            if ($footer !== '') {
                $sheet[] = $footer;
            }
            $parts[] = implode('. ', array_filter($sheet));
        }
        $parts[] = trim((string)($node['challenge'] ?? ''));
        // Lettered the way the screen letters them, so somebody listening and somebody
        // reading are working from the same list and can talk to each other about it.
        $choices = (array)($node['choices'] ?? []);
        if (count($choices) > 1) {
            foreach (array_values($choices) as $position => $choice) {
                $text = trim((string)($choice['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $parts[] = chr(65 + $position) . '. ' . $text;
            }
        }
        return trim(implode("\n\n", array_filter($parts, static function ($part) {
            return $part !== '';
        })));
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
     * The aim of the scenario, in one sentence, from the teacher's own band.
     *
     * THE SPOKEN GOAL MUST AGREE WITH THE COLOURS.
     *
     * The scoreboard turns a reading green at the teacher's band - 67 by default - and tension
     * reads the other way up, green at 100 minus that. A goal pitched anywhere else would
     * contradict the screen: a learner on 70 would see green and still be short of the number
     * they had just been told. So the target is the band, not a number of its own, and a
     * teacher who moves the band moves the goal with it.
     *
     * Shown on the opening slide AND spoken by the narrator, from this one function, so the two
     * cannot drift apart.
     *
     * @param int $green Where a reading becomes good, 1-99.
     * @param string|null $lang Language to phrase it in, or null for the current one.
     * @return string
     */
    public static function opening_goal(int $green, ?string $lang = null): string {
        $green = max(1, min(99, $green));
        return get_string_manager()->get_string(
            'opening:goal',
            'mod_aibranchedscenario',
            (object)['high' => $green, 'low' => 100 - $green],
            $lang
        );
    }

    /**
     * Everything the narrator says over the opening slide.
     *
     * The role, the hook, then what the three readings are and what the learner is aiming
     * for. The explanation is spoken rather than printed: on screen it would be three more
     * lines under the hook, which is the clutter the opening was cleared of, and the legend in
     * the bar already carries it in text for anyone not listening. The goal is both spoken and
     * shown, because it is the one thing a learner has to carry into every decision.
     *
     * @param array $definition The scenario.
     * @param int $green The teacher's good band.
     * @param string $language Scenario language, which the narrator speaks.
     * @return string
     */
    public static function opening_script(array $definition, int $green, string $language): string {
        $lang = self::label_lang($language);
        $parts = [
            (string)($definition['role'] ?? ''),
            (string)($definition['hook'] ?? ''),
            get_string_manager()->get_string('opening:readings', 'mod_aibranchedscenario', null, $lang),
            self::opening_goal($green, $lang),
        ];
        return trim(implode("\n\n", array_filter(array_map('trim', $parts), static function ($part) {
            return $part !== '';
        })));
    }

    /**
     * Narrate the opening situation.
     *
     * Reads the role, the situation, what the three readings are and what the learner is
     * aiming for. See opening_script().
     *
     * @param provider $provider The generation provider.
     * @param array $definition The scenario definition.
     * @param string $language Scenario language.
     * @param string $voice Narrator voice.
     * @param int $green The teacher's good band, which sets the spoken goal.
     * @return bool Whether a clip was stored.
     */
    public function generate_opening_narration(
        provider $provider,
        array $definition,
        string $language,
        string $voice,
        int $green = 67
    ): bool {
        $text = self::opening_script($definition, $green, $language);
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

        // The debrief reads one clip per option: the outcome note saying where that option
        // leads. It used to be four lists of closing advice read an item at a time plus a
        // clip for each whole page, which was more recorded advice than recorded story.
        foreach ((array)($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'decision') {
                continue;
            }
            foreach ((array)($node['choices'] ?? []) as $choice) {
                if (trim((string)($choice['outcomenote'] ?? '')) !== '') {
                    $count++;
                }
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
     * Copy just the named pictures into a published revision, leaving the rest alone.
     *
     * publish_media() is a whole-set operation: it deletes the revision's area and refills
     * it from the working copy. That is right at publish time and wrong for a top-up, where
     * the working copy holds only the handful of frames just generated and the revision
     * holds the twenty-six that were already fine. Using it here would replace a revision
     * that was four pictures short with one that had four pictures.
     *
     * So this copies by name, replaces rather than deletes, and touches nothing it was not
     * asked about.
     *
     * @param int $revisionnumber The revision being topped up.
     * @param string[] $keys The image keys just generated.
     * @return int Files that reached the revision.
     */
    public function publish_image_keys(int $revisionnumber, array $keys): int {
        $wanted = array_flip(array_map('strval', $keys));
        $fs = get_file_storage();
        $copied = 0;
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_aibranchedscenario',
            self::AREA_SCENE,
            false,
            'itemid, filepath, filename',
            false
        );
        $slot = $this->slot($revisionnumber);
        foreach ($files as $file) {
            $name = $file->get_filename();
            if (!isset($wanted[pathinfo($name, PATHINFO_FILENAME)]) || !$this->mine((int)$file->get_itemid())) {
                continue;
            }
            $existing = $fs->get_file(
                $this->context->id,
                'mod_aibranchedscenario',
                self::AREA_REVISION_SCENE,
                $slot,
                '/',
                $name
            );
            if ($existing) {
                // A top-up of a key that somehow already had a file replaces it rather than
                // failing: the caller asked for this frame, and refusing here would leave a
                // teacher pressing the button with nothing changing and no reason given.
                $existing->delete();
            }
            $fs->create_file_from_storedfile([
                'contextid' => $this->context->id,
                'component' => 'mod_aibranchedscenario',
                'filearea'  => self::AREA_REVISION_SCENE,
                'itemid'    => $slot,
                'filepath'  => '/',
                'filename'  => $name,
            ], $file);
            $copied++;
        }
        return $copied;
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
            $fs->delete_area_files($this->context->id, 'mod_aibranchedscenario', $to, $this->slot($revisionnumber));
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
            $slot = $this->slot($revisionnumber);
            foreach ($files as $file) {
                // Only this rung's working files. The three scenarios in an activity share
                // the working areas, so without this the foundation scenario's publish
                // would sweep up the intermediate one's half-finished artwork.
                if (!$this->mine((int)$file->get_itemid())) {
                    continue;
                }
                $target = [
                    'contextid' => $this->context->id,
                    'component' => 'mod_aibranchedscenario',
                    'filearea'  => $to,
                    'itemid'    => $slot,
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
                    $slot,
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
        $slot = $this->slot($revisionnumber);
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            $slot,
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
                $slot,
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
            if (!$this->mine((int)$file->get_itemid())) {
                continue;
            }
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
