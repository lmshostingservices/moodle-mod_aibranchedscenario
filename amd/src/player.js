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
import * as Scheme from 'mod_aibranchedscenario/scheme';

const SELECTORS = {
    root: '[data-region="player"]',
    brief: '[data-region="brief"]',
    node: '[data-region="node"]',
    consequence: '[data-region="consequence"]',
    debrief: '[data-region="debrief"]',
    loading: '[data-region="loading"]',
    error: '[data-region="error"]',
    railStatus: '[data-region="railstatus"]',
    meters: '[data-region="meters"]',
};

/**
 * Player controller.
 */
class Player {

    /**
     * The smallest a slide may be and still be worth framing, in pixels.
     *
     * Below this there is not enough screen for a picture, a paragraph and a set of
     * options, so the player stops holding the slide to one screen and lets the page
     * scroll instead - which is honest, rather than hiding the overflow of a box that
     * was never going to fit.
     *
     * @type {Number}
     */
    static MIN_FRAME = 420;

    /**
     * The largest a slide is allowed to be, in pixels.
     *
     * The frame used to take whatever was left of the window, which on a tall screen made
     * a very tall slide: the picture column is capped in width by the player, so growing
     * only the height crops a 16:9 photograph into a letterbox on its side and leaves the
     * text floating in the middle of an enormous card. There is no need to use every pixel.
     * A slide is capped at a size that suits its own proportions, and anyone who wants the
     * whole screen has the fullscreen control for exactly that.
     *
     * @type {Number}
     */
    static MAX_FRAME = 620;

    /**
     * The largest the type is allowed to grow when a screen has room to spare.
     *
     * The fit scale only ever stepped down, which is right on a page - the frame is sized
     * to the page and the type is what gives. In fullscreen it was wrong: the frame grew to
     * the whole screen and the type did not follow it, so a learner who went fullscreen got
     * the same words they had before with a great deal more white around them. The scale
     * grows there now, and this is where it stops - past about half again the line length
     * runs past what is comfortable to read and the screen starts to look like a slide made
     * for a room rather than one made for a person.
     *
     * @type {Number}
     */
    /**
     * The ceiling on the type scale.
     *
     * 1.5 filled the frame, which was the point - but at half again the body copy on a
     * sparse screen overtook its own heading and the card read as a large-print edition
     * rather than as a well-set one. A third larger uses the room without breaking the
     * relationship between the sizes in the scale.
     *
     * @var {Number}
     */
    static MAX_FIT = 1.3;

    /**
     * The smallest the type is allowed to get while a screen is being fitted.
     *
     * It was .74, and a screen that still did not fit there was left to overflow - which
     * on the debrief showed as a scrollbar inside the card, the one thing the fit exists
     * to avoid. A few more steps down is a better answer than a scrollbar on the page a
     * learner reads most carefully. Below this the type is smaller than the interface
     * around it and the screen stops looking deliberate, so the floor stays.
     *
     * @type {Number}
     */
    static MIN_FIT = 0.68;

    /**
     * The smallest the scenario title is allowed to get while it is being held to one row,
     * as a fraction of the size the stylesheet gives it.
     *
     * Past this the title is smaller than the text under it, which reads as a mistake
     * rather than as a heading. A title long enough to need more than this is allowed to
     * wrap - two rows of bar is better than a heading nobody can read.
     *
     * @type {Number}
     */
    static MIN_TITLE = 0.72;

    /**
     * How long a screen takes to leave, in milliseconds.
     *
     * Short enough that it never feels like waiting, long enough that the eye reads it as
     * one screen replacing another rather than as a flicker.
     *
     * @type {Number}
     */
    static LEAVE_MS = 160;

    /**
     * Height held for the deck arrows on screens that do not have them, in pixels.
     *
     * Zero since the arrows moved onto the slide's left and right edges: they take no
     * height of their own now, so there is none to hold for them.
     *
     * @type {Number}
     */
    static NAV_RESERVE = 0;

