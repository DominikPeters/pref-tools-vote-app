/**
 * Results Core Module
 *
 * Shared logic for rendering results on both admin and public views.
 */

import { api, escapeHtml, showUndoToast } from './app.js';
import { renderReport, getReportTypeName, getReportTypeIcon } from './report-types/index.js';

/**
 * Load and render results for a poll
 *
 * @param {string} publicId - The poll's public ID
 * @param {Object} options - Configuration options
 * @param {HTMLElement} options.container - Container element to render into
 * @param {string} [options.adminToken] - Admin token for admin view (optional)
 * @param {boolean} [options.isAdmin] - Whether this is an admin view
 * @param {Function} [options.onReportClick] - Callback when a report is clicked (admin)
 * @param {Function} [options.onAddReport] - Callback when add report button is clicked
 */
export async function loadAndRenderResults(publicId, options = {}) {
    const { container, adminToken, isAdmin = false } = options;

    if (!container) {
        console.error('No container provided for results');
        return;
    }

    container.innerHTML = '<p class="loading">Loading results...</p>';

    try {
        // Fetch poll data
        const pollResult = await api.get(`/api/polls/${publicId}`);
        const poll = pollResult.poll;

        // Fetch reports (admin sees all, public sees only is_public)
        const reportsUrl = adminToken
            ? `/api/polls/${publicId}/admin/${adminToken}/reports`
            : `/api/polls/${publicId}/reports`;

        const reportsResult = await api.get(reportsUrl);
        const reports = reportsResult.reports || [];

        // Render results
        renderResults(container, poll, reports, { isAdmin, adminToken, publicId, ...options });

    } catch (err) {
        console.error('Failed to load results:', err);
        container.innerHTML = '<p class="error-message">Failed to load results.</p>';
    }
}

/**
 * Render the results UI
 */
function renderResults(container, poll, reports, options) {
    const { isAdmin, publicId, adminToken, onAddReport } = options;

    // Group reports by question
    const reportsByQuestion = {};
    reports.forEach(report => {
        if (!reportsByQuestion[report.question_id]) {
            reportsByQuestion[report.question_id] = [];
        }
        reportsByQuestion[report.question_id].push(report);
    });

    // Check if there are any reports at all
    const hasReports = reports.length > 0;

    let html = '';

    // Response count summary
    html += `<div class="results-summary card">
        <p>Results for <strong>${escapeHtml(poll.title)}</strong></p>
    </div>`;

    // Render each question
    poll.questions.forEach(question => {
        // Skip section headers
        if (question.type === 'section_header') return;

        const questionReports = reportsByQuestion[question.id] || [];

        html += `
            <div class="result-question card" data-question-id="${question.id}">
                <h3>${escapeHtml(question.text)}</h3>
                ${question.description ? `<p class="question-description">${escapeHtml(question.description)}</p>` : ''}

                <div class="question-reports" data-question-id="${question.id}">
                    ${questionReports.length === 0 && !isAdmin
                        ? '<p class="no-reports">No results available for this question.</p>'
                        : ''}
                </div>

                ${isAdmin ? `
                    <button class="btn btn-secondary btn-add-report" data-question-id="${question.id}">
                        + Add Analysis
                    </button>
                ` : ''}
            </div>
        `;
    });

    container.innerHTML = html;

    // Render individual reports
    reports.forEach(report => {
        const reportsContainer = container.querySelector(`.question-reports[data-question-id="${report.question_id}"]`);
        if (reportsContainer) {
            renderReportCard(reportsContainer, report, options);
        }
    });

    // Bind add report buttons
    if (isAdmin && onAddReport) {
        container.querySelectorAll('.btn-add-report').forEach(btn => {
            btn.addEventListener('click', () => {
                onAddReport(parseInt(btn.dataset.questionId));
            });
        });
    }

    // Initialize drag-and-drop reordering for admin
    if (isAdmin) {
        initReportsSortable(container, publicId, adminToken);
    }
}

/**
 * Render a single report card
 */
