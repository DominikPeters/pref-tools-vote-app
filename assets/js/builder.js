/**
 * WYSIWYG Form Builder
 *
 * Implements a Google/Microsoft Forms-like builder with three question states:
 * 1. Display (unfocused): Shows question as it will appear to voters
 * 2. Hover: Adds gray background and drag handle
 * 3. Editing: Full editing UI with inputs and settings
 *
 * Click on a question to edit, click outside to collapse back to display.
 */

import { api, generateTempId, showToast, showUndoToast, showConfirmModal, setButtonLoading, clearButtonLoading, basePath, escapeHtml } from './app.js';
import { renderQuestion, OPTION_TYPES, QUESTION_TYPES } from './question-renderer.js';
import { marked } from './marked.esm.js';

// Configure marked to match Parsedown behavior
marked.setOptions({
    breaks: true,  // Match Parsedown's setBreaksEnabled(true)
    gfm: true,     // GitHub Flavored Markdown
});

// ==========================================================================
// State Management
// ==========================================================================

const defaultState = {
    title: 'Untitled Poll',
    description: '',
    votingMode: 'open',
    randomizeOptions: false,
    thankYouMessage: '',
    questions: [],
    publicId: null,
    adminToken: null,
    isDirty: false,
    modeLocked: false, // True if voting mode is locked (responses exist)
};

const state = { ...defaultState, questions: [] };

let isEditMode = false;  // True if editing an existing poll (vs creating new)
let activeQuestionId = null;  // Currently editing question ID
let questionsSortable = null;  // SortableJS instance for questions

// Turnstile integration for anonymous users
let turnstileWidgetId = null;
let turnstileToken = null;

// ==========================================================================
// Initialization
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    if (window.POLL_DATA) {
        isEditMode = true;
        loadFromServer(window.POLL_DATA, window.ADMIN_TOKEN);
        clearLocalStorage();
    } else {
        const loadedFromStorage = loadFromLocalStorage();
        if (loadedFromStorage) {
            const clearBtn = document.getElementById('clearBtn');
            if (clearBtn) clearBtn.style.display = '';
        }
    }

    initElements();
    render();
    setupAutoSave();
    setupClickHandling();
});

function initElements() {
    // Title
    document.getElementById('pollTitle').addEventListener('input', (e) => {
        state.title = e.target.value || 'Untitled Poll';
        markDirty();
    });

    // Poll description with markdown preview
    const pollDescTextarea = document.getElementById('pollDescription');
    const pollDescPreview = document.getElementById('pollDescriptionPreview');
    const addPollDescBtn = document.getElementById('addPollDescriptionBtn');

    pollDescTextarea.addEventListener('input', (e) => {
        state.description = e.target.value;
        markDirty();
    });

    pollDescTextarea.addEventListener('blur', () => {
        if (state.description.trim()) {
            // Show preview
            pollDescPreview.innerHTML = marked.parse(state.description);
            pollDescPreview.style.display = '';
            pollDescTextarea.style.display = 'none';
            addPollDescBtn.style.display = 'none';
        } else {
            // No description - show button
            state.description = '';
            pollDescPreview.style.display = 'none';
            pollDescTextarea.style.display = 'none';
            addPollDescBtn.style.display = '';
        }
    });

    pollDescPreview.addEventListener('click', () => {
        // Switch to edit mode
        pollDescPreview.style.display = 'none';
        pollDescTextarea.style.display = '';
        pollDescTextarea.focus();
    });

    addPollDescBtn.addEventListener('click', () => {
        // Switch to edit mode
        addPollDescBtn.style.display = 'none';
        pollDescTextarea.style.display = '';
        pollDescTextarea.focus();
    });

    // Voting mode
    document.querySelectorAll('input[name="votingMode"]').forEach(input => {
        input.addEventListener('change', (e) => {
            state.votingMode = e.target.value;
            markDirty();
        });
    });

    // Display options
    document.getElementById('randomizeOptions').addEventListener('change', (e) => {
        state.randomizeOptions = e.target.checked;
        markDirty();
    });

    // Add question button - toggles the type selector tray
    const addQuestionBtn = document.getElementById('addQuestionBtn');
    const questionTypeTray = document.getElementById('questionTypeTray');

    addQuestionBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        initTurnstile();
        const isOpen = questionTypeTray.classList.toggle('open');
        addQuestionBtn.classList.toggle('tray-open', isOpen);
    });

    // Type buttons in the tray - add question of selected type
    questionTypeTray.querySelectorAll('.type-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const type = btn.dataset.type;
            addQuestion(type);
            questionTypeTray.classList.remove('open');
            addQuestionBtn.classList.remove('tray-open');
        });
    });

    // Close tray when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.add-question-wrapper')) {
            questionTypeTray.classList.remove('open');
            addQuestionBtn.classList.remove('tray-open');
        }
    });

    // Action buttons
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.addEventListener('click', saveDraft);

    document.getElementById('publishBtn').addEventListener('click', publishPoll);

    // Cancel button (edit mode)
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            window.location.href = `${basePath}/${state.publicId}/admin/${state.adminToken}`;
        });
    }

    // Clear button (create mode)
    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            const confirmed = await showConfirmModal({
                title: 'Clear Poll',
                message: 'Are you sure you want to clear all questions and settings? This cannot be undone.',
                confirmText: 'Clear All',
                danger: true,
            });
            if (confirmed) {
                resetForm();
            }
        });
    }

    // Thank You message button
    const thankYouBtn = document.getElementById('editThankYouBtn');
    if (thankYouBtn) {
        thankYouBtn.addEventListener('click', openThankYouModal);
        updateThankYouStatus();
    }

    // Preview button
    const previewBtn = document.getElementById('previewBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', openPreview);
    }
}

/**
 * Handle clicks on questions using event delegation
 * This approach is cleaner than adding click listeners to each wrapper
 */
