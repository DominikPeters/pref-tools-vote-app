/**
 * Results Core Module
 *
 * Shared logic for rendering results on both admin and public views.
 */

import { api, escapeHtml, showUndoToast } from './app.js';
import { renderReport, getReportTypeName } from './report-types/index.js';
import { t, formatDate as i18nFormatDate } from './i18n.js';

// SVG Icons (Feather Icons style)
const icons = {
    menu: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',

    settings: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',

    eye: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',

    lock: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',

    trash: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
};

function getIcon(name, size = 16) {
    const icon = icons[name];
    if (!icon) return '';
    if (size !== 16) {
        return icon.replace(/width="16" height="16"/, `width="${size}" height="${size}"`);
    }
    return icon;
}

// Export for use in results-admin.js
export { getIcon };

/**
 * Format an ISO date string for display
 */
function formatDate(isoString) {
    if (!isoString) return '';
    return i18nFormatDate(new Date(isoString));
}

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

    container.innerHTML = `<p class="loading">${t('loading_results')}</p>`;

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

        return { poll, reports };

    } catch (err) {
        console.error('Failed to load results:', err);
        container.innerHTML = `<p class="error-message">${t('error_loading')}</p>`;
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

    // Poll summary card
    const questionCount = poll.questions.filter(q => q.type !== 'section_header').length;
    const responseCount = poll.response_count ?? 0;

    let dateInfo = '';
    if (poll.closed_at) {
        dateInfo = t('closed_on', { date: formatDate(poll.closed_at) });
    } else if (poll.created_at) {
        dateInfo = t('created_on', { date: formatDate(poll.created_at) });
    }

    // Wrap the count in strong tag by splitting the translated string
    const formatStat = (key, count) => {
        const translated = t(key, { count });
        return translated.replace(count.toString(), `<strong>${count}</strong>`);
    };

    html += `<div class="results-summary card">
        <div class="summary-stats">
            <span class="stat">${formatStat('response_count', responseCount)}</span>
            <span class="stat">${formatStat('question_count', questionCount)}</span>
            <span class="status-badge status-${poll.status}">${t('status_' + poll.status)}</span>
            ${dateInfo ? `<span class="stat date-info">${dateInfo}</span>` : ''}
        </div>
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
                        ? `<p class="no-reports">${t('no_results_available')}</p>`
                        : ''}
                </div>

                ${isAdmin ? `
                    <button class="btn btn-secondary btn-add-report" data-question-id="${question.id}">
                        + ${t('add_analysis')}
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

    // Text blocks in public view: no card styling, no header
    const isPublicTextBlock = report.report_type === 'text_block' && !isAdmin;

    const card = document.createElement('div');
    card.className = isPublicTextBlock ? 'report-card report-card-plain' : 'report-card';
    card.dataset.reportId = report.id;
    card.dataset.type = report.report_type;

    const typeName = getReportTypeName(report.report_type);
    const hasConfig = configurableTypes?.has(report.report_type);

    let headerHtml = '';

    // Skip header for public text blocks
    if (!isPublicTextBlock) {
        headerHtml = `
            <div class="report-header">
                ${isAdmin ? `<span class="report-drag-handle" data-tooltip="${t('drag_to_reorder')}" data-tooltip-pos="left">${getIcon('menu')}</span>` : ''}
                <span class="report-name">${escapeHtml(typeName)}</span>
        `;

        if (isAdmin) {
            headerHtml += `
                <div class="report-actions">
                    ${hasConfig ? `<button class="btn-icon edit-config" data-tooltip="${t('settings')}">${getIcon('settings')}</button>` : ''}
                    <button class="btn-icon toggle-public" data-tooltip="${report.is_public ? t('make_private') : t('make_public')}">
                        ${getIcon(report.is_public ? 'eye' : 'lock')}
                    </button>
                    <button class="btn-icon delete-report" data-tooltip="${t('delete_analysis')}">${getIcon('trash')}</button>
                </div>
            `;
        }

        headerHtml += `</div>`;
    }

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
                toggleBtn.innerHTML = getIcon(report.is_public ? 'eye' : 'lock');
                toggleBtn.setAttribute('data-tooltip', report.is_public ? t('make_private') : t('make_public'));
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
            const undoToast = showUndoToast(t('analysis_deleted'), () => {
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

export { renderReport, getReportTypeName };
