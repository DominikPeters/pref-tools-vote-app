/**
 * Form Builder JavaScript
 *
 * Modes & Buttons:
 * ┌─────────────────────────┬─────────────────────────────────────────────────┐
 * │ Mode                    │ Buttons                                         │
 * ├─────────────────────────┼─────────────────────────────────────────────────┤
 * │ Create (fresh)          │ Preview, [Save Draft]*, Publish                 │
 * │ Create (from storage)   │ Preview, Clear, [Save Draft]*, Publish          │
 * │ Edit draft              │ Preview, Cancel, Update Draft, Publish          │
 * │ Edit open/closed        │ Preview, Cancel, Save Changes                   │
 * └─────────────────────────┴─────────────────────────────────────────────────┘
 * * Save Draft only shown for logged-in users
 *
 * Storage:
 * - Create mode: auto-saves to localStorage on every change
 * - Edit mode: no localStorage (clears it on init so next create is fresh)
 */

import { api, generateTempId, debounce, showToast, basePath } from './app.js';

// Default state for resetting
const defaultState = {
    title: 'Untitled Vote',
    description: '',
    settings: {
        collectName: false,
        visibility: 'private',
        visibilityTiming: 'after_close',
        allowEditOwn: true,
        allowEditAny: false,
        randomizeOptions: false,
    },
    questions: [],
    publicId: null,
    adminToken: null,
    isDirty: false,
};

// Form state
const state = { ...defaultState, settings: { ...defaultState.settings }, questions: [] };

// Question types that need options
const OPTION_TYPES = [
    'single_choice', 'approval', 'ranking', 'star', 'grade', 'yes_no_abstain'
];

// Track if we're in edit mode (don't save to localStorage)
let isEditMode = false;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Check if editing an existing vote
    if (window.VOTE_DATA) {
        isEditMode = true;
        loadFromServer(window.VOTE_DATA, window.ADMIN_TOKEN);
        // Clear localStorage so next create is fresh
        clearLocalStorage();
    } else {
        const loadedFromStorage = loadFromLocalStorage();
        // Show Clear button if we loaded saved data
        if (loadedFromStorage) {
            const clearBtn = document.getElementById('clearBtn');
            if (clearBtn) {
                clearBtn.style.display = '';
            }
        }
    }
    initElements();
    render();
    setupAutoSave();
});

function initElements() {
    // Title and description
    document.getElementById('voteTitle').addEventListener('input', (e) => {
        state.title = e.target.value || 'Untitled Vote';
        markDirty();
    });

    document.getElementById('voteDescription').addEventListener('input', (e) => {
        state.description = e.target.value;
        markDirty();
    });

    // Settings
    document.getElementById('collectName').addEventListener('change', (e) => {
        state.settings.collectName = e.target.checked;
        markDirty();
    });

    document.querySelectorAll('input[name="visibility"]').forEach(input => {
        input.addEventListener('change', (e) => {
            state.settings.visibility = e.target.value;
            markDirty();
        });
    });

    document.querySelectorAll('input[name="visibilityTiming"]').forEach(input => {
        input.addEventListener('change', (e) => {
            state.settings.visibilityTiming = e.target.value;
            markDirty();
        });
    });

    document.getElementById('allowEditOwn').addEventListener('change', (e) => {
        state.settings.allowEditOwn = e.target.checked;
        markDirty();
    });

    document.getElementById('allowEditAny').addEventListener('change', (e) => {
        state.settings.allowEditAny = e.target.checked;
        markDirty();
    });

    document.getElementById('randomizeOptions').addEventListener('change', (e) => {
        state.settings.randomizeOptions = e.target.checked;
        markDirty();
    });

    // Add question button
    document.getElementById('addQuestionBtn').addEventListener('click', addQuestion);

    // Action buttons
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveDraft);
    }
    document.getElementById('publishBtn').addEventListener('click', publishVote);
    document.getElementById('previewBtn').addEventListener('click', previewVote);

    // Cancel button (edit mode) - go back to admin
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            window.location.href = `${basePath}/${state.publicId}/admin/${state.adminToken}`;
        });
    }

    // Clear button (create mode) - reset form
    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', resetForm);
    }
}

function render() {
    // Update form fields
    document.getElementById('voteTitle').value = state.title;
    document.getElementById('voteDescription').value = state.description;

    // Update settings
    document.getElementById('collectName').checked = state.settings.collectName;
    document.querySelector(`input[name="visibility"][value="${state.settings.visibility}"]`).checked = true;
    document.querySelector(`input[name="visibilityTiming"][value="${state.settings.visibilityTiming}"]`).checked = true;
    document.getElementById('allowEditOwn').checked = state.settings.allowEditOwn;
    document.getElementById('allowEditAny').checked = state.settings.allowEditAny;
    document.getElementById('randomizeOptions').checked = state.settings.randomizeOptions;

    // Render questions
    renderQuestions();
}

