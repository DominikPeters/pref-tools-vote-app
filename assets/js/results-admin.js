import { tUi, tUiHtml } from './i18n.js';
/**
 * Results Admin Page JavaScript
 *
 * Admin interface for managing reports/analyses on poll results.
 */

import { api, escapeHtml, showToast, showUndoToast } from './app.js';
import { loadAndRenderResults, fetchAvailableTypes, createReport, renderReport, getIcon } from './results-core.js';

let currentPollData = null;
let availableTypes = null;
let currentReports = [];

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.results-admin-content');
    if (!container) return;

    const publicId = container.dataset.publicId;
    const adminToken = container.dataset.adminToken;

    initResultsAdmin(publicId, adminToken, container);
    initVisibilityControl(publicId, adminToken);
});

/**
 * Initialize the visibility dropdown control
 */
function initVisibilityControl(publicId, adminToken) {
    const visibilitySelect = document.getElementById('resultsVisibility');
    if (!visibilitySelect) return;

    visibilitySelect.addEventListener('change', async () => {
        const newVisibility = visibilitySelect.value;

        try {
            await api.put(`/api/polls/${publicId}/admin/${adminToken}`, {
                visibility: newVisibility,
            });
            showToast(tUi('js_visibility_updated'), 'success');
        } catch (err) {
            console.error('Failed to update visibility:', err);
            showToast(tUi('js_failed_to_update_visibility'), 'error');
        }
    });
}

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
    const results = await loadAndRenderResults(publicId, {
        container: document.getElementById('resultsData'),
        adminToken,
        isAdmin: true,
        configurableTypes,
        onAddReport: (questionId) => showReportDrawer(questionId, publicId, adminToken),
        onEditConfig: (report) => showEditConfigModal(report, publicId, adminToken),
        onReportDeleted: (report) => {
            currentReports = currentReports.filter(r => r.id !== report.id);
            refreshQuestionCardExtras(publicId, adminToken);
        },
    });

    if (results) {
        currentPollData = results.poll;
        currentReports = results.reports;
        addSetupBanner(publicId, container);
        refreshQuestionCardExtras(publicId, adminToken);
    }
}

/**
 * Dismissable banner explaining that standard analyses are set up automatically
 */
function addSetupBanner(publicId, container) {
    if (currentReports.length === 0) return;

    const storageKey = `resultsSetupBannerDismissed:${publicId}`;
    try {
        if (localStorage.getItem(storageKey)) return;
    } catch (e) { /* localStorage unavailable */ }

    const banner = document.createElement('div');
    banner.className = 'results-setup-banner';
    banner.innerHTML = `
        <span>${tUiHtml('js_results_setup_banner')}</span>
        <button class="btn-close" title="${tUiHtml('js_dismiss')}">&times;</button>
    `;
    container.prepend(banner);

    banner.querySelector('.btn-close').addEventListener('click', () => {
        banner.remove();
        try {
            localStorage.setItem(storageKey, '1');
        } catch (e) { /* localStorage unavailable */ }
    });
}

/**
 * Per-question extras: empty-state note and an "Add recommended" one-click
 * button for questions missing their recommended default analyses.
 */
