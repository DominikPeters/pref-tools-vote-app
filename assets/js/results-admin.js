/**
 * Results Admin Page JavaScript
 *
 * Admin interface for managing reports/analyses on poll results.
 */

import { api, escapeHtml, showToast } from './app.js';
import { loadAndRenderResults, fetchAvailableTypes, createReport, renderReport } from './results-core.js';

let currentPollData = null;
let availableTypes = null;

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.results-admin-content');
    if (!container) return;

    const publicId = container.dataset.publicId;
    const adminToken = container.dataset.adminToken;

    initResultsAdmin(publicId, adminToken, container);
});

async function initResultsAdmin(publicId, adminToken, container) {
    // Fetch available types first
    try {
        availableTypes = await fetchAvailableTypes(publicId, adminToken);
    } catch (err) {
        console.error('Failed to fetch available types:', err);
    }

    // Build set of configurable type names
    const configurableTypes = new Set(
        (availableTypes?.all_types || [])
            .filter(t => t.has_config)
            .map(t => t.type)
    );

    // Load and render results
    await loadAndRenderResults(publicId, {
        container: document.getElementById('resultsData'),
        adminToken,
        isAdmin: true,
        configurableTypes,
        onAddReport: (questionId) => showReportDrawer(questionId, publicId, adminToken),
        onEditConfig: (report) => showEditConfigModal(report, publicId, adminToken),
    });

    // Also load poll data for reference
    try {
        const result = await api.get(`/api/polls/${publicId}`);
        currentPollData = result.poll;
    } catch (err) {
        console.error('Failed to load poll data:', err);
    }
}

/**
 * Show the drawer for adding a new report
 */
function showReportDrawer(questionId, publicId, adminToken) {
    // Get available types for this question
    const types = availableTypes?.types_by_question?.[questionId] || [];

    if (types.length === 0) {
        showToast('No report types available for this question type', 'error');
        return;
    }

    // Find the question container
    const questionCard = document.querySelector(`.result-question[data-question-id="${questionId}"]`);
    if (!questionCard) return;

    // Check if drawer already exists
    let drawer = questionCard.querySelector('.report-drawer');
    if (drawer) {
        drawer.remove();
        return; // Toggle off
    }

    // Create drawer
    drawer = document.createElement('div');
    drawer.className = 'report-drawer';

    drawer.innerHTML = `
        <div class="drawer-header">
            <span>Add Analysis</span>
            <button class="btn-close" title="Close">&times;</button>
        </div>
        <div class="drawer-content">
            <div class="report-type-grid">
                ${types.map(type => `
                    <button class="report-type-card" data-type="${type.type}" data-requires-config="${type.requires_config}">
                        <div class="type-name">${escapeHtml(type.name)}</div>
                        <div class="type-description">${escapeHtml(type.description)}</div>
                    </button>
                `).join('')}
            </div>
            <div class="config-step" style="display: none;"></div>
        </div>
    `;

    // Insert before the add button
    const addBtn = questionCard.querySelector('.btn-add-report');
    addBtn.parentNode.insertBefore(drawer, addBtn);

    // Bind close button
    drawer.querySelector('.btn-close').addEventListener('click', () => drawer.remove());

    // Bind type cards
    drawer.querySelectorAll('.report-type-card').forEach(card => {
        card.addEventListener('click', async () => {
            const reportType = card.dataset.type;
            const requiresConfig = card.dataset.requiresConfig === 'true';

            if (requiresConfig) {
                showConfigStep(drawer, questionId, reportType, publicId, adminToken);
            } else {
                await addReport(questionId, reportType, null, publicId, adminToken);
                drawer.remove();
            }
        });
    });
}

/**
 * Show configuration step for reports that need it (creation mode)
 * Only shows required fields during creation
 */
function showConfigStep(drawer, questionId, reportType, publicId, adminToken) {
    const typeData = availableTypes?.all_types?.find(t => t.type === reportType);
    if (!typeData?.config_schema) return;

    const configStep = drawer.querySelector('.config-step');
    const typeGrid = drawer.querySelector('.report-type-grid');

    // Filter to only required fields for creation
    const requiredFields = typeData.config_schema.fields.filter(f => f.required);

    typeGrid.style.display = 'none';
    configStep.style.display = 'block';

    // Build config form
    let formHtml = `<h4>${escapeHtml(typeData.name)} Configuration</h4>`;
    formHtml += '<form class="config-form">';

    requiredFields.forEach(field => {
        formHtml += renderConfigField(field, field.default);
    });

    formHtml += `
        <div class="form-actions">
            <button type="button" class="btn btn-secondary btn-back">Back</button>
            <button type="submit" class="btn btn-primary">Add</button>
        </div>
    `;
    formHtml += '</form>';

    configStep.innerHTML = formHtml;

    // Bind back button
    configStep.querySelector('.btn-back').addEventListener('click', () => {
        configStep.style.display = 'none';
        typeGrid.style.display = '';
    });

    // Bind form submit
    configStep.querySelector('.config-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const config = {};
        for (const [key, value] of formData.entries()) {
            config[key] = value;
        }

        await addReport(questionId, reportType, config, publicId, adminToken);
        drawer.remove();
    });
}

/**
 * Render a single config field
 */
