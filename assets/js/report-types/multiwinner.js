/**
 * Multi-Winner Voting Rule Winner Report Renderer
 * Card showing the winning committee(s) under a selected multi-winner rule
 */

import { escapeHtml } from '../app.js';

export function renderMultiwinner(container, data, config) {
    const { rule, rule_name, committee_size, committees, explanation, is_tie, total_responses, error } = data;

    if (error) {
        container.innerHTML = `<p class="report-error">${escapeHtml(error)}</p>`;
        return;
    }

    if (!committees || committees.length === 0) {
        container.innerHTML = '<p class="no-data">No winning committee determined yet.</p>';
        return;
    }

    let committeesHtml = '';
    
    committees.forEach((committee, idx) => {
        const memberNames = committee.map(m => `<li>${escapeHtml(m.option)}</li>`).join('');
        committeesHtml += `
            <div class="abc-committee">
                ${is_tie ? `<p class="committee-idx">Committee #${idx + 1}</p>` : ''}
                <ul class="committee-members">
                    ${memberNames}
                </ul>
            </div>
        `;
    });

    const hasExplanation = !!explanation;

    const html = `
        <div class="report-winner-card abc-winner">
            <p class="rule-name">${escapeHtml(rule_name)}</p>
            <p class="committee-size">Committee Size: ${escapeHtml(committee_size)}</p>
            ${is_tie ? '<p class="tie-notice">Tied winning committees:</p>' : '<p class="winner-label">Winning Committee</p>'}
            <div class="committees-container">
                ${committeesHtml}
            </div>
            
            ${hasExplanation ? `
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

    // Bind toggle button if it exists
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