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
 * The missing-picture panel on the draft review page.
 *
 * A published revision can be short of pictures: the service refused some, a run stopped
 * part-way, or the scenario was edited after it was illustrated. Until this existed the
 * only remedy was to regenerate every picture at the full media price - a teacher four
 * pictures short paid for thirty - because nothing could say WHICH four were missing.
 *
 * Two steps, deliberately separate, in the same order the generation wizard uses: check
 * first and see what it will be, then generate. Checking spends nothing, so a teacher can
 * look as often as they like.
 *
 * @module     mod_aibranchedscenario/topup
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';
import Toast from 'mod_aibranchedscenario/toast';

const SELECTORS = {
    panel: '[data-region="topup"]',
    status: '[data-region="topupstatus"]',
    list: '[data-region="topuplist"]',
    check: '[data-action="topupcheck"]',
    make: '[data-action="topupmake"]',
};

/**
 * Wire the panel, if this page has one.
 *
 * @param {Number} cmid The course module.
 * @param {Number} tier Which rung of the ladder this page is reviewing.
 * @returns {void}
 */
export const init = (cmid, tier) => {
    const panel = document.querySelector(SELECTORS.panel);
    if (!panel) {
        return;
    }
    const status = panel.querySelector(SELECTORS.status);
    const list = panel.querySelector(SELECTORS.list);
    const check = panel.querySelector(SELECTORS.check);
    const make = panel.querySelector(SELECTORS.make);
    // The keys the last check reported. Generate sends these rather than re-deriving them,
    // so a teacher makes exactly what they were shown and nothing else.
    let missing = [];

    const busy = (on) => {
        [check, make].forEach((button) => {
            if (button) {
                button.disabled = on;
            }
        });
    };

    const draw = async(response) => {
        missing = (response.missing || []).map((row) => row.key);
        list.textContent = '';
        (response.missing || []).forEach((row) => {
            const item = document.createElement('li');
            item.className = 'aibs-quality-item';
            item.textContent = row.what;
            list.appendChild(item);
        });
        if (make) {
            make.hidden = missing.length === 0;
        }
        if (missing.length) {
            status.textContent = await getString('topup:short', 'mod_aibranchedscenario', missing.length);
            return;
        }
        // The other two answers are worth saying even when nothing is missing: an orphan
        // means the map moved under the pictures, and a shared file is two screens showing
        // one photograph, which is the fault this whole panel exists because of.
        const notes = [];
        if ((response.orphaned || []).length) {
            notes.push(await getString(
                'topup:orphaned',
                'mod_aibranchedscenario',
                response.orphaned.length
            ));
        }
        if ((response.shared || []).length) {
            notes.push(await getString('topup:shared', 'mod_aibranchedscenario'));
        }
        if (!notes.length) {
            notes.push(await getString('topup:nothingmissing', 'mod_aibranchedscenario'));
        }
        status.textContent = notes.join(' ');
    };

    const call = async(keys) => {
        busy(true);
        try {
            const response = await Ajax.call([{
                methodname: 'mod_aibranchedscenario_topup_media',
                args: {cmid: cmid, keys: keys, tier: tier || 1},
            }])[0];
            await draw(response);
            if (keys.length) {
                const made = await getString(
                    'topup:made',
                    'mod_aibranchedscenario',
                    {made: response.made, wanted: response.wanted}
                );
                Toast.show(response.note ? made + ' ' + response.note : made);
            }
        } catch (error) {
            // The message is the plugin's own, already made safe on the way out - never a
            // provider sentence and never a stack trace.
            status.textContent = error.message || '';
        } finally {
            busy(false);
        }
    };

    if (check) {
        check.addEventListener('click', () => call([]));
    }
    if (make) {
        make.addEventListener('click', () => {
            if (missing.length) {
                call(missing);
            }
        });
    }
};