function setupClickHandling() {
    const container = document.getElementById('questionsList');

    // Use event delegation on the container
    container.addEventListener('click', (e) => {
        // Don't interfere with interactive elements inside the editor
        if (e.target.closest('.question-editor')) {
            return;
        }

        // Don't interfere with drag handle
        if (e.target.closest('.drag-handle-question')) {
            return;
        }

        // Click on the display area of a question - enter edit mode
        const wrapper = e.target.closest('.question-wrapper');
        if (wrapper) {
            const questionId = wrapper.dataset.questionId;
            if (questionId !== activeQuestionId) {
                setActiveQuestion(questionId);
            }
        }
    });

    // Handle clicks outside questions to collapse editor
    document.addEventListener('click', (e) => {
        // Ignore clicks inside question wrappers
        if (e.target.closest('.question-wrapper')) {
            return;
        }

        // Ignore clicks on UI elements that shouldn't collapse
        if (e.target.closest('.add-question-wrapper') ||
            e.target.closest('.builder-actions') ||
            e.target.closest('.settings-panel') ||
            e.target.closest('.poll-meta') ||
            e.target.closest('.toast')) {
            return;
        }

        // Click outside - collapse current editor
        if (activeQuestionId) {
            setActiveQuestion(null);
        }
    });
}

// ==========================================================================
// Rendering
// ==========================================================================

function render() {
    document.getElementById('pollTitle').value = state.title;

    // Poll description preview setup
    const pollDescTextarea = document.getElementById('pollDescription');
    const pollDescPreview = document.getElementById('pollDescriptionPreview');
    const addPollDescBtn = document.getElementById('addPollDescriptionBtn');

    pollDescTextarea.value = state.description;

    if (state.description.trim()) {
        // Show preview
        pollDescPreview.innerHTML = marked.parse(state.description);
        pollDescPreview.style.display = '';
        pollDescTextarea.style.display = 'none';
        addPollDescBtn.style.display = 'none';
    } else {
        // Show add button
        pollDescPreview.style.display = 'none';
        pollDescTextarea.style.display = 'none';
        addPollDescBtn.style.display = '';
    }

    // Voting mode
    const votingModeInput = document.querySelector(`input[name="votingMode"][value="${state.votingMode}"]`);
    if (votingModeInput) votingModeInput.checked = true;

    // Mode lock warning
    const modeLockWarning = document.getElementById('modeLockWarning');
    if (modeLockWarning) {
        modeLockWarning.style.display = state.modeLocked ? '' : 'none';
    }

    // Disable voting mode inputs if locked
    document.querySelectorAll('input[name="votingMode"]').forEach(input => {
        input.disabled = state.modeLocked;
        const modeCard = input.closest('.mode-option');
        if (modeCard) {
            modeCard.classList.toggle('disabled', state.modeLocked);
        }
    });

    // Display options
    document.getElementById('randomizeOptions').checked = state.randomizeOptions;

    renderQuestions();
}

