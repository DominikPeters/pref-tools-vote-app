/**
 * Choice Counts Report Renderer
 * Bar chart showing vote counts for each option
 */

import { escapeHtml } from '../app.js';

export function renderChoiceCounts(container, data, config) {
    const { scores, max_score, total_responses } = data;

    if (!scores || scores.length === 0) {
        container.innerHTML = '<p class="no-data">No votes yet.</p>';
        return;
    }

    const html = `
        <div class="report-choice-counts">
            <div class="bar-chart">
                ${scores.map(item => {
                    const barWidth = max_score > 0 ? (item.count / max_score * 100) : 0;
                    return `
                        <div class="bar-row">
                            <div class="bar-label">${escapeHtml(item.option)}</div>
                            <div class="bar-container">
                                <div class="bar" style="width: ${barWidth}%"></div>
                            </div>
                            <div class="bar-value">${item.count} (${item.percentage}%)</div>
                        </div>
                    `;
                }).join('')}
            </div>
            <p class="report-note">${total_responses} response${total_responses !== 1 ? 's' : ''}</p>
        </div>
    `;

    container.innerHTML = html;
}
