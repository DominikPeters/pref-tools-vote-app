/**
 * ABC Multi-Rule Comparison Report Renderer
 * Shows winning committees under multiple ABC voting rules
 */

import { escapeHtml } from '../app.js';

export function renderABCMultiRuleComparison(container, data, config) {
    const { results, summary, committee_size, total_rules, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = '<p class="no-data">No rules selected or computed.</p>';
        return;
    }

    // Results table (rule -> committee members)
    const resultRows = results.map(r => {
        const memberNames = r.committee.map(m => escapeHtml(m.option)).join(', ');
        const tieClass = r.is_tie ? 'multi-rule-tie' : '';
        return `
            <tr class="${tieClass}">
                <td class="rule-name">${escapeHtml(r.rule_name)}</td>
                <td class="rule-committee">
                    ${memberNames}
                    ${r.is_tie ? ' <span class="tie-badge">(tie)</span>' : ''}
                </td>
            </tr>
        `;
    }).join('');

    // Summary section (frequency of options across rules)
    const summaryRows = summary.map(s => {
        const pct = Math.round((s.count / total_rules) * 100);
        return `
            <div class="multi-rule-summary-row">
                <div class="summary-option">${escapeHtml(s.option)}</div>
                <div class="summary-bar-container">
                    <div class="summary-bar" style="width: ${pct}%"></div>
                    <span class="summary-count">${s.count}/${total_rules} rules</span>
                </div>
            </div>
        `;
    }).join('');

    const html = `
        <div class="report-multi-rule abc-multi-rule">
            <p class="committee-size">Committee Size: ${escapeHtml(committee_size)}</p>
            <table class="multi-rule-table">
                <thead>
                    <tr>
                        <th>Voting Rule</th>
                        <th>Winning Committee</th>
                    </tr>
                </thead>
                <tbody>
                    ${resultRows}
                </tbody>
            </table>
            ${config.show_summary !== false ? `
                <div class="multi-rule-summary">
                    <h4>Option Frequency in winning committees</h4>
                    ${summaryRows}
                </div>
            ` : ''}
        </div>
    `;

    container.innerHTML = html;
}
