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
import {get_strings as getStrings, get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Toast from 'mod_aibranchedscenario/toast';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

const POLL_INTERVAL = 4000;
const POLL_LIMIT = 150;

const SELECTORS = {
    root: '[data-region="wizard"]',
    step: '[data-region="step"]',
    steps: '[data-region="steps"]',
    error: '[data-region="error"]',
    loading: '[data-region="loading"]',
    loadingText: '[data-region="loadingtext"]',
    loadingEta: '[data-region="loadingeta"]',
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
            'fillingfields', 'promptcopied', 'restore:nothing',
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

        this.root.querySelectorAll('[data-group]').forEach((group) => this.syncOther(group));
        const wanted = parseInt(new URL(window.location.href).searchParams.get('step'), 10);
        this.showStep(wanted >= 1 && wanted <= this.stepCount ? wanted : 1);
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
            case 'copyprompt':
                this.copyPrompt();
                break;
            case 'restoredraft':
                this.restoreDraft();
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

        // Two primary buttons sat side by side on the last step, one of which had
        // nowhere to go, and Back was offered on the first step where it does nothing.
        const back = this.root.querySelector('[data-action="back"]');
        const next = this.root.querySelector('[data-action="next"]');
        if (back) {
            back.hidden = step === 1;
        }
        if (next) {
            next.hidden = step === this.stepCount;
        }

        this.scrollBelowHeader(this.root);
    }

    /**
     * How much of the top of the viewport the theme has already taken.
     *
     * Sites pin a course banner, a navbar, or both, to the top of the window, so scrolling
     * a step to the top of the viewport put its heading underneath them and the teacher
     * arrived at a step already scrolled past its own title. There is no way to ask a
     * theme how tall its header is, so this measures what is actually painted across the
     * top edge.
     *
     * @returns {Number} Height in pixels to stay clear of.
     */
    stickyOffset() {
        let offset = 0;
        const width = window.innerWidth || document.documentElement.clientWidth;
        const probes = [width * 0.5, width * 0.15, width * 0.85];
        probes.forEach((x) => {
            let found;
            try {
                found = document.elementsFromPoint(Math.round(x), 2) || [];
            } catch (e) {
                found = [];
            }
            found.forEach((element) => {
                if (!element || element === document.body || element === document.documentElement) {
                    return;
                }
                if (this.root.contains(element)) {
                    return;
                }
                const position = window.getComputedStyle(element).position;
                if (position !== 'fixed' && position !== 'sticky') {
                    return;
                }
                const rect = element.getBoundingClientRect();
                if (rect.top <= 2 && rect.bottom > offset) {
                    offset = rect.bottom;
                }
            });
        });
        return Math.min(offset, (window.innerHeight || 800) / 3);
    }

    /**
     * Bring an element to rest just below whatever the theme has pinned to the top.
     *
     * @param {Element} element The element to bring into view.
     * @returns {void}
     */
    scrollBelowHeader(element) {
        if (!element) {
            return;
        }
        const top = element.getBoundingClientRect().top + window.pageYOffset
            - this.stickyOffset() - 16;
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({top: Math.max(0, top), behavior: reduced ? 'auto' : 'smooth'});
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
        this.syncOther(group, true);
        this.dirty = true;
    }

    /**
     * Show or hide the detail box that belongs to a group's "Other" option.
     *
     * Choosing Other and moving on sent the service the literal word "other", which
     * tells it nothing. The box asks for the one thing Other is missing, and is only
     * in the way when Other is not the answer.
     *
     * @param {HTMLElement} group The option group.
     * @returns {void}
     */
    syncOther(group, focus) {
        const detail = this.root.querySelector(`[data-otherfor="${group.dataset.group}"]`);
        if (!detail) {
            return;
        }
        const chosen = group.querySelector('[aria-pressed="true"]');
        const wanted = !!chosen && chosen.dataset.value === 'other';
        const field = detail.closest('.aibs-otherfield') || detail;
        const wasHidden = field.hidden;
        field.hidden = !wanted;
        // Only ever move the caret in response to the click that revealed the box.
        // Called from init() or from a suggestion it would drag the page about, and
        // land a screen reader in a text box instead of at the heading.
        if (wanted && focus && wasHidden) {
            // The box has only just stopped being hidden, and an element with no layout
            // box yet cannot take focus, so this waits for the frame that gives it one.
            window.requestAnimationFrame(() => detail.focus());
        }
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
    /**
     * Show or hide the busy indicator.
     *
     * @param {Boolean} busy Whether work is in progress.
     * @param {String} [message] Status text to show.
     * @param {Boolean} [showEta] Whether to show how long this usually takes. Only the
     *     long operations pass this: quoting minutes beside a one-second suggestion
     *     would read as a warning rather than as reassurance.
     * @returns {void}
     */
    setBusy(busy, message, showEta) {
        this.busy = busy;
        const loading = this.root.querySelector(SELECTORS.loading);
        if (loading) {
            loading.hidden = !busy;
        }
        const text = this.root.querySelector(SELECTORS.loadingText);
        if (text && message) {
            text.textContent = message;
        }
        const eta = this.root.querySelector(SELECTORS.loadingEta);
        if (eta) {
            eta.hidden = !(busy && showEta);
        }
        this.root.querySelectorAll(
            '[data-action="generate"], [data-action="publish"], [data-action="populate"], [data-action="suggest"]'
        ).forEach((button) => {
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
        const message = (error && error.message) ? error.message : this.strings['error:generic'];
        region.textContent = message;
        region.hidden = false;
        this.scrollBelowHeader(region);
        // The region is at the top of a long form. Scrolling to it helps a sighted
        // teacher who is already looking at the page; the toast is for the one who
        // pressed a button at the bottom and is watching that button.
        Toast.show(message, 'error');
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
                Toast.show(this.strings.saved, 'success');
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
        // Filling the wizard now takes a populate call and up to eight suggestions, so
        // the window in which a teacher can press the button again is long enough that
        // they will. A second press would race the first, writing two different
        // snapshots into the same fields and spending the credits twice.
        if (this.busy) {
            return false;
        }
        this.clearError();
        this.setBusy(true);
        try {
            // The whole of what the teacher has chosen so far goes up, not just the
            // source content, so that a chosen industry shapes everything that comes
            // back rather than being ignored.
            const response = await this.call('populate_wizard', {source: this.collect()});
            this.apply(response.source);
            await this.fillGaps();
            this.dirty = true;
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Fill any field the populate response left empty.
     *
     * Populate answers with the shape shared by the other LMS Labs plugins, which
     * covers a handful of the wizard's fields and none of its pickers. Rather than
     * leave a teacher to choose a setting, an atmosphere, the complications and the
     * stakes by hand, whatever is still empty afterwards is asked for one field at a
     * time. Fields already carrying a value are never touched, so a considered answer
     * is not overwritten and a second press costs nothing for what is already filled.
     *
     * @returns {Promise} Resolves once every empty field has been attempted.
     */
    async fillGaps() {
        const empty = this.emptyFields();
        if (!empty.length) {
            return true;
        }

        // Small batches: enough to keep the wait short, few enough that a slow service
        // is not hit with fourteen requests at once.
        const batch = 4;
        for (let i = 0; i < empty.length; i += batch) {
            const slice = empty.slice(i, i + batch);
            this.setBusy(true, this.strings.fillingfields);
            const source = this.collect();
            const answers = await Promise.all(slice.map((field) =>
                this.call('suggest_field', {field: field, source: source})
                    .then((response) => ({field: field, response: response}))
                    .catch((error) => ({field: field, error: error}))
            ));
            let failure = null;
            answers.forEach((answer) => {
                if (answer.error) {
                    failure = failure || answer.error;
                    return;
                }
                this.applySuggestion(answer.field, answer.response);
            });
            // Swallowing these left a teacher looking at a half-filled wizard with no
            // idea why. The commonest cause is the daily quota, and once that is reached
            // every remaining request would fail too, so the run stops and says so.
            if (failure) {
                this.showError(failure);
                return false;
            }
        }
        return this.fillCharacters();
    }

    /** @type {Number} How many people a scenario is given when nobody has been named. */
    static get CHARACTER_TARGET() {
        return 2;
    }

    /**
     * Give the scenario people to be difficult with.
     *
     * One request returns one person, so asking once filled Character 1 and left the
     * rest blank. These have to be asked for one at a time rather than in a batch: each
     * request carries the people already named, which is the only thing stopping the
     * service from offering the same person again.
     *
     * Two is the target rather than the four slots on the page. A scene with the learner
     * and two others is as many as an illustration can hold and as many as a five
     * decision scenario can give anything to do; the remaining slots are there for a
     * teacher who wants them.
     *
     * @returns {Promise} Resolves once the scenario has people, or the service refuses.
     */
    async fillCharacters() {
        for (let attempt = 0; attempt < Wizard.CHARACTER_TARGET; attempt++) {
            const source = this.collect();
            const named = Array.isArray(source.characters) ? source.characters.length : 0;
            if (named >= Wizard.CHARACTER_TARGET || named >= this.characterSlots()) {
                return true;
            }
            let response;
            try {
                response = await this.call('suggest_field', {field: 'characterfull', source: source});
            } catch (error) {
                this.showError(error);
                return false;
            }
            if (!this.applyCharacter(response.suggestion || '')) {
                // Nothing usable came back. Asking again would spend another credit on
                // the same answer.
                return true;
            }
        }
        return true;
    }

    /**
     * How many character slots the page offers.
     *
     * @returns {Number} The slot count.
     */
    characterSlots() {
        return this.root.querySelectorAll('[data-character]').length;
    }

    /**
     * Which of the fields worth filling are still empty.
     *
     * @returns {Array} Field names, in the order the wizard presents them.
     */
    emptyFields() {
        const order = [
            'title', 'audience', 'industry', 'setting', 'atmosphere', 'openingsituation',
            'centralproblem', 'whyhard', 'stakes', 'participantrole',
        ];
        const current = this.collect();
        return order.filter((field) => {
            const value = current[field];
            if (Array.isArray(value)) {
                return value.length === 0;
            }
            if (typeof value === 'undefined' || String(value).trim() === '') {
                return true;
            }
            // Every single-choice picker is rendered with a schema default already
            // pressed, so "has a value" does not mean "somebody chose it". Treating a
            // default as an answer is what made industry, setting and atmosphere
            // impossible to fill.
            const group = this.root.querySelector(`[data-group="${field}"]`);
            return !!group && group.dataset.default === String(value);
        });
    }

    /**
     * Write one suggestion into the control it belongs to.
     *
     * @param {String} field The field the suggestion is for.
     * @param {Object} response The web service response.
     * @returns {void}
     */
    applySuggestion(field, response, replace) {
        const group = this.root.querySelector(`[data-group="${field}"]`);
        if (group) {
            // A picker is answered with option keys. The route returns them in `values`;
            // a model answering in text returns them comma separated, which is what the
            // field's specification asks for.
            const wanted = (response.values && response.values.length)
                ? response.values
                : String(response.suggestion || '').split(',').map((v) => v.trim()).filter(Boolean);
            const options = Array.from(group.querySelectorAll('[data-action="option"]'));
            const known = options.map((option) => option.dataset.value);
            const hits = wanted.filter((value) => known.indexOf(value) >= 0);
            // Nothing recognisable came back. Leaving the group alone is the only safe
            // move: clearing it would take away a choice the teacher had made.
            if (!hits.length) {
                return;
            }
            const multiple = group.dataset.multiple === '1';
            const chosen = multiple ? hits : hits.slice(0, 1);
            options.forEach((option) => {
                option.setAttribute('aria-pressed', chosen.indexOf(option.dataset.value) >= 0 ? 'true' : 'false');
            });
            this.syncOther(group);
            return;
        }

        const input = this.root.querySelector(`[data-field="${field}"]`);
        if (input && response.suggestion) {
            input.value = response.suggestion;
        } else if (input && !response.suggestion && !replace) {
            Toast.show(this.strings.nosuggestion, 'info');
            Notification.addNotification({message: this.strings.nosuggestion, type: 'info'});
        }
    }

    /**
     * Fill the first empty character from a single suggestion.
     *
     * The brief sent with the request asks for the four parts on one line separated by
     * vertical bars, so this splits on that rather than guessing at prose.
     *
     * @param {String} suggestion The suggested character.
     * @returns {Boolean} Whether a slot was filled.
     */
    applyCharacter(suggestion) {
        const parts = suggestion.split('|').map((part) => part.trim()).filter((part) => part !== '');
        // The brief asks for four bar-separated parts. A model that answers in prose
        // instead would otherwise have its whole sentence written into the name field
        // and stored as a person's name.
        if (parts.length < 2) {
            return false;
        }
        const keys = ['name', 'role', 'trait', 'appearance'];
        const fieldsets = this.root.querySelectorAll('[data-character]');
        for (const fieldset of fieldsets) {
            const name = fieldset.querySelector('[data-character-field="name"]');
            if (name && name.value.trim() !== '') {
                continue;
            }
            keys.forEach((key, index) => {
                const element = fieldset.querySelector(`[data-character-field="${key}"]`);
                if (element && parts[index]) {
                    element.value = parts[index];
                }
            });
            return true;
        }
        return false;
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
            this.syncOther(group);
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
        const fields = String(element.dataset.suggest || '').split(',')
            .map((name) => name.trim()).filter((name) => name !== '');
        if (!fields.length) {
            return false;
        }
        // A Suggest button sits under one box but belongs to the whole step. Rewriting
        // the central problem and leaving the complications and the stakes describing
        // the previous idea left the step contradicting itself.
        if (fields.length > 1) {
            return this.suggestFields(fields);
        }
        const field = fields[0];
        this.clearError();
        this.setBusy(true);
        try {
            const response = await this.call('suggest_field', {field: field, source: this.collect()});
            const input = this.root.querySelector('[data-field="' + field + '"]');
            if (input && response.suggestion) {
                input.value = response.suggestion;
                this.dirty = true;
            } else if (!response.suggestion) {
                Toast.show(this.strings.nosuggestion, 'info');
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

        // The one button in the product that spends money used to go straight from
        // click to request: no balance, no estimate, and no warning that it replaces a
        // draft somebody may have spent an hour editing.
        if (!await this.confirmGeneration()) {
            return false;
        }

        this.setBusy(true, this.strings.generationqueued, true);
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
     * Ask before spending credits, and say what the run will cost.
     *
     * @returns {Promise} Resolves true when the teacher confirms.
     */
    async confirmGeneration() {
        let plan;
        try {
            plan = await this.call('get_generation_plan', {});
        } catch (error) {
            // The estimate is a courtesy. Losing it should not stop a teacher who has
            // decided to generate, but the warning about replacing a draft still holds.
            plan = null;
        }

        const lines = [];
        if (plan) {
            // The price comes first, because it is the thing a teacher is deciding about.
            lines.push(await getString('confirmgenerate:price', 'mod_aibranchedscenario', {
                price: plan.price,
                images: plan.images,
                narrations: plan.narrations,
            }));
            // Deliberately carries no credit figure of its own: the price above is the
            // number that is deducted, and a second, smaller credit number beside it read
            // as a contradiction rather than as extra detail.
            lines.push(await getString('confirmgenerate:cost', 'mod_aibranchedscenario', {
                scenes: plan.scenes,
                images: plan.images,
            }));
            if (plan.balanceknown && !plan.unlimited) {
                lines.push(await getString('confirmgenerate:balance', 'mod_aibranchedscenario', plan.credits));
            }
            if (plan.allowancelimited && plan.allowanceremaining < plan.estimate) {
                lines.push(await getString('confirmgenerate:allowance', 'mod_aibranchedscenario',
                    plan.allowanceremaining));
            }
            if (plan.replacesdraft) {
                lines.push(await getString('confirmgenerate:replaces', 'mod_aibranchedscenario'));
            }
        } else {
            lines.push(await getString('confirmgenerate:replaces', 'mod_aibranchedscenario'));
        }

        const modal = await ModalSaveCancel.create({
            title: await getString('confirmgenerate:title', 'mod_aibranchedscenario'),
            body: lines.map((line) => `<p>${line}</p>`).join(''),
        });
        modal.setSaveButtonText(await getString('generatescenario', 'mod_aibranchedscenario'));

        return new Promise((resolve) => {
            modal.getRoot().on(ModalEvents.save, () => resolve(true));
            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
                resolve(false);
            });
            modal.show();
        });
    }

    /**
     * Put back the working copy the last generation replaced.
     *
     * @returns {Promise} Resolves once the page has been reloaded, or the undo refused.
     */
    async restoreDraft() {
        if (this.busy) {
            return false;
        }
        this.clearError();
        this.setBusy(true);
        try {
            const response = await this.call('restore_draft', {});
            if (response.restored) {
                this.dirty = false;
                window.location.reload();
                return true;
            }
            Toast.show(this.strings['restore:nothing'], 'info');
            Notification.addNotification({message: this.strings['restore:nothing'], type: 'info'});
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
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
                this.setBusy(true, this.strings.generationrunning, true);
            }
            if (status.status === 'ready') {
                // A run where some or all of the images failed used to look identical
                // to a complete one, so the teacher published a revision with gaps.
                if (status.mediamessage) {
                    this.setBusy(false);
                    await Notification.alert(
                        this.strings.generationready,
                        status.mediamessage
                    );
                }
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
            Toast.show(this.strings.published, 'success');
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
    /**
     * Refresh every field a Suggest button covers.
     *
     * These go together or not at all: the answers have to describe one situation, and
     * a picker rewritten to match a central problem that has itself just changed would
     * be describing the previous idea. Unlike the autofill pass, this deliberately
     * replaces values that are already there - the teacher pressed the button.
     *
     * @param {Array} fields Field names.
     * @returns {Promise} Resolves once every field has been attempted.
     */
    async suggestFields(fields) {
        if (this.busy) {
            return false;
        }
        this.clearError();
        this.setBusy(true, this.strings.fillingfields);
        try {
            const source = this.collect();
            const answers = await Promise.all(fields.map((field) =>
                this.call('suggest_field', {field: field, source: source})
                    .then((response) => ({field: field, response: response}))
                    .catch((error) => ({field: field, error: error}))
            ));
            const failure = answers.find((answer) => answer.error);
            answers.forEach((answer) => {
                if (!answer.error) {
                    this.applySuggestion(answer.field, answer.response, true);
                }
            });
            if (failure) {
                this.showError(failure.error);
                return false;
            }
            this.dirty = true;
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Copy a prompt the teacher can paste into ChatGPT or any other assistant.
     *
     * A site without LMS Labs credits, or a teacher who would rather iterate somewhere
     * else, still needs a way in. The prompt describes the definition this plugin
     * imports and carries the teacher's own source content with it, so what comes back
     * pastes straight into the box below.
     *
     * @returns {Promise} Resolves once the prompt is on the clipboard.
     */
    async copyPrompt() {
        const template = this.root.querySelector('[data-region="promptsource"]');
        const content = this.root.querySelector('[data-field="sourcecontent"]');
        if (!template) {
            return false;
        }
        const prompt = template.textContent.trim()
            + '\n\n----- SOURCE CONTENT -----\n\n'
            + (content ? content.value : '');
        try {
            await navigator.clipboard.writeText(prompt);
            Toast.show(this.strings.promptcopied, 'success');
            Notification.addNotification({message: this.strings.promptcopied, type: 'success'});
        } catch (error) {
            // Clipboard access is refused in some browsers and over plain HTTP. Showing
            // the prompt is the fallback: the teacher can still select and copy it.
            template.hidden = false;
        }
        return true;
    }

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
                // An import produces a finished scenario, so the teacher belongs at the
                // step where it is reviewed and published rather than back at the top of
                // a wizard they have just bypassed.
                const url = new URL(window.location.href);
                url.searchParams.set('step', String(this.stepCount));
                window.location.assign(url.toString());
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
            Toast.show(this.strings.saved, 'success');
            Notification.addNotification({message: this.strings.saved, type: 'success'});
            // Deliberately not clearing the wizard's dirty flag: this saved one scene,
            // not the wizard. Clearing it here disarmed the unsaved-changes guard for
            // an edit made on an earlier step, which was then lost without a prompt.
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
