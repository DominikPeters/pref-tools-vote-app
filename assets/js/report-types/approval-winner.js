/**
 * Approval Winner Report Renderer
 * Card showing the winner(s) with most votes
 */

import { escapeHtml } from '../app.js';

export function renderApprovalWinner(container, data, config) {
    const { winners, is_tie, total_responses } = data;

    if (!winners || winners.length === 0) {
        container.innerHTML = '<p class="no-data">No winner determined yet.</p>';
        return;
    }

    const winnerNames = winners.map(w => escapeHtml(w.option)).join(', ');
    const winnerCount = winners[0].count;
    const winnerPercentage = winners[0].percentage;

    const html = `
        <div class="report-winner-card">
            ${is_tie ? '<p class="tie-notice">Tie between:</p>' : '<p class="winner-label">Winner</p>'}
            <div class="winner-name">${winnerNames}</div>
            <div class="winner-stats">
                <span class="winner-count">${winnerCount} vote${winnerCount !== 1 ? 's' : ''}</span>
                <span class="winner-percent">(${winnerPercentage}%)</span>
            </div>
        </div>
    `;

    container.innerHTML = html;
}
