/**
 * Voting Rule Winner Report Renderer
 * Card showing the winner under a selected voting rule
 */

import { escapeHtml } from '../app.js';

export function renderVotingRuleWinner(container, data, config) {
    const { rule, rule_name, winners, is_tie, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!winners || winners.length === 0) {
        container.innerHTML = '<p class="no-data">No winner determined yet.</p>';
        return;
    }

    const winnerNames = winners.map(w => escapeHtml(w.option)).join(', ');

    const html = `
        <div class="report-winner-card voting-rule-winner">
            <p class="rule-name">${escapeHtml(rule_name)}</p>
            ${is_tie ? '<p class="tie-notice">Tie between:</p>' : '<p class="winner-label">Winner</p>'}
            <div class="winner-name">${winnerNames}</div>
        </div>
    `;

    container.innerHTML = html;
}
