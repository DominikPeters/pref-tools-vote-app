/**
 * Condorcet Winner Report Renderer
 * Shows the Condorcet winner if one exists
 */

import { escapeHtml } from '../app.js';
import { t } from '../i18n.js';

// SVG icons
const winnerIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>';
const noWinnerIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';

export function renderCondorcetWinner(container, data, config) {
    const { exists, winner, message, total_responses } = data;

    if (exists && winner) {
        const html = `
            <div class="report-winner-card condorcet-winner">
                <p class="winner-label">
                    ${winnerIcon}
                    ${t('condorcet_winner')}
                </p>
                <div class="winner-name">${escapeHtml(winner.option)}</div>
                <p class="winner-explanation">${t('condorcet_explanation')}</p>
            </div>
        `;
        container.innerHTML = html;
    } else {
        const html = `
            <div class="report-winner-card condorcet-no-winner">
                <p class="no-winner-label">
                    ${noWinnerIcon}
                    ${t('no_condorcet_winner')}
                </p>
                <p class="no-winner-explanation">${escapeHtml(message || t('condorcet_cycle'))}</p>
            </div>
        `;
        container.innerHTML = html;
    }
}
