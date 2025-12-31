/**
 * Response Matrix Report Renderer
 * Doodle-style table showing each voter's choices
 */

import { escapeHtml } from '../app.js';

/**
 * Get a color for a grade based on its position in the scale
 * Uses a gradient from green (best) to red (worst)
 */
function getGradeColor(gradeIndex, totalGrades) {
    if (gradeIndex < 0 || totalGrades <= 1) return 'var(--color-text-dim)';

    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const lightness = isDarkMode ? 65 : 45;

    // 0 = best (green), totalGrades-1 = worst (red)
    const hue = 120 - (gradeIndex / (totalGrades - 1)) * 120;
    return `hsl(${hue}, 60%, ${lightness}%)`;
}

/**
 * Get a color for a star rating
 */
function getStarColor(value, max) {
    if (!value || max <= 1) return 'var(--color-text-dim)';

    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const lightness = isDarkMode ? 65 : 45;

    const ratio = value / max;
    const hue = ratio * 120; // 0 = red, 120 = green
    return `hsl(${hue}, 60%, ${lightness}%)`;
}

export function renderResponseMatrix(container, data, config) {
    const { question_type, options, rows, grades, total_responses, show_names } = data;

    if (!rows || rows.length === 0) {
        container.innerHTML = '<p class="no-data">No responses yet.</p>';
        return;
    }

    // Build header row
    const headerCells = options.map(o =>
        `<th class="matrix-option-header" title="${escapeHtml(o.label)}">${escapeHtml(abbreviate(o.label, 12))}</th>`
    ).join('');

    // Build body rows
    const totalOptions = options.length;
    const bodyRows = rows.map(row => {
        const cells = row.cells.map(cell => renderCell(cell, question_type, grades, totalOptions)).join('');
        return `
            <tr>
                <td class="matrix-voter">${escapeHtml(row.voter)}</td>
                ${cells}
            </tr>
        `;
    }).join('');

    const html = `
        <div class="report-response-matrix">
            <div class="matrix-scroll-container">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-voter-header">Voter</th>
                            ${headerCells}
                        </tr>
                    </thead>
                    <tbody>
                        ${bodyRows}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    container.innerHTML = html;
}

// Simple SVG checkmark
const checkmarkSvg = `<svg class="cell-checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;

/**
 * Get background color for a rank (1 = darkest blue, higher = lighter)
 */
function getRankColor(rank, maxRank) {
    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (!rank || !maxRank || maxRank <= 1) {
        return isDarkMode ? 'hsl(217, 40%, 25%)' : 'hsl(217, 70%, 85%)';
    }

    if (isDarkMode) {
        // Dark mode: Rank 1 = 40% lightness (brighter), last rank = 15% lightness (darker)
        const lightness = 40 - ((rank - 1) / (maxRank - 1)) * 25;
        return `hsl(217, 40%, ${lightness}%)`;
    } else {
        // Light mode: Rank 1 = 50% lightness (darker), last rank = 90% lightness (lighter)
        const lightness = 50 + ((rank - 1) / (maxRank - 1)) * 40;
        return `hsl(217, 70%, ${lightness}%)`;
    }
}

/**
 * Render a single cell based on its type
 */
function renderCell(cell, questionType, grades, totalOptions) {
    if (cell.type === 'empty') {
        return `<td class="matrix-cell cell-empty"></td>`;
    }

    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    switch (cell.type) {
        case 'check':
            return `<td class="matrix-cell ${cell.class}">
                ${cell.value ? checkmarkSvg : ''}
            </td>`;

        case 'rank': {
            const bgColor = getRankColor(cell.value, totalOptions);
            let textColor;
            if (isDarkMode) {
                textColor = 'var(--color-text)';
            } else {
                textColor = cell.value <= Math.ceil(totalOptions / 2) ? '#fff' : '#1e40af';
            }
            return `<td class="matrix-cell ${cell.class}" style="background-color: ${bgColor}; color: ${textColor};">${cell.display}</td>`;
        }

        case 'star': {
            const color = getStarColor(cell.value, cell.max);
            return `<td class="matrix-cell ${cell.class}" style="color: ${color};" title="${cell.value}/${cell.max} stars">${cell.display}</td>`;
        }

        case 'grade': {
            const totalGrades = grades ? grades.length : cell.total_grades;
            const color = getGradeColor(cell.grade_index, totalGrades);
            return `<td class="matrix-cell ${cell.class}" style="color: ${color};" title="${escapeHtml(cell.full_grade || cell.value)}">${escapeHtml(cell.display)}</td>`;
        }

        case 'yna':
            return `<td class="matrix-cell ${cell.class}">${cell.display}</td>`;

        default:
            return `<td class="matrix-cell">${escapeHtml(cell.display || '?')}</td>`;
    }
}

/**
 * Abbreviate text to a maximum length
 */
function abbreviate(text, maxLen) {
    if (!text || text.length <= maxLen) return text;
    return text.substring(0, maxLen - 1) + '…';
}
