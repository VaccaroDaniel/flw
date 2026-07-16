// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Item selector for the trade centre.
 *
 * @copyright 2023 Adrian Greeve <abgreeve@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';
import * as Templates from 'core/templates';
import ajax from 'core/ajax';

const SEARCH_WIDGET_SELECTOR = '.search-widget[data-searchtype="block_stash-item"]';
const SEARCH_WIDGET_TEMPLATE = 'block_stash/itemsearch_body';
const EMPTY_RESULTS_MESSAGE = 'No matching items found';
const DEFAULT_ERROR_MESSAGE = 'Unable to load items';

let registered = false;

/**
 * Our entry point into starting to build the search widget.
 *
 * @method init
 */
export const init = () => {
    if (registered) {
        return;
    }

    const pendingPromise = new Pending();
    registerListenerEvents();
    pendingPromise.resolve();
    registered = true;
};

/**
 * Register search widget related event listeners.
 */
const registerListenerEvents = () => {
    document.querySelectorAll(SEARCH_WIDGET_SELECTOR).forEach((widget) => {
        widget.addEventListener('show.bs.dropdown', async(e) => {
            const dropdownMenuContainer = widget.querySelector('.dropdown-menu');
            const toggle = e.relatedTarget || widget.querySelector('[data-bs-toggle="dropdown"]');
            const courseID = toggle?.dataset?.courseid;

            if (!dropdownMenuContainer || !courseID) {
                return;
            }

            renderLoadingState(dropdownMenuContainer);

            try {
                const data = await swapitemFetch(courseID);
                await renderSearchWidget(dropdownMenuContainer, data.items || []);
            } catch (error) {
                renderErrorState(dropdownMenuContainer, error?.message || DEFAULT_ERROR_MESSAGE);
            }
        });

        widget.addEventListener('hide.bs.dropdown', () => {
            const dropdownMenuContainer = widget.querySelector('.dropdown-menu');
            if (dropdownMenuContainer) {
                dropdownMenuContainer.innerHTML = '';
            }
        });
    });
};

/**
 * Show a simple loading state while the AJAX request completes.
 *
 * @param {HTMLElement} container The dropdown menu container.
 */
const renderLoadingState = (container) => {
    container.innerHTML = `
        <div class="px-3 py-2 text-center text-muted">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </div>
    `;
};

/**
 * Render an error state into the dropdown.
 *
 * @param {HTMLElement} container The dropdown menu container.
 * @param {string} message The message to render.
 */
const renderErrorState = (container, message) => {
    container.innerHTML = `
        <div class="alert alert-danger m-2" role="alert">
            ${escapeHTML(message)}
        </div>
    `;
};

/**
 * Render the search widget contents and wire up live filtering.
 *
 * @param {HTMLElement} container The dropdown menu container.
 * @param {Array} items The items to display.
 */
const renderSearchWidget = async(container, items) => {
    const {html, js} = await Templates.renderForPromise(SEARCH_WIDGET_TEMPLATE, {
        uniqid: getUniqueId(),
    });
    Templates.replaceNodeContents(container, html, js);

    const input = container.querySelector('[data-region="input"]');
    const clearButton = container.querySelector('[data-action="clearsearch"]');
    const resultsContainer = container.querySelector('[data-region="search-results-container-widget"]');

    if (!input || !resultsContainer) {
        return;
    }

    const renderResults = (searchTerm = '') => {
        const normalizedSearchTerm = searchTerm.trim().toLowerCase();
        const filteredItems = items.filter((item) => {
            if (!normalizedSearchTerm) {
                return true;
            }

            return item.name.toLowerCase().includes(normalizedSearchTerm);
        });

        resultsContainer.innerHTML = buildResultMarkup(filteredItems, EMPTY_RESULTS_MESSAGE, 'name');
        if (clearButton) {
            clearButton.classList.toggle('d-none', normalizedSearchTerm === '');
        }
    };

    input.addEventListener('input', (event) => {
        renderResults(event.target.value);
    });

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            input.value = '';
            renderResults('');
            input.focus();
        });
    }

    renderResults();
    input.focus();
};

/**
 * Build the result markup for the dropdown.
 *
 * @param {Array} items The items to render.
 * @param {string} emptyMessage The empty state message.
 * @param {string} textKey The key used for the link label.
 * @returns {string}
 */
const buildResultMarkup = (items, emptyMessage, textKey) => {
    if (!items.length) {
        return `
            <div class="dropdown-item-text text-muted small">
                ${escapeHTML(emptyMessage)}
            </div>
        `;
    }

    return items.map((item) => `
        <a class="dropdown-item${item.active ? ' active' : ''}" href="${escapeHTML(item.url)}">
            ${escapeHTML(item[textKey])}
        </a>
    `).join('');
};

/**
 * Fetch the swappable items for the course.
 *
 * @param {number|string} courseid The course ID.
 * @returns {Promise<object>}
 */
const swapitemFetch = (courseid) => {
    const request = {
        methodname: 'block_stash_get_swap_items_for_search_widget',
        args: {
            courseid: courseid,
        },
    };
    return ajax.call([request])[0];
};

/**
 * Escape HTML content before inserting it into the DOM.
 *
 * @param {string} value The value to escape.
 * @returns {string}
 */
const escapeHTML = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

/**
 * Generate a unique-ish ID for the search input template.
 *
 * @returns {string}
 */
const getUniqueId = () => `${Date.now()}-${Math.round(Math.random() * 100000)}`;
