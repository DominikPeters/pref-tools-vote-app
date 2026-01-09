/**
 * Multi-Rule Comparison Report Renderer
 * Shows winners under multiple voting rules in a comparison table
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

export function renderMultiRuleComparison(container, data, config) {
    const { results, summary, total_rules, total_responses } = data;

    if (!results || results.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_rules_selected')}</p>`;
        return;
    }

    // Results table (rule -> winner(s))
    const resultRows = results.map(r => {
        const translatedRuleName = tFallback(`rule_${r.rule}`, r.rule_name);
        const winnerNames = r.winners.map(w => escapeHtml(w.option)).join(', ');
        const tieClass = r.is_tie ? 'multi-rule-tie' : '';
        return `
            <tr class="${tieClass}">
                <td class="rule-name">${escapeHtml(translatedRuleName)}</td>
                <td class="rule-winners">${winnerNames}${r.is_tie ? ` <span class="tie-badge">(${t('result_tied').toLowerCase()})</span>` : ''}</td>
            </tr>
        `;
    }).join('');

    // Summary section (which options won under how many rules)
    const summaryRows = summary.map(s => {
        const pct = Math.round((s.count / total_rules) * 100);
        const barWidth = pct;
        return `
            <div class="multi-rule-summary-row">
                <div class="summary-option">${escapeHtml(s.option)}</div>
                <div class="summary-bar-container">
                    <div class="summary-bar" style="width: ${barWidth}%"></div>
                    <span class="summary-count">${t('rules_count', { count: s.count, total: total_rules })}</span>
                </div>
            </div>
        `;
    }).join('');

    const html = `
        <div class="report-multi-rule">
            <table class="multi-rule-table">
                <thead>
                    <tr>
                        <th>${t('voting_rule')}</th>
                        <th>${t('winners')}</th>
                    </tr>
                </thead>
                <tbody>
                    ${resultRows}
                </tbody>
            </table>
            ${config.show_summary !== false ? `
                <div class="multi-rule-summary">
                    <h4>${t('winners_by_rule_count')}</h4>
                    ${summaryRows}
                </div>
            ` : ''}
        </div>
    `;

    container.innerHTML = html;
}
