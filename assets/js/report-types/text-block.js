/**
 * Text Block Report Renderer
 * Displays user-provided markdown content (rendered on backend using Parsedown)
 */

export function renderTextBlock(container, data, config) {
    const { html } = data;

    if (!html || html.trim() === '') {
        container.innerHTML = '<p class="no-data">No content provided.</p>';
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
