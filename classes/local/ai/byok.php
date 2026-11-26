<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aibranchedscenario\local\ai;

/**
 * Bring your own AI: answer the LMS Labs routes on the site's own AI.
 *
 * With a Bring your own AI licence (AI Grader Central Config 1.1.0 or later) an administrator chooses, for text,
 * images and voice separately, whether LMS Labs credits or the site's own AI does the work. Each route below goes
 * to the site's own AI only when its kind of work is set that way; anything else still goes to LMS Labs.
 *
 * Text (scenario, wizard fill and field suggestions) runs scenario_engine, the service's own logic. Images and
 * narration go straight to the site's image and voice providers. Calls go from this Moodle site to the provider,
 * never through LMS Labs, and use no LMS Labs credits.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** Component the licence and usage are recorded against. */
    const COMPONENT = 'mod_aibranchedscenario';
    /** Scenario writing can take several minutes on some providers, and runs from a task. */
    const SCENARIO_TIMEOUT = 300;
    /** Picture size the service delivers. */
    const IMAGE_WIDTH = 1600;
    /** Picture size the service delivers. */
    const IMAGE_HEIGHT = 900;
    /** The plugin's Google voices, and the closest OpenAI voice for each. */
    const OPENAI_VOICES = ['Aoede' => 'nova', 'Kore' => 'shimmer', 'Leda' => 'coral', 'Zephyr' => 'sage',
        'Puck' => 'onyx', 'Charon' => 'echo', 'Fenrir' => 'ash', 'Orus' => 'ballad'];

    /**
     * Which kind of AI a route needs.
     *
     * @param string $route Route name: generate, populate, suggest, image or speech.
     * @return string|null text, image or voice; null for routes that stay on LMS Labs.
     */
    public static function service(string $route): ?string {
        $map = ['generate' => 'text', 'populate' => 'text', 'suggest' => 'text', 'image' => 'image', 'speech' => 'voice'];
        return $map[$route] ?? null;
    }

    /**
     * Whether a kind of work runs on the site's own AI now.
     *
     * @param string $service text, image or voice.
     * @return bool
     */
    public static function uses(string $service): bool {
        return class_exists('\local_aiconfig\byok\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, $service);
    }

    /**
     * Whether a route runs on the site's own AI now.
     *
     * @param string $path Route path or name.
     * @return bool
     */
    public static function handles(string $path): bool {
        $service = self::service(basename($path));
        return $service !== null && self::uses($service);
    }

    /**
     * Whether any of this plugin's work runs on the site's own AI.
     *
     * @return bool
     */
    public static function any(): bool {
        return self::uses('text') || self::uses('image') || self::uses('voice');
    }

    /**
     * Answer a route as the service would, returning what the service puts in its data block.
     *
     * @param string $path Route path.
     * @param array $payload Request body, without credentials.
     * @return array Keys: data, quality (generate only).
     * @throws generation_exception
     */
    public static function call(string $path, array $payload): array {
        $route = basename($path);
        \core_php_time_limit::raise(self::SCENARIO_TIMEOUT + 60);
        scenario_engine::$aicall = function (string $system, string $user, array $options) use ($route): string {
            if ($route === 'generate') {
                $options['timeout'] = self::SCENARIO_TIMEOUT;
            }
            return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, $options);
        };
        try {
            switch ($route) {
                case 'generate':
                    $out = scenario_engine::generate($payload);
                    return ['data' => $out['scenario'], 'quality' => $out['quality']];
                case 'populate':
                    return ['data' => scenario_engine::populate($payload)];
                case 'suggest':
                    return ['data' => scenario_engine::suggest($payload)];
                case 'image':
                    return ['data' => self::image((string) ($payload['prompt'] ?? ''))];
                case 'speech':
                    return ['data' => self::speech(
                        (string) ($payload['text'] ?? ''),
                        (string) ($payload['language'] ??
                        'en-AU'),
                        (string) ($payload['voice'] ?? 'Aoede')
                    )];
            }
        } catch (byok_failure $e) {
            throw new generation_exception('error:byokfailed', $e->stage . ': ' . $e->rule);
        } catch (generation_exception $e) {
            throw $e;
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'byok_notavailable') {
                throw new generation_exception('error:byokunavailable', $e->getMessage());
            }
            throw new generation_exception('error:byokfailed', \core_text::substr(
                clean_param($e->getMessage(), PARAM_TEXT),
                0,
                300
            ));
        }
        throw new generation_exception('error:byokfailed', 'route: ' . $route);
    }

    /**
     * One scene picture, delivered as the service delivers it: a 1600 x 900 JPEG.
     *
     * @param string $prompt The plugin's finished image prompt; sent unchanged, as the service does.
     * @return array Keys: base64, mimeType, model.
     */
    protected static function image(string $prompt): array {
        if (\core_text::strlen(trim($prompt)) < 10) {
            throw new byok_failure('request', 'request', 'INVALID_REQUEST');
        }
        $bytes = \local_aiconfig\byok\ai::image(self::COMPONENT, $prompt, ['size' => '1536x1024', 'quality' => 'medium']);
        $jpeg = self::cover_jpeg($bytes);
        if ($jpeg !== null) {
            return ['base64' => base64_encode($jpeg), 'mimeType' => 'image/jpeg', 'model' => 'own-ai'];
        }
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new byok_failure('provider', 'image_output', 'IMAGE_OUTPUT_INVALID');
        }
        return ['base64' => base64_encode($bytes), 'mimeType' => $mime, 'model' => 'own-ai'];
    }

    /**
     * Crop and scale to fill 1600 x 900, then save as JPEG quality 86.
     *
     * @param string $bytes Image file contents.
     * @return string|null JPEG bytes, or null when GD cannot read the image.
     */
    public static function cover_jpeg(string $bytes): ?string {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = max(self::IMAGE_WIDTH / $width, self::IMAGE_HEIGHT / $height);
        $cropw = (int) round(self::IMAGE_WIDTH / $scale);
        $croph = (int) round(self::IMAGE_HEIGHT / $scale);
        $target = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            (int) (($width - $cropw) / 2),
            (int) (($height - $croph) / 2),
            self::IMAGE_WIDTH,
            self::IMAGE_HEIGHT,
            $cropw,
            $croph
        );
        ob_start();
        imagejpeg($target, null, 86);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        return $jpeg === '' ? null : $jpeg;
    }

    /**
     * One narration clip.
     *
     * The plugin names Google Chirp voices. A Google key uses them as they are; an OpenAI key gets the closest
     * OpenAI voice of the same kind; ElevenLabs uses the voice the administrator chose.
     *
     * @param string $text
     * @param string $language
     * @param string $voice
     * @return array Keys: base64, mimeType.
     */
    protected static function speech(string $text, string $language, string $voice): array {
        $text = trim($text);
        if ($text === '' || \core_text::strlen($text) > 4500) {
            throw new byok_failure('request', 'request', 'INVALID_REQUEST');
        }
        $vendor = \local_aiconfig\byok\router::vendor(\local_aiconfig\byok\router::VOICE);
        if ($vendor === 'openai') {
            $voice = self::OPENAI_VOICES[$voice] ?? '';
        } else if ($vendor !== 'googletts') {
            $voice = '';
        }
        $audio = \local_aiconfig\byok\ai::speech(self::COMPONENT, $text, ['language' => $language, 'voice' => $voice]);
        if (strlen($audio) < 128) {
            throw new byok_failure('provider', 'speech_output', 'SPEECH_OUTPUT_INVALID');
        }
        return ['base64' => base64_encode($audio), 'mimeType' => 'audio/mpeg'];
    }
}