function renderConfigField(field, currentValue) {
    let html = `<div class="form-group">`;
    html += `<label for="config-${field.name}">${escapeHtml(field.label)}</label>`;

    if (field.type === 'select' && field.options) {
        html += `<select id="config-${field.name}" name="${field.name}" class="form-control">`;
        field.options.forEach(opt => {
            const selected = opt.value === currentValue ? 'selected' : '';
            html += `<option value="${opt.value}" ${selected}>${escapeHtml(opt.label)}</option>`;
        });
        html += `</select>`;
    } else if (field.type === 'checkbox') {
        const checked = currentValue ? 'checked' : '';
        html += `<input type="checkbox" id="config-${field.name}" name="${field.name}" ${checked} class="form-checkbox">`;
    } else {
        html += `<input type="text" id="config-${field.name}" name="${field.name}"
                        value="${currentValue || ''}" class="form-control">`;
    }

    html += `</div>`;
    return html;
}

/**
 * Show edit config modal for an existing report
 */
function showEditConfigModal(report, publicId, adminToken) {
    const typeData = availableTypes?.all_types?.find(t => t.type === report.report_type);
    if (!typeData?.config_schema) {
        showToast('No configuration options for this report type', 'error');
        return;
    }

    // Remove any existing modal
    const existingModal = document.querySelector('.config-modal-overlay');
    if (existingModal) existingModal.remove();

    // Create modal
    const overlay = document.createElement('div');
    overlay.className = 'config-modal-overlay';

    let fieldsHtml = '';
    typeData.config_schema.fields.forEach(field => {
        const currentValue = report.config?.[field.name] ?? field.default;
        fieldsHtml += renderConfigField(field, currentValue);
    });

    overlay.innerHTML = `
        <div class="config-modal">
            <div class="modal-header">
                <h3>Edit ${escapeHtml(typeData.name)} Settings</h3>
                <button class="btn-close" title="Close">&times;</button>
            </div>
            <form class="config-form">
                ${fieldsHtml}
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary btn-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(overlay);

    // Bind close/cancel
    const closeModal = () => overlay.remove();
    overlay.querySelector('.btn-close').addEventListener('click', closeModal);
    overlay.querySelector('.btn-cancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });

    // Bind form submit
    overlay.querySelector('.config-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const config = { ...report.config }; // Preserve existing config
        for (const [key, value] of formData.entries()) {
            config[key] = value;
        }
        // Handle unchecked checkboxes
        typeData.config_schema.fields.forEach(field => {
            if (field.type === 'checkbox' && !formData.has(field.name)) {
                config[field.name] = false;
            }
        });

        try {
            const result = await api.put(
                `/api/polls/${publicId}/admin/${adminToken}/reports/${report.id}`,
                { config }
            );

            // Update the report card with new data
            const card = document.querySelector(`.report-card[data-report-id="${report.id}"]`);
            if (card) {
                const contentContainer = card.querySelector('.report-content');
                report.config = result.report.config;
                report.cached_result = result.report.cached_result;
                renderReport(contentContainer, report);
            }

            showToast('Settings saved', 'success');
            closeModal();
        } catch (err) {
            console.error('Failed to update config:', err);
            showToast('Failed to save settings', 'error');
        }
    });
}

/**
 * Add a new report
 */
async function addReport(questionId, reportType, config, publicId, adminToken) {
    try {
        const report = await createReport(publicId, adminToken, {
            question_id: questionId,
            report_type: reportType,
            config: config,
            is_public: false,
        });

        // Add the report card to the UI
        const reportsContainer = document.querySelector(`.question-reports[data-question-id="${questionId}"]`);
        if (reportsContainer) {
            // Remove "no reports" message if present
            const noReports = reportsContainer.querySelector('.no-reports');
            if (noReports) noReports.remove();

            // Create and add the new report card
            addReportCardToContainer(reportsContainer, report, publicId, adminToken);
        }

        showToast('Analysis added', 'success');
    } catch (err) {
        console.error('Failed to add report:', err);
        showToast('Failed to add analysis', 'error');
    }
}

/**
 * Add a report card to a container
 */
function addReportCardToContainer(container, report, publicId, adminToken) {
    const card = document.createElement('div');
    card.className = 'report-card';
    card.dataset.reportId = report.id;

    const typeNames = {
        'choice_counts': 'Vote Counts',
        'approval_winner': 'Approval Winner',
        'borda_scores': 'Borda Scores',
        'pairwise_margins': 'Pairwise Margins',
        'voting_rule_winner': 'Voting Rule Winner',
    };

    const typeName = typeNames[report.report_type] || report.report_type;
    const typeData = availableTypes?.all_types?.find(t => t.type === report.report_type);
    const hasConfig = typeData?.has_config;

    card.innerHTML = `
        <div class="report-header">
            <span class="report-drag-handle" title="Drag to reorder">⠿</span>
            <span class="report-name">${escapeHtml(typeName)}</span>
            <div class="report-actions">
                ${hasConfig ? '<button class="btn-icon edit-config" title="Settings">⚙️</button>' : ''}
                <button class="btn-icon toggle-public" title="${report.is_public ? 'Make private' : 'Make public'}">
                    ${report.is_public ? '👁' : '🔒'}
                </button>
                <button class="btn-icon delete-report" title="Delete">🗑</button>
            </div>
        </div>
        <div class="report-content"></div>
    `;

    // Render the report content
    const contentContainer = card.querySelector('.report-content');
    renderReport(contentContainer, report);

    // Bind actions
    const editConfigBtn = card.querySelector('.edit-config');
    const toggleBtn = card.querySelector('.toggle-public');
    const deleteBtn = card.querySelector('.delete-report');

    editConfigBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        showEditConfigModal(report, publicId, adminToken);
    });

    toggleBtn.addEventListener('click', async (e) => {
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

    deleteBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        if (!confirm('Delete this analysis?')) return;

        try {
            await api.delete(`/api/polls/${publicId}/admin/${adminToken}/reports/${report.id}`);
            card.remove();
        } catch (err) {
            console.error('Failed to delete report:', err);
        }
    });

    container.appendChild(card);
}