    /**
     * Create a controller bound to a player root element.
     *
     * @param {HTMLElement} root The player root element.
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.audioEnabled = root.dataset.audio === '1';
        this.showMetrics = root.dataset.metrics !== '0';
        // Where a reading stops counting as good and where it becomes a problem. These were
        // two numbers written into this file, which meant a de-escalation exercise and a
        // sales conversation were told the same thing about a trust of 55.
        this.bandGreen = parseInt(root.dataset.bandgreen, 10);
        this.bandRed = parseInt(root.dataset.bandred, 10);
        if (isNaN(this.bandGreen)) {
            this.bandGreen = 67;
        }
        if (isNaN(this.bandRed)) {
            this.bandRed = 34;
        }
        // When the teacher has asked for it, the way on is genuinely closed while a screen
        // is being read rather than merely dimmed.
        this.requireListen = root.dataset.requirelisten === '1';
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
        this.hoverRow = null;
        this.attemptId = 0;
        this.nextSeq = 1;
        this.step = 0;
        // The narrative stage of the screen on show, which is what the bar counts. It is
        // not the number of clicks: a beat is a click and is not a decision.
        this.stage = 1;
        this.pendingNode = null;
        this.sceneImage = '';
        // Every decision made in this attempt, in order. Seeded from the server on resume
        // so the record is the attempt's, not the browser session's.
        this.journey = [];
        this.audio = null;
        this.wayOnTimer = null;
        // The frame is measured once and then held. Recomputing it on every screen made it
        // a slightly different size on a lesson slide, a decision and a consequence, so the
        // card grew and shrank as the learner moved through. It is re-measured only when
        // the window itself changes.
        this.frameHeight = 0;
        this.frameMeasured = false;
        this.strings = {};
        this.fanfarePlayed = false;
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
            'deckposition', 'standing:strong', 'standing:mixed', 'standing:weak',
        ];
        const values = await getStrings(keys.map((key) => ({key, component: 'mod_aibranchedscenario'})));
        keys.forEach((key, index) => {
            this.strings[key] = values[index];
        });

        this.syncStickyOffset();
        this.watchPinned();
        this.startMeters();
        // The opening lesson is on the page before anything is asked of the server.
        this.startDeck(this.root.querySelector('[data-region="brief"] [data-region="deck"]'));
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => {
                // The window is the only thing that may change the frame.
                this.frameHeight = 0;
                this.frameMeasured = false;
                this.root.style.removeProperty('--aibs-slide-max');
                this.syncStickyOffset();
                this.fitSlide();
            }, 150);
        });

        // The theme's header is not on screen in fullscreen, so the sticky bar's offset
        // has to be measured again in both directions.
        ['fullscreenchange', 'webkitfullscreenchange'].forEach((name) => {
            document.addEventListener(name, () => {
                this.frameHeight = 0;
                this.frameMeasured = false;
                this.root.style.removeProperty('--aibs-slide-max');
                this.syncStickyOffset();
                this.fitSlide();
                this.refreshFullscreenButton();
            });
        });

        // Delegated, because the options are re-rendered on every screen: a listener bound
        // to the rows themselves would be bound to rows that no longer exist by the second
        // decision.
        this.root.addEventListener('pointerover', (event) => {
            const row = event.target.closest
                ? event.target.closest('[data-action="choose"]')
                : null;
            if (row) {
                this.playHover(row);
            } else {
                this.hoverRow = null;
            }
        });
        this.root.addEventListener('pointerleave', () => {
            this.hoverRow = null;
        }, true);

        this.root.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action]');
            if (!target || !this.root.contains(target)) {
                return;
            }
            event.preventDefault();
            // The click ends here. A theme is free to bind its own handlers to the page -
            // one of them was opening the course index drawer when the metrics legend was
            // clicked - and none of them has any business acting on a control inside the
            // player.
            event.stopPropagation();
            this.handle(target.dataset.action, target);
        });

        // A panel opened from the bar closes when the learner looks elsewhere, the way a
        // menu does. Both listeners are on the document because the point is what happens
        // outside the player, not inside it.
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.aibs-legend')) {
                this.closeLegend();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeLegend();
            }
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
            case 'legend':
                this.toggleLegend(element);
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
        // Smooth scrolling started from script ignores the CSS that turns motion off, so
        // the preference is read here as well.
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        region.scrollIntoView({behavior: reduced ? 'auto' : 'smooth', block: 'center'});
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
            this.journey = Array.isArray(response.journey) ? response.journey.slice() : [];
            this.drawHistory();
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
            this.journey.push({
                seq: this.journey.length + 1,
                nodetitle: element.closest('[data-nodeid]')
                    ? (element.closest('[data-nodeid]').querySelector('.aibs-h3') || {}).textContent || ''
                    : '',
                choicetext: (element.querySelector('.aibs-choice-text') || {}).textContent || '',
                signal: response.signal,
            });
            this.drawHistory();
            this.pendingNode = response.finished ? null : response.node;
            this.finished = response.finished;
            // A consequence that says nothing is worse than no consequence at all. A beat
            // carries a synthesised choice with no prose, no feedback and no effects, so
            // pressing its Continue drew a screen showing the word "Neutral", three
            // readings that had not moved and a second Continue - two clicks to be told
            // nothing, in the middle of the scenario. Where there is genuinely nothing to
            // report, the screen is skipped and the learner goes on to what happens next.
            if (this.saysSomething(response)) {
                await this.leaveRegion(SELECTORS.node);
                await this.renderConsequence(response);
            } else {
                this.hideRegion(SELECTORS.consequence);
                await this.showPendingNode();
            }
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
            // Gated on the activity having narration at all, not merely on a recording
            // existing. A scenario whose teacher turned narration off still carries the
            // clips, so this used to render an armed play button that playSpeech() then
            // refused to act on - a control that looked live and did nothing at all.
            hasspeechaudio: Boolean(node.speechurl) && this.audioEnabled,
            speakerinitial: (node.speaker || '').trim().charAt(0).toUpperCase(),
        });
        // Kept so the consequence can show the scene the decision was taken in: the room
        // has not changed because the learner chose something in it.
        this.sceneImage = node.imageurl || '';
        // The bar and the card both said "Decision N" and they said different numbers.
        // The card printed the node's own narrative stage; the bar counted events, and an
        // event is logged for a beat as well as a decision, so four clicks through two
        // beats had the bar reading "Decision 5 of 5" while the card beside it said
        // "Decision 3". Two counters, two definitions, both on screen at once. There is
        // one number now and it is the node's, which is the one the learner can see.
        const stage = parseInt(node.stage, 10);
        if (stage > 0) {
            this.stage = stage;
        }
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
            // The colour was saying something the words never did: green meant "this is
            // high now" while the text underneath only ever described the movement. A
            // learner who cannot tell the colours apart was told "up 12" and nothing about
            // where it had got to. Each ring now says its standing in words as well.
            const band = this.band(standing);
            const tone = band.tone;
            const standingword = this.strings[band.word];
            const arc = (value) => {
                const bounded = Math.max(0, Math.min(100, value));
                const filled = (circumference * bounded) / 100;
                return filled.toFixed(1) + ' ' + circumference;
            };
            deltas.push({
                label: this.strings['metric:' + key],
                // The direction the number moved, drawn as an arrow, and the size on its
                // own. The worded form stays as the accessible text: an arrow plus a digit
                // is quick to read and says nothing to somebody listening to the page.
                isengagement: key === 'engagement',
                istrust: key === 'trust',
                istension: key === 'tension',
                isup: change > 0,
                isdown: change < 0,
                isflat: change === 0,
                amount: size,
                before: before,
                after: after,
                tone: tone,
                startdash: arc(before),
                dash: arc(after),
                good: good,
                bad: change !== 0 && !good,
                same: change === 0,
                change: wording,
                standing: standingword,
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
            hasimage: Boolean(this.sceneImage),
            imageurl: this.sceneImage,
            // The setting hid the readings in the bar and left them here, on the screen
            // where the numbers matter most and where the legend explaining them is no
            // longer reachable. It now means what it says on every screen.
            showdeltas: this.showMetrics,
            deltas: this.showMetrics ? deltas : [],
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
            await this.leaveRegion(SELECTORS.consequence);
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
            this.dropConfetti();
            this.focusRegion(SELECTORS.debrief);
            return true;
        }
        this.setBusy(true);
        try {
            const response = await this.call('get_debrief', {attemptid: this.attemptId});
            // The closing card is the last thing a learner sees and it said the same thing
            // after every run: a green tick, a blue figure and a grey pill. A tick means
            // "all correct", so it was telling someone who scored 32% that they had got it
            // right. The verdict, the mark, the pill and the figure all take the band the
            // score actually earned, and the tick is kept for the one run that earns it.
            const endpercent = Math.round(response.score);
            const endband = this.band(endpercent);
            const endperfect = endpercent >= 100;
            const verdictkey = endperfect ? 'endverdict:perfect' : {
                'aibs-tone-good': 'endverdict:strong',
                'aibs-tone-warn': 'endverdict:mixed',
                'aibs-tone-bad': 'endverdict:weak',
            }[endband.tone];
            // The scenario's own frames, walked once. Each page takes its own picture when
            // it has one and the next unused frame when it does not; the walk never
            // returns to a frame it has already handed out while an unused one remains.
            const spare = (response.sceneurls || []).slice();
            const used = new Set([response.whatmatteredimageurl, response.criticalimageurl,
                response.practiceimageurl, response.takeawaysimageurl, response.outcomeimageurl]
                .filter(Boolean));
            const pageimage = (own) => {
                if (own) {
                    return own;
                }
                while (spare.length) {
                    const next = spare.shift();
                    if (next && !used.has(next)) {
                        used.add(next);
                        return next;
                    }
                }
                return '';
            };
            const context = Object.assign({}, response, {
                outcomeclass: 'aibs-outcome-' + response.outcome,
                endtone: endband.tone,
                endperfect: endperfect,
                // One mark per band: a tick when it went well, a caution when it was mixed,
                // a cross when it cost. A green exclamation mark is a warning drawn in the
                // colour of a success, which tells a learner two contradictory things.
                endgood: endband.tone === 'aibs-tone-good',
                endwarn: endband.tone === 'aibs-tone-warn',
                endbad: endband.tone === 'aibs-tone-bad',
                endverdict: this.strings[verdictkey] || '',
                hassourceconnection: response.sourceconnectionparas.length > 0,
                hascritical: response.criticaldecisions.length > 0,
                haspractice: response.practice.length > 0,
                // Numbered here rather than in the payload: the web service returns these
                // as plain strings and other things read it, so the counting belongs to
                // the screen that draws the numbers. Each list starts again at one.
                whatmattered: this.numbered(response.whatmattered),
                criticaldecisions: this.numbered(response.criticaldecisions),
                practice: this.numbered(response.practice),
                hastakeaways: response.takeaways.length > 0,
                // Every screen is picture-left, including these - but for a long time every
                // one of them borrowed the SAME frame, the one the scenario opened on. The
                // picture is there so a page is remembered by it, and five pages carrying
                // one photograph are remembered by none of them.
                //
                // A page uses the frame briefed from its own words. Where that frame does
                // not exist - an older revision, or a generation that was refused - it
                // takes the next unused scene from the scenario instead of falling back to
                // the opening frame again, so no two pages in a row look alike.
                hasimage: Boolean(this.openingImage()),
                imageurl: this.openingImage(),
                whatmatteredimageurl: pageimage(response.whatmatteredimageurl),
                criticalimageurl: pageimage(response.criticalimageurl),
                practiceimageurl: pageimage(response.practiceimageurl),
                takeawaysimageurl: pageimage(response.takeawaysimageurl),
                outcomeimageurl: pageimage(response.outcomeimageurl),
                allowreplay: this.root.dataset.replay !== '0',
                radar: response.radar.map((entry) => {
                    const percent = Math.round(entry.value * 100);
                    // The grade was the one set of numbers with no colour on it at all,
                    // on the screen where the learner is being judged.
                    return Object.assign({}, entry, {
                        percent: percent,
                        tone: this.band(percent).tone,
                        standing: this.strings[this.band(percent).word],
                    });
                }),
                // The debrief is a deck rather than one long page, so the decisions are
                // handed over a page at a time. Two to a page: one reads as a lot of
                // clicking, three puts the last one under the fold again.
                journeypages: this.chunk(response.journey.map((entry) => Object.assign({}, entry, {
                    signalclass: 'aibs-signal-' + entry.signal,
                    hasfeedback: entry.feedbackparas.length > 0,
                })), 2),
                outcomeaudiourl: response.outcomeaudiourl || '',
                whatmatteredaudiourl: response.whatmatteredaudiourl || '',
                practiceaudiourl: response.practiceaudiourl || '',
                takeawaysaudiourl: response.takeawaysaudiourl || '',
            });
            this.hideRegion(SELECTORS.node);
            this.hideRegion(SELECTORS.consequence);
            await this.render(SELECTORS.debrief, 'mod_aibranchedscenario/debrief', context);
            // The debrief used to be read and not listened to - a decision taken here and
            // never stated anywhere a teacher could see it. A learner who turned narration
            // on heard every screen of the scenario and then silence for the half of the
            // product that explains what just happened. Its slides carry audio now, and the
            // deck plays each slide's clip as it is shown. Stopping the node's clip here
            // is right - it should not run on into the debrief - and is all that happens:
            // stopAudio() halts what is playing and sets no flag, so the deck's own clips
            // start normally.
            this.stopAudio();
            // The deck's own reveal runs each slide's arrivals as it is shown; animating
            // the skill bars here ran them against a slide that was still hidden.
            this.startDeck(this.root.querySelector(SELECTORS.debrief + ' [data-region="deck"]'));
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
                // The clip for a decision already exists - it is the one played on the
                // consequence screen when the learner made that choice. The page plays the
                // first of them, so the record of a decision has the voice the moment had.
                // The page used to be handed ONE url - `.find()` on the first decision that
                // had a clip - so a page holding two decisions read one of them and
                // stopped. The clips travel on the cards instead, which fixes that and buys
                // something else: the player knows which card each clip belongs to, so the
                // card being read can say so on screen.
                pageaudiourl: '',
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
        // Whatever this slide animates, it animates now that it can be seen.
        this.revealSlide(slides[wanted]);

        // The scenario is over, and the card that says so is on screen. The confetti is
        // already guarded against reduced motion and against running twice; the sound is
        // guarded by the learner's own narration setting, because somebody who turned the
        // voice off did not ask for a chime instead.
        // The celebration is for a perfect run and nothing else. It fired on every finish,
        // so a learner who had just been told their decisions cost the deal got confetti
        // and a chime for it, which reads as the product not having understood what
        // happened. The card says so itself now - it carries aibs-endperfect only when
        // every decision was the best one available.
        // The tick is reserved for a flawless run and the celebration is not the same
        // question: 88.8% and a strong outcome is a result worth marking, and a closing
        // card that sits in silence after one reads as the product having missed what
        // happened. The mark says "not every decision was the best available"; the
        // confetti and the chime say "you did well", and both are true at once.
        if (slides[wanted].classList.contains('aibs-deckend')
                && slides[wanted].classList.contains('aibs-tone-good')) {
            this.dropConfetti(slides[wanted]);
            this.playFanfare();
        }

        this.showPosition(wanted + 1, slides.length, this.strings.deckposition);
        const prev = deck.querySelector('[data-action="deckprev"]');
        const next = deck.querySelector('[data-action="decknext"]');
        // A disabled arrow at the end of the deck is a control that cannot do anything,
        // sitting where a learner expects one that can. It is taken away rather than
        // greyed: there is nothing after the last page, and saying so by leaving a dead
        // button there says it badly.
        if (prev) {
            prev.disabled = wanted === 0;
            prev.hidden = wanted === 0;
        }
        if (next) {
            next.disabled = wanted === slides.length - 1;
            next.hidden = wanted === slides.length - 1;
        }

        // A slide may carry its own narration - the opening lesson does, the debrief does
        // not - and the control follows whichever it is.
        // A slide may be one recording or several: the decision record puts two cards on a
        // page, each with a clip of its own. They are collected in the order they are read
        // in, so the queue and the cards stay in step and the card being read can be marked
        // on screen.
        if (slides[wanted].dataset.script) {
            this.runScript(slides[wanted]);
        } else {
            this.playAudio(this.slideClips(slides[wanted]), '');
        }
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
     * Let a screen leave before the next one arrives.
     *
     * Every screen animated in and none of them animated out: the decision vanished in the
     * same frame the consequence appeared, and the consequence vanished in the same frame
     * the next decision appeared. Each screen was polished on its own and the joins between
     * them were a jolt, which is what makes a sequence of good screens feel disjointed.
     *
     * It is a fade rather than a slide because the picture on the left does not change
     * between a decision and its consequence - the room is the same room - and sliding it
     * out and back in would say that it had.
     *
     * @param {String} selector The region selector.
     * @returns {Promise} Resolves once the screen has gone.
     */
    async leaveRegion(selector) {
        const region = this.root.querySelector(selector);
        if (!region || region.hidden || !region.firstElementChild) {
            this.hideRegion(selector);
            return;
        }
        // Somebody who has asked for less motion is asking for this too, and a leaving
        // screen is never worth waiting for if the animation cannot run.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.hideRegion(selector);
            return;
        }
        region.classList.add('aibs-is-leaving');
        await new Promise((resolve) => {
            window.setTimeout(resolve, Player.LEAVE_MS);
        });
        region.classList.remove('aibs-is-leaving');
        this.hideRegion(selector);
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

