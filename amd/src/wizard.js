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

/**
 * Drives the five step authoring wizard, generation polling and node text editing.
 *
 * @module     mod_aibranchedscenario/wizard
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';

const POLL_INTERVAL = 4000;
const POLL_LIMIT = 150;

const SELECTORS = {
    root: '[data-region="wizard"]',
    step: '[data-region="step"]',
    steps: '[data-region="steps"]',
    error: '[data-region="error"]',
    loading: '[data-region="loading"]',
    loadingText: '[data-region="loadingtext"]',
};

/**
 * Wizard controller.
 */
/**
 * Base64 encode a JSON document for transport.
 *
 * The web service takes the definition base64 encoded so that a document
 * containing angle brackets, quotation marks or non-ASCII characters survives the
 * parameter layer byte for byte. btoa() only accepts Latin-1, so the string is
 * converted to UTF-8 bytes first, and the result is wrapped at 64 characters
 * because that is the line length Moodle's PARAM_BASE64 accepts.
 *
 * @param {String} json The JSON document.
 * @return {String} The document, base64 encoded and line wrapped.
 */
const encodeDefinition = (json) => {
    const bytes = new TextEncoder().encode(json);
    let binary = '';
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });
    return window.btoa(binary).match(/.{1,64}/g).join('\n');
};

class Wizard {

