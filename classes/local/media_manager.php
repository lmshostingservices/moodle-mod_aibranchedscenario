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
        $filename = $key . '.' . $extension;

        $fs = get_file_storage();
        $fs->delete_area_files_select(
            $this->context->id,
            'mod_aibranchedscenario',
            $filearea,
            '= :itemid',
            ['itemid' => $itemid]
        );

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
     * @return array Keys: images, narrations - how many of each were attempted.
     */
    public function generate_for_definition(provider $provider, \stdClass $scenario, array $definition): array {
        $wantsimages = !empty($scenario->enableimages) && get_config('mod_aibranchedscenario', 'allowimages');
        $wantsaudio = !empty($scenario->enableaudio) && get_config('mod_aibranchedscenario', 'allowaudio');
        if (!$wantsimages && !$wantsaudio) {
            return ['images' => 0, 'narrations' => 0];
        }

        $this->clear_working_media();

        $source = scenario_manager::get_source($scenario);
        $style = $source['imagestyle'] ?? 'cinematic';
        $voice = self::configured_voice();
        $counts = ['images' => 0, 'narrations' => 0];

        $index = 0;
        foreach ($definition['nodes'] as $node) {
            if ($wantsimages) {
                // Endings are given a frame too. The closing image is the one a learner
                // is left looking at while they read what their decisions came to.
                $this->generate_scene($provider, $definition, $node, $style, $index);
                $counts['images']++;
                if (!empty($node['crisisvariant']['situation'])) {
                    $this->generate_scene($provider, $definition, $node, $style, $index, true);
                    $counts['images']++;
                }
            }
            if ($node['type'] === 'outcome') {
                $index++;
                continue;
            }
            if ($wantsaudio) {
                $this->generate_narration($provider, $node, $scenario->scenariolang, $voice, $index);
                $counts['narrations']++;
            }
            $index++;
        }

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
            debugging('Scene image generation skipped: ' . $e->errorcode, DEBUG_DEVELOPER);
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
        $text = trim($node['situation'] . "\n\n" . $node['facilitatorspeech']);
        if ($text === '') {
            return false;
        }
        try {
            $result = $provider->generate_speech(\core_text::substr($text, 0, 4500), $voice, $language);
            $this->store(self::AREA_NARRATION, $index, $node['id'], $result['data'], $result['mimetype']);
            return true;
        } catch (generation_exception $e) {
            debugging('Narration generation skipped: ' . $e->errorcode, DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Copy the working-copy media into the immutable areas for a published revision.
     *
     * @param int $revisionnumber The revision number being published.
     * @return void
     */
    public function publish_media(int $revisionnumber): void {
        $fs = get_file_storage();
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
            foreach ($files as $file) {
                $fs->create_file_from_storedfile([
                    'contextid' => $this->context->id,
                    'component' => 'mod_aibranchedscenario',
                    'filearea'  => $to,
                    'itemid'    => $revisionnumber,
                    'filepath'  => '/',
                    'filename'  => $file->get_filename(),
                ], $file);
            }
        }
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
     * The configured narration voice, validated against a safe shape.
     *
     * @return string
     */
    public static function configured_voice(): string {
        $voice = (string)get_config('mod_aibranchedscenario', 'defaultvoice');
        if ($voice === '' || !preg_match('/^[A-Za-z][A-Za-z0-9\-]{1,32}$/', $voice)) {
            $voice = 'Aoede';
        }
        return $voice;
    }
}
