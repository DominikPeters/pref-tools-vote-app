/**
 * Multi-Winner Multi-Rule Comparison Report Renderer
 * Table comparing winning committees under multiple multi-winner rules
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

export function renderMultiwinnerMultiRuleComparison(container, data, config) {
    const { results, summary, committee_size, total_rules, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_results_to_compare')}</p>`;
        return;
    }

    const showSummary = config.show_summary !== false;

    let html = `
        <div class="report-comparison multiwinner-comparison">
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>${t('voting_rule')}</th>
                            <th>${t('winning_committee')} (${t('committee_size_label', { size: committee_size })})</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${results.map(res => {
                            const translatedRuleName = tFallback(`rule_${res.rule}`, res.rule_name);
                            return `
                            <tr>
                                <td class="rule-name">
                                    ${escapeHtml(translatedRuleName)}
                                    ${res.is_tie ? `<span class="tie-badge" title="${t('tie_showing_first')}">${t('result_tied')}</span>` : ''}
                                </td>
                                <td>
                                    <ul class="committee-list-inline">
                                        ${res.committee.map(m => `<li>${escapeHtml(m.option)}</li>`).join('')}
                                    </ul>
                                </td>
                            </tr>
                        `}).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    if (showSummary && summary && summary.length > 0) {
        html += `
            <div class="report-summary mt-4">
                <h5>${t('candidate_frequency')}</h5>
                <p class="summary-desc">${t('candidate_frequency_desc', { count: total_rules })}</p>
                <div class="table-container">
                    <table class="report-table summary-table">
                        <thead>
                            <tr>
                                <th>${t('candidate')}</th>
                                <th>${t('count')}</th>
                                <th>${t('rules')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${summary.map(item => `
                                <tr>
                                    <td>${escapeHtml(item.option)}</td>
                                    <td><strong>${escapeHtml(item.count)}</strong> / ${escapeHtml(total_rules)}</td>
                                    <td class="small-text">${escapeHtml(item.rules.join(', '))}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}