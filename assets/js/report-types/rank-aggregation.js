/**
 * Rank Aggregation Report Renderer
 * Displays rankings produced by a Social Welfare Function
 */

import { escapeHtml } from '../app.js';

export function renderRankAggregation(container, data, config) {
    const { swf_name, rankings, is_tie, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!rankings || rankings.length === 0) {
        container.innerHTML = '<p class="no-data">No ranking determined yet.</p>';
        return;
    }

    let html = `
        <div class="report-rank-aggregation">
            <p class="swf-name">${escapeHtml(swf_name)}</p>
            ${is_tie ? `<p class="tie-notice">There are ${rankings.length} tied optimal rankings:</p>` : ''}
    `;

    rankings.forEach((ranking, rIdx) => {
        if (is_tie) {
            html += `<h5 class="ranking-header">Ranking #${rIdx + 1}</h5>`;
        }
        
        html += `<div class="aggregated-ranking">`;
        ranking.forEach((tier, tIdx) => {
            const isTiedTier = tier.length > 1;
            const rankLabel = tIdx + 1;
            
            html += `
                <div class="ranking-tier">
                    <div class="tier-rank">${rankLabel}</div>
                    <div class="tier-options ${isTiedTier ? 'is-tied' : ''}">
                        ${tier.map(opt => `
                            <div class="tier-option" data-option-id="${opt.option_id}">
                                ${escapeHtml(opt.option)}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    });

    html += `</div>`;
    container.innerHTML = html;
}