function renderQuestions() {
    const container = document.getElementById('questionsList');
    container.innerHTML = '';

    let questionNumber = 0;
    state.questions.forEach((question, index) => {
        // Section headers don't get numbered
        if (question.type !== 'section_header') {
            questionNumber++;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'question-wrapper';
        wrapper.dataset.questionId = question._id;

        // Drag handle is always present (visible on hover or always in edit mode)
        const dragHandle = `
            <div class="drag-handle-question" data-tooltip="Drag to reorder" data-tooltip-pos="right">
                <span class="drag-dots">&#8942;&#8942;</span>
            </div>
        `;

        if (question._id === activeQuestionId) {
            wrapper.classList.add('editing');
            wrapper.innerHTML = dragHandle + renderQuestionEditor(question, index, questionNumber);
            setupEditorEvents(wrapper, question);
        } else {
            // For display mode, render markdown descriptions client-side
            const displayQuestion = {
                ...question,
                description_html: question.description ? marked.parse(question.description) : null,
                options: question.options.map(o => ({
                    ...o,
                    description_html: o.description ? marked.parse(o.description) : null
                }))
            };
            wrapper.innerHTML = dragHandle + renderQuestion(displayQuestion, {
                disabled: true,
                showNumbers: question.type !== 'section_header',
                questionNumber: questionNumber
            });
        }

        container.appendChild(wrapper);
    });

    initQuestionsSortable();
}

/**
 * Get the required toggle behavior for a question type
 */
function getRequiredBehavior(question) {
    if (question.type === 'section_header') return { hidden: true };
    if (question.type === 'ranking') return { disabled: true, forced: true };
    // ranking_truncated uses min setting to control required behavior
    return { disabled: false };
}

/**
 * Grade preset definitions
 */
const GRADE_PRESETS = {
    'default': { label: 'Excellent → Reject', grades: ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Reject'] },
    'a-f': { label: 'A – F', grades: ['A', 'B', 'C', 'D', 'E', 'F'] },
    'plus-minus': { label: '++ / + / 0 / − / −−', grades: ['++', '+', '0', '−', '−−'] },
    'pass-fail': { label: 'Pass / Fail', grades: ['Pass', 'Fail'] },
    'custom': { label: 'Custom...', grades: null },
};

/**
 * Render type-specific settings for a question
 */
function renderTypeSettings(question) {
    const settings = question.settings || {};

    switch (question.type) {
        case 'single_choice':
            const scAllowOther = settings.allowOther ?? false;
            return `
                <div class="type-settings single-choice-settings">
                    <label class="checkbox-label">
                        <input type="checkbox" class="setting-allow-other" ${scAllowOther ? 'checked' : ''}>
                        <span>Allow "Other" option</span>
                    </label>
                </div>
            `;

        case 'approval':
            const min = settings.min ?? 0;
            const optionCount = question.options?.length || 0;
            // Show empty (placeholder "All") if max is null or equals option count
            const max = settings.max;
            const maxDisplay = (max === null || max === undefined || max >= optionCount) ? '' : max;
            const apAllowOther = settings.allowOther ?? false;
            return `
                <div class="type-settings approval-settings" data-option-count="${optionCount}">
                    <label>
                        <span>Min:</span>
                        <input type="number" class="setting-min" value="${min}" min="0" max="${optionCount}">
                    </label>
                    <label>
                        <span>Max:</span>
                        <input type="number" class="setting-max" value="${maxDisplay}" min="1" max="${optionCount}" placeholder="All">
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" class="setting-allow-other" ${apAllowOther ? 'checked' : ''}>
                        <span>Allow "Other"</span>
                    </label>
                </div>
            `;

        case 'participatory_budgeting':
            const pbMin = settings.min ?? 0;
            const pbOptionCount = question.options?.length || 0;
            const pbMax = settings.max;
            const pbMaxDisplay = (pbMax === null || pbMax === undefined || pbMax >= pbOptionCount) ? '' : pbMax;
            const currency = settings.currency ?? '';
            return `
                <div class="type-settings pb-settings" data-option-count="${pbOptionCount}">
                    <label>
                        <span>Min:</span>
                        <input type="number" class="setting-min" value="${pbMin}" min="0" max="${pbOptionCount}">
                    </label>
                    <label>
                        <span>Max:</span>
                        <input type="number" class="setting-max" value="${pbMaxDisplay}" min="1" max="${pbOptionCount}" placeholder="All">
                    </label>
                    <label>
                        <span>Currency:</span>
                        <input type="text" class="setting-currency" value="${escapeAttr(currency)}" placeholder="$, €, zł...">
                    </label>
                </div>
            `;

        case 'ranking_truncated':
            const rtMin = settings.min ?? 0;
            const rtOptionCount = question.options?.length || 0;
            const rtMax = settings.max;
            const rtMaxDisplay = (rtMax === null || rtMax === undefined || rtMax >= rtOptionCount) ? '' : rtMax;
            return `
                <div class="type-settings ranking-truncated-settings" data-option-count="${rtOptionCount}">
                    <label>
                        <span>Min:</span>
                        <input type="number" class="setting-min" value="${rtMin}" min="0" max="${rtOptionCount}">
                    </label>
                    <label>
                        <span>Max:</span>
                        <input type="number" class="setting-max" value="${rtMaxDisplay}" min="1" max="${rtOptionCount}" placeholder="All">
                    </label>
                </div>
            `;

        case 'star':
            const starCount = settings.starCount ?? 5;
            return `
                <div class="type-settings star-settings">
                    <label>
                        <span>Stars:</span>
                        <input type="number" class="setting-star-count" value="${starCount}" min="2" max="10">
                    </label>
                </div>
            `;

        case 'grade':
            const currentGrades = settings.grades || GRADE_PRESETS['default'].grades;
            const currentPreset = settings.preset || 'default';
            const isCustom = currentPreset === 'custom';
            const customValue = isCustom ? currentGrades.join(', ') : '';

            return `
                <div class="type-settings grade-settings">
                    <label>
                        <span>Scale:</span>
                        <select class="setting-grade-preset">
                            ${Object.entries(GRADE_PRESETS).map(([key, preset]) =>
                                `<option value="${key}" ${key === currentPreset ? 'selected' : ''}>${preset.label}</option>`
                            ).join('')}
                        </select>
                    </label>
                    <label class="custom-grades-row" style="${isCustom ? '' : 'display: none'}">
                        <input type="text" class="setting-custom-grades" value="${escapeAttr(customValue)}"
                            placeholder="Comma separated, e.g. Excellent, Good, Poor">
                    </label>
                </div>
            `;

        case 'yes_no_abstain':
            const allowAbstain = settings.allowAbstain !== false;
            return `
                <div class="type-settings yna-settings">
                    <label class="checkbox-label">
                        <input type="checkbox" class="setting-allow-abstain" ${allowAbstain ? 'checked' : ''}>
                        <span>Allow "Abstain"</span>
                    </label>
                </div>
            `;

        default:
            return '';
    }
}

/**
 * Render the editing UI for a question
 */
function renderQuestionEditor(question, index, questionNumber) {
    const typeOptions = QUESTION_TYPES.map(t =>
        `<option value="${t.value}" ${t.value === question.type ? 'selected' : ''}>${t.label}</option>`
    ).join('');

    const showOptions = OPTION_TYPES.includes(question.type);
    const isSectionHeader = question.type === 'section_header';
    const requiredBehavior = getRequiredBehavior(question);

    // For section headers, show "Section" instead of question number
    const numberDisplay = isSectionHeader ? '' : `<span class="question-number">${questionNumber}.</span>`;
    const titlePlaceholder = isSectionHeader ? 'Section title' : 'Question text';

    return `
        <div class="question-editor ${isSectionHeader ? 'section-header-editor' : ''}">
            <div class="editor-accent-bar"></div>
            <div class="editor-toolbar">
                <button type="button" class="btn-icon copy-question" data-tooltip="Duplicate">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </button>
                <button type="button" class="btn-icon delete-question" data-tooltip="Delete question">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
                <button type="button" class="btn-icon move-up" data-tooltip="Move up" ${index === 0 ? 'disabled' : ''}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="18 15 12 9 6 15"></polyline>
                    </svg>
                </button>
                <button type="button" class="btn-icon move-down" data-tooltip="Move down" ${index === state.questions.length - 1 ? 'disabled' : ''}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>

            <div class="editor-header">
                ${numberDisplay}
                <input type="text" class="question-title-input" value="${escapeAttr(question.text)}" placeholder="${titlePlaceholder}">
                <select class="question-type-select">${typeOptions}</select>
            </div>

            <div class="editor-description">
                <button type="button" class="btn-add-description" style="${question.description ? 'display: none' : ''}">+ Add description</button>
                <textarea class="question-description-input" style="${!question.description ? 'display: none' : ''}" placeholder="Description (optional)">${escapeAttr(question.description || '')}</textarea>
            </div>

            ${showOptions ? `
                <div class="editor-options">
                    <div class="options-list" data-question-id="${question._id}">
                        ${question.options.map((opt, i) => renderOptionEditor(opt, i, question)).join('')}
                    </div>
                    <button type="button" class="btn btn-small btn-add-option">+ Add Option</button>
                </div>
            ` : ''}

            <div class="editor-footer">
                ${renderTypeSettings(question)}
                ${!requiredBehavior.hidden ? `
                    <label class="checkbox-label required-toggle ${requiredBehavior.disabled ? 'disabled' : ''}">
                        <input type="checkbox" class="question-required-input"
                            ${question.required || requiredBehavior.forced ? 'checked' : ''}
                            ${requiredBehavior.disabled ? 'disabled' : ''}>
                        <span>Required</span>
                        ${requiredBehavior.disabled ? '<span class="required-hint">(always)</span>' : ''}
                    </label>
                ` : ''}
            </div>
        </div>
    `;
}

/**
 * Render a single option in edit mode
 */
function renderOptionEditor(option, index, question) {
    // Participatory budgeting uses a subcard layout with description and cost
    if (question.type === 'participatory_budgeting') {
        const cost = option.features?.cost ?? '';
        return `
            <div class="option-editor option-editor-card" data-option-id="${option._id}">
                <div class="option-card-header">
                    <span class="option-drag-handle" data-tooltip="Drag to reorder" data-tooltip-pos="left">&#9776;</span>
                    <span class="option-type-indicator">${getOptionIndicator(question.type)}</span>
                    <input type="text" class="option-label-input" value="${escapeAttr(option.label)}" placeholder="Project name">
                    <button type="button" class="btn-icon delete-option" data-tooltip="Delete option" data-tooltip-pos="left">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
                <div class="option-card-body">
                    <textarea class="option-description-input" placeholder="Description (supports Markdown)" rows="2">${escapeAttr(option.description || '')}</textarea>
                    <div class="option-cost-row">
                        <label>
                            <span>Cost:</span>
                            <input type="number" class="option-cost-input" value="${cost}" placeholder="0" min="0">
                        </label>
                    </div>
                </div>
            </div>
        `;
    }

    return `
        <div class="option-editor" data-option-id="${option._id}">
            <span class="option-drag-handle" data-tooltip="Drag to reorder" data-tooltip-pos="left">&#9776;</span>
            <span class="option-type-indicator">${getOptionIndicator(question.type)}</span>
            <input type="text" class="option-label-input" value="${escapeAttr(option.label)}" placeholder="Option ${index + 1}">
            <button type="button" class="btn-icon delete-option" data-tooltip="Delete option" data-tooltip-pos="left">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </button>
        </div>
    `;
}

/**
 * Get the visual indicator for an option based on question type
 */
function getOptionIndicator(type) {
    switch (type) {
        case 'single_choice': return '<span class="indicator-radio"></span>';
        case 'approval': return '<span class="indicator-checkbox"></span>';
        case 'participatory_budgeting': return '<span class="indicator-checkbox"></span>';
        default: return '';
    }
}

/**
 * Set up event listeners for the question editor
 */
function setupEditorEvents(wrapper, question) {
    // Title input
    const titleInput = wrapper.querySelector('.question-title-input');
    titleInput.addEventListener('input', (e) => {
        question.text = e.target.value;
        markDirty();
    });
    // Focus the title input when entering edit mode, unless something else is already focused
    setTimeout(() => {
        if (!wrapper.contains(document.activeElement) || document.activeElement === document.body) {
            titleInput.focus();
        }
    }, 50);

    // Description input
    const descInput = wrapper.querySelector('.question-description-input');
    const addDescBtn = wrapper.querySelector('.btn-add-description');

    if (addDescBtn) {
        addDescBtn.addEventListener('click', () => {
            addDescBtn.style.display = 'none';
            descInput.style.display = 'block';
            descInput.focus();
        });
    }

    descInput.addEventListener('input', (e) => {
        question.description = e.target.value;
        markDirty();
    });

    descInput.addEventListener('blur', (e) => {
        if (!e.target.value.trim()) {
            question.description = ''; // Normalize to empty string
            if (addDescBtn) {
                addDescBtn.style.display = 'block';
                descInput.style.display = 'none';
            }
            markDirty();
        }
    });

    // Type selector
    const typeSelect = wrapper.querySelector('.question-type-select');
    typeSelect.addEventListener('change', (e) => {
        question.type = e.target.value;
        // Add default options if switching to option type
        if (OPTION_TYPES.includes(question.type) && question.options.length === 0) {
            question.options = [
                { _id: generateTempId(), label: 'Option 1' },
                { _id: generateTempId(), label: 'Option 2' },
            ];
        }
        renderQuestions();
        markDirty();
    });

    // Required checkbox
    const requiredInput = wrapper.querySelector('.question-required-input');
    if (requiredInput) {
        requiredInput.addEventListener('change', (e) => {
            question.required = e.target.checked;

            // If unchecking required on approval type, reset min to 0
            if (!e.target.checked && question.type === 'approval' && question.settings?.min > 0) {
                question.settings.min = 0;
                // Update the input and pulse it to indicate the reset
                const minInput = wrapper.querySelector('.setting-min');
                if (minInput) {
                    minInput.value = 0;
                    minInput.classList.remove('pulse');
                    // Force reflow to restart animation
                    void minInput.offsetWidth;
                    minInput.classList.add('pulse');
                }
            }

            markDirty();
        });
    }

    // Delete button
    wrapper.querySelector('.delete-question').addEventListener('click', (e) => {
        e.stopPropagation();
        const questionIndex = state.questions.findIndex(q => q._id === question._id);
        const deletedQuestion = state.questions[questionIndex];

        // Remove question immediately
        state.questions.splice(questionIndex, 1);
        activeQuestionId = null;
        renderQuestions();
        markDirty();

        // Show undo toast
        showUndoToast('Question deleted', () => {
            // Restore the question at its original position
            state.questions.splice(questionIndex, 0, deletedQuestion);
            activeQuestionId = deletedQuestion._id;
            renderQuestions();
            markDirty();
        });
    });

    // Copy/duplicate button
    wrapper.querySelector('.copy-question').addEventListener('click', (e) => {
        e.stopPropagation();
        duplicateQuestion(question);
    });

    // Move up/down
    wrapper.querySelector('.move-up').addEventListener('click', (e) => {
        e.stopPropagation();
        moveQuestion(question._id, -1);
    });
    wrapper.querySelector('.move-down').addEventListener('click', (e) => {
        e.stopPropagation();
        moveQuestion(question._id, 1);
    });

    // Options (if present)
    const optionsList = wrapper.querySelector('.options-list');
    if (optionsList) {
        setupOptionEvents(optionsList, question);

        // Add option button
        wrapper.querySelector('.btn-add-option').addEventListener('click', () => {
            const newOptionId = generateTempId();
            question.options.push({
                _id: newOptionId,
                label: `Option ${question.options.length + 1}`,
            });
            renderQuestions();
            markDirty();

            // Focus the new option's input
            // Search in document because renderQuestions() replaced the wrapper
            const newOptionInput = document.querySelector(
                `.option-editor[data-option-id="${newOptionId}"] .option-label-input`
            );
            if (newOptionInput) {
                newOptionInput.focus();
                newOptionInput.select();
            }
        });
    }

    // Type-specific settings
    setupTypeSettingsEvents(wrapper, question);
}

/**
 * Set up event handlers for type-specific settings
 */
function setupTypeSettingsEvents(wrapper, question) {
    // Initialize settings object if needed
    if (!question.settings) {
        question.settings = {};
    }

    // Min/max settings (approval, participatory_budgeting, and ranking_truncated share this pattern)
    const minInput = wrapper.querySelector('.setting-min');
    const maxInput = wrapper.querySelector('.setting-max');
    const minMaxSettings = wrapper.querySelector('.approval-settings, .pb-settings, .ranking-truncated-settings');
    const optionCount = minMaxSettings ? parseInt(minMaxSettings.dataset.optionCount) || 0 : 0;

    if (minInput && maxInput) {
        minInput.addEventListener('change', (e) => {
            const oldMin = question.settings.min ?? 0;
            const newMin = parseInt(e.target.value) || 0;
            question.settings.min = newMin;

            // Get effective max (null means "all" = optionCount)
            const effectiveMax = question.settings.max ?? optionCount;

            // If min was equal to max and min increased, increase max too
            if (oldMin === effectiveMax && newMin > oldMin && newMin <= optionCount) {
                question.settings.max = newMin;
                // Show "All" if max equals option count, otherwise show the number
                maxInput.value = (newMin >= optionCount) ? '' : newMin;
                // Pulse the max input
                maxInput.classList.remove('pulse');
                void maxInput.offsetWidth;
                maxInput.classList.add('pulse');
            }

            markDirty();
        });

        maxInput.addEventListener('change', (e) => {
            const oldMax = question.settings.max ?? optionCount;
            const newMax = e.target.value ? parseInt(e.target.value) : null;
            // Store null if max equals or exceeds option count (means "all")
            question.settings.max = (newMax === null || newMax >= optionCount) ? null : newMax;

            const effectiveNewMax = newMax ?? optionCount;
            const currentMin = question.settings.min ?? 0;

            // If max was equal to min and max decreased, decrease min too
            if (oldMax === currentMin && effectiveNewMax < oldMax && effectiveNewMax >= 0) {
                question.settings.min = effectiveNewMax;
                minInput.value = effectiveNewMax;
                // Pulse the min input
                minInput.classList.remove('pulse');
                void minInput.offsetWidth;
                minInput.classList.add('pulse');
            }

            // Clear display if it equals option count (show placeholder "All")
            if (newMax !== null && newMax >= optionCount) {
                maxInput.value = '';
            }

            markDirty();
        });
    }

    // Currency setting (participatory_budgeting)
    const currencyInput = wrapper.querySelector('.setting-currency');
    if (currencyInput) {
        currencyInput.addEventListener('input', (e) => {
            question.settings.currency = e.target.value;
            markDirty();
        });
    }

    // Star count setting
    const starCountInput = wrapper.querySelector('.setting-star-count');
    if (starCountInput) {
        starCountInput.addEventListener('change', (e) => {
            const value = parseInt(e.target.value) || 5;
            question.settings.starCount = Math.max(2, Math.min(10, value));
            renderQuestions();
            markDirty();
        });
    }

    // Grade preset setting
    const gradePresetSelect = wrapper.querySelector('.setting-grade-preset');
    const customGradesInput = wrapper.querySelector('.setting-custom-grades');
    const customGradesLabel = wrapper.querySelector('.custom-grades-row');

    if (gradePresetSelect) {
        gradePresetSelect.addEventListener('change', (e) => {
            const preset = e.target.value;
            question.settings.preset = preset;

            if (preset === 'custom') {
                if (customGradesLabel) customGradesLabel.style.display = '';
                // Parse existing custom grades or use default
                const customValue = customGradesInput?.value.trim();
                if (customValue) {
                    question.settings.grades = customValue.split(',').map(g => g.trim()).filter(g => g);
                }
            } else {
                if (customGradesLabel) customGradesLabel.style.display = 'none';
                question.settings.grades = GRADE_PRESETS[preset].grades;
            }

            renderQuestions();
            markDirty();
        });
    }

    if (customGradesInput) {
        customGradesInput.addEventListener('change', (e) => {
            const value = e.target.value.trim();
            if (value) {
                question.settings.grades = value.split(',').map(g => g.trim()).filter(g => g);
                renderQuestions();
                markDirty();
            }
        });
    }

    // Y/N/A allow abstain setting
    const allowAbstainInput = wrapper.querySelector('.setting-allow-abstain');
    if (allowAbstainInput) {
        allowAbstainInput.addEventListener('change', (e) => {
            question.settings.allowAbstain = e.target.checked;
            renderQuestions();
            markDirty();
        });
    }

    // Allow "Other" option setting (single_choice, approval)
    const allowOtherInput = wrapper.querySelector('.setting-allow-other');
    if (allowOtherInput) {
        allowOtherInput.addEventListener('change', (e) => {
            question.settings.allowOther = e.target.checked;
            markDirty();
        });
    }
}

/**
 * Set up events for options within editor
 */
function setupOptionEvents(optionsList, question) {
    // Option inputs
    optionsList.querySelectorAll('.option-editor').forEach(optEl => {
        const optionId = optEl.dataset.optionId;
        const option = question.options.find(o => o._id === optionId);
        if (!option) return;

        const labelInput = optEl.querySelector('.option-label-input');
        labelInput.addEventListener('input', (e) => {
            option.label = e.target.value;
            markDirty();
        });

        // Description input (participatory_budgeting)
        const descInput = optEl.querySelector('.option-description-input');
        if (descInput) {
            descInput.addEventListener('input', (e) => {
                option.description = e.target.value;
                markDirty();
            });
        }

        // Cost input (participatory_budgeting)
        const costInput = optEl.querySelector('.option-cost-input');
        if (costInput) {
            costInput.addEventListener('input', (e) => {
                if (!option.features) option.features = {};
                const value = e.target.value;
                option.features.cost = value === '' ? null : parseFloat(value) || 0;
                markDirty();
            });
        }

        const deleteBtn = optEl.querySelector('.delete-option');
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (question.options.length > 2) {
                const optionIndex = question.options.findIndex(o => o._id === optionId);
                const deletedOption = question.options[optionIndex];
                const currentQuestionId = question._id; // Preserve edit mode

                // Remove option immediately
                question.options.splice(optionIndex, 1);
                renderQuestions();
                markDirty();

                // Show undo toast
                showUndoToast('Option deleted', () => {
                    // Restore the option at its original position
                    question.options.splice(optionIndex, 0, deletedOption);
                    activeQuestionId = currentQuestionId; // Keep question in edit mode
                    renderQuestions();
                    markDirty();
                });
            } else {
                showToast('Need at least 2 options', 'error');
            }
        });
    });

    // Make options sortable
    new Sortable(optionsList, {
        animation: 150,
        handle: '.option-drag-handle',
        ghostClass: 'option-ghost',
        onEnd: (evt) => {
            const optionId = evt.item.dataset.optionId;
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;

            if (oldIndex !== newIndex) {
                const [moved] = question.options.splice(oldIndex, 1);
                question.options.splice(newIndex, 0, moved);
                markDirty();
            }
        }
    });
}

