/**
 * Distribution Multi-Rule Comparison Report Renderer
 * Shows comparison of aggregated distributions under different rules
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

/**
 * Render distribution multi-rule comparison report
 * @param {HTMLElement} container
 * @param {object} data - { results, options, total_responses }
 * @param {object} config
 */
export function renderDistributionMultiRuleComparison(container, data, config) {
    const { results, options, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_responses')}</p>`;
        return;
    }

    // Build a map of option_id to color/name for quick lookup
    const optionMap = {};
    options.forEach(opt => {
        optionMap[opt.option_id] = opt;
    });

    // Build stacked bars for each rule
    const ruleBars = results.map(result => {
        const translatedRuleName = tFallback(`dist_rule_${result.rule}`, result.rule_name);

        const segments = result.distribution.map(d => {
            const opt = optionMap[d.option_id];
            if (!opt || d.percentage === 0) return '';
            return `<div class="da-bar-segment" style="width: ${d.percentage}%; background-color: ${opt.color};" title="${escapeHtml(opt.name)}: ${d.percentage}%"></div>`;
        }).join('');

        return `
            <div class="da-comparison-row">
                <div class="da-rule-label">${escapeHtml(translatedRuleName)}</div>
                <div class="da-bar-container">
                    <div class="da-bar">
                        ${segments}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Build the legend
    const legendItems = options.map(opt => `
        <span class="da-legend-item">
            <span class="da-legend-color" style="background-color: ${opt.color};"></span>
            ${escapeHtml(opt.name)}
        </span>
    `).join('');

    // Build comparison table
    const tableHeader = `
        <tr>
            <th>${t('rule')}</th>
            ${options.map(opt => `<th title="${escapeHtml(opt.name)}">${escapeHtml(opt.name)}</th>`).join('')}
        </tr>
    `;

    const tableRows = results.map(result => {
        const translatedRuleName = tFallback(`dist_rule_${result.rule}`, result.rule_name);

        const cells = options.map(opt => {
            const distItem = result.distribution.find(d => d.option_id === opt.option_id);
            const percentage = distItem ? distItem.percentage : 0;
            return `<td class="da-percentage-cell">${percentage}%</td>`;
        }).join('');

        return `
            <tr>
                <td class="da-rule-cell">${escapeHtml(translatedRuleName)}</td>
                ${cells}
            </tr>
        `;
    }).join('');

    const html = `
        <div class="report-distribution-multi-rule-comparison">
            <div class="da-header">
                <p class="da-response-count">${total_responses} ${t('responses')}</p>
            </div>

            <div class="da-comparison-bars">
                ${ruleBars}
            </div>

            <div class="da-legend">
                ${legendItems}
            </div>

            <table class="da-comparison-table">
                <thead>
                    ${tableHeader}
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}
