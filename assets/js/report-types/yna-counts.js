/**
 * Yes/No/Abstain Counts Report Renderer
 * Shows vote tallies for each option
 */

import { escapeHtml } from '../app.js';

export function renderYNACounts(container, data, config) {
    const { results, total_responses } = data;

    if (!results || results.length === 0) {
        container.innerHTML = '<p class="no-data">No responses yet.</p>';
        return;
    }

    const rows = results.map(r => `
        <tr>
            <td class="yna-option">${escapeHtml(r.option)}</td>
            <td class="yna-cell yna-yes">
                <div class="yna-bar-container">
                    <div class="yna-bar yes-bar" style="width: ${r.yes_pct}%"></div>
                    <span class="yna-value">${r.yes}</span>
                </div>
            </td>
            <td class="yna-cell yna-no">
                <div class="yna-bar-container">
                    <div class="yna-bar no-bar" style="width: ${r.no_pct}%"></div>
                    <span class="yna-value">${r.no}</span>
                </div>
            </td>
            <td class="yna-cell yna-abstain">
                <div class="yna-bar-container">
                    <div class="yna-bar abstain-bar" style="width: ${r.abstain_pct}%"></div>
                    <span class="yna-value">${r.abstain}</span>
                </div>
            </td>
        </tr>
    `).join('');

    const html = `
        <div class="report-yna-counts">
            <table class="yna-table">
                <thead>
                    <tr>
                        <th>Option</th>
                        <th class="yna-header yes-header">Yes</th>
                        <th class="yna-header no-header">No</th>
                        <th class="yna-header abstain-header">Abstain</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}
