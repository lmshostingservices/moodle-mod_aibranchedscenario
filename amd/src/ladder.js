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
 * The scenario chooser.
 *
 * The chooser is three links and needs no behaviour of its own - a learner picks a card
 * and the page loads. What it does need is the one thing every surface in this plugin
 * needs: to know whether the page behind it is light or dark, which is measured rather
 * than asked of the operating system, because a site theme's dark mode and the learner's
 * system setting are frequently not the same answer.
 *
 * @module     mod_aibranchedscenario/ladder
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

import * as Scheme from 'mod_aibranchedscenario/scheme';

/**
 * Match the chooser to the page it is sitting on, and keep it matched.
 *
 * @returns {void}
 */
export const init = () => {
    const root = document.querySelector('.aibs-ladder');
    if (!root) {
        return;
    }
    Scheme.watch(root);
};
