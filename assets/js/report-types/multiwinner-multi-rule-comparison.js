/**
 * Multi-Winner Multi-Rule Comparison Report Renderer
 * Table comparing winning committees under multiple multi-winner rules
 */

import { escapeHtml } from '../app.js';

export function renderMultiwinnerMultiRuleComparison(container, data, config) {
    const { results, summary, committee_size, total_rules, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = '<p class="no-data">No results to compare.</p>';
        return;
    }

    const showSummary = config.show_summary !== false;

    let html = `
        <div class="report-comparison multiwinner-comparison">
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Voting Rule</th>
                            <th>Winning Committee (size ${escapeHtml(committee_size)})</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${results.map(res => `
                            <tr>
                                <td class="rule-name">
                                    ${escapeHtml(res.rule_name)}
                                    ${res.is_tie ? '<span class="tie-badge" title="Tied (showing first winning committee)">Tie</span>' : ''}
                                </td>
                                <td>
                                    <ul class="committee-list-inline">
                                        ${res.committee.map(m => `<li>${escapeHtml(m.option)}</li>`).join('')}
                                    </ul>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    if (showSummary && summary && summary.length > 0) {
        html += `
            <div class="report-summary mt-4">
                <h5>Candidate Frequency</h5>
                <p class="summary-desc">How often each candidate appears in a winning committee across the ${escapeHtml(total_rules)} rules compared.</p>
                <div class="table-container">
                    <table class="report-table summary-table">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Count</th>
                                <th>Rules</th>
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