function refreshQuestionCardExtras(publicId, adminToken) {
    (currentPollData?.questions || []).forEach(question => {
        if (question.type === 'section_header') return;

        const questionCard = document.querySelector(`.result-question[data-question-id="${question.id}"]`);
        if (!questionCard) return;

        const activeTypes = currentReports
            .filter(r => r.question_id == question.id)
            .map(r => r.report_type);
        const defaults = availableTypes?.default_reports_by_question?.[question.id] || [];
        const missing = defaults.filter(d => !activeTypes.includes(d.report_type));

        // Empty-state note inside the reports container
        const reportsContainer = questionCard.querySelector('.question-reports');
        let note = reportsContainer.querySelector('.no-reports');
        if (activeTypes.length === 0 && !note) {
            note = document.createElement('p');
            note.className = 'no-reports';
            note.textContent = tUi('js_results_no_analyses');
            reportsContainer.appendChild(note);
        } else if (activeTypes.length > 0 && note) {
            note.remove();
        }

        // One-click button to add the missing recommended analyses
        let btn = questionCard.querySelector('.btn-add-recommended');
        if (missing.length === 0) {
            btn?.remove();
            return;
        }
        if (!btn) {
            btn = document.createElement('button');
            btn.className = 'btn btn-secondary btn-add-recommended';
            const addBtn = questionCard.querySelector('.btn-add-report');
            addBtn.parentNode.insertBefore(btn, addBtn);
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                // Recompute at click time; the set may have changed since render
                const nowActive = currentReports
                    .filter(r => r.question_id == question.id)
                    .map(r => r.report_type);
                const toAdd = (availableTypes?.default_reports_by_question?.[question.id] || [])
                    .filter(d => !nowActive.includes(d.report_type));
                for (const d of toAdd) {
                    await addReport(question.id, d.report_type, d.config, publicId, adminToken);
                }
            });
        }
        btn.textContent = tUi('js_add_recommended_analyses');
    });
}

// Icons for the wizard intents ("What do you want to show?")
const INTENT_ICONS = {
    who_wins: 'trophy',
    how_voted: 'chart-bar',
    multiple_winners: 'users',
    compare_methods: 'scale-balanced',
    export: 'file-export',
    text: 'file-lines',
};

/**
 * Show the drawer for adding a new report.
 * Opens a two-step wizard: intent selection ("What do you want to show?"),
 * then the matching analyses. A "Browse all analyses" link shows the full
 * categorized catalog instead.
 */
