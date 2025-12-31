/**
 * Majority Judgment Report Renderer
 * Shows grade distribution bars with median line for each option
 */

import { escapeHtml } from '../app.js';

/**
 * Get a color for a grade based on its position in the scale
 * Uses a gradient from green (best) to red (worst)
 */
function getGradeColor(gradeIndex, totalGrades) {
    if (totalGrades <= 1) return 'hsl(120, 60%, 50%)';

    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const lightness = isDarkMode ? 40 : 50; // Slightly darker segments in dark mode to avoid glare

    // 0 = best (green), totalGrades-1 = worst (red)
    const hue = 120 - (gradeIndex / (totalGrades - 1)) * 120;
    return `hsl(${hue}, 60%, ${lightness}%)`;
}

export function renderMajorityJudgment(container, data, config) {
    const { winners, is_tie, grades, distributions, total_responses } = data;

    if (!distributions || distributions.length === 0) {
        container.innerHTML = '<p class="no-data">No responses yet.</p>';
        return;
    }

    // Winner card
    const winnerNames = winners.map(w => escapeHtml(w.option)).join(', ');
    const winnerHtml = `
        <div class="report-winner-card mj-winner">
            ${is_tie ? '<p class="tie-notice">Tie between:</p>' : '<p class="winner-label">Majority Judgment Winner</p>'}
            <div class="winner-name">${winnerNames}</div>
        </div>
    `;

    // Grade distributions
    const distributionRows = distributions.map(d => {
        // Build the stacked bar segments
        const segments = d.grade_proportions.map((gp, idx) => {
            if (gp.percentage === 0) return '';
            const color = getGradeColor(idx, grades.length);
            return `<div class="mj-bar-segment" style="width: ${gp.percentage}%; background-color: ${color};" title="${escapeHtml(String(gp.grade))}: ${gp.percentage}%"></div>`;
        }).join('');

        // Median line is always at 50% - it shows which grade the median falls in
        const isWinner = d.is_winner;
        const rowClass = isWinner ? 'mj-row mj-winner-row' : 'mj-row';

        return `
            <div class="${rowClass}">
                <div class="mj-option-label">${escapeHtml(d.option)}</div>
                <div class="mj-bar-container">
                    <div class="mj-bar">
                        ${segments}
                    </div>
                    <div class="mj-median-line" title="Median: ${escapeHtml(String(d.median_grade))}"></div>
                </div>
                <div class="mj-median-label">${escapeHtml(String(d.median_grade))}</div>
            </div>
        `;
    }).join('');

    // Grade legend
    const legendItems = grades.map((g, idx) => {
        const color = getGradeColor(idx, grades.length);
        return `<span class="mj-legend-item"><span class="mj-legend-color" style="background-color: ${color};"></span>${escapeHtml(String(g))}</span>`;
    }).join('');

    const html = `
        <div class="report-majority-judgment">
            ${winnerHtml}
            <div class="mj-distributions">
                ${distributionRows}
            </div>
            <div class="mj-legend">
                ${legendItems}
            </div>
        </div>
    `;

    container.innerHTML = html;
}
