/**
 * Apportionment Multi-Rule Comparison Report Renderer
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

export function renderApportionmentMultiRuleComparison(container, data, config) {
    const { results, options, seats, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_methods_selected')}</p>`;
        return;
    }

    let tableHtml = `
        <div class="table-responsive">
            <table class="multi-rule-table apportionment-comparison-table">
                <thead>
                    <tr>
                        <th class="text-left">${t('option')}</th>
                        <th>${t('votes')}</th>
                        ${results.map(r => {
                            const translatedRuleName = tFallback(`rule_${r.rule}`, r.rule_name);
                            return `<th class="text-center">${escapeHtml(translatedRuleName)}</th>`;
                        }).join('')}
                    </tr>
                </thead>
                <tbody>
    `;

    options.forEach((opt, optIdx) => {
        tableHtml += `
            <tr>
                <td class="party-cell">
                    <span class="party-name-text">${escapeHtml(opt.name)}</span>
                </td>
                <td class="votes-cell text-right">${escapeHtml(opt.votes.toLocaleString())}</td>
                ${results.map(r => `<td class="seats-cell text-center">${escapeHtml(r.allocation[optIdx])}</td>`).join('')}
            </tr>
        `;
    });

    tableHtml += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="2" class="text-right">${t('total_seats')}</th>
                        ${results.map(() => `<th class="text-center">${escapeHtml(seats)}</th>`).join('')}
                    </tr>
                </tfoot>
            </table>
        </div>
    `;

    container.innerHTML = `
        <div class="report-multi-rule apportionment-comparison">
            <p class="committee-size">${t('apportionment_comparison_desc', { methods: results.length, seats: seats })}</p>
            ${tableHtml}
        </div>
    `;
}