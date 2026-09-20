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
 * The sound language.
 *
 * Five cues, synthesised rather than shipped. Nothing is downloaded, nothing is cached,
 * nothing has to be hosted, and the whole vocabulary is about forty lines - which also
 * means the one rule that matters can be enforced in code rather than in a style guide:
 *
 *   THERE IS NO BUZZER.
 *
 * A game-show buzzer on a wrong answer tells the learner they are doing a quiz, which is
 * the single thing this product is trying not to be. When a decision goes badly the
 * consequence says so - the room changes, somebody stops talking - and the sound under it
 * is low and quiet, the sound of something having gone wrong rather than of a klaxon
 * announcing it.
 *
 * The cues are deliberately unequal. If every good decision got the same chime, the chime
 * would stop meaning anything by the third one; scarcity is what makes the big moments
 * land. So an ordinary good choice gets one soft note, a genuinely strong one under
 * pressure gets a rising pair, and the full triad is kept for the end of the scenario and
 * nothing else.
 *
 * @module     mod_aibranchedscenario/cues
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

/**
 * The vocabulary.
 *
 * Each cue is a list of [frequency, startOffset, duration, peakGain]. Kept as data rather
 * than as code so the whole sound of the product can be read at once, and so that adding a
 * sixth cue is a decision somebody makes deliberately rather than a function they write.
 *
 * Frequencies are notes rather than round numbers: C5 523.25, E5 659.25, G5 783.99,
 * A4 440, F3 174.61, C3 130.81. The low ones are felt more than heard, which is the point
 * of them.
 *
 * @type {Object}
 */
const VOCABULARY = {
    // An ordinary good decision. One note, quiet, gone in a third of a second.
    good: [[659.25, 0, 0.30, 0.055]],
    // A strong decision, usually one taken under pressure. Rises, which is what makes it
    // read as approval rather than as a notification.
    strong: [[523.25, 0, 0.26, 0.06], [783.99, 0.09, 0.34, 0.07]],
    // Something is developing. Low, slow, under the scene rather than on top of it - the
    // sound equivalent of the light changing.
    danger: [[174.61, 0, 0.55, 0.05]],
    // It has happened. Still not a buzzer: two low notes falling, quiet, like a door
    // closing somewhere else in the building.
    incident: [[174.61, 0, 0.45, 0.06], [130.81, 0.12, 0.55, 0.05]],
    // Neither helped nor cost. Two notes on the same pitch, going nowhere: an
    // acknowledgement, deliberately not a reward.
    flat: [[440, 0, 0.22, 0.045], [440, 0.13, 0.22, 0.045]],
    // The scenario is over. The only cue that gets three notes, and it resolves upward.
    complete: [[523.25, 0, 0.36, 0.09], [659.25, 0.11, 0.36, 0.09], [783.99, 0.22, 0.40, 0.09]],
};

/** @var {AudioContext|null} One context for the page, opened on the first cue. */
let context = null;

/**
 * The browser's audio context constructor, whatever it is called here.
 *
 * @returns {Function|null}
 */
const constructor = () => window.AudioContext || window.webkitAudioContext || null;

/**
 * Play one cue.
 *
 * Silently does nothing when the cue is unknown, when sound is off, or when the browser
 * will not open an audio context. A scenario that throws because a laptop has no sound
 * card would be a worse product than a silent one.
 *
 * @param {String} name One of the cues in VOCABULARY.
 * @param {Boolean} enabled Whether sound is switched on for this learner and this site.
 * @returns {Boolean} Whether anything was played.
 */
export const play = (name, enabled) => {
    const notes = VOCABULARY[name];
    const Ctor = constructor();
    if (!notes || !enabled || !Ctor) {
        return false;
    }
    try {
        if (!context) {
            context = new Ctor();
        }
        // Browsers suspend the context until a gesture. Every cue in this product follows
        // a click, so resuming here is enough and no unlock dance is needed.
        if (context.state === 'suspended' && context.resume) {
            context.resume();
        }
        notes.forEach(([frequency, offset, duration, peak]) => {
            const at = context.currentTime + offset;
            const osc = context.createOscillator();
            const gain = context.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(frequency, at);
            // Shaped, never switched: an abrupt start or stop on a sine wave is a click,
            // and a click is the cheapest way to make a product sound broken.
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(peak, at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + duration);
            osc.connect(gain).connect(context.destination);
            osc.start(at);
            osc.stop(at + duration + 0.02);
        });
        return true;
    } catch (error) {
        return false;
    }
};

/**
 * Which cue belongs to a decision that has just been answered.
 *
 * The signal is what the scenario said about the choice; the tension reading is how the
 * room is doing. A poor choice in a room that is already strained is an incident; the same
 * choice early on is a warning. This is the whole of the mapping, in one place, so the
 * product cannot drift into playing a celebration over bad news.
 *
 * @param {String} signal positive, neutral or negative.
 * @param {Number} tension The tension reading after the choice, 0-100.
 * @param {Boolean} underPressure Whether somebody was leaning on the learner.
 * @returns {String|null} A cue name, or null for silence.
 */
export const forDecision = (signal, tension, underPressure) => {
    if (signal === 'positive') {
        return underPressure ? 'strong' : 'good';
    }
    if (signal === 'negative') {
        return tension >= 70 ? 'incident' : 'danger';
    }
    // The plausible middle is acknowledged but never rewarded. It was briefly going to be
    // silent, on the reasoning that rewards should be scarce - but a decision that neither
    // helped nor cost is still a RESULT, and a screen that reports a result in silence
    // reports it as nothing at all. So it gets a voice that goes nowhere, which is exactly
    // what a neutral outcome is, and which nobody could mistake for approval.
    return 'flat';
};