function showReportDrawer(questionId, publicId, adminToken) {
    // Get available types for this question
    const types = availableTypes?.types_by_question?.[questionId] || [];

    if (types.length === 0) {
        showToast(tUi('js_results_no_report_types'), 'error');
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
            <button class="btn-drawer-back" title="${tUiHtml('js_back')}" hidden>${getReportIcon('chevron-left')}</button>
            <span class="drawer-title">${tUiHtml('add_analysis')}</span>
            <button class="btn-close" title="${tUiHtml('js_close')}">&times;</button>
        </div>
        <div class="drawer-content"></div>
    `;

    // Insert before the add button
    const addBtn = questionCard.querySelector('.btn-add-report');
    addBtn.parentNode.insertBefore(drawer, addBtn);

    // Bind close button
    drawer.querySelector('.btn-close').addEventListener('click', () => drawer.remove());

    const ctx = { drawer, questionId, publicId, adminToken, types };
    showIntentStep(ctx);
}

/**
 * Set the drawer header title and back-button behavior
 */
function setDrawerHeader(ctx, title, onBack) {
    const backBtn = ctx.drawer.querySelector('.btn-drawer-back');
    ctx.drawer.querySelector('.drawer-title').textContent = title;
    if (onBack) {
        backBtn.hidden = false;
        backBtn.onclick = onBack;
    } else {
        backBtn.hidden = true;
        backBtn.onclick = null;
    }
}

/**
 * Wizard step 1: pick an intent
 */
function showIntentStep(ctx) {
    const intents = availableTypes?.intents || {};

    // Group this question's types by intent
    const typesByIntent = {};
    for (const type of ctx.types) {
        const key = type.intent && intents[type.intent] ? type.intent : 'how_voted';
        if (!typesByIntent[key]) typesByIntent[key] = [];
        typesByIntent[key].push(type);
    }

    let html = `<p class="wizard-question">${tUiHtml('js_what_do_you_want_to_show')}</p>`;
    html += '<div class="report-type-grid">';
    for (const [key, meta] of Object.entries(intents)) {
        if (!typesByIntent[key] || typesByIntent[key].length === 0) continue;
        html += `
            <button class="report-type-card intent-card" data-intent="${key}">
                <span class="type-icon">${getReportIcon(INTENT_ICONS[key] || 'chart-bar')}</span>
                <div class="type-info">
                    <div class="type-name">${escapeHtml(meta.label)}</div>
                    <div class="type-description">${escapeHtml(meta.description)}</div>
                </div>
            </button>
        `;
    }
    html += `</div>`;
    html += `<div class="browse-all-row"><button type="button" class="btn-link browse-all">${tUiHtml('js_browse_all_analyses')}</button></div>`;

    const content = ctx.drawer.querySelector('.drawer-content');
    content.innerHTML = html;
    setDrawerHeader(ctx, tUi('add_analysis'), null);

    content.querySelectorAll('.intent-card').forEach(card => {
        card.addEventListener('click', () => {
            const key = card.dataset.intent;
            const intentTypes = typesByIntent[key] || [];
            if (intentTypes.length === 1) {
                // Only one matching analysis: skip the list step
                selectType(ctx, intentTypes[0], () => showIntentStep(ctx));
            } else {
                showIntentTypesStep(ctx, key, intentTypes);
            }
        });
    });
    content.querySelector('.browse-all').addEventListener('click', () => showAllTypesStep(ctx));
}

/**
 * Wizard step 2: pick an analysis within the chosen intent
 */
function showIntentTypesStep(ctx, intentKey, intentTypes) {
    const meta = availableTypes?.intents?.[intentKey];
    const content = ctx.drawer.querySelector('.drawer-content');
    content.innerHTML = `<div class="report-type-grid">${renderTypeCards(ctx, intentTypes)}</div>`;
    setDrawerHeader(ctx, meta?.label || tUi('add_analysis'), () => showIntentStep(ctx));
    bindTypeCards(ctx, content, () => showIntentTypesStep(ctx, intentKey, intentTypes));
}

/**
 * Expert view: the full catalog grouped by category
 */
function showAllTypesStep(ctx) {
    const categories = availableTypes?.categories || {};

    // Group types by category
    const typesByCategory = {};
    for (const type of ctx.types) {
        const cat = type.category || 'data_export';
        if (!typesByCategory[cat]) typesByCategory[cat] = [];
        typesByCategory[cat].push(type);
    }

    let html = '';
    for (const catKey of Object.keys(categories)) {
        const catTypes = typesByCategory[catKey];
        if (!catTypes || catTypes.length === 0) continue;
        html += `
            <div class="report-category">
                <div class="report-category-header">${escapeHtml(categories[catKey] || catKey)}</div>
                <div class="report-type-grid">${renderTypeCards(ctx, catTypes)}</div>
            </div>
        `;
    }

    const content = ctx.drawer.querySelector('.drawer-content');
    content.innerHTML = html;
    setDrawerHeader(ctx, tUi('js_all_analyses'), () => showIntentStep(ctx));
    bindTypeCards(ctx, content, () => showAllTypesStep(ctx));
}

/**
 * Render report type cards (with default badges and duplicate-disabling)
 */
function renderTypeCards(ctx, typesArr) {
    const activeReportTypes = currentReports
        .filter(r => r.question_id == ctx.questionId)
        .map(r => r.report_type);
    const defaultTypes = (availableTypes?.default_reports_by_question?.[ctx.questionId] || [])
        .map(d => d.report_type);

    return typesArr.map(type => {
        const isAlreadySelected = activeReportTypes.includes(type.type);
        const isDisabled = !type.has_config && isAlreadySelected;
        const isDefault = defaultTypes.includes(type.type);
        const disabledAttr = isDisabled ? 'disabled' : '';
        const disabledClass = isDisabled ? 'disabled' : '';
        const tooltip = isDisabled ? tUi('js_this_analysis_is_already_added') : '';

        return `
            <button class="report-type-card ${disabledClass}"
                    data-type="${type.type}"
                    ${disabledAttr}
                    ${tooltip ? `title="${escapeHtml(tooltip)}"` : ''}>
                <span class="type-icon">${getReportIcon(type.icon)}</span>
                <div class="type-info">
                    <div class="type-name">${escapeHtml(type.name)}${isDefault ? ` <span class="badge-default">${tUiHtml('js_default')}</span>` : ''}</div>
                    <div class="type-description">${escapeHtml(type.description)}</div>
                </div>
            </button>
        `;
    }).join('');
}

/**
 * Bind click handlers to type cards; backFn returns to the current view
 */
function bindTypeCards(ctx, container, backFn) {
    container.querySelectorAll('.report-type-card').forEach(card => {
        card.addEventListener('click', () => {
            const type = ctx.types.find(t => t.type === card.dataset.type);
            if (type) selectType(ctx, type, backFn);
        });
    });
}

/**
 * A type was chosen: configure it if needed, otherwise add it right away
 */
async function selectType(ctx, type, backFn) {
    if (type.requires_config) {
        showConfigStep(ctx, type, backFn);
    } else {
        await addReport(ctx.questionId, type.type, null, ctx.publicId, ctx.adminToken);
        ctx.drawer.remove();
    }
}

/**
 * Get SVG icon for a report type
 */
function getReportIcon(iconName) {
    const icons = {
        'chart-bar': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="12" width="4" height="9"/><rect x="10" y="6" width="4" height="15"/><rect x="17" y="3" width="4" height="18"/></svg>',
        'trophy': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
        'table': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/></svg>',
        'diagram-project': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M12 8v4"/><path d="M8 14l4-2 4 2"/></svg>',
        'crown': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8l4 12h12l4-12-5 4-5-6-5 6-5-4z"/><path d="M4 20h16"/></svg>',
        'list-ol': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6h11"/><path d="M10 12h11"/><path d="M10 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>',
        'columns': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/></svg>',
        'calculator': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>',
        'scale-balanced': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M3 6l3 9h6l3-9"/><path d="M15 6l3 9h-6"/><circle cx="12" cy="3" r="1"/></svg>',
        'check-circle': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>',
        'table-cells': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>',
        'file-export': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15l3 3 3-3"/></svg>',
        'file-lines': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
        'users': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'chevron-left': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',
    };
    return icons[iconName] || icons['chart-bar'];
}

/**
 * Collect config values from a form, handling checkboxes arrays
 */
function collectFormConfig(form, fields) {
    const config = {};
    const formData = new FormData(form);

    fields.forEach(field => {
        if (field.type === 'checkboxes') {
            // Collect all checked values as array
            config[field.name] = formData.getAll(`${field.name}[]`);
        } else if (field.type === 'checkbox') {
            config[field.name] = formData.has(field.name);
        } else {
            const value = formData.get(field.name);
            if (value !== null) {
                config[field.name] = value;
            }
        }
    });

    return config;
}

/**
 * Show configuration step for reports that need it (creation mode)
 * Only shows required fields during creation
 */
function showConfigStep(ctx, typeData, backFn) {
    if (!typeData?.config_schema) return;

    const drawerContent = ctx.drawer.querySelector('.drawer-content');

    // Filter to only required fields for creation
    const requiredFields = typeData.config_schema.fields.filter(f => f.required);

    // Build config form
    let formHtml = `<div class="config-step">`;
    formHtml += '<form class="config-form">';

    requiredFields.forEach(field => {
        formHtml += renderConfigField(field, field.default, ctx.questionId);
    });

    formHtml += `
        <div class="form-actions">
            <button type="button" class="btn btn-secondary btn-back">${tUiHtml('js_back')}</button>
            <button type="submit" class="btn btn-primary">${tUiHtml('js_add')}</button>
        </div>
    `;
    formHtml += '</form>';
    formHtml += '</div>';

    // Replace drawer content with config form
    drawerContent.innerHTML = formHtml;
    setDrawerHeader(ctx, typeData.name, backFn);
    bindConfigDependencies(drawerContent);

    // Bind back button
    drawerContent.querySelector('.btn-back').addEventListener('click', backFn);

    // Bind form submit
    drawerContent.querySelector('.config-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const config = collectFormConfig(e.target, typeData.config_schema.fields);
        await addReport(ctx.questionId, typeData.type, config, ctx.publicId, ctx.adminToken);
        ctx.drawer.remove();
    });
}

/**
 * Resolve a field's options, using dynamic per-question options if specified
 */
function getFieldOptions(field, questionId) {
    const sources = {
        votingRules: 'voting_rules_by_question',
        multiwinnerRules: 'multiwinner_rules_by_question',
        apportionmentRules: 'apportionment_rules_by_question',
        distributionRules: 'distribution_rules_by_question',
        socialWelfareFunctions: 'social_welfare_functions_by_question',
    };
    const source = sources[field.dynamicOptions];
    if (source && questionId && availableTypes?.[source]) {
        return availableTypes[source][questionId] || [];
    }
    return field.options || [];
}

/**
 * Label markup for a rule option: name, "default" badge, one-line description
 */
function renderOptionInfo(opt) {
    return `
        <span class="rule-option-info">
            <span class="rule-option-name">${escapeHtml(opt.label)}${opt.default ? ` <span class="badge-default">${tUiHtml('js_default')}</span>` : ''}</span>
            ${opt.description ? `<span class="rule-option-desc">${escapeHtml(opt.description)}</span>` : ''}
        </span>
    `;
}

/**
 * Render a single config field
 */
function renderConfigField(field, currentValue, questionId) {
    let dependsOnAttrs = '';
    if (field.dependsOn) {
        dependsOnAttrs = `data-depends-on-field="${field.dependsOn.field}" data-depends-on-value="${field.dependsOn.value}"`;
        // If depends on question settings, add question ID for lookup
        if (field.dependsOn.field.startsWith('question.') && questionId) {
            dependsOnAttrs += ` data-depends-on-question-id="${questionId}"`;
        }
    }
    let html = `<div class="form-group" ${dependsOnAttrs}>`;
    
    // For single checkboxes, we don't want the double label
    if (field.type !== 'checkbox') {
        html += `<label>${escapeHtml(field.label)}</label>`;
    }

    if (field.type === 'select') {
        const options = getFieldOptions(field, questionId);

        if (field.dynamicOptions) {
            // Rule pickers: radio cards with descriptions and a "default" badge
            const selectedValue = options.some(o => o.value === currentValue)
                ? currentValue
                : (options.find(o => o.default)?.value ?? options[0]?.value);
            html += `<div class="rule-radio-list">`;
            options.forEach(opt => {
                const checked = opt.value === selectedValue ? 'checked' : '';
                html += `
                    <label class="rule-radio-item">
                        <input type="radio" name="${field.name}" value="${opt.value}" ${checked}>
                        ${renderOptionInfo(opt)}
                    </label>
                `;
            });
            html += `</div>`;
        } else {
            html += `<select id="config-${field.name}" name="${field.name}" class="form-control">`;
            options.forEach(opt => {
                const selected = opt.value === currentValue ? 'selected' : '';
                html += `<option value="${opt.value}" ${selected}>${escapeHtml(opt.label)}</option>`;
            });
            html += `</select>`;
        }
    } else if (field.type === 'checkbox') {
        const checked = currentValue ? 'checked' : '';
        html += `
            <label class="checkbox-item">
                <input type="checkbox" id="config-${field.name}" name="${field.name}" ${checked}>
                <span class="checkbox-label">${escapeHtml(field.label)}</span>
            </label>
        `;
    } else if (field.type === 'checkboxes') {
        const options = getFieldOptions(field, questionId);

        // currentValue is an array of selected values
        const selectedValues = Array.isArray(currentValue) ? currentValue : [];

        html += `<div class="checkboxes-list" data-field-name="${field.name}">`;
        options.forEach(opt => {
            // Default rules are pre-checked if no current selection
            const isChecked = selectedValues.length > 0
                ? selectedValues.includes(opt.value)
                : opt.default;
            const checked = isChecked ? 'checked' : '';
            html += `
                <label class="checkbox-item">
                    <input type="checkbox" name="${field.name}[]" value="${opt.value}" ${checked}>
                    ${renderOptionInfo(opt)}
                </label>
            `;
        });
        html += `</div>`;
    } else if (field.type === 'textarea') {
        const placeholder = field.placeholder ? `placeholder="${escapeHtml(field.placeholder)}"` : '';
        html += `<textarea id="config-${field.name}" name="${field.name}"
                          class="form-control" rows="6" ${placeholder}>${escapeHtml(currentValue || '')}</textarea>`;
    } else if (field.type === 'number') {
        let max = field.max;
        if (field.dynamicMax === 'numOptions' && questionId && currentPollData?.questions) {
            const question = currentPollData.questions.find(q => q.id == questionId);
            if (question && question.options) {
                max = question.options.length;
            }
        }

        let value = currentValue || '';
        if (max !== undefined && value !== '' && value > max) {
            value = max;
        }

        html += `<input type="number" id="config-${field.name}" name="${field.name}"
                        value="${value}" class="form-control"
                        ${field.min !== undefined ? `min="${field.min}"` : ''}
                        ${max !== undefined ? `max="${max}"` : ''}>`;
    } else {
        html += `<input type="text" id="config-${field.name}" name="${field.name}"
                        value="${escapeHtml(currentValue || '')}" class="form-control">`;
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
        showToast(tUi('js_results_no_configuration'), 'error');
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
        fieldsHtml += renderConfigField(field, currentValue, report.question_id);
    });

    overlay.innerHTML = `
        <div class="config-modal">
            <div class="modal-header">
                <h3>${tUiHtml('js_edit_name_settings', { name: typeData.name })}</h3>
                <button class="btn-close" title="${tUiHtml('js_close')}">&times;</button>
            </div>
            <form class="config-form">
                ${fieldsHtml}
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary btn-cancel">${tUiHtml('cancel')}</button>
                    <button type="submit" class="btn btn-primary">${tUiHtml('save')}</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(overlay);
    bindConfigDependencies(overlay);

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
        const config = collectFormConfig(e.target, typeData.config_schema.fields);

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
                renderReport(contentContainer, report, { publicId, adminToken });
            }

            showToast(tUi('js_settings_saved'), 'success');
            closeModal();
        } catch (err) {
            console.error('Failed to update config:', err);
            showToast(tUi('js_failed_to_save_settings'), 'error');
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
            is_public: true,
        });

        currentReports.push(report);

        // Add the report card to the UI
        const reportsContainer = document.querySelector(`.question-reports[data-question-id="${questionId}"]`);
        if (reportsContainer) {
            // Remove "no reports" message if present
            const noReports = reportsContainer.querySelector('.no-reports');
            if (noReports) noReports.remove();

            // Create and add the new report card
            addReportCardToContainer(reportsContainer, report, publicId, adminToken);
        }

        refreshQuestionCardExtras(publicId, adminToken);
        showToast(tUi('js_analysis_added'), 'success');
    } catch (err) {
        console.error('Failed to add report:', err);
        showToast(tUi('js_failed_to_add_analysis'), 'error');
    }
}

