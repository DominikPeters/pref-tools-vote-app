/**
 * Multi-SWF Comparison Report Renderer
 * Compares rankings from multiple Social Welfare Functions
 */

import { escapeHtml } from '../app.js';

export function renderMultiSWFComparison(container, data, config) {
    const { results, total_swfs, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!results || results.length === 0) {
        container.innerHTML = '<p class="no-data">No results determined yet.</p>';
        return;
    }

    let html = `<div class="report-multi-swf">`;

    results.forEach(result => {
        const { swf_name, rankings, is_tie } = result;
        const topRanking = rankings[0]; // Just show the first one in comparison to save space

        html += `
            <div class="swf-comparison-item">
                <h4 class="swf-title">
                    ${escapeHtml(swf_name)}
                    ${is_tie ? `<span class="tie-badge" title="This method resulted in ${rankings.length} tied rankings. Only the first one is shown here.">(tie)</span>` : ''}
                </h4>
                <div class="swf-ranking-preview">
        `;

        topRanking.forEach((tier, tIdx) => {
            const isTiedTier = tier.length > 1;
            const optionNames = tier.map(opt => escapeHtml(opt.option)).join(', ');
            
            html += `
                <div class="preview-tier">
                    <span class="preview-rank">${tIdx + 1}.</span>
                    <span class="preview-options ${isTiedTier ? 'is-tied' : ''}">${optionNames}</span>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;
    });

    html += `</div>`;
    container.innerHTML = html;
}