// ==========================================================================
// Question Operations
// ==========================================================================

function setActiveQuestion(questionId) {
    if (activeQuestionId === questionId) return;
    activeQuestionId = questionId;
    renderQuestions();
}

function addQuestion(type = 'single_choice') {
    const question = {
        _id: generateTempId(),
        type: type,
        text: '',
        description: '',
        required: type === 'ranking' ? true : true, // ranking is always required
        options: [],
        settings: {},
    };

    // Add default options for types that need them
    if (OPTION_TYPES.includes(type)) {
        question.options = [
            { _id: generateTempId(), label: 'Option 1' },
            { _id: generateTempId(), label: 'Option 2' },
        ];
    }

    // Set type-specific default settings
    if (type === 'star') {
        question.settings.starCount = 5;
    } else if (type === 'grade') {
        question.settings.preset = 'default';
        question.settings.grades = GRADE_PRESETS['default'].grades;
    } else if (type === 'yes_no_abstain') {
        question.settings.allowAbstain = true;
    }

    state.questions.push(question);
    activeQuestionId = question._id;  // Open for editing immediately
    renderQuestions();
    markDirty();
}

function duplicateQuestion(question) {
    const copy = {
        _id: generateTempId(),
        type: question.type,
        text: question.text + ' (copy)',
        description: question.description,
        required: question.required,
        settings: question.settings ? { ...question.settings } : {},
        options: question.options.map(o => ({
            _id: generateTempId(),
            label: o.label,
            description: o.description || '',
            features: o.features ? { ...o.features } : null,
        })),
    };

    const index = state.questions.findIndex(q => q._id === question._id);
    state.questions.splice(index + 1, 0, copy);
    activeQuestionId = copy._id;
    renderQuestions();
    markDirty();
}