/**
 * Add a report card to a container
 */
function addReportCardToContainer(container, report, publicId, adminToken) {
    const card = document.createElement('div');
    card.className = 'report-card';
    card.dataset.reportId = report.id;
    card.dataset.type = report.report_type;

    const typeData = availableTypes?.all_types?.find(t => t.type === report.report_type);
    const typeName = typeData?.name || report.report_type;
    const hasConfig = typeData?.has_config;

    card.innerHTML = `
        <div class="report-header">
            <span class="report-drag-handle" data-tooltip="${tUiHtml('drag_to_reorder')}" data-tooltip-pos="left">${getIcon('menu')}</span>
            <span class="report-name">${escapeHtml(typeName)}</span>
            <div class="report-actions">
                ${hasConfig ? `<button class="btn-icon edit-config" data-tooltip="${tUiHtml('settings')}">${getIcon('settings')}</button>` : ''}
                <button class="btn-icon toggle-public" data-tooltip="${report.is_public ? tUiHtml('make_private') : tUiHtml('make_public')}">
                    ${getIcon(report.is_public ? 'eye' : 'lock')}
                </button>
                <button class="btn-icon delete-report" data-tooltip="${tUiHtml('delete_analysis')}">${getIcon('trash')}</button>
            </div>
        </div>
        <div class="report-content"></div>
    `;

    // Render the report content
    const contentContainer = card.querySelector('.report-content');
    renderReport(contentContainer, report, { publicId, adminToken });

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
            toggleBtn.innerHTML = getIcon(report.is_public ? 'eye' : 'lock');
            toggleBtn.setAttribute('data-tooltip', report.is_public ? tUi('make_private') : tUi('make_public'));
        } catch (err) {
            console.error('Failed to toggle visibility:', err);
        }
    });

    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();

        // Hide card immediately
        card.style.display = 'none';

        // Track if delete should proceed
        let shouldDelete = true;

        // Show undo toast
        showUndoToast(tUi('analysis_deleted'), () => {
            // Undo - show card again
            shouldDelete = false;
            card.style.display = '';
        });

        // Delete from server after undo period
        setTimeout(async () => {
            if (!shouldDelete) return;
            try {
                await api.delete(`/api/polls/${publicId}/admin/${adminToken}/reports/${report.id}`);
                currentReports = currentReports.filter(r => r.id !== report.id);
                card.remove();
                refreshQuestionCardExtras(publicId, adminToken);
            } catch (err) {
                console.error('Failed to delete report:', err);
                // Show card again on error
                card.style.display = '';
            }
        }, 5100); // Slightly after toast dismisses
    });

    container.appendChild(card);
}

