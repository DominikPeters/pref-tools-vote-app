/**
 * Apportionment Rule Winner Report Renderer
 */

import { escapeHtml } from '../app.js';

export function renderApportionmentWinner(container, data, config) {
    const { rule_name, seats, allocation, explanation, ties, total_votes, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    let allocationHtml = '';
    allocation.forEach(row => {
        allocationHtml += `
            <div class="allocation-row">
                <div class="party-info">
                    <span class="party-name">${escapeHtml(row.option)}</span>
                </div>
                <div class="allocation-data">
                    <span class="votes-count">${escapeHtml(row.votes.toLocaleString())} votes</span>
                    <span class="seats-count">${escapeHtml(row.seats)} seats</span>
                </div>
            </div>
        `;
    });

    const html = `
        <div class="report-winner-card apportionment-winner">
            <p class="rule-name">${escapeHtml(rule_name)}</p>
            <p class="total-seats">Total Seats: ${escapeHtml(seats)}</p>
            <div class="allocation-container">
                ${allocationHtml}
            </div>
            
            ${explanation ? `
                <div class="explanation-container">
                    <button class="btn btn-secondary btn-small btn-toggle-explanation">
                        Show Calculation Steps
                    </button>
                    <div class="explanation-content" style="display: none;">
                        ${explanation}
                    </div>
                </div>
            ` : ''}
        </div>
    `;

    container.innerHTML = html;

    const toggleBtn = container.querySelector('.btn-toggle-explanation');
    if (toggleBtn) {
        const content = container.querySelector('.explanation-content');
        toggleBtn.addEventListener('click', () => {
            const isVisible = content.style.display !== 'none';
            content.style.display = isVisible ? 'none' : 'block';
            toggleBtn.textContent = isVisible ? 'Show Calculation Steps' : 'Hide Calculation Steps';
        });
    }
}