function moveQuestion(questionId, direction) {
    const index = state.questions.findIndex(q => q._id === questionId);
    const newIndex = index + direction;

    if (newIndex >= 0 && newIndex < state.questions.length) {
        const [moved] = state.questions.splice(index, 1);
        state.questions.splice(newIndex, 0, moved);
        renderQuestions();
        markDirty();
    }
}

// ==========================================================================
// SortableJS for Questions
// ==========================================================================

function initQuestionsSortable() {
    const container = document.getElementById('questionsList');

    // Destroy existing sortable if any
    if (questionsSortable) {
        questionsSortable.destroy();
    }

    questionsSortable = new Sortable(container, {
        animation: 150,
        handle: '.drag-handle-question',
        ghostClass: 'question-ghost',
        chosenClass: 'question-chosen',
        dragClass: 'question-drag',
        onStart: () => {
            // Clear active question without re-rendering (re-render would destroy DOM mid-drag)
            activeQuestionId = null;
        },
        onEnd: (evt) => {
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;

            if (oldIndex !== newIndex) {
                const [moved] = state.questions.splice(oldIndex, 1);
                state.questions.splice(newIndex, 0, moved);
                markDirty();
            }
            // Always re-render to sync DOM with state (also collapses any editor that was open)
            renderQuestions();
        }
    });
}

