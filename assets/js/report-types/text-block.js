/**
 * Text Block Report Renderer
 * Displays user-provided markdown content
 */

import { marked } from 'https://cdn.jsdelivr.net/npm/marked/lib/marked.esm.js';

// Configure marked for safety
marked.setOptions({
    breaks: true,  // Convert \n to <br>
    gfm: true,     // GitHub Flavored Markdown
});

export function renderTextBlock(container, data, config) {
    const { content } = data;

    if (!content || content.trim() === '') {
        container.innerHTML = '<p class="no-data">No content provided.</p>';
        return;
    }

    // Parse markdown and render
    const html = `
        <div class="report-text-block">
            <div class="markdown-content">${marked.parse(content)}</div>
        </div>
    `;

    container.innerHTML = html;
}