    /**
     * Create a controller bound to a wizard root element.
     *
     * @param {HTMLElement} root The wizard root element.
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.step = 1;
        this.stepCount = root.querySelectorAll(SELECTORS.step).length;
        this.strings = {};
        this.busy = false;
        this.dirty = false;
    }

    /**
     * Load strings and bind events.
     *
     * @returns {Promise} Resolves once the wizard is ready.
     */
    async init() {
        const keys = [
            'saved', 'generationqueued', 'generationrunning', 'generationready',
            'published', 'error:generic', 'nosuggestion', 'unsavedchanges',
        ];
        const values = await getStrings(keys.map((key) => ({key, component: 'mod_aibranchedscenario'})));
        keys.forEach((key, index) => {
            this.strings[key] = values[index];
        });

        this.root.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action]');
            if (!target || !this.root.contains(target)) {
                return;
            }
            event.preventDefault();
            this.handle(target.dataset.action, target);
        });

        this.root.addEventListener('input', () => {
            this.dirty = true;
        });

        window.addEventListener('beforeunload', (event) => {
            if (!this.dirty) {
                return undefined;
            }
            event.preventDefault();
            event.returnValue = '';
            return '';
        });

        this.showStep(1);
        return true;
    }

    /**
     * Route a delegated action.
     *
     * @param {String} action The action name.
     * @param {HTMLElement} element The activated element.
     * @returns {void}
     */
    handle(action, element) {
        switch (action) {
            case 'gotostep':
                this.showStep(parseInt(element.dataset.step, 10));
                break;
            case 'next':
                this.showStep(Math.min(this.stepCount, this.step + 1));
                break;
            case 'back':
                this.showStep(Math.max(1, this.step - 1));
                break;
            case 'option':
                this.toggleOption(element);
                break;
            case 'save':
                this.save(true);
                break;
            case 'populate':
                this.populate();
                break;
            case 'suggest':
                this.suggest(element);
                break;
            case 'generate':
                this.generate();
                break;
            case 'publish':
                this.publish();
                break;
            case 'import':
                this.importDefinition();
                break;
            case 'savenode':
                this.saveNode(element);
                break;
            default:
                break;
        }
    }

    /**
     * Show one wizard step.
     *
     * @param {Number} step The step number.
     * @returns {void}
     */
    showStep(step) {
        this.step = step;
        this.root.querySelectorAll(SELECTORS.step).forEach((section) => {
            section.hidden = parseInt(section.dataset.step, 10) !== step;
        });
        this.root.querySelectorAll('.aibs-wizard-step').forEach((button) => {
            const value = parseInt(button.dataset.step, 10);
            if (value === step) {
                button.setAttribute('aria-current', 'step');
            } else {
                button.removeAttribute('aria-current');
            }
            button.classList.toggle('aibs-is-complete', value < step);
        });
        this.root.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Toggle a single or multi select option.
     *
     * @param {HTMLElement} element The option button.
     * @returns {void}
     */
    toggleOption(element) {
        const group = element.closest('[data-group]');
        if (!group) {
            return;
        }
        const multiple = group.dataset.multiple === '1';
        const pressed = element.getAttribute('aria-pressed') === 'true';
        if (!multiple) {
            group.querySelectorAll('[data-action="option"]').forEach((option) => {
                option.setAttribute('aria-pressed', 'false');
            });
            element.setAttribute('aria-pressed', 'true');
        } else {
            element.setAttribute('aria-pressed', pressed ? 'false' : 'true');
        }
        this.dirty = true;
    }

    /**
     * Read the current wizard values out of the form controls.
     *
     * @returns {Object} The wizard values.
     */
    collect() {
        const source = {};
        this.root.querySelectorAll('[data-field]').forEach((element) => {
            const name = element.dataset.field;
            source[name] = name === 'decisions' ? parseInt(element.value, 10) : element.value;
        });

        this.root.querySelectorAll('[data-group]').forEach((group) => {
            const name = group.dataset.group;
            const multiple = group.dataset.multiple === '1';
            const chosen = [];
            group.querySelectorAll('[aria-pressed="true"]').forEach((option) => {
                chosen.push(option.dataset.value);
            });
            source[name] = multiple ? chosen : (chosen[0] || '');
        });

        source.characters = [];
        this.root.querySelectorAll('[data-character]').forEach((fieldset) => {
            const character = {};
            fieldset.querySelectorAll('[data-character-field]').forEach((element) => {
                character[element.dataset.characterField] = element.value;
            });
            if (character.name && character.name.trim() !== '') {
                source.characters.push(character);
            }
        });

        source.openingmetrics = {};
        this.root.querySelectorAll('[data-metric-field]').forEach((element) => {
            source.openingmetrics[element.dataset.metricField] = parseInt(element.value, 10);
        });

        source.principles = [];
        return source;
    }

    /**
     * Call one of the plugin's external functions.
     *
     * @param {String} method The external function name without the component prefix.
     * @param {Object} args The call arguments.
     * @returns {Promise} Resolves with the response.
     */
    call(method, args) {
        return Ajax.call([{
            methodname: 'mod_aibranchedscenario_' + method,
            args: Object.assign({cmid: this.cmid}, args),
        }])[0];
    }

    /**
     * Show or hide the busy indicator.
     *
     * @param {Boolean} busy Whether a request is in flight.
     * @param {String} message Optional status message.
     * @returns {void}
     */
    setBusy(busy, message) {
        this.busy = busy;
        const loading = this.root.querySelector(SELECTORS.loading);
        if (loading) {
            loading.hidden = !busy;
        }
        const text = this.root.querySelector(SELECTORS.loadingText);
        if (text && message) {
            text.textContent = message;
        }
        this.root.querySelectorAll('[data-action="generate"], [data-action="publish"]').forEach((button) => {
            button.disabled = busy;
        });
    }

    /**
     * Show an error in the wizard.
     *
     * @param {Object} error The rejected response.
     * @returns {void}
     */
    showError(error) {
        const region = this.root.querySelector(SELECTORS.error);
        if (!region) {
            Notification.exception(error);
            return;
        }
        region.textContent = (error && error.message) ? error.message : this.strings['error:generic'];
        region.hidden = false;
        region.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    /**
     * Clear the error region.
     *
     * @returns {void}
     */
    clearError() {
        const region = this.root.querySelector(SELECTORS.error);
        if (region) {
            region.hidden = true;
            region.textContent = '';
        }
    }

    /**
     * Save the wizard values.
     *
     * @param {Boolean} announce Whether to confirm the save to the author.
     * @returns {Promise} Resolves once saved.
     */
    async save(announce) {
        this.clearError();
        try {
            await this.call('save_source', {source: this.collect()});
            this.dirty = false;
            if (announce) {
                Notification.addNotification({message: this.strings.saved, type: 'success'});
            }
        } catch (error) {
            this.showError(error);
            return false;
        }
        return true;
    }

    /**
     * Fill the wizard from the pasted source content.
     *
     * @returns {Promise} Resolves once the fields are filled.
     */
    async populate() {
        this.clearError();
        this.setBusy(true);
        try {
            const brief = this.root.querySelector('[data-field="brief"]');
            const content = this.root.querySelector('[data-field="sourcecontent"]');
            const response = await this.call('populate_wizard', {
                brief: brief ? brief.value : '',
                sourcecontent: content ? content.value : '',
            });
            this.apply(response.source);
            this.dirty = true;
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Write a set of wizard values back into the form controls.
     *
     * @param {Object} source The wizard values.
     * @returns {void}
     */
    apply(source) {
        this.root.querySelectorAll('[data-field]').forEach((element) => {
            const name = element.dataset.field;
            if (Object.prototype.hasOwnProperty.call(source, name) && source[name] !== '') {
                element.value = source[name];
            }
        });

        this.root.querySelectorAll('[data-group]').forEach((group) => {
            const name = group.dataset.group;
            if (!Object.prototype.hasOwnProperty.call(source, name)) {
                return;
            }
            const wanted = Array.isArray(source[name]) ? source[name] : [source[name]];
            group.querySelectorAll('[data-action="option"]').forEach((option) => {
                option.setAttribute('aria-pressed', wanted.indexOf(option.dataset.value) >= 0 ? 'true' : 'false');
            });
        });

        if (Array.isArray(source.characters)) {
            this.root.querySelectorAll('[data-character]').forEach((fieldset, index) => {
                const character = source.characters[index];
                if (!character) {
                    return;
                }
                fieldset.querySelectorAll('[data-character-field]').forEach((element) => {
                    const key = element.dataset.characterField;
                    if (character[key]) {
                        element.value = character[key];
                    }
                });
            });
        }

        if (source.openingmetrics) {
            this.root.querySelectorAll('[data-metric-field]').forEach((element) => {
                const key = element.dataset.metricField;
                if (typeof source.openingmetrics[key] === 'number') {
                    element.value = source.openingmetrics[key];
                }
            });
        }
    }

    /**
     * Ask the service to suggest one field.
     *
     * @param {HTMLElement} element The suggest button.
     * @returns {Promise} Resolves once the suggestion is applied.
     */
    async suggest(element) {
        const field = element.dataset.suggest;
        this.clearError();
        this.setBusy(true);
        try {
            const response = await this.call('suggest_field', {field: field, source: this.collect()});
            const input = this.root.querySelector('[data-field="' + field + '"]');
            if (input && response.suggestion) {
                input.value = response.suggestion;
                this.dirty = true;
            } else if (!response.suggestion) {
                Notification.addNotification({message: this.strings.nosuggestion, type: 'info'});
            }
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Save the wizard values then queue a generation job and follow it.
     *
     * @returns {Promise} Resolves once generation finishes or fails.
     */
    async generate() {
        this.clearError();
        if (!await this.save(false)) {
            return false;
        }
        this.setBusy(true, this.strings.generationqueued);
        try {
            const queued = await this.call('queue_generation', {});
            await this.poll(queued.jobid);
        } catch (error) {
            this.setBusy(false);
            this.showError(error);
        }
        return true;
    }

    /**
     * Poll a generation job until it finishes.
     *
     * @param {Number} jobid The job identifier.
     * @returns {Promise} Resolves once the job leaves the queue.
     */
    async poll(jobid) {
        for (let attempt = 0; attempt < POLL_LIMIT; attempt++) {
            await new Promise((resolve) => {
                window.setTimeout(resolve, POLL_INTERVAL);
            });
            let status;
            try {
                status = await this.call('get_job_status', {jobid: jobid});
            } catch (error) {
                this.setBusy(false);
                this.showError(error);
                return false;
            }
            if (status.status === 'running') {
                this.setBusy(true, this.strings.generationrunning);
            }
            if (status.status === 'ready') {
                this.setBusy(true, this.strings.generationready);
                this.dirty = false;
                window.location.reload();
                return true;
            }
            if (status.status === 'error') {
                this.setBusy(false);
                this.showError({message: status.errormessage});
                return false;
            }
        }
        this.setBusy(false);
        this.showError({message: this.strings['error:generic']});
        return false;
    }

    /**
     * Publish the working copy.
     *
     * @returns {Promise} Resolves once published.
     */
    async publish() {
        this.clearError();
        this.setBusy(true);
        try {
            await this.call('publish_scenario', {});
            Notification.addNotification({message: this.strings.published, type: 'success'});
            this.dirty = false;
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Import a scenario definition pasted as JSON.
     *
     * @returns {Promise} Resolves once the definition is stored or rejected.
     */
    async importDefinition() {
        const field = this.root.querySelector('[data-region="import"]');
        if (!field || field.value.trim() === '') {
            return false;
        }
        this.clearError();
        this.setBusy(true);
        try {
            const response = await this.call('import_definition', {
                definition: encodeDefinition(field.value)
            });
            if (response.imported) {
                this.dirty = false;
                window.location.reload();
            } else {
                this.showError({message: response.problems});
            }
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Save the edited text of one node.
     *
     * @param {HTMLElement} element The save button inside the node editor.
     * @returns {Promise} Resolves once saved.
     */
    async saveNode(element) {
        const container = element.closest('[data-node]');
        if (!container) {
            return false;
        }
        this.clearError();

        const fields = {};
        container.querySelectorAll('[data-node-field]').forEach((input) => {
            fields[input.dataset.nodeField] = input.value;
        });

        const choices = [];
        container.querySelectorAll('[data-choice]').forEach((fieldset) => {
            const choice = {id: fieldset.dataset.choice};
            fieldset.querySelectorAll('[data-choice-field]').forEach((input) => {
                choice[input.dataset.choiceField] = input.value;
            });
            choices.push(choice);
        });

        element.disabled = true;
        try {
            await this.call('save_node_text', {
                nodeid: container.dataset.node,
                fields: fields,
                choices: choices,
            });
            Notification.addNotification({message: this.strings.saved, type: 'success'});
            this.dirty = false;
        } catch (error) {
            this.showError(error);
        } finally {
            element.disabled = false;
        }
        return true;
    }
}

/**
 * Initialise the wizard on the page.
 *
 * @param {Number} cmid The course module id.
 * @returns {Promise} Resolves once the wizard is ready.
 */
export const init = (cmid) => {
    const root = document.querySelector(SELECTORS.root + '[data-cmid="' + cmid + '"]');
    if (!root || root.dataset.initialised === '1') {
        return Promise.resolve(false);
    }
    root.dataset.initialised = '1';
    const wizard = new Wizard(root);
    return wizard.init();
};