// ==========================================================================
// State Persistence
// ==========================================================================

function markDirty() {
    state.isDirty = true;
    saveToLocalStorage();
}

function saveToLocalStorage() {
    if (isEditMode) return;
    localStorage.setItem('poll_draft', JSON.stringify(state));
}

function loadFromLocalStorage() {
    const saved = localStorage.getItem('poll_draft');
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.assign(state, data);
            return true;
        } catch (e) {
            console.error('Failed to load draft:', e);
        }
    }
    return false;
}

function loadFromServer(voteData, adminToken) {
    state.title = voteData.title || 'Untitled Poll';
    state.description = voteData.description || '';
    state.publicId = voteData.public_id;
    state.adminToken = adminToken;
    state.isDirty = false;

    state.votingMode = voteData.voting_mode || 'open';
    state.randomizeOptions = voteData.randomize_options || false;
    state.thankYouMessage = voteData.thank_you_message || '';
    state.modeLocked = !!voteData.mode_locked_at;

    state.questions = (voteData.questions || []).map(q => ({
        _id: generateTempId(),
        id: q.id,
        type: q.type,
        text: q.text || '',
        description: q.description || '',
        required: q.required !== false,
        settings: q.settings || {},
        // Filter out user-added options (created by voters via "Other")
        options: (q.options || [])
            .filter(o => !o.features?.isUserAdded)
            .map(o => ({
                _id: generateTempId(),
                id: o.id,
                label: o.label || '',
                description: o.description || '',
                features: o.features || null,
            })),
    }));

    localStorage.removeItem('poll_draft');
}

