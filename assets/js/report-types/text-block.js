import { tHtml } from '../i18n.js';
/**
 * Text Block Report Renderer
 * Displays user-provided markdown content (rendered on backend using Parsedown)
 */

export function renderTextBlock(container, data, config) {
    const { html } = data;

    if (!html || html.trim() === '') {
        container.innerHTML = `<p class="no-data">${tHtml('js_no_content_provided')}</p>`;
        return;
    }

    // Display pre-rendered HTML from backend
    const markup = `
        <div class="report-text-block">
            <div class="markdown-content">${html}</div>
        </div>
    `;

    container.innerHTML = markup;
}
