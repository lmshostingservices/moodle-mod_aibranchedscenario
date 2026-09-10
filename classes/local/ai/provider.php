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

/**
 * Contract for the service that turns source content into a scenario.
 *
 * Deliberately expressed as product operations rather than model calls. The plugin
 * holds no AI provider credentials; it authenticates to a service that owns them,
 * owns the prompts and owns the credit ledger.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider {
    /** @var string Billable operation: full scenario generation. */
    const OP_SCENARIO = 'scenario';

    /** @var string Billable operation: fill the wizard from a brief. */
    const OP_POPULATE = 'populate';

    /** @var string Billable operation: suggest one field. */
    const OP_SUGGEST = 'suggest';

    /** @var string Billable operation: one scene image. */
    const OP_IMAGE = 'image';

    /** @var string Billable operation: one narration clip. */
    const OP_SPEECH = 'speech';

    /**
     * Whether the provider has usable credentials.
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Human readable provider name for the settings page and logs.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Connection and balance status for the settings page.
     *
     * @return array Keys: connected (bool), credits (int), unlimited (bool),
     *               siteid (string), message (string), buyurl (string).
     */
    public function get_status(): array;

    /**
     * Fill the authoring wizard from a free-text brief and optional source content.
     *
     * @param array $request Keys: brief, sourcecontent, language.
     * @return array Wizard field values, unvalidated.
     * @throws generation_exception
     */
    public function populate(array $request): array;

    /**
     * Suggest a value for one wizard field given the rest of the wizard context.
     *
     * @param string $field Field name being suggested.
     * @param array $context Current wizard values.
     * @return array Keys: suggestion (string) or values (array) for multi-select fields.
     * @throws generation_exception
     */
    public function suggest(string $field, array $context): array;

    /**
     * Build a complete branching scenario.
     *
     * @param array $request Normalised wizard inputs plus language and contract version.
     * @return array Keys: scenario (array), meta (array).
     * @throws generation_exception
     */
    public function generate_scenario(array $request): array;

    /**
     * Generate one scene image.
     *
     * @param string $prompt Scene description.
     * @param string $style One of the plugin's image styles.
     * @return array Keys: data (raw binary), mimetype (string).
     * @throws generation_exception
     */
    public function generate_image(string $prompt, string $style, string $scenetitle = ''): array;

    /**
     * Generate one narration clip.
     *
     * @param string $text Text to speak.
     * @param string $voice Voice identifier.
     * @param string $language BCP-47 language code.
     * @return array Keys: data (raw binary), mimetype (string).
     * @throws generation_exception
     */
    public function generate_speech(string $text, string $voice, string $language): array;

    /**
     * Non-secret metadata about the most recent call.
     *
     * @return array Keys: model, durationms, creditsused, creditsremaining.
     */
    public function get_last_meta(): array;

    /**
     * The most recent request and response, redacted so it can be stored and displayed.
     *
     * Implementations must replace credentials with a description of their shape rather
     * than their value, and must not return generated learner content. The purpose is
     * to make a rejected request diagnosable after the fact.
     *
     * @return array Empty when no call has been made.
     */
    public function get_last_exchange(): array;
}