function clearLocalStorage() {
    localStorage.removeItem('poll_draft');
}

function resetForm() {
    state.title = defaultState.title;
    state.description = defaultState.description;
    state.votingMode = defaultState.votingMode;
    state.randomizeOptions = defaultState.randomizeOptions;
    state.thankYouMessage = defaultState.thankYouMessage;
    state.modeLocked = false;
    state.questions = [];
    state.publicId = null;
    state.adminToken = null;
    state.isDirty = false;
    activeQuestionId = null;

    clearLocalStorage();

    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) clearBtn.style.display = 'none';

    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.textContent = 'Save Draft';

    updateThankYouStatus();
    render();
}

function setupAutoSave() {
    setInterval(() => {
        if (state.isDirty) {
            saveToLocalStorage();
        }
    }, 5000);
}

// ==========================================================================
// API Operations
// ==========================================================================

async function saveDraft() {
    const saveBtn = document.getElementById('saveBtn');

    try {
        if (saveBtn) setButtonLoading(saveBtn);

        const data = prepareData();
        if (!state.publicId) {
            data.status = 'draft';
        }

        let result;
        if (state.publicId && state.adminToken) {
            result = await api.put(`/api/polls/${state.publicId}/admin/${state.adminToken}`, data);
        } else {
            // Include Turnstile token for new polls
            const token = getTurnstileToken();
            if (token) {
                data.turnstile_token = token;
            }
            result = await api.post('/api/polls', data);
            state.publicId = result.vote.public_id;
            state.adminToken = result.vote.admin_token;
        }

        state.isDirty = false;

        if (!window.POLL_DATA) {
            saveToLocalStorage();
        }

        if (window.POLL_DATA) {
            // Edit mode - redirect without success toast
            window.location.href = `${basePath}/${state.publicId}/admin/${state.adminToken}`;
        } else {
            // Create mode - show success toast with link, stay on page
            if (saveBtn) clearButtonLoading(saveBtn);
            showToast('Saved! View in <a href="' + basePath + '/dashboard">Dashboard</a>', 'success');
            if (saveBtn) saveBtn.textContent = 'Update Draft';
        }
    } catch (err) {
        if (saveBtn) clearButtonLoading(saveBtn);
        showToast(err.message, 'error');
        resetTurnstile();
    }
}

async function publishPoll() {
    // Validate
    if (!state.title.trim()) {
        showToast('Please add a title', 'error');
        return;
    }

    if (state.questions.length === 0) {
        showToast('Please add at least one question', 'error');
        return;
    }

    for (const q of state.questions) {
        if (!q.text.trim()) {
            showToast('Please fill in all question texts', 'error');
            return;
        }
    }

    // Check Turnstile if required for new polls
    if (window.TURNSTILE_ENABLED && !state.publicId && !getTurnstileToken()) {
        showToast('Please wait for security verification to complete', 'error');
        return;
    }

    const publishBtn = document.getElementById('publishBtn');

    try {
        if (publishBtn) setButtonLoading(publishBtn);

        const data = prepareData();
        data.status = 'open';

        let result;
        if (state.publicId && state.adminToken) {
            result = await api.put(`/api/polls/${state.publicId}/admin/${state.adminToken}`, data);
        } else {
            // Include Turnstile token for new polls
            const token = getTurnstileToken();
            if (token) {
                data.turnstile_token = token;
            }
            result = await api.post('/api/polls', data);
        }

        clearLocalStorage();
        state.isDirty = false;

        const adminUrl = result.admin_url || `${basePath}/${state.publicId}/admin/${state.adminToken}`;
        window.location.href = adminUrl;
    } catch (err) {
        if (publishBtn) clearButtonLoading(publishBtn);
        showToast(err.message, 'error');
        resetTurnstile();
    }
}

