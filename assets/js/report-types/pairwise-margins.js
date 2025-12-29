/**
 * Pairwise Margins Report Renderer
 * SVG graph showing head-to-head margins between candidates
 */

import { escapeHtml } from '../app.js';

export function renderPairwiseMargins(container, data, config) {
    const { candidates, edges, condorcet_winner, total_responses } = data;

    if (!candidates || candidates.length === 0) {
        container.innerHTML = '<p class="no-data">No candidates.</p>';
        return;
    }

    // Calculate layout dimensions
    const n = candidates.length;
    const width = 400;
    const height = 400;
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) * 0.35;

    // Position candidates in a circle
    const positions = candidates.map((c, i) => {
        const angle = (2 * Math.PI * i / n) - Math.PI / 2; // Start from top
        return {
            id: c.id,
            label: c.label,
            x: centerX + radius * Math.cos(angle),
            y: centerY + radius * Math.sin(angle),
        };
    });

    const posMap = {};
    positions.forEach(p => posMap[p.id] = p);

    // Build SVG
    let svg = `<svg viewBox="0 0 ${width} ${height}" class="pairwise-graph">`;
    svg += '<defs>';
    svg += '<marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">';
    svg += '<polygon points="0 0, 10 3.5, 0 7" fill="#666" />';
    svg += '</marker>';
    svg += '</defs>';

    // Draw edges
    edges.forEach(edge => {
        const from = posMap[edge.from];
        const to = posMap[edge.to];
        if (!from || !to) return;

        // Calculate arrow position (shortened to not overlap with nodes)
        const dx = to.x - from.x;
        const dy = to.y - from.y;
        const len = Math.sqrt(dx * dx + dy * dy);
        const nodeRadius = 20;

        const startX = from.x + (dx / len) * nodeRadius;
        const startY = from.y + (dy / len) * nodeRadius;
        const endX = to.x - (dx / len) * (nodeRadius + 5);
        const endY = to.y - (dy / len) * (nodeRadius + 5);

        // Edge line
        svg += `<line x1="${startX}" y1="${startY}" x2="${endX}" y2="${endY}"
                      stroke="#666" stroke-width="2" marker-end="url(#arrowhead)" />`;

        // Margin label
        const midX = (startX + endX) / 2;
        const midY = (startY + endY) / 2;
        svg += `<text x="${midX}" y="${midY}" text-anchor="middle" dy="-5"
                      class="margin-label">${edge.margin}</text>`;
    });

    // Draw nodes
    positions.forEach(pos => {
        const isCondorcet = condorcet_winner === pos.id;
        const nodeClass = isCondorcet ? 'condorcet-winner' : '';

        svg += `<circle cx="${pos.x}" cy="${pos.y}" r="20"
                        class="candidate-node ${nodeClass}" />`;
        svg += `<text x="${pos.x}" y="${pos.y + 35}" text-anchor="middle"
                      class="candidate-label">${escapeHtml(pos.label)}</text>`;
    });

    svg += '</svg>';

    // Build full HTML
    let html = `<div class="report-pairwise-margins">`;

    if (condorcet_winner) {
        const winnerName = candidates.find(c => c.id === condorcet_winner)?.label || 'Unknown';
        html += `<p class="condorcet-notice">Condorcet winner: <strong>${escapeHtml(winnerName)}</strong></p>`;
    }

    html += svg;
    html += `</div>`;

    container.innerHTML = html;
}