        // Everything else inside the player has to come off the total too, or the frame is
        // sized for space that is already spoken for and the page scrolls by exactly that
        // much. A flat allowance was guessing: the deck's arrows and the card's own
        // padding are between ninety and a hundred and fifty pixels depending on the
        // screen. They are measured instead. Heights do not move with the scroll position,
        // so this is right whether the page is at the top or not. The metric legend is not
        // in this list: it lives inside the bar now and opens over the scene, so the bar's
        // own height already accounts for it and measuring it again would double count.
        let chrome = this.outerHeight(bar, true) - barheight;

        // Nothing is held for the arrows any more: they sit on the slide's edges rather
        // than under it, so they cost no height on any screen.
        chrome += Player.NAV_RESERVE;
        const rootstyle = window.getComputedStyle(this.root);
        chrome += parseFloat(rootstyle.paddingBottom) || 0;

        // What is actually left, before any opinion about whether it is enough.
        const available = viewport - Math.round(offset) - barheight - chrome - 12;

        // Whether a slide can be a fixed frame is a question about how much room there is,
        // and only the browser knows that. It used to be asked as a media query - at least
        // 900 wide and 620 tall - which cannot see the theme's header or the course
        // banner above the player. On a 900x620 window under a 260px banner the query says
        // yes and there are 180 pixels to work with; the floor of 300 then produced a frame
        // taller than the space it was meant to fit into, and the overflow was hidden with
        // nothing to scroll. Measured across seventeen window sizes and three banner
        // heights, eleven of forty-eight combinations overflowed this way.
        //
        // So the question is answered from the measurement. Below the minimum a slide can
        // honestly be, the plugin stops pretending: the frame is dropped, the slide becomes
        // as tall as its content and the page scrolls, which is the right behaviour when
        // there is genuinely not enough screen. Above it, the frame is exactly the space
        // available and nothing goes below the fold.
        // Already measured: hand back the same answer rather than working out a new one.
        // Both halves of it are held - the height and whether there is a frame at all -
        // because a screen that decided there was no room and a screen that decided there
        // was would otherwise disagree, and the card would change shape between them.
        if (this.frameMeasured) {
            this.root.classList.toggle('aibs-no-frame', !this.frameHeight);
            this.root.style.setProperty('--aibs-slide-max', this.frameHeight + 'px');
            return Boolean(this.frameHeight);
        }

