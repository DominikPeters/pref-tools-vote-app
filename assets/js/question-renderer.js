/**
 * Shared Question Renderer
 *
 * Renders question display markup used by both the builder (unfocused state)
 * and the voter form. This ensures visual consistency between what the poll
 * creator sees while building and what voters see.
 */

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

/**
 * Render a complete question block
 *
 * @param {Object} question - Question data
 * @param {Object} options - Rendering options
 * @param {boolean} options.disabled - If true, inputs are disabled (for builder display)
 * @param {boolean} options.showNumbers - If true, show question numbers
 * @param {number} options.questionNumber - The question number to display
 * @returns {string} HTML string
 */
export function renderQuestion(question, options = {}) {
    const { disabled = false, showNumbers = false, questionNumber = 1 } = options;

    // Section headers have different rendering
    if (question.type === 'section_header') {
        const headerText = question.text || 'Section';
        const descriptionHtml = renderDescription(question);
        return `
            <div class="section-header" data-question-id="${question.id || question._id}" data-type="${question.type}">
                <div class="section-header-text">${escapeHtml(headerText)}</div>
                ${descriptionHtml ? `<div class="section-header-description${question.description_html ? ' markdown' : ''}">${descriptionHtml}</div>` : ''}
            </div>
        `;
    }

    const numberPrefix = showNumbers ? `${questionNumber}. ` : '';
    const questionText = question.text || 'Untitled Question';
    const requiredMarker = question.required ? '<span class="required-marker">*</span>' : '';
    const descriptionHtml = renderDescription(question);

    return `
        <div class="question-display" data-question-id="${question.id || question._id}" data-type="${question.type}">
            <div class="question-text">
                ${numberPrefix}${escapeHtml(questionText)} ${requiredMarker}
            </div>
            ${descriptionHtml ? `<div class="question-description${question.description_html ? ' markdown' : ''}">${descriptionHtml}</div>` : ''}
            <div class="question-input">
                ${renderQuestionInput(question, disabled)}
            </div>
        </div>
    `;
}

/**
 * Render question description, using pre-rendered HTML if available
 */
function renderDescription(question) {
    if (!question.description) {
        return '';
    }
    // Use pre-rendered markdown HTML from server if available
    if (question.description_html) {
        return question.description_html;
    }
    // Fallback to escaped text (for builder where we don't have pre-rendered HTML)
    return escapeHtml(question.description);
}

/**
 * Render just the input portion of a question (without the wrapper)
 *
 * @param {Object} question - Question data with type and options
 * @param {boolean} disabled - Whether inputs should be disabled
 * @returns {string} HTML string
 */
