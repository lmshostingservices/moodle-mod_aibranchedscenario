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
 * Drives the branching scenario player.
 *
 * The browser holds no scenario data of its own. It asks the server which node to
 * show, sends the identifier of the choice the learner picked, and renders whatever
 * the server sends back.
 *
 * @module     mod_aibranchedscenario/player
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';

const SELECTORS = {
    root: '[data-region="player"]',
    brief: '[data-region="brief"]',
    node: '[data-region="node"]',
    consequence: '[data-region="consequence"]',
    debrief: '[data-region="debrief"]',
    loading: '[data-region="loading"]',
    error: '[data-region="error"]',
    rail: '[data-region="rail"]',
    railStatus: '[data-region="railstatus"]',
    meters: '[data-region="meters"]',
};

/**
 * Player controller.
 */
class Player {

    /**
     * Create a controller bound to a player root element.
     *
     * @param {HTMLElement} root The player root element.
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.audioEnabled = root.dataset.audio === '1';
        this.muted = false;
        this.attemptId = 0;
        this.nextSeq = 1;
        this.step = 0;
        this.pendingNode = null;
        this.audio = null;
        this.strings = {};
        this.busy = false;
    }

    /**
     * Load the strings the module renders and bind the event delegate.
     *
     * @returns {Promise} Resolves once the player is ready.
     */
    async init() {
        const keys = [
            'signal:positive', 'signal:neutral', 'signal:negative',
            'continue', 'seewhathappened', 'metric:engagement', 'metric:trust',
            'metric:tension', 'error:generic', 'narrationon', 'narrationoff',
            'stageprogress', 'attemptfinished',
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

        return true;
    }

    /**
     * Route a delegated action.
     *
     * @param {String} action The action name.
     * @param {HTMLElement} element The element that was activated.
     * @returns {void}
     */
    handle(action, element) {
        if (this.busy && action !== 'mute' && action !== 'print') {
            return;
        }
        switch (action) {
            case 'start':
                this.startAttempt(false);
                break;
            case 'replay':
                this.startAttempt(true);
                break;
            case 'choose':
                this.choose(element);
                break;
            case 'continue':
                this.showPendingNode();
                break;
            case 'mute':
                this.toggleMute(element);
                break;
            case 'print':
                window.print();
                break;
            default:
                break;
        }
    }

    /**
     * Show or hide the busy indicator.
     *
     * @param {Boolean} busy Whether a request is in flight.
     * @returns {void}
     */
    setBusy(busy) {
        this.busy = busy;
        const loading = this.root.querySelector(SELECTORS.loading);
        if (loading) {
            loading.hidden = !busy;
        }
    }

    /**
     * Show an error message in the player rather than as a page-level alert.
     *
     * @param {Object} error The rejected web service response.
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
     * Hide the error region.
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
     * Start or resume an attempt.
     *
     * @param {Boolean} forceNew Whether to abandon any open attempt.
     * @returns {Promise} Resolves once the first node is on screen.
     */
    async startAttempt(forceNew) {
        this.clearError();
        this.setBusy(true);
        try {
            const response = await this.call('start_attempt', {forcenew: forceNew});
            this.attemptId = response.attemptid;
            this.nextSeq = response.nextseq;
            this.step = response.nextseq - 1;
            const brief = this.root.querySelector(SELECTORS.brief);
            if (brief) {
                brief.hidden = true;
            }
            this.hideRegion(SELECTORS.debrief);
            this.hideRegion(SELECTORS.consequence);
            this.updateMeters(response.metrics, null);
            await this.renderNode(response.node);
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Send the learner's choice and show its consequence.
     *
     * @param {HTMLElement} element The chosen button.
     * @returns {Promise} Resolves once the consequence is on screen.
     */
    async choose(element) {
        const nodeElement = element.closest('[data-nodeid]');
        if (!nodeElement) {
            return false;
        }
        this.clearError();
        this.setBusy(true);

        const buttons = nodeElement.querySelectorAll('[data-action="choose"]');
        buttons.forEach((button) => {
            button.disabled = true;
        });
        element.classList.add('aibs-is-chosen');

        try {
            const response = await this.call('submit_choice', {
                attemptid: this.attemptId,
                nodeid: nodeElement.dataset.nodeid,
                choiceid: element.dataset.choiceid,
                seq: this.nextSeq,
            });
            this.nextSeq = response.seq + 1;
            this.step = response.seq;
            this.stopAudio();
            this.updateMeters(response.after, response.before);
            this.pendingNode = response.finished ? null : response.node;
            this.finished = response.finished;
            await this.renderConsequence(response);
        } catch (error) {
            buttons.forEach((button) => {
                button.disabled = false;
            });
            element.classList.remove('aibs-is-chosen');
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Render the node the learner is now on.
     *
     * @param {Object} node The node payload.
     * @returns {Promise} Resolves once rendered.
     */
    async renderNode(node) {
        const context = Object.assign({}, node, {hasimage: Boolean(node.imageurl)});
        await this.render(SELECTORS.node, 'mod_aibranchedscenario/node', context);
        this.hideRegion(SELECTORS.consequence);
        this.updateRail();
        this.playAudio(node.audiourl);
        this.focusRegion(SELECTORS.node);
    }

    /**
     * Render the consequence of a decision.
     *
     * @param {Object} response The submit_choice response.
     * @returns {Promise} Resolves once rendered.
     */
    async renderConsequence(response) {
        const metricKeys = ['engagement', 'trust', 'tension'];
        const deltas = [];
        metricKeys.forEach((key) => {
            const before = response.before[key];
            const after = response.after[key];
            if (before === after) {
                return;
            }
            const change = after - before;
            deltas.push({
                label: this.strings['metric:' + key],
                before: before,
                after: after,
                up: change > 0,
                down: change < 0,
                same: change === 0,
                change: (change > 0 ? '+' : '') + change,
            });
        });

        const context = {
            signal: response.signal,
            signalclass: 'aibs-signal-' + response.signal,
            signallabel: this.strings['signal:' + response.signal],
            consequenceparas: response.consequenceparas,
            feedbackparas: response.feedbackparas,
            hasfeedback: response.feedbackparas.length > 0,
            principle: response.principle,
            deltas: deltas,
            finished: response.finished,
            continuelabel: response.finished ? this.strings.seewhathappened : this.strings.continue,
        };

        this.hideRegion(SELECTORS.node);
        await this.render(SELECTORS.consequence, 'mod_aibranchedscenario/consequence', context);
        this.updateRail();
        this.focusRegion(SELECTORS.consequence);
    }

    /**
     * Move on from a consequence to the next node, or to the debrief.
     *
     * @returns {Promise} Resolves once the next screen is shown.
     */
    async showPendingNode() {
        this.clearError();
        if (this.pendingNode) {
            const node = this.pendingNode;
            this.pendingNode = null;
            this.hideRegion(SELECTORS.consequence);
            await this.renderNode(node);
            return true;
        }
        return this.showDebrief();
    }

    /**
     * Fetch and render the debrief for the finished attempt.
     *
     * @returns {Promise} Resolves once the debrief is shown.
     */
    async showDebrief() {
        this.setBusy(true);
        try {
            const response = await this.call('get_debrief', {attemptid: this.attemptId});
            const context = Object.assign({}, response, {
                outcomeclass: 'aibs-outcome-' + response.outcome,
                hassourceconnection: response.sourceconnectionparas.length > 0,
                hascritical: response.criticaldecisions.length > 0,
                haspractice: response.practice.length > 0,
                hastakeaways: response.takeaways.length > 0,
                allowreplay: this.root.dataset.replay !== '0',
                radar: response.radar.map((entry) => Object.assign({}, entry, {
                    percent: Math.round(entry.value * 100),
                })),
                journey: response.journey.map((entry) => Object.assign({}, entry, {
                    signalclass: 'aibs-signal-' + entry.signal,
                    hasfeedback: entry.feedbackparas.length > 0,
                })),
            });
            this.hideRegion(SELECTORS.node);
            this.hideRegion(SELECTORS.consequence);
            await this.render(SELECTORS.debrief, 'mod_aibranchedscenario/debrief', context);
            this.animateSkillBars();
            this.setRailComplete();
            this.focusRegion(SELECTORS.debrief);
        } catch (error) {
            this.showError(error);
        } finally {
            this.setBusy(false);
        }
        return true;
    }

    /**
     * Render a template into one of the player regions.
     *
     * @param {String} selector The region selector.
     * @param {String} template The template name.
     * @param {Object} context The template context.
     * @returns {Promise} Resolves once rendered.
     */
    async render(selector, template, context) {
        const region = this.root.querySelector(selector);
        if (!region) {
            return false;
        }
        const {html, js} = await Templates.renderForPromise(template, context);
        Templates.replaceNodeContents(region, html, js);
        region.hidden = false;
        return true;
    }

    /**
     * Hide a region and empty it.
     *
     * @param {String} selector The region selector.
     * @returns {void}
     */
    hideRegion(selector) {
        const region = this.root.querySelector(selector);
        if (region) {
            region.hidden = true;
            region.innerHTML = '';
        }
    }

    /**
     * Move focus to a region so keyboard and screen reader users follow the story.
     *
     * @param {String} selector The region selector.
     * @returns {void}
     */
    focusRegion(selector) {
        const region = this.root.querySelector(selector);
        if (!region) {
            return;
        }
        const heading = region.querySelector('h3, h4, p');
        const target = heading || region;
        target.setAttribute('tabindex', '-1');
        target.focus({preventScroll: true});
        region.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Update the dynamics meters, flagging which direction each moved.
     *
     * @param {Object} after The metrics after the decision.
     * @param {Object|null} before The metrics before the decision, when known.
     * @returns {void}
     */
    updateMeters(after, before) {
        const container = this.root.querySelector(SELECTORS.meters);
        if (!container) {
            return;
        }
        Object.keys(after).forEach((key) => {
            const meter = container.querySelector('[data-metric="' + key + '"]');
            if (!meter) {
                return;
            }
            const value = meter.querySelector('[data-region="metervalue"]');
            const fill = meter.querySelector('[data-region="meterfill"]');
            if (value) {
                value.textContent = after[key];
            }
            if (fill) {
                fill.style.width = after[key] + '%';
            }
            meter.classList.remove('aibs-is-rising', 'aibs-is-falling');
            if (before && after[key] > before[key]) {
                meter.classList.add('aibs-is-rising');
            } else if (before && after[key] < before[key]) {
                meter.classList.add('aibs-is-falling');
            }
        });
    }

    /**
     * Reflect progress on the rail and announce it.
     *
     * @returns {void}
     */
    updateRail() {
        const rail = this.root.querySelector(SELECTORS.rail);
        if (!rail) {
            return;
        }
        const steps = rail.querySelectorAll('.aibs-rail-step');
        steps.forEach((element, index) => {
            element.classList.remove('aibs-is-done', 'aibs-is-current');
            if (index < this.step) {
                element.classList.add('aibs-is-done');
            } else if (index === this.step) {
                element.classList.add('aibs-is-current');
            }
        });
        const status = this.root.querySelector(SELECTORS.railStatus);
        if (status && steps.length) {
            status.textContent = this.strings.stageprogress
                .replace('{$a->current}', Math.min(this.step + 1, steps.length))
                .replace('{$a->total}', steps.length);
        }
    }

    /**
     * Mark every rail step complete once the attempt has finished.
     *
     * @returns {void}
     */
    setRailComplete() {
        const rail = this.root.querySelector(SELECTORS.rail);
        if (!rail) {
            return;
        }
        rail.querySelectorAll('.aibs-rail-step').forEach((element) => {
            element.classList.remove('aibs-is-current');
            element.classList.add('aibs-is-done');
        });
        const status = this.root.querySelector(SELECTORS.railStatus);
        if (status) {
            status.textContent = this.strings.attemptfinished;
        }
    }

    /**
     * Animate the debrief skill bars from zero to their value.
     *
     * @returns {void}
     */
    animateSkillBars() {
        const fills = this.root.querySelectorAll('.aibs-skill-fill');
        fills.forEach((fill) => {
            const target = fill.style.width;
            fill.style.width = '0%';
            fill.classList.add('aibs-is-animated');
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    fill.style.width = target;
                });
            });
        });
    }

    /**
     * Play the narration for a node, when narration is enabled and not muted.
     *
     * @param {String} url The audio URL, or an empty string.
     * @returns {void}
     */
    playAudio(url) {
        if (!this.audioEnabled || this.muted || !url) {
            return;
        }
        this.stopAudio();
        this.audio = new Audio(url);
        this.audio.play().catch(() => {
            // Autoplay was refused by the browser; the learner can still read the scene.
            return true;
        });
    }

    /**
     * Stop any narration that is playing.
     *
     * @returns {void}
     */
    stopAudio() {
        if (this.audio) {
            this.audio.pause();
            this.audio = null;
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    }

    /**
     * Toggle narration on and off.
     *
     * @param {HTMLElement} button The toggle button.
     * @returns {void}
     */
    toggleMute(button) {
        this.muted = !this.muted;
        button.setAttribute('aria-pressed', this.muted ? 'true' : 'false');
        button.textContent = this.muted ? this.strings.narrationoff : this.strings.narrationon;
        if (this.muted) {
            this.stopAudio();
        }
    }
}

/**
 * Initialise the player on the page.
 *
 * @param {Number} cmid The course module id.
 * @returns {Promise} Resolves once the player is ready.
 */
export const init = (cmid) => {
    const root = document.querySelector(SELECTORS.root + '[data-cmid="' + cmid + '"]');
    if (!root || root.dataset.initialised === '1') {
        return Promise.resolve(false);
    }
    root.dataset.initialised = '1';
    const player = new Player(root);
    return player.init();
};