        const framed = available >= Player.MIN_FRAME;
        this.root.classList.toggle('aibs-no-frame', !framed);
        // Capped, not maximised - except in fullscreen, where using the whole screen is the
        // entire point of having asked for it. Keeping the cap there left a 620px card in
        // the middle of a 1080px screen, which is the opposite of what the button promises.
        const ceilingheight = this.isFullscreen() ? available : Math.min(available, Player.MAX_FRAME);
        const height = framed ? Math.round(ceilingheight) : 0;
        this.root.style.setProperty('--aibs-slide-max', height + 'px');
        if (!framed) {
            // Settled: there is no room here, and that does not change screen by screen.
            this.frameMeasured = true;
            this.frameHeight = 0;
        }
        return framed;
    }

    /**
     * An element's height including the margins that push things away from it.
     *
     * @param {HTMLElement} element The element.
     * @param {Boolean} marginsonly Return only the margins, not the box itself.
     * @returns {Number} Height in pixels.
     */
    outerHeight(element, marginsonly) {
        if (!element) {
            return 0;
        }
        const style = window.getComputedStyle(element);
        const margins = (parseFloat(style.marginTop) || 0) + (parseFloat(style.marginBottom) || 0);
        if (marginsonly) {
            return margins;
        }
        return element.getBoundingClientRect().height + margins;
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
        const width = window.innerWidth || document.documentElement.clientWidth;
        const height = window.innerHeight || 800;
        // A full height overlay would otherwise push the scene off the bottom of the
        // screen, so nothing is allowed to claim more than half of it.
        const ceiling = height / 2;

        // Pinned furniture stacks. A theme's navbar takes the first sixty pixels and a
        // course format's banner sits under it, pinned to the bottom of the navbar rather
        // than to the top of the window - the AI course format's hero is sticky from y 61
        // to y 181 on an activity page. Probing only the very top edge found the navbar,
        // missed the banner entirely, and parked this plugin's own bar underneath it.
        //
        // So the strip is walked downwards instead: anything fixed or sticky that touches
        // the band already claimed extends it, and the walk stops at the first gap.
        // The walk goes from one band to the next, not in fixed steps. Stepping by a fixed
        // amount looked right and was wrong: with a sixteenth of the ceiling as the stride,
        // a 945px window steps 30px at a time, so after claiming a 60px site bar the next
        // sample lands at 89px - past the four-pixel gap test - and the walk stops without
        // ever probing inside the course banner sitting at 60 to 187. It reported 60px of
        // pinned furniture where there were 187, and the player's own bar parked itself
        // underneath the banner.
        // Probing just below whatever has been claimed cannot miss the next band, however
        // tall or short either one is. The walk ends when a probe claims nothing new.
        let band = 0;
        for (let guard = 0; guard < 12; guard++) {
            const y = Math.min(Math.round(band) + 2, Math.round(ceiling));
            const before = band;
            [width * 0.5, width * 0.15, width * 0.85].forEach((x) => {
                let found;
                try {
                    found = document.elementsFromPoint(Math.round(x), Math.max(1, y)) || [];
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
                    // Only furniture that reaches what is already claimed can extend it,
                    // so a pinned button halfway down the page is not mistaken for a
                    // header.
                    if (rect.top <= band + 4 && rect.bottom > band) {
                        band = rect.bottom;
                    }
                });
            });

            // Nothing below the last band is pinned, so there is nothing more to find.
            if (band <= before || band >= ceiling) {
                break;
            }
        }
        return Math.min(band, ceiling);
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
            const spoken = meter.querySelector('[data-region="meterspoken"]');
            if (value) {
                // From whatever is on screen to the new reading, so the bar counts in
                // step with the rings on the consequence rather than snapping while they
                // sweep. Before is unknown on the opening render; then it counts from
                // what is already shown, which is the same number, and so does nothing.
                const from = before && typeof before[key] !== 'undefined'
                    ? Number(before[key])
                    : parseInt(value.textContent, 10);
                this.countUp(value, from, Number(after[key]));
            }
            if (spoken) {
                // The ring is a picture; this is the same reading in words, for anyone
                // who is listening to the page rather than looking at it.
                spoken.textContent = (meter.dataset.label || '') + ' ' + after[key];
            }
            this.drawMeter(fill, after[key]);
            this.toneMeter(meter, key, after[key]);
            meter.classList.remove('aibs-is-rising', 'aibs-is-falling');
            if (before && after[key] > before[key]) {
                meter.classList.add('aibs-is-rising');
            } else if (before && after[key] < before[key]) {
                meter.classList.add('aibs-is-falling');
            }
        });
    }

    /**
     * Which band a reading falls in, against the thresholds the teacher set.
     *
     * Used by every number the learner is shown: the readings in the bar, the rings on a
     * consequence, and the four skill bars in the debrief. One function, so a scenario
     * cannot say a value is good in one place and middling in another.
     *
     * @param {Number} standing A value from 0 to 100, already turned the right way up.
     * @returns {Object} {tone, word} - a CSS modifier and a string key.
     */
    band(standing) {
        if (standing >= this.bandGreen) {
            return {tone: 'aibs-tone-good', word: 'standing:strong'};
        }
        if (standing < this.bandRed) {
            return {tone: 'aibs-tone-bad', word: 'standing:weak'};
        }
        return {tone: 'aibs-tone-warn', word: 'standing:mixed'};
    }

    /**
     * Set a meter ring to a percentage.
     *
     * The ring is one circle with a dash pattern: the first number is how much of the
     * circumference is drawn, the second is the whole of it. Animating the first is what
     * makes the ring sweep round, and the CSS transition on it does the work.
     *
     * @param {?SVGCircleElement} ring The ring to draw.
     * @param {Number} percent Where to draw it to, 0 to 100.
     * @returns {void}
     */
    drawMeter(ring, percent) {
        if (!ring) {
            return;
        }
        const radius = parseFloat(ring.getAttribute('r')) || 15;
        const circumference = 2 * Math.PI * radius;
        const clamped = Math.max(0, Math.min(100, Number(percent) || 0));
        ring.setAttribute(
            'stroke-dasharray',
            ((clamped / 100) * circumference).toFixed(2) + ' ' + circumference.toFixed(2)
        );
    }

    /**
     * Colour a reading in the bar against the bands.
     *
     * @param {HTMLElement} meter The reading's container.
     * @param {String} key Which reading it is.
     * @param {Number} value Its current value.
     * @returns {void}
     */
    toneMeter(meter, key, value) {
        if (!meter) {
            return;
        }
        // Tension reads the other way up: a low tension is a good one.
        const standing = key === 'tension' ? 100 - Number(value) : Number(value);
        const tone = this.band(standing).tone;
        meter.classList.remove('aibs-tone-good', 'aibs-tone-warn', 'aibs-tone-bad');
        meter.classList.add(tone);
    }

    /**
     * Draw the meters for the first time, sweeping up from empty.
     *
     * The markup renders them at zero so that the opening sweep happens on the page the
     * learner is actually looking at, rather than being finished before it appears.
     *
     * @returns {void}
     */
    startMeters() {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.root.querySelectorAll('[data-region="meterfill"]').forEach((ring, index) => {
            const target = Number(ring.dataset.value) || 0;
            const meter = ring.closest('[data-metric]');
            this.toneMeter(meter, meter ? meter.dataset.metric : '', target);
            const value = meter ? meter.querySelector('[data-region="metervalue"]') : null;
            if (reduced) {
                this.drawMeter(ring, target);
                return;
            }
            // Staggered, like every other set of things that arrives together here.
            const delay = 80 + (index * 80);
            window.setTimeout(() => this.drawMeter(ring, target), delay);
            // The ring sweeps up from empty on the opening render, so the number does
            // too - a dial filling under a number that was already final looked like two
            // unrelated things happening in the same circle.
            this.countUp(value, 0, target, delay);
        });
    }

    /**
     * Reflect progress on the rail and announce it.
     *
     * @returns {void}
     */
    updateRail() {
        const total = parseInt(this.root.dataset.stages, 10) || 0;
        if (!total) {
            return;
        }
        const current = Math.min(Math.max(this.stage || 1, 1), total);
        this.showPosition(current, total, this.strings.stageprogress);
    }

    /**
     * The picture the scenario opened on, for screens that have none of their own.
     *
     * @returns {String} An image URL, or the empty string.
     */
    openingImage() {
        const img = this.root.querySelector('[data-region="brief"] .aibs-scene-img');
        return img ? img.getAttribute('src') || '' : '';
    }

    /**
     * Is there anything on this consequence worth stopping the learner for?
     *
     * Three things can make it worth a screen: something happened in the story, the
     * teacher's note on why it mattered, or one of the three readings moving. With none of
     * them the screen is a signal word and a button, which teaches nothing and reads as a
     * fault in the plugin rather than as a quiet moment in the scenario.
     *
     * The validator already refuses a real decision whose choice has no consequence, so in
     * practice this is the beat's synthesised choice and nothing else.
     *
     * @param {Object} response The reply from submit_choice.
     * @returns {Boolean} True when the screen has something to say.
     */
    saysSomething(response) {
        if ((response.consequenceparas || []).length || (response.feedbackparas || []).length) {
            return true;
        }
        const before = response.before || {};
        const after = response.after || {};
        return ['engagement', 'trust', 'tension'].some((key) => {
            return (parseInt(after[key], 10) || 0) !== (parseInt(before[key], 10) || 0);
        });
    }

    /**
     * Hold the scenario title to a single row.
     *
     * The bar is a title on the left and the controls on the right. A long title wrapped
     * to a second line and took the whole bar with it, so the controls dropped onto a row
     * of their own and the player lost a band of the screen to a heading. Truncating it
     * would be worse - the title is the one thing on the bar that says what this is - so
     * the type steps down instead, and only as far as it has to.
     *
     * @returns {void}
     */
    fitTitle() {
        const title = this.root.querySelector('.aibs-masthead-compact .aibs-title');
        if (!title) {
            return;
        }
        title.style.removeProperty('font-size');
        window.requestAnimationFrame(() => {
            let size = parseFloat(window.getComputedStyle(title).fontSize) || 0;
            if (!size) {
                return;
            }
            const floor = size * Player.MIN_TITLE;
            let guard = 0;
            while (title.scrollWidth > title.clientWidth + 1 && size > floor && guard < 20) {
                size -= 0.5;
                guard++;
                title.style.fontSize = size.toFixed(1) + 'px';
            }
        });
    }

    /**
     * Open or close the panel that explains the three readings.
     *
     * @param {HTMLElement} button The control that was pressed.
     * @returns {void}
     */
    toggleLegend(button) {
        const body = this.root.querySelector('.aibs-legend-body');
        if (!body) {
            return;
        }
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', open ? 'false' : 'true');
        body.hidden = open;
    }

    /**
     * Close the readings panel, wherever the learner clicked.
     *
     * @returns {void}
     */
    closeLegend() {
        const button = this.root.querySelector('[data-action="legend"]');
        const body = this.root.querySelector('.aibs-legend-body');
        if (!button || !body) {
            return;
        }
        button.setAttribute('aria-expanded', 'false');
        body.hidden = true;
    }

    /**
     * Redraw the read-only record of decisions already made.
     *
     * @returns {void}
     */
    drawHistory() {
        const panel = this.root.querySelector('[data-region="history"]');
        const list = this.root.querySelector('[data-region="historylist"]');
        if (!panel || !list) {
            return;
        }
        // Nothing decided yet is nothing to look back at, so the control is not there.
        panel.hidden = this.journey.length === 0;
        list.textContent = '';
        this.journey.forEach((step) => {
            const item = document.createElement('li');
            item.className = 'aibs-history-item aibs-signal-' + (step.signal || 'neutral');
            const title = document.createElement('span');
            title.className = 'aibs-history-title';
            title.textContent = step.nodetitle || '';
            const choice = document.createElement('span');
            choice.className = 'aibs-history-choice';
            choice.textContent = step.choicetext || '';
            item.appendChild(title);
            item.appendChild(choice);
            list.appendChild(item);
        });
    }

    /**
     * Put the learner's position in the one place it is ever shown.
     *
     * There used to be two of these and they counted different things: a row of numbered
     * dots saying which decision this was, and a line under the deck saying which slide
     * this was. Two progress indicators on one screen is not twice as informative, it is a
     * question about which one to believe.
     *
     * @param {Number} current Where the learner is, counting from one.
     * @param {Number} total How many there are.
     * @param {String} template The string to phrase it with.
     * @returns {void}
     */
    showPosition(current, total, template) {
        const chip = this.root.querySelector('[data-region="position"]');
        const phrase = (template || '{$a->current} of {$a->total}')
            .replace('{$a->current}', current)
            .replace('{$a->total}', total);
        if (chip) {
            // Split on the number so the one the learner is on can carry the weight and
            // the total can sit behind it, without the phrasing being assumed here - a
            // translation may put the words in any order.
            const parts = phrase.split(String(current));
            chip.textContent = '';
            if (parts.length === 2) {
                if (parts[0].trim()) {
                    chip.appendChild(document.createTextNode(parts[0]));
                }
                const now = document.createElement('span');
                now.className = 'aibs-position-now';
                now.textContent = String(current);
                chip.appendChild(now);
                const rest = document.createElement('span');
                rest.className = 'aibs-position-of';
                rest.textContent = parts[1];
                chip.appendChild(rest);
            } else {
                chip.textContent = phrase;
            }
        }
        // Said once, in words, for anyone listening rather than looking.
        const status = this.root.querySelector(SELECTORS.railStatus);
        if (status) {
            status.textContent = phrase;
        }
    }

    /**
     * Mark every rail step complete once the attempt has finished.
     *
     * @returns {void}
     */
    setRailComplete() {
        const chip = this.root.querySelector('[data-region="position"]');
        if (chip) {
            chip.textContent = this.strings.attemptfinished;
        }
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
    animateSkillBars(scope) {
        const within = scope || this.root;
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const stagger = reduced ? 0 : 80;
        const fills = within.querySelectorAll('.aibs-skill-fill');
        fills.forEach((fill, index) => {
            const target = fill.style.width;
            if (reduced) {
                return;
            }
            fill.style.width = '0%';
            fill.classList.add('aibs-is-animated');
            // One after another, the same eighty milliseconds apart as the rings and the
            // readings. Four bars all filling on the same frame reads as a page loading;
            // four arriving in order reads as a result being given.
            window.setTimeout(() => {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => {
                        fill.style.width = target;
                    });
                });
            }, index * stagger);
        });

        // The percentage beside each bar counts up with it. These were the last numbers
        // in the player that simply appeared, on the one screen where the learner is
        // being told how they did.
        within.querySelectorAll('[data-region="skillvalue"]').forEach((value, index) => {
            this.countUp(value, 0, parseInt(value.dataset.to, 10), index * stagger);
        });
    }

    /**
     * Pair each line of a list with its position, one-based.
     *
     * Mustache cannot count, and these lists arrive as plain strings.
     *
     * @param {String[]} lines The list as the service returned it.
     * @returns {Object[]} One entry per line, carrying its number and its text.
     */
    numbered(lines) {
        return (lines || []).map((text, index) => ({number: index + 1, text: text}));
    }

    /**
     * Mark the finish, once.
     *
     * Drawn rather than loaded: a handful of absolutely positioned pieces falling through
     * the card, in the player's own palette rather than in party colours, so the moment
     * reads as this product celebrating rather than as a widget bolted on. No library and
     * no image - the whole effect is forty elements and one keyframe.
     *
     * Skipped outright for anybody who has asked for reduced motion; the CSS hides the
     * layer as well, so this is belt and braces rather than the only guard.
     *
     * @returns {void}
     */
    /**
     * A short rising chime when the scenario closes.
     *
     * Synthesised rather than loaded: three notes from an oscillator is a few lines, and a
     * sound file would be one more asset to ship, to serve through pluginfile and to get
     * wrong in a theme that blocks media. It is quiet, it is under half a second, and it
     * plays once.
     *
     * Silent for anybody who turned narration off - a learner who did not want a voice did
     * not ask for a chime instead - and for anybody who asked for reduced motion, since the
     * request is for less going on rather than specifically for less movement. Any browser
     * that refuses to make a sound without a gesture simply does not make one; nothing here
     * is load-bearing.
     *
     * @returns {void}
     */
    playFanfare() {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!this.audioEnabled || this.muted || reduced || this.fanfarePlayed) {
            return;
        }
        this.fanfarePlayed = true;
        const Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) {
            return;
        }
        try {
            const ctx = new Ctor();
            // A major triad, arpeggiated. It resolves upward, which is what makes it read
            // as an ending rather than as a notification.
            [523.25, 659.25, 783.99].forEach((frequency, step) => {
                const at = ctx.currentTime + (step * 0.11);
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, at);
                // Shaped rather than switched: an abrupt start or stop on a sine is a click.
                gain.gain.setValueAtTime(0.0001, at);
                gain.gain.exponentialRampToValueAtTime(0.09, at + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.34);
                osc.connect(gain).connect(ctx.destination);
                osc.start(at);
                osc.stop(at + 0.36);
            });
            window.setTimeout(() => {
                if (ctx.close) {
                    ctx.close();
                }
            }, 900);
        } catch (error) {
            // A browser that will not open an audio context is not a reason to fail here.
            this.fanfarePlayed = true;
        }
    }

    dropConfetti(scope) {
        const layer = (scope || this.root).querySelector('[data-region="confetti"]');
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!layer || reduced || layer.dataset.done === '1') {
            return;
        }
        layer.dataset.done = '1';
        const styles = getComputedStyle(this.root);
        const palette = ['--aibs-positive', '--aibs-accent', '--aibs-warn', '--aibs-border']
            .map((token) => styles.getPropertyValue(token).trim())
            .filter((colour) => colour !== '');
        if (!palette.length) {
            return;
        }
        // The fall was translateY(120%), and a percentage on translateY is a percentage of
        // the ELEMENT, not of the layer it falls through - so a 10px piece fell 12px and
        // the confetti was a band of colour across the top of the card that faded where it
        // started. The distance is the height it actually has to cross, measured.
        const distance = Math.max(layer.getBoundingClientRect().height, 240);
        const pieces = 90;
        for (let i = 0; i < pieces; i++) {
            const piece = document.createElement('span');
            piece.className = 'aibs-finish-piece';
            const wide = 5 + Math.round(Math.random() * 4);
            piece.style.background = palette[i % palette.length];
            piece.style.height = (wide + Math.round(Math.random() * 6)) + 'px';
            piece.style.insetInlineStart = (Math.random() * 100).toFixed(2) + '%';
            piece.style.width = wide + 'px';
            piece.style.setProperty(
                '--aibs-spin',
                (Math.random() < .5 ? -1 : 1) * (180 + Math.round(Math.random() * 540)) + 'deg'
            );
            piece.style.setProperty('--aibs-fall', Math.round(distance * (1.05 + Math.random() * .25)) + 'px');
            piece.style.setProperty('--aibs-drift', (Math.round((Math.random() - .5) * 120)) + 'px');
            piece.style.animationDelay = (Math.random() * 1.1).toFixed(2) + 's';
            piece.style.animationDuration = (2.2 + (Math.random() * 1.6)).toFixed(2) + 's';
            layer.appendChild(piece);
        }
        // The layer is taken out once the last piece has landed, so a finished screen is
        // not left holding forty dead elements for as long as the learner sits on it.
        window.setTimeout(() => layer.replaceChildren(), 5200);
    }

    /**
     * Run a slide's arrivals at the moment it is actually put on screen.
     *
     * A deck holds every page in the DOM and hides all but one, so anything animated when
     * the deck was built ran against a hidden slide and was finished long before the
     * learner arrowed to it. The skill bars were the case that mattered: by the time "How
     * you handled it" was reached, the bars were already full and the percentages already
     * final, so the one screen that is supposed to deliver a result just sat there.
     *
     * Once per slide - stepping back to a page does not replay it, which would make the
     * deck feel like it was reloading rather than like pages of one thing.
     *
     * @param {HTMLElement} slide The slide being shown.
     * @returns {void}
     */
    revealSlide(slide) {
        if (!slide || slide.dataset.aibsRevealed === '1') {
            return;
        }
        slide.dataset.aibsRevealed = '1';
        this.animateSkillBars(slide);
        this.animateScore(slide);
        // Every screen that delivers a result should be heard as well as seen. The skills
        // page is the one that says how the learner actually handled it - four bars filling
        // in silence - and it was the barest screen in the product: no sound, no cue, four
        // numbers and a paragraph. It now reports itself the same way a consequence does,
        // in the same voice, banded against the same thresholds the bars are coloured by.
        this.soundSlide(slide);
    }

    /**
     * Let a debrief page say how it went, out loud.
     *
     * @param {HTMLElement} slide The slide being revealed.
     * @returns {void}
     */
    soundSlide(slide) {
        if (!slide) {
            return;
        }
        // The bars carry their own band in a class, put there by the same banding function
        // that colours them - so the sound is taken from the data rather than worked out a
        // second time and left to drift away from what is on screen.
        const bars = [...slide.querySelectorAll('[class*="aibs-tone-"]')];
        if (!bars.length) {
            return;
        }
        const band = (name) => bars.filter(
            (bar) => bar.className.indexOf('aibs-tone-' + name) !== -1
        ).length;
        if (band('bad')) {
            this.playCue('negative');
        } else if (band('warn')) {
            this.playCue('neutral');
        } else if (band('good')) {
            this.playCue('positive');
        }
    }

    /**
     * Count the grade up to itself.
     *
     * The one figure on the debrief the learner is actually waiting for was the only one
     * that simply appeared. The decimal places come from what was rendered, so a score of
     * 75 does not arrive as "75.0".
     *
     * @param {HTMLElement} scope The slide to look in.
     * @returns {void}
     */
    animateScore(scope) {
        const el = (scope || this.root).querySelector('.aibs-score-value');
        if (!el) {
            return;
        }
        const shown = el.textContent.trim();
        const to = parseFloat(shown);
        if (isNaN(to)) {
            return;
        }
        const dot = shown.indexOf('.');
        this.countUp(el, 0, to, 140, dot === -1 ? 0 : shown.length - dot - 1);
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
     * Does anything on this screen have more content than the box it is drawn in?
     *
     * The fit loop used to ask one element - the slide's body - whether it overflowed, and
     * a two-column card is the case where that question has the wrong answer. The body of
     * a consequence with a picture is a grid whose height is set by the photograph beside
     * it; the words live in the other column, and each block in that column - the prose,
     * the amber note - clips inside ITS own box. The body therefore reported that
     * everything fitted while the situation was cut off mid-sentence and the explanation
     * was cut off under it, which is exactly what a learner was shown.
     *
     * So the question is asked of the body and of everything inside it. The picture is
     * excluded because a photograph is meant to be cropped to its frame, and the
     * screen-reader text is excluded because it is a 1px clipped box on purpose.
     *
     * @param {HTMLElement} body The slide body being measured.
     * @returns {Boolean} True when something is being cut off.
     */
    overflowing(body) {
        if (!body) {
            return false;
        }
        if (body.scrollHeight > body.clientHeight + 1) {
            return true;
        }
        const boxes = body.querySelectorAll('*');
        for (let i = 0; i < boxes.length; i++) {
            const el = boxes[i];
            if (el.classList.contains('aibs-scene') || el.classList.contains('aibs-scene-img')
                    || el.closest('.aibs-scene')
                    || el.classList.contains('aibs-sr-only')
                    || el.classList.contains('aibs-visually-hidden')
                    || el.closest('.aibs-sr-only, .aibs-visually-hidden')) {
                continue;
            }
            // Not "has a height": the case that matters is a block squeezed to nothing.
            // Measured on the card a learner reported, the prose had a client height of 0
            // and 339px of text inside it, while the body reported 7px of overflow - so a
            // guard that skipped zero-height boxes skipped the only box being cut, and two
            // steps of the scale "fixed" a screen whose sentence was still severed
            // mid-word. Whether the element is drawn at all is a separate question, and
            // that is what the rect count answers.
            if (!el.getClientRects().length) {
                continue;
            }
            const style = window.getComputedStyle(el);
            if (style.overflowY !== 'hidden' && style.overflowY !== 'auto'
                    && style.overflowY !== 'scroll') {
                continue;
            }
            if (el.scrollHeight > el.clientHeight + 1) {
                return true;
            }
        }
        return false;
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
        this.fadeScenes();
        this.fitTitle();
        // What else is on the screen changes from one screen to the next - the arrows are
        // on a deck and not on a decision, the top bar grows a line when it wraps - so the
        // space available is worked out again each time rather than once at load.
        this.syncStickyOffset();
        const slides = this.root.querySelectorAll('.aibs-slide, .aibs-consequence, .aibs-deckslide');
        slides.forEach((slide) => {
            const body = slide.querySelector('.aibs-node-body') || slide;
            slide.style.removeProperty('--aibs-fit');
            // Two frames, so the browser has laid the new screen out before it is measured.
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    // The frame was sized from measurements of everything around it. Anything
                    // those measurements missed - a theme that adds a bar of its own, a browser
                    // that reports a viewport it does not really give you, a bar that wrapped
                    // onto a second line after the fact - shows up as the slide's own bottom
                    // edge sitting past the fold. That is visible after layout, so the frame is
                    // corrected against where the slide actually ended up before the text is
                    // scaled to it.
                    this.trimToFold(slide);
                    let scale = 1;
                    let guard = 0;
                    while (this.overflowing(body)
                            && scale > Player.MIN_FIT && guard < 22) {
                        scale -= 0.04;
                        guard++;
                        slide.style.setProperty('--aibs-fit', scale.toFixed(2));
                    }
                    // The scale only ever stepped down, which is right on a page: the frame
                    // is sized to the page and the type is what gives. In fullscreen it was
                    // wrong. The frame grew to the whole screen and the type did not follow
                    // it, so a learner who went fullscreen got the same words they had
                    // before with a great deal more white around them. Where the screen fits
                    // with room to spare, the type grows into it.
                    // A slide that fits with room to spare grows into it, on a page as
                    // well as in fullscreen.
                    //
                    // Growth used to be gated on fullscreen, so on a normal page the scale
                    // could only ever step DOWN. A sparse screen - a short consequence, an
                    // ending, a two-column card with a few lines in each - therefore sat at
                    // its base size inside a frame with half the height empty, which reads
                    // as small type rather than as a calm layout. The loop measures every
                    // step, so it stops the moment the next one would not fit; the only
                    // thing that changes is that it is now allowed to try.
                    if (guard === 0) {
                        while (scale < Player.MAX_FIT && guard < 28) {
                            const next = Math.round((scale + 0.04) * 100) / 100;
                            slide.style.setProperty('--aibs-fit', next.toFixed(2));
                            // Reading scrollHeight settles the layout, so each step is
                            // measured rather than assumed. The last step that still fits
                            // is the one that is kept.
                            if (this.overflowing(body)) {
                                slide.style.setProperty('--aibs-fit', scale.toFixed(2));
                                break;
                            }
                            scale = next;
                            guard++;
                        }
                    }
                    this.lockPageScroll();
                });
            });
        });
    }

    /**
     * Let a scene photograph arrive rather than appear.
     *
     * The picture is half the screen, and it was snapping in the instant it decoded -
     * fully formed, at full strength, with no relationship to the text that came in
     * beside it on a curve. It now fades over a quarter of a second. Images already in
     * the cache are marked loaded straight away, so a revisit does not re-fade.
     *
     * @returns {void}
     */
    fadeScenes() {
        this.root.querySelectorAll('.aibs-scene-img').forEach((img) => {
            if (img.dataset.faded === '1') {
                return;
            }
            img.dataset.faded = '1';
            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('aibs-is-loaded');
                return;
            }
            const done = () => img.classList.add('aibs-is-loaded');
            img.addEventListener('load', done, {once: true});
            // A picture that never arrives must not leave a permanently invisible box
            // where the learner expects one.
            img.addEventListener('error', done, {once: true});
        });
    }

    /**
     * Whether the player itself is the element filling the screen.
     *
     * @returns {Boolean} True when this player is in fullscreen.
     */
    isFullscreen() {
        const active = document.fullscreenElement || document.webkitFullscreenElement;
        return Boolean(active) && active === this.root;
    }

    /**
     * Pull the slide frame back up if the slide ended up past the bottom of the screen.
     *
     * Sizing the frame from the chrome around it is arithmetic, and arithmetic can be
     * wrong about a page it has not seen. Where the slide actually is cannot be wrong,
     * so the last word goes to the measurement rather than to the sum.
     *
     * @param {HTMLElement} slide The slide on screen.
     * @returns {void}
     */
    trimToFold(slide) {
        if (!slide || slide.offsetParent === null) {
            return;
        }
        const viewport = window.innerHeight || document.documentElement.clientHeight;
        // Measured on the player, not on the slide. The slide is not the last thing on the
        // screen - the deck's arrows, the card's own bottom margin and the player's padding
        // all sit below it - so a slide that ended above the fold could still leave eighty
        // pixels of the player below it, which is exactly what it did at every window size.
        const rect = this.root.getBoundingClientRect();
        const overflow = Math.round(rect.bottom - viewport);
        if (overflow <= 0) {
            // It fits as measured, so this is the frame from here on.
            if (!this.frameHeight) {
                const declaredok = parseInt(
                    window.getComputedStyle(this.root).getPropertyValue('--aibs-slide-max'), 10);
                if (!isNaN(declaredok) && declaredok > 0) {
                    this.frameHeight = declaredok;
                    this.frameMeasured = true;
                }
            }
            return;
        }
        const declared = parseInt(
            window.getComputedStyle(this.root).getPropertyValue('--aibs-slide-max'), 10);
        const base = isNaN(declared) ? Math.round(rect.height) : declared;
        // Eight pixels of daylight, so a rounded edge is not flush with the fold.
        const wanted = base - overflow - 8;
        if (wanted < Player.MIN_FRAME) {
            // Correcting this far would make a frame too small to read. The frame is given
            // up instead of being shrunk into something unusable with its overflow hidden.
            this.root.classList.add('aibs-no-frame');
            this.root.style.setProperty('--aibs-slide-max', '0px');
            return;
        }
        // This is the frame for the rest of the session, at this window size.
        this.frameHeight = wanted;
        this.frameMeasured = true;
        this.root.style.setProperty('--aibs-slide-max', wanted + 'px');
    }

    /**
     * Animate the consequence rings and count their numbers to the new value.
     *
     * @returns {void}
     */
    animateRings() {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        // Everything else in the player that arrives as a set arrives one after another -
        // the choices, the lesson cards. The three rings were the exception, all starting
        // on the same frame, which reads as a machine reporting rather than as a result
        // landing. Eighty milliseconds apart is the same interval the choices use.
        const stagger = reduced ? 0 : 80;
        this.root.querySelectorAll('.aibs-ring-fill').forEach((ring, index) => {
            const to = ring.dataset.dash;
            if (reduced) {
                ring.style.strokeDasharray = to;
                return;
            }
            ring.style.strokeDasharray = ring.dataset.startdash || '0 175.93';
            ring.classList.add('aibs-is-animated');
            window.setTimeout(() => {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => {
                        ring.style.strokeDasharray = to;
                    });
                });
            }, index * stagger);
        });

        this.root.querySelectorAll('.aibs-ring-value').forEach((value, index) => {
            this.countUp(
                value,
                parseInt(value.dataset.from, 10),
                parseInt(value.dataset.to, 10),
                index * stagger
            );
        });
    }

    /**
     * Count one number up to another, in step with the shape it sits on.
     *
     * Every number the learner watches change - the three readings in the bar, the three
     * rings on a consequence, the four skills in the debrief - counts rather than
     * switching, and they all count with the same curve and over the same time as the
     * ring or bar beside them fills. Written once here because when this was inlined at
     * each call site the rings counted and the bar did not, and the two sat on the same
     * screen disagreeing about whether a number arrives or lands.
     *
     * A reading that is not moving is not animated: counting 50 up to 50 is a flicker.
     *
     * @param {?HTMLElement} el The element whose text is the number.
     * @param {Number} from Where the count starts.
     * @param {Number} to Where it ends.
     * @param {Number} [delay=0] Milliseconds to wait first, for staggering a set.
     * @param {Number} [places=0] Decimal places to keep, for a figure like 74.5.
     * @returns {void}
     */
    countUp(el, from, to, delay, places) {
        if (!el || isNaN(to)) {
            return;
        }
        const dp = places || 0;
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (isNaN(from) || reduced || from === to) {
            el.textContent = to.toFixed(dp);
            return;
        }
        const started = (window.performance ? window.performance.now() : Date.now())
            + (delay || 0);
        const duration = 900;
        const step = (now) => {
            if (now < started) {
                window.requestAnimationFrame(step);
                return;
            }
            const elapsed = Math.min(1, (now - started) / duration);
            // Ease out, so the number settles rather than stopping dead.
            const eased = 1 - Math.pow(1 - elapsed, 3);
            el.textContent = (from + ((to - from) * eased)).toFixed(dp);
            if (elapsed < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    /**
     * Stop the page scrolling, once there is nothing below the fold to scroll to.
     *
     * The scenario is a player, not a document: a learner should never be scrolling to
     * find the options, and a page that can scroll invites them to. Everything is sized to
     * fit, and this closes the gap between "fits" and "cannot be moved".
     *
     * Two guards, because a locked page with something unreachable on it is far worse than
     * a page that scrolls. The lock is only taken above the layout breakpoint, where the
     * side-by-side slide applies - a narrow screen stacks the picture above the text and is
     * meant to scroll - and only while the player actually ends inside the viewport. If a
     * short window or an unusually tall theme header pushes it past the bottom, the lock is
     * handed straight back.
     *
     * @returns {void}
     */
    lockPageScroll() {
        const viewport = window.innerHeight || document.documentElement.clientHeight || 0;
        const wide = (window.innerWidth || document.documentElement.clientWidth || 0) >= 900;
        // Where the player ends measured from the top of the document, which is what a
        // page scrolled back to the top would show.
        const bottom = this.root.getBoundingClientRect().bottom + (window.scrollY || 0);
        const fits = bottom <= viewport + 24;
        const lock = wide && fits;
        ['aibs-noscroll'].forEach((name) => {
            document.documentElement.classList.toggle(name, lock);
            document.body.classList.toggle(name, lock);
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
    /**
     * A small sound when the pointer crosses an option.
     *
     * The lettered options are the one place on the screen where a learner is deciding, and
     * they were silent - the card lifted and nothing else happened. A short, quiet, rising
     * blip under the hover makes the row feel like something you are choosing rather than
     * text you are reading past, which is most of what makes an interface feel answerable.
     *
     * Deliberately small: forty milliseconds, low gain, one note, and never repeated while
     * the pointer sits on the same row. It is confirmation, not a notification - the moment
     * it becomes something you notice, it is something you resent.
     *
     * Silent for anyone who turned narration off, muted the player, or asked for reduced
     * motion, on the same reasoning as every other sound the player makes. Also silent for
     * a pointer that is not really a pointer: a touch device fires a hover on the way to a
     * tap, so the blip would arrive with the press rather than before it.
     *
     * @param {HTMLElement} row The option the pointer has entered.
     * @returns {void}
     */
    playHover(row) {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!this.audioEnabled || this.muted || reduced || !row || row.disabled) {
            return;
        }
        if (window.matchMedia && !window.matchMedia('(hover: hover)').matches) {
            return;
        }
        // One blip per arrival, not one per pointermove.
        if (this.hoverRow === row) {
            return;
        }
        this.hoverRow = row;
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
            // The pitch steps up the list, so moving down the options is a little scale
            // rather than the same note four times. It is the difference between a control
            // that acknowledges you and one that beeps.
            const rows = [...this.root.querySelectorAll('[data-action="choose"]')];
            const step = Math.max(0, rows.indexOf(row));
            const at = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(587.33 * Math.pow(1.0595, step * 2), at);
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(0.05, at + 0.008);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.09);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(at);
            osc.stop(at + 0.11);
        } catch (e) {
            this.cueContext = null;
        }
    }

    playCue(signal) {
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!this.audioEnabled || this.muted || reduced) {
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
            // Two quiet sine notes a third apart said "something happened" and very little
            // about what. A learner should know which of the two screens they are on before
            // they have read a word, and the two cues should not be near-identical shapes at
            // near-identical volume separated only by direction.
            //
            // Well judged: a major triad climbing C-E-G, each note held under the next so
            // the chord builds rather than ticks, with a fifth underneath for body. It
            // resolves upward and it arrives - that is what reads as having got it right.
            //
            // Costly: two notes falling a minor sixth onto a flattened tone, thicker and
            // slightly detuned so it has an edge to it. Firm, not punishing: this is a
            // learner being told a decision cost something, not a buzzer telling them off.
            // Neutral used to be silent, on the reasoning that nothing much had happened.
            // Something had: the learner made a decision and it neither helped nor cost -
            // which is a result, and a screen that reports a result in silence reports it
            // as nothing at all. It gets a voice of its own: two notes on the same pitch,
            // going nowhere, which is exactly what a neutral outcome is.
            if (signal === 'neutral') {
                const at = ctx.currentTime;
                [0, 0.13].forEach((offset) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.value = 440;
                    gain.gain.setValueAtTime(0.0001, at + offset);
                    gain.gain.exponentialRampToValueAtTime(0.12, at + offset + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, at + offset + 0.22);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(at + offset);
                    osc.stop(at + offset + 0.25);
                });
                return;
            }
            const positive = signal === 'positive';
            const voices = positive
                ? [
                    {f: 523.25, at: 0, hold: 0.5, gain: 0.17, type: 'triangle'},
                    {f: 659.25, at: 0.1, hold: 0.42, gain: 0.17, type: 'triangle'},
                    {f: 783.99, at: 0.2, hold: 0.44, gain: 0.19, type: 'triangle'},
                    {f: 261.63, at: 0, hold: 0.6, gain: 0.08, type: 'sine'},
                ]
                : [
                    {f: 392.0, at: 0, hold: 0.3, gain: 0.17, type: 'triangle'},
                    {f: 392.0 * 1.004, at: 0, hold: 0.3, gain: 0.09, type: 'triangle'},
                    {f: 246.94, at: 0.14, hold: 0.46, gain: 0.19, type: 'triangle'},
                    {f: 246.94 * 0.996, at: 0.14, hold: 0.46, gain: 0.1, type: 'sine'},
                ];
            voices.forEach((voice) => {
                const at = ctx.currentTime + voice.at;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = voice.type;
                osc.frequency.value = voice.f;
                // Shaped rather than switched: an abrupt start or stop is a click, and a
                // click is the one sound nobody reads as meaning anything.
                gain.gain.setValueAtTime(0.0001, at);
                gain.gain.exponentialRampToValueAtTime(voice.gain, at + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, at + voice.hold);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(at);
                osc.stop(at + voice.hold + 0.03);
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
    /**
     * Play the next recording on a screen that carries more than one.
     *
     * Kept apart from playAudio() deliberately: playAudio sets up a screen - it stops what
     * was playing, works out the queue, holds the way on and resets the button. Advancing
     * within a screen must do none of that, or each card on the decision record would
     * re-hold the Continue it had just released and reset the control the learner is
     * watching.
     *
     * @param {String} url The next clip.
     * @returns {void}
     */
    playQueued(url) {
        if (this.audio) {
            this.audio.pause();
            this.audio.src = '';
        }
        this.audio = new Audio(url);
        this.audio.muted = this.muted;
        this.markReading(url);
        this.audio.addEventListener('ended', () => {
            if (this.audioQueue && this.audioQueue.length && !this.muted) {
                this.playQueued(this.audioQueue.shift());
                return;
            }
            this.narrationDone = true;
            this.markReading('');
            this.releaseWayOn();
            this.refreshAudioButton();
        });
        this.audio.addEventListener('error', () => {
            // One clip that will not load must not silence the rest of the page.
            if (this.audioQueue && this.audioQueue.length) {
                this.playQueued(this.audioQueue.shift());
                return;
            }
            this.narrationDone = true;
            this.releaseWayOn();
            this.refreshAudioButton();
        });
        const started = this.audio.play();
        if (started && typeof started.catch === 'function') {
            started.catch(() => {
                this.narrationDone = true;
                this.releaseWayOn();
                this.refreshAudioButton();
            });
        }
        this.refreshAudioButton();
    }

    /**
     * Every recording on a slide, in the order the slide is read in.
     *
     * @param {HTMLElement} slide The slide being shown.
     * @returns {String} Pipe-separated clip urls.
     */
    /**
     * Play a list page as a sequence rather than showing it as a list.
     *
     * A page of five lessons used to arrive whole, with one recording of the whole list
     * read over the top of it - a wall of text with a voice somewhere behind it, and no way
     * for a learner to know which line was being read. It is played instead: one card
     * floats up, the picture beside it changes to the frame from that part of the scenario,
     * that card's own clip reads it with the card marked, and the next arrives when the
     * clip finishes.
     *
     * Everything is in the markup either way. With narration off, muted, reduced motion
     * asked for, or a browser that will not play, every card is simply shown at once - the
     * choreography is an enhancement and never the only way to read the page.
     *
     * @param {HTMLElement} slide The scripted slide.
     * @returns {void}
     */
    runScript(slide) {
        const cards = [...slide.querySelectorAll('.aibs-script-item')];
        if (!cards.length) {
            return;
        }
        const image = slide.querySelector('[data-region="scriptimage"]');
        const reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const play = this.audioEnabled && !this.muted && !reduced
            && cards.some((card) => card.dataset.audio);

        // Stepping back onto a page that has already run shows it finished rather than
        // replaying it, which would make the deck feel like it was reloading.
        if (slide.dataset.scriptDone === '1' || !play) {
            cards.forEach((card) => card.classList.add('aibs-is-in'));
            slide.dataset.scriptDone = '1';
            this.playAudio('', '');
            return;
        }
        slide.dataset.scriptDone = '1';
        cards.forEach((card) => card.classList.remove('aibs-is-in'));
        this.stopAudio();
        this.holdWayOn();

        let at = 0;
        const step = () => {
            if (at >= cards.length) {
                this.markReading('');
                this.narrationDone = true;
                this.releaseWayOn();
                this.refreshAudioButton();
                return;
            }
            const card = cards[at];
            at++;
            card.classList.add('aibs-is-in');
            // The frame changes with the card, so the picture is about the line being read
            // rather than being one photograph the whole page sits next to.
            if (image && card.dataset.image && image.src !== card.dataset.image) {
                image.classList.remove('aibs-is-loaded');
                image.src = card.dataset.image;
                window.requestAnimationFrame(() => image.classList.add('aibs-is-loaded'));
            }
            if (!card.dataset.audio) {
                window.setTimeout(step, 900);
                return;
            }
            this.markReading(card.dataset.audio);
            this.audio = new Audio(card.dataset.audio);
            this.audio.muted = this.muted;
            const onward = () => {
                // A short beat between cards, so the page reads as paced rather than as a
                // recording that was cut too tight.
                window.setTimeout(step, 420);
            };
            this.audio.addEventListener('ended', onward);
            this.audio.addEventListener('error', onward);
            const started = this.audio.play();
            if (started && typeof started.catch === 'function') {
                started.catch(() => {
                    // Autoplay refused: show the page rather than leaving it half drawn
                    // behind a recording that is never going to start.
                    cards.forEach((one) => one.classList.add('aibs-is-in'));
                    this.markReading('');
                    this.narrationDone = true;
                    this.releaseWayOn();
                    this.refreshAudioButton();
                });
            }
            this.refreshAudioButton();
        };
        step();
    }

    slideClips(slide) {
        if (!slide) {
            return '';
        }
        const clips = [slide.dataset.audio || ''];
        slide.querySelectorAll('[data-region="readable"][data-audio]').forEach((card) => {
            clips.push(card.dataset.audio || '');
        });
        return clips.filter(Boolean).join('|');
    }

    /**
     * Mark the card currently being read, and unmark everything else.
     *
     * A page that reads two cards one after the other gave a learner no way to tell which
     * one they were hearing - two cards, one voice, and the reader left matching the words
     * to the column by guesswork. The card being read wears the same lift the pointer gives
     * it, which is a state the product already uses to mean "this one", so nothing new has
     * to be learned to read it.
     *
     * @param {String} url The clip now playing.
     * @returns {void}
     */
    markReading(url) {
        this.root.querySelectorAll('[data-region="readable"]').forEach((card) => {
            const on = Boolean(url) && card.dataset.audio === url;
            card.classList.toggle('aibs-is-reading', on);
            // Announced, not just coloured: a learner using a screen reader is told which
            // card is being read rather than being left with the same problem in sound.
            if (on) {
                card.setAttribute('aria-current', 'true');
            } else {
                card.removeAttribute('aria-current');
            }
        });
    }

    playAudio(url, speechurl) {
        // First, before any of the early exits below. Whatever is playing belongs to the
        // screen being left, and it has to stop whether or not the screen being arrived at
        // has a recording of its own. It did not: on a slide with no clip, or with the
        // narration muted, both early returns skipped the stop and the previous reading
        // carried on over the new card.
        this.stopAudio();
        // A screen may carry more than one recording - the decision record puts two cards
        // on a page and each has a clip of its own - so the url is a queue, pipe separated,
        // and a screen with one clip is simply a queue of one.
        this.audioQueue = String(url || '').split('|').filter(Boolean);
        this.audioUrl = this.audioQueue.shift() || '';
        this.speechUrl = speechurl || '';
        this.narrationDone = !this.audioUrl;
        // The spoken line no longer gates the way on. It is played by pressing the avatar,
        // so holding Continue until it had been heard would hold it until the learner
        // happened to notice a control they may never press - which is a locked screen with
        // no explanation. The narration is what the screen waits for; the line is an extra
        // the learner can take or leave.
        this.speechDone = true;
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
        this.markReading(this.audioUrl);
        this.audio.addEventListener('ended', () => {
            // The next card on this page, if there is one, before anything is released:
            // the page is not finished being read until every clip on it has been.
            if (this.audioQueue && this.audioQueue.length && !this.muted) {
                const next = this.audioQueue.shift();
                this.audioUrl = next;
                this.playQueued(next);
                return;
            }
            this.narrationDone = true;
            // The screen is read in two voices, one after the other.
            //
            // The spoken line used to be chained on here, on the reasoning that a learner
            // listening straight through would otherwise never hear it. That reasoning was
            // right about the problem and wrong about the fix: the narrator's clip for a
            // scene ALREADY reads the quoted line as part of the situation, so chaining the
            // character's own recording after it played the same words twice in two
            // different voices, one after the other. The avatar is what plays it - it is a
            // control, and pressing it is what a control is for.
            this.releaseWayOn();
        });
        // A clip that dies half way through fires error, not ended. Without this the way
        // on was held behind a recording that was never going to finish.
        this.audio.addEventListener('error', () => {
            this.narrationDone = true;
            this.speechDone = true;
            this.releaseWayOn();
            this.refreshAudioButton();
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
        this.audio.addEventListener('error', () => {
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
        // The deck's arrows are the way on through the opening lesson, so they wait for the
        // reading the same way Continue does. They fade rather than disable, so the learner
        // can see where they will be, and they come back on their own after the longest
        // clip we would ever generate - a recording that stalls must never be the reason
        // somebody cannot leave a slide.
        this.setDeckWaiting(true);
        window.clearTimeout(this.wayOnTimer);
        this.wayOnTimer = window.setTimeout(() => {
            this.narrationDone = true;
            this.speechDone = true;
            this.releaseWayOn();
        }, 90000);
    }

    /**
     * Fade the deck arrows while a recording is still playing.
     *
     * @param {Boolean} waiting Whether the reading is still going.
     * @returns {void}
     */
    setDeckWaiting(waiting) {
        const holding = Boolean(waiting);
        this.root.querySelectorAll('.aibs-deck-nav').forEach((nav) => {
            nav.classList.toggle('aibs-is-waiting', holding);
        });
        if (!this.requireListen) {
            return;
        }
        // Held closed, not just dimmed. Previous is left alone: going back is not skipping
        // anything, and a learner who wants to hear a slide again must be able to reach it.
        this.root.querySelectorAll('[data-action="decknext"]').forEach((button) => {
            button.disabled = holding;
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
        window.clearTimeout(this.wayOnTimer);
        this.root.querySelectorAll('[data-action="continue"], .aibs-continuebtn').forEach((button) => {
            button.disabled = false;
            button.classList.remove('aibs-is-waiting');
        });
        this.setDeckWaiting(false);
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
        // Nothing is being read, so nothing wears the mark. Leaving it behind would point
        // at a card the learner is no longer hearing, which is worse than not pointing.
        this.markReading('');
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

        // Mute, not stop.
        //
        // This used to call stopAudio(), which pauses the clip AND discards it, so
        // unmuting had nothing to resume and started the narration again from the top. A
        // learner who silenced one sentence lost their place in the whole scene and had to
        // sit through it a second time - which is not what a speaker icon promises.
        //
        // Silencing the element leaves it playing, so unmuting picks up exactly where the
        // voice had got to. Speech synthesis has no muted property, so it is paused and
        // resumed, which comes to the same thing from the listener's side.
        if (this.audio) {
            this.audio.muted = this.muted;
        }
        if (window.speechSynthesis) {
            if (this.muted) {
                window.speechSynthesis.pause();
            } else {
                window.speechSynthesis.resume();
            }
        }

        if (this.muted) {
            // Muting is still a decision not to listen, so the way on is not held any
            // longer - that part was right and is unchanged.
            this.narrationDone = true;
            this.speechDone = true;
            this.releaseWayOn();
        } else if (!this.audio && this.audioUrl) {
            // Nothing is playing to unmute - the slide was arrived at while muted - so this
            // press is what starts it.
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
    // Before anything is drawn, so the first paint is already the right colour for the
    // page rather than a white flash that corrects itself.
    Scheme.watch(root);
    const player = new Player(root);
    return player.init();
};
