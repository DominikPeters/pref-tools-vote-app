/**
 * Participatory Budgeting Rule Winner Report Renderer
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

export function renderPBWinner(container, data, config) {
    const { rule, rule_name, total_budget, winners, notes, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!winners || winners.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_winning_projects_yet')}</p>`;
        return;
    }

    const translatedRuleName = tFallback(`rule_${rule}`, rule_name);

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
            <p class="rule-name">${escapeHtml(translatedRuleName)}</p>
            <div class="pb-budget-summary">
                <div class="budget-stat">
                    <span class="stat-label">${t('total_budget')}:</span>
                    <span class="stat-value">${escapeHtml(total_budget.toLocaleString())}</span>
                </div>
                <div class="budget-stat">
                    <span class="stat-label">${t('spent')}:</span>
                    <span class="stat-value ${totalCost > total_budget ? 'over-budget' : ''}">${escapeHtml(totalCost.toLocaleString())}</span>
                </div>
            </div>

            <p class="winner-label">${t('winning_projects')}</p>
            <div class="pb-winners-list">
                ${winnerItems}
            </div>

            <div class="pb-outcome-stats">
                <p>${t('avg_voter_approves', { count: avgApproved.toFixed(2) })}</p>
                ${notes?.comparison ? `<p class="comparison-note">${escapeHtml(notes.comparison)}</p>` : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
}
