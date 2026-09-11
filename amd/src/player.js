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
        // Set when the browser refuses to start sound without a gesture, and when the
        // current scene simply has no recording. Both look identical to a learner —
        // silence — so both have to be said out loud on the control.
        this.audioBlocked = false;
        this.audioUrl = '';
        // A screen can carry two recordings: the narrator reading the situation, which
        // starts on its own, and the character's own line, which the learner plays by
        // clicking the avatar. The way on is held back until both have been heard.
        this.speechUrl = '';
        this.narrationDone = true;
        this.speechDone = true;
        this.cueContext = null;
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
            'narration:on', 'narration:off', 'narration:blocked', 'narration:none',
            'narration:tipon', 'narration:tipoff', 'narration:tipblocked', 'narration:tipnone',
            'stageprogress', 'attemptfinished',
            'deltaup', 'deltadown', 'deltareduced', 'deltaraised', 'deltasame',
            'error:printblocked', 'listento', 'fullscreen:enter', 'fullscreen:exit',
            'deckposition',
        ];
        const values = await getStrings(keys.map((key) => ({key, component: 'mod_aibranchedscenario'})));
        keys.forEach((key, index) => {
            this.strings[key] = values[index];
        });

        this.syncStickyOffset();
        this.watchPinned();
        // The opening lesson is on the page before anything is asked of the server.
        this.startDeck(this.root.querySelector('[data-region="brief"] [data-region="deck"]'));
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => this.syncStickyOffset(), 150);
        });

        // The theme's header is not on screen in fullscreen, so the sticky bar's offset
        // has to be measured again in both directions.
        ['fullscreenchange', 'webkitfullscreenchange'].forEach((name) => {
            document.addEventListener(name, () => {
                this.syncStickyOffset();
                this.refreshFullscreenButton();
            });
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
        if (this.busy && action !== 'mute' && action !== 'print' && action !== 'speak'
                && action !== 'fullscreen') {
            return;
        }
        switch (action) {
            case 'showresult':
                this.showFinishedResult(parseInt(element.dataset.attemptid, 10));
                break;
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
            case 'speak':
                this.playSpeech(element);
                break;
            case 'fullscreen':
                this.toggleFullscreen();
                break;
            case 'deckprev':
                this.stepDeck(element, -1);
                break;
            case 'decknext':
                this.stepDeck(element, 1);
                break;
            case 'print':
                this.printDebrief();
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
        const context = Object.assign({}, node, {
            hasimage: Boolean(node.imageurl),
            hasspeaker: Boolean(node.speech) && Boolean(node.speaker),
            hasspeechaudio: Boolean(node.speechurl),
            speakerinitial: (node.speaker || '').trim().charAt(0).toUpperCase(),
        });
        await this.render(SELECTORS.node, 'mod_aibranchedscenario/node', context);
        this.hideRegion(SELECTORS.consequence);
        this.updateRail();
        this.fitSlide();
        this.playAudio(node.audiourl, node.speechurl);
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
        // Ring geometry. r = 28 in a 72 box, so the full circle is 2 * PI * 28.
        const circumference = 175.93;
        metricKeys.forEach((key) => {
            const before = response.before[key];
            const after = response.after[key];
            const change = after - before;
            // Tension is the one metric where the good direction is downwards, so a fall
            // there reads as a gain. Colouring by the sign alone put a green +12 next to a
            // red -8 on the same well-judged choice, which told the learner the opposite of
            // what happened. The wording spells the direction out rather than leaving a bare
            // signed number to be read as a score.
            const inverted = key === 'tension';
            const good = change === 0 ? false : (inverted ? change < 0 : change > 0);
            const size = Math.abs(change);
            let wording = this.strings.deltasame;
            if (change !== 0) {
                wording = inverted
                    ? (change < 0 ? this.strings.deltareduced : this.strings.deltaraised)
                    : (change > 0 ? this.strings.deltaup : this.strings.deltadown);
                wording = wording.replace('{$a}', size);
            }
            // Traffic light on the standing value rather than on the movement: a learner
            // wants to know where the room is now, and the arrow underneath says which way
            // it just moved. Tension reads the other way round, so it is inverted here too.
            const standing = inverted ? 100 - after : after;
            let tone = 'aibs-tone-warn';
            if (standing >= 67) {
                tone = 'aibs-tone-good';
            } else if (standing < 34) {
                tone = 'aibs-tone-bad';
            }
            const arc = (value) => {
                const bounded = Math.max(0, Math.min(100, value));
                const filled = (circumference * bounded) / 100;
                return filled.toFixed(1) + ' ' + circumference;
            };
            deltas.push({
                label: this.strings['metric:' + key],
                before: before,
                after: after,
                tone: tone,
                startdash: arc(before),
                dash: arc(after),
                good: good,
                bad: change !== 0 && !good,
                same: change === 0,
                change: wording,
            });
        });

        const context = {
            signal: response.signal,
            signalclass: 'aibs-signal-' + response.signal,
            signallabel: this.strings['signal:' + response.signal],
            ispositive: response.signal === 'positive',
            isneutral: response.signal === 'neutral',
            isnegative: response.signal === 'negative',
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
        this.fitSlide();
        this.animateRings();
        // A costly screen and a well-judged one look alike for the second it takes to
        // start reading. The cue says which it is before a word has been read.
        this.playCue(response.signal);
        // Consequence screens are narrated too. Scenarios generated before that was true
        // carry no clip for the branch, and passing the empty string moves the control to
        // its "nothing on this screen" state rather than leaving it claiming to be playing.
        this.playAudio(response.audiourl || '', '');
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
     * Show the debrief for an attempt the learner already finished.
     *
     * @param {Number} attemptid The finished attempt.
     * @returns {Promise} Resolves once the debrief is shown.
     */
    async showFinishedResult(attemptid) {
        if (!attemptid) {
            return false;
        }
        this.attemptId = attemptid;
        this.hideRegion(SELECTORS.brief);
        return this.showDebrief();
    }

    /**
     * Fetch and render the debrief for the finished attempt.
     *
     * @returns {Promise} Resolves once the debrief is shown.
     */
    async showDebrief() {
        // "Show the debrief" was offered in the activity settings, exported to the
        // template, and read by nothing: a teacher who turned it off got the debrief
        // anyway. When it is off the learner is told the scenario is finished and left
        // there, which is the whole point of turning it off.
        if (this.root.dataset.debrief === '0') {
            this.hideRegion(SELECTORS.node);
            this.hideRegion(SELECTORS.consequence);
            await this.render(SELECTORS.debrief, 'mod_aibranchedscenario/debriefhidden', {
                allowreplay: this.root.dataset.replay !== '0',
            });
            this.stopAudio();
            this.playAudio('', '');
            this.setRailComplete();
            this.focusRegion(SELECTORS.debrief);
            return true;
        }
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
                // The debrief is a deck rather than one long page, so the decisions are
                // handed over a page at a time. Two to a page: one reads as a lot of
                // clicking, three puts the last one under the fold again.
                journeypages: this.chunk(response.journey.map((entry) => Object.assign({}, entry, {
                    signalclass: 'aibs-signal-' + entry.signal,
                    hasfeedback: entry.feedbackparas.length > 0,
                })), 2),
            });
            this.hideRegion(SELECTORS.node);
            this.hideRegion(SELECTORS.consequence);
            await this.render(SELECTORS.debrief, 'mod_aibranchedscenario/debrief', context);
            // The debrief is read, not listened to: none of its slides carries audio, so
            // starting the deck also puts the control into its "nothing here" state.
            this.stopAudio();
            this.startDeck(this.root.querySelector(SELECTORS.debrief + ' [data-region="deck"]'));
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
     * Split a list into pages of a given size.
     *
     * @param {Array} items The list.
     * @param {Number} size How many to a page.
     * @returns {Array} One entry per page, each with first, last and its items.
     */
    chunk(items, size) {
        const pages = [];
        for (let start = 0; start < items.length; start += size) {
            const slice = items.slice(start, start + size);
            pages.push({
                first: start + 1,
                last: start + slice.length,
                decisions: slice,
            });
        }
        return pages;
    }

    /**
     * Start a deck on its first page.
     *
     * Two things in this plugin are decks: the opening lesson, which teaches the
     * principles with a worked example before the scenario starts, and the debrief, which
     * carries the outcome, the skills, every decision and the takeaways. Both were single
     * pages long enough that a learner scrolled past the half of it that mattered.
     *
     * @param {HTMLElement} deck The deck container.
     * @returns {void}
     */
    startDeck(deck) {
        if (!deck) {
            return;
        }
        this.showDeckSlide(deck, 0);
    }

    /**
     * Move a deck forward or back from whichever control was pressed.
     *
     * @param {HTMLElement} element The control.
     * @param {Number} direction 1 forwards, -1 back.
     * @returns {void}
     */
    stepDeck(element, direction) {
        const deck = element.closest('[data-region="deck"]');
        if (!deck) {
            return;
        }
        const current = parseInt(deck.dataset.slide, 10) || 0;
        this.showDeckSlide(deck, current + direction);
    }

    /**
     * Show one page of a deck.
     *
     * @param {HTMLElement} deck The deck container.
     * @param {Number} index Zero based page number.
     * @returns {void}
     */
    showDeckSlide(deck, index) {
        const slides = deck.querySelectorAll('[data-region="deckslide"]');
        if (!slides.length) {
            return;
        }
        const wanted = Math.max(0, Math.min(slides.length - 1, index));
        deck.dataset.slide = String(wanted);
        slides.forEach((slide, position) => {
            slide.hidden = position !== wanted;
        });

        const count = deck.querySelector('[data-region="deckcount"]');
        if (count) {
            count.textContent = this.strings.deckposition
                .replace('{$a->current}', wanted + 1)
                .replace('{$a->total}', slides.length);
        }
        const prev = deck.querySelector('[data-action="deckprev"]');
        const next = deck.querySelector('[data-action="decknext"]');
        if (prev) {
            prev.disabled = wanted === 0;
        }
        if (next) {
            next.disabled = wanted === slides.length - 1;
        }

        // A slide may carry its own narration - the opening lesson does, the debrief does
        // not - and the control follows whichever it is.
        this.playAudio(slides[wanted].dataset.audio || '', '');
        this.fitSlide();
        const heading = slides[wanted].querySelector('h3, h4, p');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({preventScroll: true});
        }
        this.scrollBelowHeader(deck);
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
        this.scrollBelowHeader(region);
    }

    /**
     * Park the plugin's sticky bar below whatever the theme has pinned above it.
     *
     * Two sticky elements both asking for top: 0 overlap, so the theme's course banner
     * would sit on top of the scenario's own bar. The measurement has to happen in the
     * browser because no theme publishes its header height, and it has to be redone on
     * resize because most of them collapse the banner at narrow widths.
     *
     * @returns {void}
     */
    syncStickyOffset() {
        const bar = this.root.querySelector('[data-region="topbar"]');
        if (!bar) {
            return;
        }
        // Measured with the bar's own contribution removed, or it would compound on
        // every call until it walked off the bottom of the screen.
        bar.style.setProperty('--aibs-sticky-top', '0px');
        const offset = this.stickyOffset();
        bar.style.setProperty('--aibs-sticky-top', Math.round(offset) + 'px');

        // A slide is only a slide if it fits on the screen. The text column used to set
        // the height and the picture stretched to match, so a wordy scene pushed its own
        // options below the fold - the thing the side-by-side layout existed to prevent.
        // The ceiling is what is left of the viewport once the theme's header and this
        // bar have taken their share, measured rather than guessed at.
        const barheight = Math.round(bar.getBoundingClientRect().height);
        const viewport = window.innerHeight || document.documentElement.clientHeight;
        const available = Math.max(320, viewport - Math.round(offset) - barheight - 48);
        this.root.style.setProperty('--aibs-slide-max', available + 'px');
    }

    /**
     * How much of the top of the viewport the theme has already taken.
     *
     * Sites pin a course banner, a navbar, or both, to the top of the window. Scrolling a
     * new scene to the top of the viewport therefore put its first line underneath them,
     * and the learner arrived at a scene already scrolled past its own opening. There is
     * no way to ask a theme how tall its header is, so this measures what is actually
     * painted across the top edge: anything fixed or sticky that covers the top of the
     * window is counted, and the tallest wins.
     *
     * @returns {Number} Height in pixels to stay clear of.
     */
    stickyOffset() {
        let offset = 0;
        const width = window.innerWidth || document.documentElement.clientWidth;
        // Several points across the edge, because a header may be split into pieces or
        // sit to one side.
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
                return;
            });
        });
        // A full height overlay would otherwise push the scene off the bottom of the
        // screen, so nothing is allowed to claim more than a third of it.
        const ceiling = (window.innerHeight || 800) / 3;
        return Math.min(offset, ceiling);
    }

    /**
     * Bring a region to rest just below whatever the theme has pinned to the top.
     *
     * @param {Element} region The element to bring into view.
     * @returns {void}
     */
    scrollBelowHeader(region) {
        const gap = 16;
        const top = region.getBoundingClientRect().top + window.pageYOffset
            - this.stickyOffset() - gap;
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({top: Math.max(0, top), behavior: reduced ? 'auto' : 'smooth'});
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
     * Track whether the bar is pinned, because it should not look pinned when it is not.
     *
     * The bar paints its background out past both edges so a course banner does not show
     * either side of it while the page scrolls under. At rest that flat band ran across
     * the top of the player and squared off its two top corners, which is what made them
     * look wrong against the rounded ones at the bottom. The bleed is now only worn while
     * the bar is actually stuck to the top.
     *
     * @returns {void}
     */
    watchPinned() {
        const bar = this.root.querySelector('[data-region="topbar"]');
        if (!bar) {
            return;
        }
        const update = () => {
            const top = bar.getBoundingClientRect().top;
            const offset = parseFloat(
                getComputedStyle(bar).getPropertyValue('--aibs-sticky-top')
            ) || 0;
            bar.classList.toggle('aibs-is-pinned', top <= offset + 1);
        };
        window.addEventListener('scroll', update, {passive: true});
        update();
    }

    /**
     * Shrink a screen until it fits, rather than letting it scroll.
     *
     * A slide that scrolls is not a slide: the options are the point of the screen and
     * they were going below the fold on wordy scenes. Nothing here is allowed to scroll,
     * so when the text does not fit the type steps down until it does. The floor is the
     * smallest size still comfortable to read; past that the learner is better served by
     * the fullscreen control than by six-point text.
     *
     * @returns {void}
     */
    fitSlide() {
        const slides = this.root.querySelectorAll('.aibs-slide, .aibs-consequence');
        slides.forEach((slide) => {
            const body = slide.querySelector('.aibs-node-body') || slide;
            slide.style.removeProperty('--aibs-fit');
            // Two frames, so the browser has laid the new screen out before it is measured.
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    let scale = 1;
                    let guard = 0;
                    while (body.scrollHeight > body.clientHeight + 1 && scale > 0.74 && guard < 14) {
                        scale -= 0.04;
                        guard++;
                        slide.style.setProperty('--aibs-fit', scale.toFixed(2));
                    }
                });
            });
        });
    }

    /**
     * Animate the consequence rings and count their numbers to the new value.
     *
     * @returns {void}
     */
    animateRings() {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.root.querySelectorAll('.aibs-ring-fill').forEach((ring) => {
            const to = ring.dataset.dash;
            if (reduced) {
                ring.style.strokeDasharray = to;
                return;
            }
            ring.style.strokeDasharray = ring.dataset.startdash || '0 175.93';
            ring.classList.add('aibs-is-animated');
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    ring.style.strokeDasharray = to;
                });
            });
        });

        this.root.querySelectorAll('.aibs-ring-value').forEach((value) => {
            const from = parseInt(value.dataset.from, 10);
            const to = parseInt(value.dataset.to, 10);
            if (isNaN(from) || isNaN(to) || reduced || from === to) {
                value.textContent = isNaN(to) ? value.textContent : String(to);
                return;
            }
            const started = window.performance ? window.performance.now() : Date.now();
            const duration = 900;
            const step = (now) => {
                const elapsed = Math.min(1, (now - started) / duration);
                // Ease out, so the number settles rather than stopping dead.
                const eased = 1 - Math.pow(1 - elapsed, 3);
                value.textContent = String(Math.round(from + ((to - from) * eased)));
                if (elapsed < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        });
    }

    /**
     * Sound the short cue that says whether a screen went well or badly.
     *
     * Synthesised rather than shipped as files: two tones carry the meaning, and a
     * plugin that ships no audio assets has nothing to license, localise or cache.
     *
     * @param {String} signal positive, neutral or negative.
     * @returns {void}
     */
    playCue(signal) {
        if (!this.audioEnabled || this.muted || signal === 'neutral') {
            return;
        }
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) {
            return;
        }
        try {
            if (!this.cueContext) {
                this.cueContext = new Context();
            }
            const ctx = this.cueContext;
            if (ctx.state === 'suspended' && ctx.resume) {
                ctx.resume();
            }
            // Rising major third for a good screen, falling minor third for a costly one.
            const notes = signal === 'positive' ? [523.25, 659.25] : [392.0, 311.13];
            notes.forEach((frequency, index) => {
                const at = ctx.currentTime + (index * 0.13);
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = frequency;
                gain.gain.setValueAtTime(0.0001, at);
                gain.gain.exponentialRampToValueAtTime(0.13, at + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.26);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(at);
                osc.stop(at + 0.3);
            });
        } catch (e) {
            // A browser that will not make a sound is not a reason to stop the scenario.
            this.cueContext = null;
        }
    }

    /**
     * Play the narration for a screen, when narration is enabled and not muted.
     *
     * @param {String} url The narrator's audio URL, or an empty string.
     * @param {String} speechurl The character's own line, or an empty string.
     * @returns {void}
     */
    playAudio(url, speechurl) {
        this.audioUrl = url || '';
        this.speechUrl = speechurl || '';
        this.narrationDone = !this.audioUrl;
        this.speechDone = !this.speechUrl;
        if (!this.audioEnabled) {
            this.narrationDone = true;
            this.speechDone = true;
            this.releaseWayOn();
            return;
        }
        this.audioBlocked = false;
        this.holdWayOn();
        if (!this.audioUrl || this.muted) {
            if (this.muted) {
                this.narrationDone = true;
                this.speechDone = true;
            }
            this.releaseWayOn();
            this.refreshAudioButton();
            return;
        }
        this.stopAudio();
        this.audio = new Audio(this.audioUrl);
        this.audio.addEventListener('ended', () => {
            this.narrationDone = true;
            this.releaseWayOn();
        });
        const started = this.audio.play();
        if (started && typeof started.catch === 'function') {
            started.catch(() => {
                // Browsers refuse to start sound before the person has interacted with
                // the page. Swallowing that left a silent player and a control claiming
                // narration was on, which is indistinguishable from being broken. The
                // refusal is now visible and the control becomes the way to start it.
                this.audioBlocked = true;
                // Nobody can listen to something the browser will not play, so the way on
                // is handed back rather than held behind a recording that never starts.
                this.narrationDone = true;
                this.speechDone = true;
                this.releaseWayOn();
                this.refreshAudioButton();
            });
        }
        this.refreshAudioButton();
    }

    /**
     * Play the character's own line, and stop the avatar asking to be clicked.
     *
     * @param {HTMLElement} element The avatar button.
     * @returns {void}
     */
    playSpeech(element) {
        if (!this.speechUrl || !this.audioEnabled) {
            return;
        }
        this.stopAudio();
        // The narrator is reading the same screen. Cutting it off is what a person
        // expects when they deliberately press something else.
        this.narrationDone = true;
        if (element) {
            element.classList.remove('aibs-is-unplayed');
            element.classList.add('aibs-is-played');
        }
        this.audio = new Audio(this.speechUrl);
        this.audio.addEventListener('ended', () => {
            this.speechDone = true;
            this.releaseWayOn();
        });
        const started = this.audio.play();
        if (started && typeof started.catch === 'function') {
            started.catch(() => {
                this.speechDone = true;
                this.releaseWayOn();
            });
        }
    }

    /**
     * Hold back the way on until the screen has been heard.
     *
     * Only the single Continue button is held. The lettered options are the learner's
     * own decision and are never taken away from them, because a disabled decision with
     * no explanation is indistinguishable from a broken page.
     *
     * @returns {void}
     */
    holdWayOn() {
        if (this.narrationDone && this.speechDone) {
            return;
        }
        this.root.querySelectorAll('[data-action="continue"], .aibs-continuebtn').forEach((button) => {
            button.disabled = true;
            button.classList.add('aibs-is-waiting');
        });
    }

    /**
     * Give the way on back once everything on the screen has been heard.
     *
     * @returns {void}
     */
    releaseWayOn() {
        if (!this.narrationDone || !this.speechDone) {
            return;
        }
        this.root.querySelectorAll('[data-action="continue"], .aibs-continuebtn').forEach((button) => {
            button.disabled = false;
            button.classList.remove('aibs-is-waiting');
        });
    }

    /**
     * Fill the screen with the scenario, or give the page back.
     *
     * @returns {void}
     */
    toggleFullscreen() {
        const target = this.root;
        const active = document.fullscreenElement || document.webkitFullscreenElement;
        try {
            if (active) {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (exit) {
                    exit.call(document);
                }
                return;
            }
            const request = target.requestFullscreen || target.webkitRequestFullscreen;
            if (request) {
                const started = request.call(target);
                if (started && typeof started.catch === 'function') {
                    // A browser that refuses is not an error worth interrupting a
                    // scenario for; the control simply stays as it was.
                    started.catch(() => this.refreshFullscreenButton());
                }
            }
        } catch (e) {
            this.refreshFullscreenButton();
        }
    }

    /**
     * Put the fullscreen control into the state it is actually in.
     *
     * @returns {void}
     */
    refreshFullscreenButton() {
        const button = this.root.querySelector('[data-region="fullscreenbutton"]');
        if (!button) {
            return;
        }
        const active = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
        const label = this.strings[active ? 'fullscreen:exit' : 'fullscreen:enter'];
        button.setAttribute('title', label);
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        this.root.classList.toggle('aibs-is-fullscreen', active);
    }

    /**
     * Open the browser's print dialog for the debrief.
     *
     * @returns {void}
     */
    printDebrief() {
        try {
            if (typeof window.print === 'function') {
                window.print();
                return;
            }
        } catch (e) {
            // A frame that was given no permission to open a modal throws here rather
            // than printing, which is one way this button could look like it did nothing.
            try {
                if (window.top && window.top !== window && typeof window.top.print === 'function') {
                    window.top.print();
                    return;
                }
            } catch (crossorigin) {
                // A frame belonging to another site cannot be asked, and should not be.
                this.showError({message: this.strings['error:printblocked']});
                return;
            }
        }
        this.showError({message: this.strings['error:printblocked']});
    }

    /**
     * Put the narration control into the state it is actually in.
     *
     * @returns {void}
     */
    refreshAudioButton() {
        const button = this.root.querySelector('[data-region="mutebutton"]');
        if (!button) {
            return;
        }
        const label = button.querySelector('[data-region="mutelabel"]');
        let state = 'on';
        if (!this.audioUrl) {
            state = 'none';
        } else if (this.muted) {
            state = 'off';
        } else if (this.audioBlocked) {
            state = 'blocked';
        }
        const tips = {
            on: 'narration:tipon',
            off: 'narration:tipoff',
            blocked: 'narration:tipblocked',
            none: 'narration:tipnone',
        };
        if (label) {
            label.textContent = this.strings['narration:' + state];
        }
        button.setAttribute('title', this.strings[tips[state]]);
        button.setAttribute('aria-label', this.strings[tips[state]]);
        button.setAttribute('aria-pressed', state === 'on' ? 'true' : 'false');
        button.dataset.state = state;
        button.disabled = state === 'none';
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
        // When the browser blocked playback, this press is the gesture it was waiting
        // for, so the control plays rather than mutes. Anything else would ask the
        // learner to press it twice to hear one scene.
        if (this.audioBlocked && !this.muted && this.audioUrl) {
            this.audioBlocked = false;
            this.playAudio(this.audioUrl, this.speechUrl);
            this.refreshAudioButton();
            return;
        }
        this.muted = !this.muted;
        if (this.muted) {
            this.stopAudio();
            // Muting is a decision not to listen, so the way on is not held any longer.
            this.narrationDone = true;
            this.speechDone = true;
            this.releaseWayOn();
        } else if (this.audioUrl) {
            this.playAudio(this.audioUrl, this.speechUrl);
        }
        this.refreshAudioButton();
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
