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

namespace mod_aibranchedscenario\local\ai;

use curl;
use mod_aibranchedscenario\local\schema;

/**
 * LMS Labs implementation of the generation provider.
 *
 * Every call is one authenticated request to a single LMS Labs route that owns the
 * AI provider credentials, the prompt contract and the credit ledger. This plugin
 * sends a request identifier with each call so that a retry after a timeout cannot
 * be charged twice.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lmslabs_provider implements provider {
    /**
     * Build a Moodle cURL client.
     *
     * The curl class lives in lib/filelib.php, which Moodle's bootstrap only loads
     * conditionally. A web request has usually pulled it in by some other route, but a
     * scheduled or ad-hoc task running under CLI cron has not, and generation runs as
     * an ad-hoc task. Requiring it here means the first generation job on a site does
     * not die with "Class curl not found".
     *
     * @return \curl
     */
    protected static function make_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new curl();
    }

    /** @var string Default API host. */
    const DEFAULT_HOST = 'https://lms-labs.com';

    /** @var string Route prefix for this plugin's operations. */
    const ROUTE_PREFIX = '/api/aibranchedscenario';

    /** @var int Fewest characters of source content the generate route accepts. */
    const MIN_GENERATE_CHARS = 50;

    /** @var int Fewest characters of source content the populate route accepts. */
    const MIN_POPULATE_CHARS = 20;

    /** @var int Most characters of source content the suggest route accepts. */
    const MAX_SUGGEST_CHARS = 15000;

    /** @var array Metadata from the last successful call. */
    protected $lastmeta = [];

    /** @var array|null Cached resolved credentials. */
    protected $creds = null;

    /**
     * Resolve credentials once per instance.
     *
     * @return array
     */
    protected function creds(): array {
        if ($this->creds === null) {
            $this->creds = credentials::resolve();
        }
        return $this->creds;
    }

    /**
     * The configured API host, without a trailing slash.
     *
     * @return string
     */
    protected function host(): string {
        $host = trim((string)get_config('mod_aibranchedscenario', 'apihost'));
        if ($host === '') {
            $host = self::DEFAULT_HOST;
        }
        $host = rtrim($host, '/');
        if (!preg_match('#^https://[a-z0-9.\-]+(:\d+)?$#i', $host)) {
            $host = self::DEFAULT_HOST;
        }
        return $host;
    }

    /**
     * Request timeout in seconds.
     *
     * @return int
     */
    protected function timeout(): int {
        $value = (int)get_config('mod_aibranchedscenario', 'requesttimeout');
        return max(15, min(600, $value ?: 180));
    }

    /**
     * Whether the provider has usable credentials.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return credentials::are_configured();
    }

    /**
     * Human readable provider name for the settings page and logs.
     *
     * @return string
     */
    public function get_name(): string {
        return 'LMS Labs';
    }

    /**
     * Non-secret metadata about the most recent call.
     *
     * @return array Keys: model, provider, durationms, creditsused, creditsremaining, unlimited.
     */
    public function get_last_meta(): array {
        return $this->lastmeta;
    }

    /**
     * Build the credential envelope sent with every request.
     *
     * The LMS Labs routes validate their bodies with a strict schema, so any key the
     * schema does not name causes the whole request to be rejected before it is even
     * authenticated. Only siteId and apiKey belong in the body; the request
     * identifier travels in the X-Request-Id header, where it is not schema checked.
     *
     * @return array
     */
    protected function envelope(): array {
        $creds = $this->creds();
        return [
            'siteId' => $creds['siteid'],
            'apiKey' => $creds['apikey'],
        ];
    }

    /**
     * Generate an idempotency handle for one logical operation.
     *
     * @param string $operation Operation name.
     * @param string $fingerprint Stable fingerprint of the request payload.
     * @return string
     */
    public static function request_id(string $operation, string $fingerprint): string {
        return $operation . '_' . sha1($operation . '|' . $fingerprint);
    }

    /**
     * Perform an authenticated POST and return the decoded envelope.
     *
     * @param string $path Route path below the host.
     * @param array $payload Request payload merged into the credential envelope.
     * @param string $requestid Idempotency handle.
     * @return array Decoded response.
     * @throws generation_exception
     */
    protected function call(string $path, array $payload, string $requestid): array {
        if (!$this->is_configured()) {
            throw new generation_exception('error:nocredentials');
        }

        $creds = $this->creds();
        $body = json_encode(
            array_merge($this->envelope(), $payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($body === false) {
            throw new generation_exception('error:requestencode');
        }

        $started = microtime(true);
        $curl = self::make_curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $creds['apikey'],
            'X-Request-Id: ' . $requestid,
        ]);
        $response = $curl->post($this->host() . $path, $body, [
            'CURLOPT_TIMEOUT'        => $this->timeout(),
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_FOLLOWLOCATION' => 0,
        ]);
        $durationms = (int)round((microtime(true) - $started) * 1000);

        if ($curl->get_errno()) {
            throw new generation_exception('error:servicetimeout');
        }

        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        $decoded = json_decode((string)$response, true);

        if ($status === 401 || $status === 403) {
            throw new generation_exception('error:serviceunauthorised');
        }
        if ($status === 402 || (is_array($decoded) && ($decoded['error'] ?? '') === 'INSUFFICIENT_CREDITS')) {
            throw new generation_exception('error:insufficientcredits');
        }
        if ($status === 429) {
            throw new generation_exception('error:serviceratelimited');
        }
        if (!is_array($decoded)) {
            throw new generation_exception('error:serviceunreadable');
        }
        if ($status < 200 || $status >= 300 || empty($decoded['ok'])) {
            $code = isset($decoded['error']) && is_string($decoded['error'])
                ? clean_param($decoded['error'], PARAM_ALPHANUMEXT) : (string)$status;
            // The service sends a human-readable message alongside the code. Showing it
            // is the difference between "INVALID_REQUEST" and knowing what to change.
            // It is the service's own prose, not a provider error or a stack trace: it
            // is cleaned to plain text and capped, and no part of the request, and in
            // particular no credential, is ever echoed back into it.
            $detail = $code;
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                $message = trim(clean_param($decoded['message'], PARAM_TEXT));
                if ($message !== '') {
                    $detail = $code . ': ' . \core_text::substr($message, 0, 200);
                }
            }
            throw new generation_exception('error:servicefailed', $detail);
        }

        $this->lastmeta = [
            'model'            => isset($decoded['model']) && is_string($decoded['model'])
                ? clean_param($decoded['model'], PARAM_TEXT) : '',
            'provider'         => 'lmslabs',
            'durationms'       => $durationms,
            'creditsused'      => (int)($decoded['creditsUsed'] ?? 0),
            // The remaining balance is null on an unlimited plan, and isUnlimited is
            // the authoritative flag for that case.
            'creditsremaining' => isset($decoded['creditsRemaining']) && $decoded['creditsRemaining'] !== null
                ? (int)$decoded['creditsRemaining'] : 0,
            'unlimited'        => !empty($decoded['isUnlimited']),
        ];

        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new generation_exception('error:serviceunreadable');
        }
        return $decoded['data'];
    }

    /**
     * Connection and balance status for the settings page.
     *
     * @return array Keys: connected, credits, unlimited, siteid, source, component, message, buyurl.
     */
    public function get_status(): array {
        $creds = $this->creds();
        $blank = [
            'connected' => false,
            'credits'   => 0,
            'unlimited' => false,
            'siteid'    => $creds['siteid'],
            'source'    => $creds['source'],
            'component' => $creds['component'],
            'message'   => '',
            'buyurl'    => '',
        ];
        if (!$this->is_configured()) {
            $blank['message'] = get_string('status:nocredentials', 'mod_aibranchedscenario');
            return $blank;
        }

        $curl = self::make_curl();
        $curl->setHeader([
            'Accept: application/json',
            'X-API-Key: ' . $creds['apikey'],
        ]);
        $url = $this->host() . '/api/credits?siteId=' . rawurlencode($creds['siteid']);
        $response = $curl->get($url, [], [
            'CURLOPT_TIMEOUT'        => 20,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => 0,
        ]);

        if ($curl->get_errno()) {
            $blank['message'] = get_string('status:unreachable', 'mod_aibranchedscenario');
            return $blank;
        }
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        $decoded = json_decode((string)$response, true);
        if ($status === 401 || $status === 403) {
            $blank['message'] = get_string('status:unauthorised', 'mod_aibranchedscenario');
            return $blank;
        }
        if (!is_array($decoded) || $status < 200 || $status >= 300) {
            $blank['message'] = get_string('status:unexpected', 'mod_aibranchedscenario');
            return $blank;
        }

        // The creditsRaw and isUnlimited fields are authoritative; the display value is not.
        $unlimited = !empty($decoded['isUnlimited']);
        $raw = array_key_exists('creditsRaw', $decoded) ? (int)$decoded['creditsRaw'] : (int)($decoded['credits'] ?? 0);
        if ($raw === -1) {
            $unlimited = true;
        }
        return [
            'connected' => true,
            'credits'   => $unlimited ? 0 : max(0, $raw),
            'unlimited' => $unlimited,
            'siteid'    => isset($decoded['siteId']) && is_string($decoded['siteId'])
                ? clean_param($decoded['siteId'], PARAM_TEXT) : $creds['siteid'],
            'source'    => $creds['source'],
            'component' => $creds['component'],
            'message'   => '',
            'buyurl'    => isset($decoded['buyUrl']) && is_string($decoded['buyUrl'])
                ? clean_param($decoded['buyUrl'], PARAM_URL) : '',
        ];
    }

    /**
     * Fill the authoring wizard from a free-text brief and optional source content.
     *
     * @param array $request Keys: brief, sourcecontent, language.
     * @return array Wizard field values, unvalidated.
     */
    public function populate(array $request): array {
        $source = trim((string)($request['sourcecontent'] ?? ''));
        if (\core_text::strlen($source) < self::MIN_POPULATE_CHARS) {
            throw new generation_exception('error:sourcetooshort', self::MIN_POPULATE_CHARS);
        }

        // Sending what the teacher has already written stops the service overwriting
        // considered answers with generic ones. The route caps that at 30 keys of 5000
        // characters, so only the short scalar fields go.
        $payload = ['sourceContent' => $source];
        $current = self::current_values($request);
        if ($current) {
            $payload['currentValues'] = $current;
        }

        $requestid = self::request_id(self::OP_POPULATE, json_encode($payload));
        $data = $this->call(self::ROUTE_PREFIX . '/populate', $payload, $requestid);
        return scenario_mapper::fields_from_wire(is_array($data) ? $data : []);
    }

    /**
     * Reduce the wizard's current values to the short scalar subset the route accepts.
     *
     * @param array $request Wizard values.
     * @return array At most 30 keys, each a string of at most 5000 characters.
     */
    protected static function current_values(array $request): array {
        $out = [];
        $names = [
            'title', 'audience', 'setting', 'participantrole', 'openingsituation',
            'centralproblem', 'tone', 'complexity', 'industry', 'brief',
        ];
        foreach ($names as $name) {
            $value = $request[$name] ?? '';
            if (is_scalar($value) && trim((string)$value) !== '') {
                $out[$name] = \core_text::substr(trim((string)$value), 0, 5000);
            }
        }
        return $out;
    }

    /**
     * Suggest a value for one wizard field given the rest of the wizard context.
     *
     * @param string $field Field name being suggested.
     * @param array $context Current wizard values.
     * @return array Keys: suggestion, values.
     */
    public function suggest(string $field, array $context): array {
        $payload = ['field' => \core_text::substr($field, 0, 100)];

        $current = $context[$field] ?? '';
        if (is_scalar($current) && trim((string)$current) !== '') {
            $payload['currentValue'] = \core_text::substr(trim((string)$current), 0, 5000);
        }

        $source = trim((string)($context['sourcecontent'] ?? ''));
        if ($source !== '') {
            // This route accepts less source content than the others.
            $payload['sourceContent'] = \core_text::substr($source, 0, self::MAX_SUGGEST_CHARS);
        }

        $rest = self::current_values($context);
        unset($rest[$field]);
        if ($rest) {
            $payload['context'] = array_map(function ($value) {
                return \core_text::substr($value, 0, 3000);
            }, array_slice($rest, 0, 20, true));
        }

        $requestid = self::request_id(self::OP_SUGGEST, json_encode($payload));
        $data = $this->call(self::ROUTE_PREFIX . '/suggest', $payload, $requestid);
        return [
            'suggestion' => is_string($data['suggestion'] ?? null) ? $data['suggestion'] : '',
            'values'     => [],
        ];
    }

    /**
     * Build a complete branching scenario.
     *
     * @param array $request Normalised wizard inputs plus language and contract version.
     * @return array Keys: scenario, meta.
     */
    public function generate_scenario(array $request): array {
        $source = trim((string)($request['sourcecontent'] ?? ''));
        if (\core_text::strlen($source) < self::MIN_GENERATE_CHARS) {
            throw new generation_exception('error:sourcetooshort', self::MIN_GENERATE_CHARS);
        }

        $payload = ['sourceContent' => $source];

        $optional = [
            'title'      => $request['title'] ?? '',
            'audience'   => $request['audience'] ?? '',
            'role'       => $request['participantrole'] ?? '',
            'setting'    => $request['setting'] ?? '',
            'language'   => $request['language'] ?? '',
            'tone'       => $request['tone'] ?? '',
            'complexity' => $request['complexity'] ?? '',
        ];
        foreach ($optional as $name => $value) {
            if (is_scalar($value) && trim((string)$value) !== '') {
                $payload[$name] = trim((string)$value);
            }
        }

        $decisions = (int)($request['decisions'] ?? 0);
        if ($decisions >= 3 && $decisions <= 8) {
            $payload['decisions'] = $decisions;
            // One node per decision, plus the beats between them and three outcomes.
            $payload['maxNodes'] = min(schema::MAX_NODES, ($decisions * 2) + 3);
        }

        $objectives = [];
        foreach ((array)($request['principles'] ?? []) as $principle) {
            $title = is_array($principle) ? ($principle['title'] ?? '') : $principle;
            if (is_scalar($title) && trim((string)$title) !== '') {
                $objectives[] = \core_text::substr(trim((string)$title), 0, 500);
            }
        }
        if ($objectives) {
            $payload['learningObjectives'] = array_slice($objectives, 0, 12);
        }

        $characters = [];
        foreach ((array)($request['characters'] ?? []) as $character) {
            if (is_array($character) && trim((string)($character['name'] ?? '')) !== '') {
                $characters[] = [
                    'name' => \core_text::substr(trim((string)$character['name']), 0, 120),
                    'role' => \core_text::substr(trim((string)($character['role'] ?? '')), 0, 200),
                ];
            }
        }
        if ($characters) {
            $payload['characters'] = array_slice($characters, 0, 6);
        }

        $instructions = self::instructions($request);
        if ($instructions !== '') {
            $payload['instructions'] = $instructions;
        }

        $requestid = self::request_id(self::OP_SCENARIO, json_encode($payload));
        $data = $this->call(self::ROUTE_PREFIX . '/generate', $payload, $requestid);
        if (!is_array($data)) {
            throw new generation_exception('error:servicenoscenario');
        }

        return [
            'scenario' => scenario_mapper::from_wire($data),
            'meta'     => $this->lastmeta,
        ];
    }

    /**
     * Assemble the free-text steer sent as the instructions field.
     *
     * The route caps this at 2000 characters, so the wizard's narrative inputs are
     * folded into one brief rather than each being given a field of its own.
     *
     * @param array $request Wizard values.
     * @return string
     */
    protected static function instructions(array $request): string {
        $parts = [];
        $prefixes = [
            'brief'            => '',
            'centralproblem'   => 'The central problem is',
            'openingsituation' => 'The scenario opens with',
        ];
        foreach ($prefixes as $name => $prefix) {
            $value = trim((string)($request[$name] ?? ''));
            if ($value !== '') {
                $parts[] = trim($prefix . ' ' . $value);
            }
        }
        foreach (['whyhard' => 'What makes this hard', 'stakes' => 'What is at stake'] as $name => $label) {
            $items = array_filter(array_map('strval', (array)($request[$name] ?? [])));
            if ($items) {
                $parts[] = $label . ': ' . implode('; ', $items);
            }
        }
        if (!empty($request['atmosphere'])) {
            $parts[] = 'Atmosphere: ' . (string)$request['atmosphere'];
        }
        return \core_text::substr(trim(implode("\n\n", $parts)), 0, 2000);
    }

    /**
     * Generate one scene image.
     *
     * @param string $prompt Scene description.
     * @param string $style One of the plugin's image styles.
     * @return array Keys: data, mimetype.
     */
    public function generate_image(string $prompt, string $style): array {
        $payload = [
            'prompt'      => $prompt,
            'style'       => $style,
            'aspectRatio' => '16:9',
        ];
        $requestid = self::request_id(self::OP_IMAGE, json_encode($payload));
        $data = $this->call(self::ROUTE_PREFIX . '/image', $payload, $requestid);
        return $this->decode_binary($data, ['image/png', 'image/jpeg', 'image/webp'], 'error:servicenoimage');
    }

    /**
     * Generate one narration clip.
     *
     * @param string $text Text to speak.
     * @param string $voice Voice identifier.
     * @param string $language BCP-47 language code.
     * @return array Keys: data, mimetype.
     */
    public function generate_speech(string $text, string $voice, string $language): array {
        $payload = [
            'text'     => $text,
            'voice'    => $voice,
            'language' => $language,
            'format'   => 'mp3',
        ];
        $requestid = self::request_id(self::OP_SPEECH, json_encode($payload));
        $data = $this->call(self::ROUTE_PREFIX . '/speech', $payload, $requestid);
        return $this->decode_binary($data, ['audio/mpeg', 'audio/mp3', 'audio/wav'], 'error:servicenoaudio');
    }

    /**
     * Decode a base64 binary payload and check its declared type.
     *
     * @param array $data Response data block.
     * @param string[] $allowedmimes Acceptable MIME types.
     * @param string $errorkey Language key used when the payload is unusable.
     * @return array Keys: data, mimetype.
     * @throws generation_exception
     */
    protected function decode_binary(array $data, array $allowedmimes, string $errorkey): array {
        $encoded = $data['base64'] ?? '';
        $mimetype = strtolower((string)($data['mimeType'] ?? ''));
        if (!is_string($encoded) || $encoded === '' || !in_array($mimetype, $allowedmimes, true)) {
            throw new generation_exception($errorkey);
        }
        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) < 128) {
            throw new generation_exception($errorkey);
        }
        $maxbytes = 12 * 1024 * 1024;
        if (strlen($binary) > $maxbytes) {
            throw new generation_exception($errorkey);
        }
        return ['data' => $binary, 'mimetype' => $mimetype];
    }

    /**
     * The canonical option lists sent with every request so the service can constrain
     * the model to values this plugin will accept.
     *
     * @return array
     */
    public static function option_lists(): array {
        return [
            'themes'       => schema::theme_ids(),
            'industries'   => schema::industries(),
            'settings'     => schema::settings_list(),
            'atmospheres'  => schema::atmospheres(),
            'whyHard'      => schema::whyhard(),
            'stakes'       => schema::stakes(),
            'tones'        => schema::tones(),
            'complexities' => schema::complexities(),
            'imageStyles'  => schema::imagestyles(),
            'languages'    => schema::languages(),
            'signals'      => schema::signals(),
            'choiceTags'   => schema::choicetags(),
            'skills'       => schema::skills(),
            'metrics'      => schema::metrics(),
            'nodeTypes'    => schema::nodetypes(),
            'outcomes'     => schema::outcomes(),
        ];
    }
}
