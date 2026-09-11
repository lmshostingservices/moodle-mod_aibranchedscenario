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
 * Match the page the activity was dropped into.
 *
 * The activity ships its own palette, which means it can disagree with the theme around
 * it. On a dark Moodle theme a light palette is a white slab in a dark page, and that is
 * the single thing that most reads as "this plugin was not built for us".
 *
 * The usual fix - `@media (prefers-color-scheme: dark)` - is wrong in both directions
 * here. A light theme on a machine set to dark would get a dark activity inside a light
 * page, which is worse than the problem. A theme with its own dark toggle, which is how
 * most commercial Moodle themes actually do it, would get nothing at all, because the
 * operating system was never asked.
 *
 * So this does not ask the operating system. It looks at the page: the first ancestor
 * behind the activity that actually paints a background, and how light that colour is. A
 * dark page gets the dark palette whatever the reason it is dark, and a light page never
 * does. A theme that toggles at runtime is picked up too, because the observer below
 * watches the attributes those toggles change.
 *
 * @module     mod_aibranchedscenario/scheme
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Number} Below this relative luminance the page counts as dark. */
const DARK_BELOW = 0.4;

/**
 * Pull the red, green and blue out of whatever the browser reported.
 *
 * Computed background colours come back as rgb()/rgba(), including the modern
 * space-separated spelling. Anything else - a gradient, a keyword the browser did not
 * resolve - is not a colour this can judge, and is skipped rather than guessed at.
 *
 * @param {String} value A computed background-color.
 * @returns {?Object} {r, g, b, a} in 0-255 (alpha 0-1), or null if it is not usable.
 */
const parse = (value) => {
    if (!value) {
        return null;
    }
    const parts = value.replace(/[^0-9.,\s/]/g, '').split(/[\s,/]+/).filter((p) => p !== '');
    if (parts.length < 3) {
        return null;
    }
    const [r, g, b] = parts.map(Number);
    const a = parts.length > 3 ? Number(parts[3]) : 1;
    if ([r, g, b, a].some((n) => isNaN(n))) {
        return null;
    }
    return {r, g, b, a};
};

/**
 * How light a colour is, on the scale the contrast rules use.
 *
 * @param {Object} rgb {r, g, b} in 0-255.
 * @returns {Number} Relative luminance, 0 (black) to 1 (white).
 */
const luminance = (rgb) => {
    const channel = (c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * channel(rgb.r) + 0.7152 * channel(rgb.g) + 0.0722 * channel(rgb.b);
};

/**
 * The colour actually painted behind an element.
 *
 * Most elements are transparent, so this walks up until it finds one that is not. The
 * document element is the last stop; a page that paints nothing anywhere is white by
 * definition, which is what the browser would show.
 *
 * @param {HTMLElement} element Where to start looking.
 * @returns {?Object} {r, g, b} of the first painted background, or null.
 */
const backdrop = (element) => {
    let node = element;
    while (node && node.nodeType === 1) {
        const colour = parse(window.getComputedStyle(node).backgroundColor);
        if (colour && colour.a > 0.5) {
            return colour;
        }
        node = node.parentElement;
    }
    return null;
};

/**
 * Set data-aibs-scheme on an element to match the page behind it.
 *
 * @param {HTMLElement} element The player, wizard or toast region.
 * @returns {String} The scheme applied, 'dark' or 'light'.
 */
export const apply = (element) => {
    if (!element) {
        return 'light';
    }
    // Measured from the parent, not from the element itself: the element paints its own
    // palette, so asking it what colour it is would only ever return the answer it was
    // last given.
    const colour = backdrop(element.parentElement || document.body);
    const scheme = colour && luminance(colour) < DARK_BELOW ? 'dark' : 'light';
    if (scheme === 'dark') {
        element.dataset.aibsScheme = 'dark';
    } else {
        delete element.dataset.aibsScheme;
    }
    return scheme;
};

/**
 * Keep an element matched to the page for as long as it is on screen.
 *
 * Themes with a dark toggle flip a class or a data attribute on <html> or <body> without
 * reloading, so a one-off reading at startup would be right until the moment someone used
 * the switch. This re-reads when those attributes change, and when the operating system
 * changes its mind - some themes follow it.
 *
 * @param {HTMLElement} element The player, wizard or toast region.
 * @returns {void}
 */
export const watch = (element) => {
    if (!element) {
        return;
    }
    apply(element);

    if (window.MutationObserver) {
        const observer = new MutationObserver(() => apply(element));
        [document.documentElement, document.body].forEach((node) => {
            if (node) {
                observer.observe(node, {attributes: true, attributeFilter: ['class', 'style', 'data-theme']});
            }
        });
    }

    if (window.matchMedia) {
        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => apply(element);
        if (query.addEventListener) {
            query.addEventListener('change', handler);
        } else if (query.addListener) {
            query.addListener(handler);
        }
    }
};
