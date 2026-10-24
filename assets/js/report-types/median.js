/**
 * Median Choice Report Renderer
 * Shows the median option(s) assuming ordered options and single-peaked preferences
 */

import { escapeHtml } from '../app.js';
import { t, tHtml } from '../i18n.js';

export function renderMedian(container, data, config) {
    const { medians, is_tie, total_responses } = data;

    if (!medians || medians.length === 0) {
        container.innerHTML = `<p class="no-data">${tHtml('no_median_yet')}</p>`;
        return;
    }

    const medianNames = medians.map(m => escapeHtml(m.option)).join(', ');

    let html;
    if (is_tie) {
        html = `
            <div class="report-winner-card">
                <p class="tie-notice">${tHtml('median_interval')}:</p>
                <div class="winner-name">${medianNames}</div>
                <div class="winner-stats">
                    <span class="winner-count">${tHtml('response_count', { count: total_responses })}</span>
                </div>
            </div>
        `;
    } else {
        html = `
            <div class="report-winner-card">
                <p class="winner-label">${tHtml('median_choice')}</p>
                <div class="winner-name">${medianNames}</div>
                <div class="winner-stats">
                    <span class="winner-count">${tHtml('response_count', { count: total_responses })}</span>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}
