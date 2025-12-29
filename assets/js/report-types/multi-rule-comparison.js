/**
 * Multi-Rule Comparison Report Renderer
 * Shows winners under multiple voting rules in a comparison table
 */

import { escapeHtml } from '../app.js';

export function renderMultiRuleComparison(container, data, config) {
    const { results, summary, total_rules, total_responses } = data;

    if (!results || results.length === 0) {
        container.innerHTML = '<p class="no-data">No rules selected or computed.</p>';
        return;
    }

    // Results table (rule -> winner(s))
    const resultRows = results.map(r => {
        const winnerNames = r.winners.map(w => escapeHtml(w.option)).join(', ');
        const tieClass = r.is_tie ? 'multi-rule-tie' : '';
        return `
            <tr class="${tieClass}">
                <td class="rule-name">${escapeHtml(r.rule_name)}</td>
                <td class="rule-winners">${winnerNames}${r.is_tie ? ' <span class="tie-badge">(tie)</span>' : ''}</td>
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
                    <span class="summary-count">${s.count}/${total_rules} rules</span>
                </div>
            </div>
        `;
    }).join('');

    const html = `
        <div class="report-multi-rule">
            <div class="multi-rule-summary">
                <h4>Winners by Rule Count</h4>
                ${summaryRows}
            </div>
            <table class="multi-rule-table">
                <thead>
                    <tr>
                        <th>Voting Rule</th>
                        <th>Winner(s)</th>
                    </tr>
                </thead>
                <tbody>
                    ${resultRows}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}
