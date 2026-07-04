/**
 * Score Distribution Report Renderer
 * Per-option histogram of scores/grades with averages
 */

import { escapeHtml } from '../app.js';
import { t } from '../i18n.js';

export function renderScoreDistribution(container, data, config) {
    const { is_numeric, grades, max_grade, options, total_responses } = data;

    if (!options || options.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_responses')}</p>`;
        return;
    }

    const html = `
        <div class="report-score-distribution">
            ${options.map(row => {
                // Stars ascend left-to-right (1…N); grades stay best-to-worst
                const cols = is_numeric ? [...row.counts].reverse() : row.counts;
                const maxCount = Math.max(1, ...row.counts.map(c => c.count));
                const summary = is_numeric
                    ? `${row.average !== null ? row.average : '–'} / ${max_grade} ★`
                    : (row.median !== null ? escapeHtml(String(row.median)) : '–');

                return `
                    <div class="score-dist-row">
                        <div class="score-dist-header">
                            <span class="score-dist-option">${escapeHtml(row.option)}</span>
                            <span class="score-dist-summary">${summary}</span>
                        </div>
                        <div class="score-dist-bars">
                            ${cols.map(c => `
                                <div class="score-dist-bar-col" title="${escapeHtml(String(c.grade))}: ${c.count}">
                                    <div class="score-dist-bar-wrap">
                                        <div class="score-dist-bar" style="height: ${Math.round(c.count / maxCount * 100)}%"></div>
                                    </div>
                                    <div class="score-dist-bar-label">${escapeHtml(String(c.grade))}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;

    container.innerHTML = html;
}