function prepareData() {
    return {
        title: state.title,
        description: state.description,
        voting_mode: state.votingMode,
        randomize_options: state.randomizeOptions,
        thank_you_message: state.thankYouMessage || null,
        questions: state.questions.map((q, index) => ({
            id: q.id,
            type: q.type,
            text: q.text,
            description: q.description,
            required: q.required,
            settings: q.settings || null,
            sort_order: index,
            options: q.options.map((o, oIndex) => ({
                id: o.id,
                label: o.label,
                description: o.description || null,
                features: o.features || null,
                sort_order: oIndex,
            })),
        })),
    };
}

// ==========================================================================
// Turnstile Integration
// ==========================================================================

/**
 * Initialize Turnstile for anonymous users
 * Called when user clicks "Add Question" button
 */
function initTurnstile() {
    // Only for anonymous users creating new polls
    if (!window.TURNSTILE_ENABLED || isEditMode) return;
    if (!window.turnstile || turnstileWidgetId !== null) return;

    const container = document.getElementById('turnstileContainer');
    if (!container) return;

    turnstileWidgetId = turnstile.render(container, {
        sitekey: window.TURNSTILE_SITE_KEY,
        callback: (token) => {
            turnstileToken = token;
        },
        'refresh-expired': 'auto',
        size: 'invisible'
    });
}

/**
 * Get Turnstile token if available
 * Returns null if Turnstile is not enabled or not initialized
 */
function getTurnstileToken() {
    if (!window.TURNSTILE_ENABLED || isEditMode) return null;
    return turnstileToken;
}

/**
 * Reset Turnstile widget (call on error)
 */
function resetTurnstile() {
    if (turnstileWidgetId !== null && window.turnstile) {
        turnstile.reset(turnstileWidgetId);
        turnstileToken = null;
    }
}

// ==========================================================================
// Preview
// ==========================================================================

/**
 * Open poll preview in a new tab
 */
async function openPreview() {
    // Validate basic requirements
    if (!state.title.trim()) {
        showToast('Please add a title before previewing', 'error');
        return;
    }

    if (state.questions.length === 0) {
        showToast('Please add at least one question before previewing', 'error');
        return;
    }

    // Prepare data in the same format as prepareData()
    const data = prepareData();

    try {
        // POST to /preview and open in new tab
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/preview`;
        form.target = '_blank';

        // Add hidden input with JSON data
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'data';
        input.value = JSON.stringify(data);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    } catch (err) {
        showToast('Failed to open preview', 'error');
    }
}

// ==========================================================================
// Thank You Modal
// ==========================================================================

function updateThankYouStatus() {
    const status = document.getElementById('thankYouStatus');
    if (status) {
        status.style.display = state.thankYouMessage ? '' : 'none';
    }
}

function openThankYouModal() {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-modal-overlay';

    overlay.innerHTML = `
        <div class="confirm-modal thank-you-modal" style="max-width: 600px;">
            <div class="confirm-modal-header">
                <h3>Customize Thank You Message</h3>
            </div>
            <div class="confirm-modal-body">
                <p style="margin-bottom: 0.75rem; color: var(--color-text-muted); font-size: 0.875rem;">
                    Shown to voters after they submit. Supports Markdown formatting.
                </p>
                <textarea id="thankYouInput" class="thank-you-textarea" rows="6" placeholder="Thank you for voting!&#10;&#10;Your response has been recorded.">${escapeHtml(state.thankYouMessage || '')}</textarea>
                <div class="thank-you-preview-section" style="margin-top: 1rem;">
                    <label style="font-size: 0.875rem; color: var(--color-text-muted); display: block; margin-bottom: 0.5rem;">Preview:</label>
                    <div id="thankYouPreview" class="thank-you-preview markdown" style="padding: 1rem; background: var(--color-bg); border-radius: 0.5rem; min-height: 3rem;"></div>
                </div>
            </div>
            <div class="confirm-modal-actions">
                <button class="btn btn-secondary btn-cancel">Cancel</button>
                <button class="btn btn-danger btn-clear" style="${state.thankYouMessage ? '' : 'display: none'}">Reset to Default</button>
                <button class="btn btn-primary btn-save">Save</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const textarea = overlay.querySelector('#thankYouInput');
    const preview = overlay.querySelector('#thankYouPreview');
    const clearBtn = overlay.querySelector('.btn-clear');

    // Update preview function
    const updatePreview = () => {
        const text = textarea.value.trim();
        if (text) {
            preview.innerHTML = marked.parse(text);
        } else {
            preview.innerHTML = '<p style="color: var(--color-text-muted); font-style: italic;">Default message: "Thank you! Your response has been recorded."</p>';
        }
    };

    // Initial preview
    updatePreview();

    // Update preview on input
    textarea.addEventListener('input', updatePreview);

    const close = () => {
        overlay.remove();
    };

    // Cancel
    overlay.querySelector('.btn-cancel').addEventListener('click', close);

    // Clear/Reset
    clearBtn.addEventListener('click', () => {
        textarea.value = '';
        updatePreview();
        clearBtn.style.display = 'none';
    });

    // Save
    overlay.querySelector('.btn-save').addEventListener('click', () => {
        state.thankYouMessage = textarea.value.trim();
        markDirty();
        updateThankYouStatus();
        close();
    });

    // Click outside to close
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });

    // Escape to close
    const handleKeydown = (e) => {
        if (e.key === 'Escape') {
            document.removeEventListener('keydown', handleKeydown);
            close();
        }
    };
    document.addEventListener('keydown', handleKeydown);

    // Focus textarea
    textarea.focus();
}

// ==========================================================================
// Utilities
// ==========================================================================

function escapeAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