function renderQuestions() {
    const container = document.getElementById('questionsList');
    container.innerHTML = '';

    state.questions.forEach((question, index) => {
        const element = createQuestionElement(question, index);
        container.appendChild(element);
    });

    // Make questions sortable
    makeSortable(container, '.question-card', (newOrder) => {
        state.questions = newOrder.map(id => state.questions.find(q => q._id === id));
        markDirty();
    });
}

function createQuestionElement(question, index) {
    const template = document.getElementById('questionTemplate');
    const element = template.content.cloneNode(true).querySelector('.question-card');

    element.dataset.questionId = question._id;

    // Type selector
    const typeSelect = element.querySelector('.question-type');
    typeSelect.value = question.type;
    typeSelect.addEventListener('change', (e) => {
        question.type = e.target.value;
        // Add default options if needed
        if (OPTION_TYPES.includes(question.type) && question.options.length === 0) {
            question.options = [
                { _id: generateTempId(), label: 'Option 1' },
                { _id: generateTempId(), label: 'Option 2' },
            ];
        }
        renderQuestions();
        markDirty();
    });

    // Question text
    const textInput = element.querySelector('.question-text');
    textInput.value = question.text;
    textInput.addEventListener('input', (e) => {
        question.text = e.target.value;
        markDirty();
    });

    // Description
    const descInput = element.querySelector('.question-description');
    descInput.value = question.description || '';
    descInput.addEventListener('input', (e) => {
        question.description = e.target.value;
        markDirty();
    });

    // Required checkbox
    const requiredCheckbox = element.querySelector('.question-required');
    requiredCheckbox.checked = question.required;
    requiredCheckbox.addEventListener('change', (e) => {
        question.required = e.target.checked;
        markDirty();
    });

    // Delete button
    element.querySelector('.delete-question').addEventListener('click', () => {
        if (confirm('Delete this question?')) {
            state.questions = state.questions.filter(q => q._id !== question._id);
            renderQuestions();
            markDirty();
        }
    });

    // Options
    const optionsList = element.querySelector('.options-list');
    const addOptionBtn = element.querySelector('.add-option');

    if (OPTION_TYPES.includes(question.type)) {
        renderOptions(optionsList, question);
        addOptionBtn.addEventListener('click', () => {
            question.options.push({
                _id: generateTempId(),
                label: `Option ${question.options.length + 1}`,
            });
            renderOptions(optionsList, question);
            markDirty();
        });
    } else {
        optionsList.style.display = 'none';
        addOptionBtn.style.display = 'none';
    }

    return element;
}

function renderOptions(container, question) {
    container.innerHTML = '';

    question.options.forEach((option, index) => {
        const template = document.getElementById('optionTemplate');
        const element = template.content.cloneNode(true).querySelector('.option-item');

        element.dataset.optionId = option._id;

        const labelInput = element.querySelector('.option-label');
        labelInput.value = option.label;
        labelInput.addEventListener('input', (e) => {
            option.label = e.target.value;
            markDirty();
        });

        element.querySelector('.delete-option').addEventListener('click', () => {
            if (question.options.length > 2) {
                question.options = question.options.filter(o => o._id !== option._id);
                renderOptions(container, question);
                markDirty();
            } else {
                showToast('Need at least 2 options', 'error');
            }
        });

        container.appendChild(element);
    });

    // Make options sortable
    makeSortable(container, '.option-item', (newOrder) => {
        question.options = newOrder.map(id => question.options.find(o => o._id === id));
        markDirty();
    });
}

function addQuestion() {
    const question = {
        _id: generateTempId(),
        type: 'single_choice',
        text: '',
        description: '',
        required: true,
        options: [
            { _id: generateTempId(), label: 'Option 1' },
            { _id: generateTempId(), label: 'Option 2' },
        ],
    };

    state.questions.push(question);
    renderQuestions();
    markDirty();

    // Focus the new question
    setTimeout(() => {
        const elements = document.querySelectorAll('.question-text');
        const lastElement = elements[elements.length - 1];
        if (lastElement) {
            lastElement.focus();
        }
    }, 100);
}