export function renderQuestionInput(question, disabled = false) {
    const disabledAttr = disabled ? 'disabled' : '';
    const options = question.options || [];

    switch (question.type) {
        case 'text_single':
            return `<input type="text" class="form-control" ${disabledAttr} placeholder="Short answer">`;

        case 'text_multi':
            return `<textarea class="form-control" rows="3" ${disabledAttr} placeholder="Long answer"></textarea>`;

        case 'single_choice':
            return `
                <div class="radio-options">
                    ${options.map(o => `
                        <label class="radio-option">
                            <input type="radio" name="q_${question.id || question._id}" value="${o.id || o._id}" ${disabledAttr}>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                </div>
            `;

        case 'approval':
            return `
                <div class="checkbox-options">
                    ${options.map(o => `
                        <label class="checkbox-option">
                            <input type="checkbox" name="q_${question.id || question._id}[]" value="${o.id || o._id}" ${disabledAttr}>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                </div>
            `;

        case 'participatory_budgeting':
            const currency = question.settings?.currency || '';
            return `
                <div class="pb-options">
                    ${options.map(o => {
                        const cost = o.features?.cost;
                        const costDisplay = cost != null ? `${escapeHtml(currency)}${cost}` : '';
                        // Use pre-rendered HTML if available, otherwise escape
                        const descHtml = o.description_html || (o.description ? escapeHtml(o.description) : '');
                        return `
                            <label class="pb-option-card">
                                <div class="pb-option-checkbox">
                                    <input type="checkbox" name="q_${question.id || question._id}[]" value="${o.id || o._id}" ${disabledAttr}>
                                </div>
                                <div class="pb-option-content">
                                    <div class="pb-option-header">
                                        <span class="pb-option-label">${escapeHtml(o.label)}</span>
                                        ${costDisplay ? `<span class="pb-option-cost">${costDisplay}</span>` : ''}
                                    </div>
                                    ${descHtml ? `<div class="pb-option-description markdown">${descHtml}</div>` : ''}
                                </div>
                            </label>
                        `;
                    }).join('')}
                </div>
            `;

        case 'ranking':
            return `
                <div class="ranking-options">
                    <p class="ranking-hint">Drag to reorder (top = best)</p>
                    <ol class="ranking-list">
                        ${options.map(o => `
                            <li class="ranking-item" data-option-id="${o.id || o._id}">
                                <span class="drag-handle">&#9776;</span>
                                <span class="option-label">${escapeHtml(o.label)}</span>
                            </li>
                        `).join('')}
                    </ol>
                    <input type="hidden" class="ranking-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'ranking_truncated':
            return `
                <div class="ranking-truncated-options" data-question-id="${question.id || question._id}">
                    <div class="ranking-truncated-hint"></div>
                    <div class="ranking-truncated-zones">
                        <div class="ranking-zone ranking-zone-available">
                            <div class="ranking-zone-header">Available options</div>
                            <ul class="ranking-available-list">
                                ${options.map(o => `
                                    <li class="ranking-truncated-item" data-option-id="${o.id || o._id}">
                                        <span class="drag-handle">&#9776;</span>
                                        <span class="option-label">${escapeHtml(o.label)}</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                        <div class="ranking-zone ranking-zone-ranked">
                            <div class="ranking-zone-header">Your ranking</div>
                            <ol class="ranking-ranked-list">
                            </ol>
                            <div class="ranking-drop-placeholder">Drag options here to rank them</div>
                        </div>
                    </div>
                    <input type="hidden" class="ranking-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'ranking_with_ties':
            return `
                <div class="ranking-ties-options" data-question-id="${question.id || question._id}">
                    <p class="ranking-hint">Drag to reorder. Items in the same group are tied.</p>
                    <div class="ranking-ties-container">
                        <div class="indifference-class">
                            ${options.map(o => `
                                <div class="ranking-ties-item" data-option-id="${o.id || o._id}" draggable="true">
                                    <span class="drag-handle">&#9776;</span>
                                    <span class="option-label">${escapeHtml(o.label)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <input type="hidden" class="ranking-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'star':
            const starCount = question.settings?.starCount || 5;
            return `
                <div class="star-options">
                    ${options.map(o => `
                        <div class="star-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <div class="star-rating" data-option-id="${o.id || o._id}">
                                ${Array.from({length: starCount}, (_, i) => i + 1).map(i => `
                                    <button type="button" class="star" data-value="${i}" ${disabledAttr}>&#9733;</button>
                                `).join('')}
                            </div>
                        </div>
                    `).join('')}
                    <input type="hidden" class="star-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'grade':
            const defaultGrades = ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Reject'];
            const grades = question.settings?.grades || defaultGrades;
            // Estimate button row width to decide between buttons vs dropdown
            const estimateButtonRowWidth = (gradeList) => {
                const BUTTON_FIXED = 33; // padding (16*2) + border (~1)
                const CHAR_WIDTH = 7;    // approximate px per character
                const GAP = 6;           // spacing-xs gap between buttons
                const buttonsWidth = gradeList.reduce((sum, g) => sum + BUTTON_FIXED + g.length * CHAR_WIDTH, 0);
                const gapsWidth = (gradeList.length - 1) * GAP;
                return buttonsWidth + gapsWidth;
            };
            const useButtons = estimateButtonRowWidth(grades) <= 350;

            if (useButtons) {
                return `
                    <div class="grade-options grade-buttons-mode">
                        ${options.map(o => `
                            <div class="grade-row">
                                <span class="option-label">${escapeHtml(o.label)}</span>
                                <div class="grade-buttons" data-option-id="${o.id || o._id}">
                                    ${grades.map(g => `
                                        <button type="button" class="grade-btn" data-value="${g.toLowerCase()}" ${disabledAttr}>${escapeHtml(g)}</button>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                        <input type="hidden" class="grade-value" name="q_${question.id || question._id}">
                    </div>
                `;
            }

            return `
                <div class="grade-options">
                    ${options.map(o => `
                        <div class="grade-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <select class="grade-select" name="q_${question.id || question._id}[${o.id || o._id}]" ${disabledAttr}>
                                <option value="">Select...</option>
                                ${grades.map(g => `<option value="${g.toLowerCase()}">${escapeHtml(g)}</option>`).join('')}
                            </select>
                        </div>
                    `).join('')}
                </div>
            `;

        case 'yes_no_abstain':
            const allowAbstain = question.settings?.allowAbstain !== false;
            return `
                <div class="yna-options">
                    ${options.map(o => `
                        <div class="yna-row">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <div class="yna-buttons" data-option-id="${o.id || o._id}">
                                <button type="button" class="yna-btn yes" data-value="yes" ${disabledAttr}>Yes</button>
                                <button type="button" class="yna-btn no" data-value="no" ${disabledAttr}>No</button>
                                ${allowAbstain ? `<button type="button" class="yna-btn abstain" data-value="abstain" ${disabledAttr}>Abstain</button>` : ''}
                            </div>
                        </div>
                    `).join('')}
                    <input type="hidden" class="yna-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'section_header':
            return ''; // No input for section headers

        default:
            return `<p class="text-muted">Unknown question type: ${escapeHtml(question.type)}</p>`;
    }
}

/**
 * Get human-readable label for a question type
 */
export function getQuestionTypeLabel(type) {
    const labels = {
        'single_choice': 'Single Choice',
        'approval': 'Approval (Multiple Choice)',
        'participatory_budgeting': 'Participatory Budgeting',
        'ranking': 'Ranking',
        'ranking_truncated': 'Ranking (Partial)',
        'ranking_with_ties': 'Ranking (With Ties)',
        'star': 'Star Rating',
        'grade': 'Grades',
        'yes_no_abstain': 'Yes / No / Abstain',
        'text_single': 'Short Text',
        'text_multi': 'Long Text',
    };
    return labels[type] || type;
}

/**
 * Question types that require options
 */
export const OPTION_TYPES = [
    'single_choice', 'approval', 'participatory_budgeting', 'ranking', 'ranking_truncated', 'ranking_with_ties', 'star', 'grade', 'yes_no_abstain'
];

/**
 * All available question types
 */
export const QUESTION_TYPES = [
    { value: 'single_choice', label: 'Single Choice' },
    { value: 'approval', label: 'Approval (Multiple Choice)' },
    { value: 'participatory_budgeting', label: 'Participatory Budgeting' },
    { value: 'ranking', label: 'Ranking' },
    { value: 'ranking_truncated', label: 'Ranking (Partial)' },
    { value: 'ranking_with_ties', label: 'Ranking (With Ties)' },
    { value: 'star', label: 'Star Rating' },
    { value: 'grade', label: 'Grades' },
    { value: 'yes_no_abstain', label: 'Yes / No / Abstain' },
    { value: 'text_single', label: 'Short Text' },
    { value: 'text_multi', label: 'Long Text' },
    { value: 'section_header', label: 'Section Header' },
];
