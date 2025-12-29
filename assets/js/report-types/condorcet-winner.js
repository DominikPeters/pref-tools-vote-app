/**
 * Condorcet Winner Report Renderer
 * Shows the Condorcet winner if one exists
 */

import { escapeHtml } from '../app.js';

export function renderCondorcetWinner(container, data, config) {
    const { exists, winner, message, total_responses } = data;

    if (exists && winner) {
        const html = `
            <div class="report-winner-card condorcet-winner">
                <p class="winner-label">
                    <i class="fa-solid fa-crown"></i>
                    Condorcet Winner
                </p>
                <div class="winner-name">${escapeHtml(winner.option)}</div>
                <p class="winner-explanation">Beats all other options in head-to-head matchups</p>
                <p class="report-note">${total_responses} response${total_responses !== 1 ? 's' : ''}</p>
            </div>
        `;
        container.innerHTML = html;
    } else {
        const html = `
            <div class="report-winner-card condorcet-no-winner">
                <p class="no-winner-label">
                    <i class="fa-solid fa-circle-xmark"></i>
                    No Condorcet Winner
                </p>
                <p class="no-winner-explanation">${escapeHtml(message || 'There is a cycle in pairwise preferences')}</p>
                <p class="report-note">${total_responses} response${total_responses !== 1 ? 's' : ''}</p>
            </div>
        `;
        container.innerHTML = html;
    }
}
