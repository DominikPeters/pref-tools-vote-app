import { t, tHtml } from '../i18n.js';
/**
 * Borda Scores Report Renderer
 * Bar chart showing Borda count scores
 */

import { escapeHtml } from '../app.js';

export function renderBordaScores(container, data, config) {
    const { scores, max_score, total_responses } = data;

    if (!scores || scores.length === 0) {
        container.innerHTML = `<p class="no-data">${tHtml('js_no_rankings_yet')}</p>`;
        return;
    }

    const html = `
        <div class="report-borda-scores">
            <p class="result-note">${tHtml('borda_score_note')}</p>
            <div class="bar-chart">
                ${scores.map((item, rank) => {
                    const barWidth = max_score > 0 ? (item.score / max_score * 100) : 0;
                    return `
                        <div class="bar-row">
                            <div class="bar-rank">#${rank + 1}</div>
                            <div class="bar-label">${escapeHtml(item.option)}</div>
                            <div class="bar-container">
                                <div class="bar" style="width: ${barWidth}%"></div>
                            </div>
                            <div class="bar-value">${tHtml('js_score_points', { count: item.score })}</div>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;

    container.innerHTML = html;
}