function makeSortable(container, itemSelector, onSort) {
    const items = container.querySelectorAll(itemSelector);

    items.forEach(item => {
        const handle = item.querySelector('.drag-handle, .drag-handle-small');
        if (!handle) return;

        handle.addEventListener('dragstart', (e) => {
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        handle.addEventListener('dragend', () => {
            item.classList.remove('dragging');

            const newOrder = Array.from(container.querySelectorAll(itemSelector))
                .map(el => el.dataset.questionId || el.dataset.optionId);

            onSort(newOrder);
        });

        item.setAttribute('draggable', 'true');
    });

    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        const dragging = container.querySelector('.dragging');
        const afterElement = getDragAfterElement(container, e.clientY, itemSelector);

        if (afterElement) {
            container.insertBefore(dragging, afterElement);
        } else {
            container.appendChild(dragging);
        }
    });
}

function getDragAfterElement(container, y, itemSelector) {
    const elements = [...container.querySelectorAll(`${itemSelector}:not(.dragging)`)];

    return elements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function markDirty() {
    state.isDirty = true;
    saveToLocalStorage();
}

function saveToLocalStorage() {
    // Don't save to localStorage in edit mode
    if (isEditMode) return;
    localStorage.setItem('vote_draft', JSON.stringify(state));
}

function loadFromLocalStorage() {
    const saved = localStorage.getItem('vote_draft');
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
    // Convert server data format to local state format
    state.title = voteData.title || 'Untitled Vote';
    state.description = voteData.description || '';
    state.publicId = voteData.public_id;
    state.adminToken = adminToken;
    state.isDirty = false;

    // Settings
    state.settings = {
        collectName: voteData.collect_name || false,
        visibility: voteData.visibility || 'private',
        visibilityTiming: voteData.visibility_timing || 'after_close',
        allowEditOwn: voteData.allow_edit_own !== false,
        allowEditAny: voteData.allow_edit_any || false,
        randomizeOptions: voteData.randomize_options || false,
    };

    // Questions
    state.questions = (voteData.questions || []).map(q => ({
        _id: generateTempId(),
        id: q.id,
        type: q.type,
        text: q.text || '',
        description: q.description || '',
        required: q.required !== false,
        settings: q.settings || {},
        options: (q.options || []).map(o => ({
            _id: generateTempId(),
            id: o.id,
            label: o.label || '',
            description: o.description || '',
        })),
    }));

    // Clear localStorage draft since we're editing an existing vote
    localStorage.removeItem('vote_draft');
}

function clearLocalStorage() {
    localStorage.removeItem('vote_draft');
}

function resetForm() {
    // Reset state to defaults
    state.title = defaultState.title;
    state.description = defaultState.description;
    state.settings = { ...defaultState.settings };
    state.questions = [];
    state.publicId = null;
    state.adminToken = null;
    state.isDirty = false;

    // Clear localStorage
    clearLocalStorage();

    // Hide Clear button
    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }

    // Reset Save Draft button text
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.textContent = 'Save Draft';
    }

    // Re-render the form
    render();
}

function setupAutoSave() {
    // Auto-save to localStorage every 5 seconds if dirty
    setInterval(() => {
        if (state.isDirty) {
            saveToLocalStorage();
        }
    }, 5000);
}

