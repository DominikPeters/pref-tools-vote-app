/**
 * Voting Rule Winner Report Renderer
 * Card showing the winner under a selected voting rule
 */

import { escapeHtml } from '../app.js';
import { t, tFallback } from '../i18n.js';

export function renderVotingRuleWinner(container, data, config) {
    const { rule, rule_name, winners, is_tie, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!winners || winners.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_winner_yet')}</p>`;
        return;
    }

    // Translate rule name if translation exists, otherwise use English from registry
    const translatedRuleName = tFallback(`rule_${rule}`, rule_name);
    const winnerNames = winners.map(w => escapeHtml(w.option)).join(', ');

    const html = `
        <div class="report-winner-card voting-rule-winner">
            <p class="rule-name">${escapeHtml(translatedRuleName)}</p>
            ${is_tie ? `<p class="tie-notice">${t('result_tied')}:</p>` : `<p class="winner-label">${t('result_winner')}:</p>`}
            <div class="winner-name">${winnerNames}</div>
        </div>
    `;

    container.innerHTML = html;
}