function renderReportCard(container, report, options) {
    const { isAdmin, publicId, adminToken, configurableTypes, onEditConfig } = options;

    const card = document.createElement('div');
    card.className = 'report-card';
    card.dataset.reportId = report.id;

    const typeName = getReportTypeName(report.report_type);
    const icon = getReportTypeIcon(report.report_type);
    const hasConfig = configurableTypes?.has(report.report_type);

    let headerHtml = `
        <div class="report-header">
            ${isAdmin ? '<span class="report-drag-handle" data-tooltip="Drag to reorder" data-tooltip-pos="left">⠿</span>' : ''}
            <span class="report-name">${escapeHtml(typeName)}</span>
    `;

    if (isAdmin) {
        headerHtml += `
            <div class="report-actions">
                ${hasConfig ? '<button class="btn-icon edit-config" data-tooltip="Settings">⚙️</button>' : ''}
                <button class="btn-icon toggle-public" data-tooltip="${report.is_public ? 'Make private' : 'Make public'}">
                    ${report.is_public ? '👁' : '🔒'}
                </button>
                <button class="btn-icon delete-report" data-tooltip="Delete analysis">🗑</button>
            </div>
        `;
    }

    headerHtml += `</div>`;

    card.innerHTML = headerHtml + '<div class="report-content"></div>';

    // Render the report content
    const contentContainer = card.querySelector('.report-content');
    renderReport(contentContainer, report, { publicId, adminToken });

    // Bind admin actions
    if (isAdmin) {
        const editConfigBtn = card.querySelector('.edit-config');
        const toggleBtn = card.querySelector('.toggle-public');
        const deleteBtn = card.querySelector('.delete-report');

        editConfigBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (onEditConfig) {
                onEditConfig(report);
            }
        });

        toggleBtn?.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                const result = await api.put(
                    `/api/polls/${publicId}/admin/${adminToken}/reports/${report.id}`,
                    { is_public: !report.is_public }
                );
                report.is_public = result.report.is_public;
                toggleBtn.textContent = report.is_public ? '👁' : '🔒';
                toggleBtn.title = report.is_public ? 'Make private' : 'Make public';
            } catch (err) {
                console.error('Failed to toggle visibility:', err);
            }
        });

        deleteBtn?.addEventListener('click', (e) => {
            e.stopPropagation();

            // Hide card immediately
            card.style.display = 'none';

            // Track if delete should proceed
            let shouldDelete = true;

            // Show undo toast
            const undoToast = showUndoToast('Analysis deleted', () => {
                // Undo - show card again
                shouldDelete = false;
                card.style.display = '';
            });

            // Delete from server after undo period
            setTimeout(async () => {
                if (!shouldDelete) return;
                try {
                    await api.delete(`/api/polls/${publicId}/admin/${adminToken}/reports/${report.id}`);
                    card.remove();
                } catch (err) {
                    console.error('Failed to delete report:', err);
                    // Show card again on error
                    card.style.display = '';
                }
            }, 5100); // Slightly after toast dismisses
        });
    }

    container.appendChild(card);
}

/**
 * Fetch available report types for a poll
 */
export async function fetchAvailableTypes(publicId, adminToken) {
    const result = await api.get(`/api/polls/${publicId}/admin/${adminToken}/reports/types`);
    return result;
}

/**
 * Create a new report
 */
export async function createReport(publicId, adminToken, data) {
    const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/reports`, data);
    return result.report;
}

/**
 * Initialize SortableJS for report reordering
 */
function initReportsSortable(container, publicId, adminToken) {
    const reportContainers = container.querySelectorAll('.question-reports');

    reportContainers.forEach(reportsContainer => {
        const questionId = parseInt(reportsContainer.dataset.questionId);

        // Only init if there are reports to sort
        if (reportsContainer.children.length === 0) return;

        new Sortable(reportsContainer, {
            animation: 150,
            handle: '.report-drag-handle',
            ghostClass: 'report-ghost',
            chosenClass: 'report-chosen',
            dragClass: 'report-drag',
            onEnd: async (evt) => {
                if (evt.oldIndex === evt.newIndex) return;

                // Collect new order of report IDs for this question
                const reportCards = reportsContainer.querySelectorAll('.report-card');
                const order = Array.from(reportCards).map(card => parseInt(card.dataset.reportId));

                try {
                    await api.post(`/api/polls/${publicId}/admin/${adminToken}/reports/reorder`, { order });
                } catch (err) {
                    console.error('Failed to reorder reports:', err);
                }
            }
        });
    });
}

export { renderReport, getReportTypeName, getReportTypeIcon };
