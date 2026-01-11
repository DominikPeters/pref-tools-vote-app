/**
 * Distribution Aggregation Report Renderer
 * Shows aggregated distribution as a table and stacked bar visualization
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

/**
 * Render distribution aggregation report
 * @param {HTMLElement} container
 * @param {object} data - { rule, rule_name, distribution, total_responses }
 * @param {object} config
 */
export function renderDistributionAggregation(container, data, config) {
    const { rule, rule_name, distribution, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!distribution || distribution.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_responses')}</p>`;
        return;
    }

    const translatedRuleName = tFallback(`dist_rule_${rule}`, rule_name);

    // Build the stacked bar segments
    const segments = distribution.map(d => {
        if (d.percentage === 0) return '';
        return `<div class="da-bar-segment" style="width: ${d.percentage}%; background-color: ${d.color};" title="${escapeHtml(d.option)}: ${d.percentage}%"></div>`;
    }).join('');

    // Build the results table
    const tableRows = distribution.map(d => `
        <tr>
            <td class="da-option-cell">
                <span class="da-color-swatch" style="background-color: ${d.color};"></span>
                ${escapeHtml(d.option)}
            </td>
            <td class="da-percentage-cell">${d.percentage}%</td>
        </tr>
    `).join('');

    // Build the legend
    const legendItems = distribution.map(d => `
        <span class="da-legend-item">
            <span class="da-legend-color" style="background-color: ${d.color};"></span>
            ${escapeHtml(d.option)}
        </span>
    `).join('');

    const html = `
        <div class="report-distribution-aggregation">
            <div class="da-header">
                <p class="da-rule-name">${escapeHtml(translatedRuleName)}</p>
                <p class="da-response-count">${total_responses} ${t('responses')}</p>
            </div>

            <div class="da-visualization">
                <div class="da-bar-container">
                    <div class="da-bar">
                        ${segments}
                    </div>
                </div>
            </div>

            <div class="da-legend">
                ${legendItems}
            </div>

            <table class="da-results-table">
                <thead>
                    <tr>
                        <th>${t('option')}</th>
                        <th>${t('percentage')}</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}
