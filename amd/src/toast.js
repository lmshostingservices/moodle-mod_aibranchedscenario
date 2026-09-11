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
 * Confirmation that lands where the person is looking.
 *
 * Moodle's own notifications render in the page's notification region, at the top of the
 * document. The authoring wizard's buttons are at the bottom of a long form, so pressing
 * Publish put a success message somewhere entirely off screen and the button read as
 * broken. Teachers pressed it again.
 *
 * These appear anchored to the viewport instead, near the action, and take themselves
 * away. They are an addition to Moodle's notifications rather than a replacement: the
 * message is still announced, and anything that genuinely needs to persist on the page
 * still goes through the error region.
 *
 * @module     mod_aibranchedscenario/toast
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

/** @var {Number} How long a message stays before it withdraws, in milliseconds. */
const LIFETIME = 4200;

/** @var {Number} Longer for anything the person may want to read twice. */
const LIFETIME_LONG = 7000;

/** @var {Number} Matches the transition in styles.css. */
const EXIT_MS = 260;

/** @var {Number} Beyond this, the oldest is retired to stop a stack covering the page. */
const MAX_VISIBLE = 3;

let region = null;

/**
 * The container, created once and attached to the body.
 *
 * It is attached to the body rather than to the wizard, because a fixed position is
 * measured against the nearest transformed ancestor rather than the viewport, and a site
 * theme is free to put a transform on any wrapper it likes. Anchoring to the body is the
 * only placement no theme can quietly move.
 *
 * @returns {Element} The live region that holds the messages.
 */
const container = () => {
    if (region && document.body.contains(region)) {
        return region;
    }
    region = document.createElement('div');
    region.className = 'aibs-toasts';
    // Polite, because a confirmation must not interrupt what a screen reader is saying;
    // the role makes it an announcement rather than a piece of the page.
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);
    return region;
};

/**
 * Take a message away.
 *
 * @param {Element} toast The message element.
 * @returns {void}
 */
const dismiss = (toast) => {
    if (!toast || toast.dataset.leaving === '1') {
        return;
    }
    toast.dataset.leaving = '1';
    toast.classList.add('aibs-toast-leaving');
    window.setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, EXIT_MS);
};

/**
 * Show a message.
 *
 * @param {String} message Text to show. Inserted as text, never as markup.
 * @param {String} [type] One of success, info or error. Defaults to success.
 * @returns {Element|null} The message element, or null when there was nothing to say.
 */
export const show = (message, type) => {
    const text = (message === null || message === undefined) ? '' : String(message).trim();
    if (text === '') {
        return null;
    }

    const host = container();
    const kind = ['success', 'info', 'error'].indexOf(type) === -1 ? 'success' : type;

    const toast = document.createElement('div');
    toast.className = `aibs-toast aibs-toast-${kind}`;

    const icon = document.createElement('span');
    icon.className = 'aibs-toast-icon';
    icon.setAttribute('aria-hidden', 'true');

    const body = document.createElement('span');
    body.className = 'aibs-toast-text';
    // textContent, so a message carrying a scenario title can never carry markup with it.
    body.textContent = text;

    toast.appendChild(icon);
    toast.appendChild(body);
    host.appendChild(toast);

    while (host.children.length > MAX_VISIBLE) {
        dismiss(host.children[0]);
    }

    // A frame later, so the browser has a starting position to animate away from.
    window.requestAnimationFrame(() => toast.classList.add('aibs-toast-in'));

    const life = kind === 'error' ? LIFETIME_LONG : LIFETIME;
    let timer = window.setTimeout(() => dismiss(toast), life);

    // Reading should never be interrupted by the thing withdrawing under the cursor.
    toast.addEventListener('mouseenter', () => window.clearTimeout(timer));
    toast.addEventListener('mouseleave', () => {
        timer = window.setTimeout(() => dismiss(toast), LIFETIME);
    });
    toast.addEventListener('click', () => {
        window.clearTimeout(timer);
        dismiss(toast);
    });

    return toast;
};

export default {show};
