/**
 * Text Responses Report Renderer
 * Lists free-text answers to a question
 */

import { escapeHtml } from '../app.js';
import { t } from '../i18n.js';

export function renderTextResponses(container, data, config) {
    const { answers } = data;

    if (!answers || answers.length === 0) {
        container.innerHTML = `<p class="no-data">${t('no_responses')}</p>`;
        return;
    }

    container.innerHTML = `
        <ul class="text-responses">
            ${answers.map(a => `<li>${escapeHtml(a)}</li>`).join('')}
        </ul>
    `;
}