async function saveDraft() {
    try {
        const data = prepareData();
        // Only set to draft if creating new, otherwise keep current status
        if (!state.publicId) {
            data.status = 'draft';
        }

        let result;
        if (state.publicId && state.adminToken) {
            result = await api.put(`/api/votes/${state.publicId}/admin/${state.adminToken}`, data);
        } else {
            result = await api.post('/api/votes', data);
            state.publicId = result.vote.public_id;
            state.adminToken = result.vote.admin_token;
        }

        state.isDirty = false;

        // If editing, don't save to localStorage
        if (!window.VOTE_DATA) {
            saveToLocalStorage();
        }

        // If we were editing (came from server), go back to admin
        if (window.VOTE_DATA) {
            showToast('Saved! Returning to admin...', 'success');
            setTimeout(() => {
                window.location.href = `${basePath}/${state.publicId}/admin/${state.adminToken}`;
            }, 1000);
        } else {
            showToast('Saved! View in <a href="' + basePath + '/dashboard">Dashboard</a>', 'success');
            // Update button text from "Save Draft" to "Update Draft"
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.textContent = 'Update Draft';
            }
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function publishVote() {
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

    try {
        const data = prepareData();
        data.status = 'open';

        let result;
        if (state.publicId && state.adminToken) {
            result = await api.put(`/api/votes/${state.publicId}/admin/${state.adminToken}`, data);
        } else {
            result = await api.post('/api/votes', data);
        }

        clearLocalStorage();
        state.isDirty = false;

        // Redirect to admin page
        const adminUrl = result.admin_url || `${basePath}/${state.publicId}/admin/${state.adminToken}`;
        window.location.href = adminUrl;
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function previewVote() {
    const modal = document.getElementById('previewModal');
    const content = document.getElementById('previewContent');

    // Build preview HTML
    content.innerHTML = renderPreview();

    // Show modal
    modal.style.display = 'flex';

    // Close handlers
    const closeBtn = document.getElementById('closePreview');
    const backdrop = modal.querySelector('.modal-backdrop');

    const closeModal = () => {
        modal.style.display = 'none';
    };

    closeBtn.onclick = closeModal;
    backdrop.onclick = closeModal;

    // Close on Escape
    const handleEscape = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

function renderPreview() {
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    let html = `
        <header class="vote-header">
            <h1>${escapeHtml(state.title || 'Untitled Vote')}</h1>
            ${state.description ? `<div class="vote-description">${escapeHtml(state.description)}</div>` : ''}
        </header>
        <form class="vote-form">
    `;

    // Voter name field
    if (state.settings.collectName) {
        html += `
            <div class="form-group name-field">
                <label>Your Name</label>
                <input type="text" disabled placeholder="Voter name">
            </div>
        `;
    }

    // Questions
    state.questions.forEach((question, index) => {
        html += `
            <div class="question-block">
                <div class="question-text">
                    ${escapeHtml(question.text || `Question ${index + 1}`)}
                    ${question.required ? '<span class="required-marker">*</span>' : ''}
                </div>
                ${question.description ? `<div class="question-description">${escapeHtml(question.description)}</div>` : ''}
                <div class="question-input">
                    ${renderQuestionInput(question)}
                </div>
            </div>
        `;
    });

    html += `
            <div class="form-actions">
                <button type="button" class="btn btn-primary btn-large" disabled>Submit Vote</button>
            </div>
        </form>
    `;

    return html;
}

function renderQuestionInput(question) {
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    switch (question.type) {
        case 'text_single':
            return '<input type="text" class="form-control" disabled placeholder="Short answer">';

        case 'text_multi':
            return '<textarea class="form-control" rows="3" disabled placeholder="Long answer"></textarea>';

        case 'single_choice':
            return `
                <div class="radio-options">
                    ${question.options.map(o => `
                        <label class="radio-option">
                            <input type="radio" disabled>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                </div>
            `;

        case 'approval':
            return `
                <div class="checkbox-options">
                    ${question.options.map(o => `
                        <label class="checkbox-option">
                            <input type="checkbox" disabled>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                </div>
            `;

        case 'ranking':
            return `
                <p class="ranking-hint" style="color: var(--color-text-muted); font-size: 0.875rem;">Drag to reorder (top = best)</p>
                <ol class="ranking-list">
                    ${question.options.map(o => `
                        <li class="ranking-item">
                            <span class="drag-handle">&#9776;</span>
                            <span class="option-label">${escapeHtml(o.label)}</span>
                        </li>
                    `).join('')}
                </ol>
            `;

        case 'star':
            return `
                <div class="star-options">
                    ${question.options.map(o => `
                        <div class="star-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <div class="star-rating">
                                ${'<span class="star">&#9733;</span>'.repeat(5)}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;

        case 'grade':
            return `
                <div class="grade-options">
                    ${question.options.map(o => `
                        <div class="grade-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <select class="grade-select" disabled>
                                <option>Select...</option>
                                <option>Excellent</option>
                                <option>Very Good</option>
                                <option>Good</option>
                                <option>Fair</option>
                                <option>Poor</option>
                                <option>Reject</option>
                            </select>
                        </div>
                    `).join('')}
                </div>
            `;

        case 'yes_no_abstain':
            return `
                <div class="yna-options">
                    ${question.options.map(o => `
                        <div class="yna-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <div class="yna-buttons">
                                <button type="button" class="yna-btn">Yes</button>
                                <button type="button" class="yna-btn">No</button>
                                <button type="button" class="yna-btn">Abstain</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;

        default:
            return '<p>Unknown question type</p>';
    }
}

function prepareData() {
    return {
        title: state.title,
        description: state.description,
        visibility: state.settings.visibility,
        visibility_timing: state.settings.visibilityTiming,
        collect_name: state.settings.collectName,
        allow_edit_own: state.settings.allowEditOwn,
        allow_edit_any: state.settings.allowEditAny,
        randomize_options: state.settings.randomizeOptions,
        questions: state.questions.map((q, index) => ({
            id: q.id, // Server ID if exists
            type: q.type,
            text: q.text,
            description: q.description,
            required: q.required,
            sort_order: index,
            options: q.options.map((o, oIndex) => ({
                id: o.id, // Server ID if exists
                label: o.label,
                sort_order: oIndex,
            })),
        })),
    };
}
