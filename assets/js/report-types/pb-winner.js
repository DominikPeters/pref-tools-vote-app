/**
 * Participatory Budgeting Rule Winner Report Renderer
 */

import { escapeHtml } from '../app.js';

export function renderPBWinner(container, data, config) {
    const { rule_name, total_budget, winners, notes, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!winners || winners.length === 0) {
        container.innerHTML = '<p class="no-data">No winning projects determined yet.</p>';
        return;
    }

    const winnerItems = winners.map(w => `
        <div class="pb-winner-item">
            <span class="pb-winner-name">${escapeHtml(w.option)}</span>
            <span class="pb-winner-cost">${escapeHtml(w.cost.toLocaleString())}</span>
        </div>
    `).join('');

    const stats = notes?.stats || {};
    const totalCost = stats.totalCost || 0;
    const avgApproved = stats.avgApprovedProjects || 0;

    const html = `
        <div class="report-winner-card pb-winner">
            <p class="rule-name">${escapeHtml(rule_name)}</p>
            <div class="pb-budget-summary">
                <div class="budget-stat">
                    <span class="stat-label">Total Budget:</span>
                    <span class="stat-value">${escapeHtml(total_budget.toLocaleString())}</span>
                </div>
                <div class="budget-stat">
                    <span class="stat-label">Spent:</span>
                    <span class="stat-value ${totalCost > total_budget ? 'over-budget' : ''}">${escapeHtml(totalCost.toLocaleString())}</span>
                </div>
            </div>

            <p class="winner-label">Winning Projects</p>
            <div class="pb-winners-list">
                ${winnerItems}
            </div>

            <div class="pb-outcome-stats">
                <p>On average, each voter approves <strong>${avgApproved.toFixed(2)}</strong> winning projects.</p>
                ${notes?.comparison ? `<p class="comparison-note">${escapeHtml(notes.comparison)}</p>` : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
}