/**
 * Handle conditional field visibility based on data-depends-on attributes
 *
 * Supports two types of dependencies:
 * 1. Form field: dependsOn.field = "fieldName" - checks another form field's value
 * 2. Question setting: dependsOn.field = "question.settings.allowOther" - checks question's settings
 */
function bindConfigDependencies(container) {
    const dependentGroups = container.querySelectorAll('.form-group[data-depends-on-field]');
    if (dependentGroups.length === 0) return;

    const updateVisibility = () => {
        dependentGroups.forEach(group => {
            const fieldName = group.getAttribute('data-depends-on-field');
            const requiredValue = group.getAttribute('data-depends-on-value');

            let currentValue;

            // Check if this is a question setting dependency
            if (fieldName.startsWith('question.')) {
                const questionId = group.getAttribute('data-depends-on-question-id');
                if (questionId && currentPollData?.questions) {
                    const question = currentPollData.questions.find(q => q.id == questionId);
                    if (question) {
                        // Parse path like "question.settings.allowOther"
                        const path = fieldName.split('.').slice(1); // Remove "question." prefix
                        currentValue = path.reduce((obj, key) => obj?.[key], question);
                    }
                }
            } else {
                // Regular form field dependency
                const field = container.querySelector(`[name="${fieldName}"]`);
                if (!field) return;
                if (field.type === 'checkbox') {
                    currentValue = field.checked;
                } else if (field.type === 'radio') {
                    currentValue = container.querySelector(`[name="${fieldName}"]:checked`)?.value;
                } else {
                    currentValue = field.value;
                }
            }

            const isVisible = String(currentValue) === String(requiredValue);
            group.style.display = isVisible ? '' : 'none';
        });
    };

    // Initial check
    updateVisibility();

    // Bind change events to all potential source fields (only for form field dependencies)
    const sourceFields = new Set();
    dependentGroups.forEach(group => {
        const fieldName = group.getAttribute('data-depends-on-field');
        // Only bind events for form fields, not question settings (those are static)
        if (!fieldName.startsWith('question.')) {
            sourceFields.add(fieldName);
        }
    });

    sourceFields.forEach(fieldName => {
        container.querySelectorAll(`[name="${fieldName}"]`).forEach(field => {
            field.addEventListener('change', updateVisibility);
            field.addEventListener('input', updateVisibility);
        });
    });
}
