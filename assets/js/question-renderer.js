/**
 * Shared Question Renderer
 *
 * Renders question display markup used by both the builder (unfocused state)
 * and the voter form. This ensures visual consistency between what the poll
 * creator sees while building and what voters see.
 */

import { t } from './i18n.js';

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
        const headerText = question.text || t('section');
        const descriptionHtml = renderDescription(question);
        return `
            <div class="section-header" data-question-id="${question.id || question._id}" data-type="${question.type}">
                <div class="section-header-text">${escapeHtml(headerText)}</div>
                ${descriptionHtml ? `<div class="section-header-description${question.description_html ? ' markdown' : ''}">${descriptionHtml}</div>` : ''}
            </div>
        `;
    }

    const numberPrefix = showNumbers ? `${questionNumber}. ` : '';
    const questionText = question.text || t('untitled_question');
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
            return `<input type="text" class="form-control" ${disabledAttr} placeholder="${t('short_answer')}">`;

        case 'text_multi':
            return `<textarea class="form-control" rows="3" ${disabledAttr} placeholder="${t('long_answer')}"></textarea>`;

        case 'single_choice':
            // Filter out user-added options (they're created by voters, not shown as predefined)
            const scOptions = options.filter(o => !o.features?.isUserAdded);
            const scAllowOther = question.settings?.allowOther ?? false;
            return `
                <div class="radio-options">
                    ${scOptions.map(o => `
                        <label class="radio-option">
                            <input type="radio" name="q_${question.id || question._id}" value="${o.id || o._id}" ${disabledAttr}>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                    ${scAllowOther ? `
                        <label class="radio-option radio-option-other">
                            <input type="radio" name="q_${question.id || question._id}" value="__other__" ${disabledAttr} data-is-other="true">
                            <span>${t('other_option')}</span>
                            <input type="text" class="other-text-input" ${disabledAttr} placeholder="${t('please_specify')}">
                        </label>
                    ` : ''}
                </div>
            `;

        case 'approval':
            // Filter out user-added options (they're created by voters, not shown as predefined)
            const apOptions = options.filter(o => !o.features?.isUserAdded);
            const apAllowOther = question.settings?.allowOther ?? false;
            return `
                <div class="checkbox-options">
                    ${apOptions.map(o => `
                        <label class="checkbox-option">
                            <input type="checkbox" name="q_${question.id || question._id}[]" value="${o.id || o._id}" ${disabledAttr}>
                            <span>${escapeHtml(o.label)}</span>
                        </label>
                    `).join('')}
                    ${apAllowOther ? `
                        <label class="checkbox-option checkbox-option-other">
                            <input type="checkbox" name="q_${question.id || question._id}[]" value="__other__" ${disabledAttr} data-is-other="true">
                            <span>${t('other_option')}</span>
                            <input type="text" class="other-text-input" ${disabledAttr} placeholder="${t('please_specify')}">
                        </label>
                    ` : ''}
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
                    <p class="ranking-hint">${t('ranking_hint')}</p>
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
                            <div class="ranking-zone-header">${t('available_options')}</div>
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
                            <div class="ranking-zone-header">${t('your_ranking')}</div>
                            <ol class="ranking-ranked-list">
                            </ol>
                            <div class="ranking-drop-placeholder">${t('drag_to_rank')}</div>
                        </div>
                    </div>
                    <input type="hidden" class="ranking-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'ranking_with_ties':
            return `
                <div class="ranking-ties-options" data-question-id="${question.id || question._id}">
                    <p class="ranking-hint">${t('ranking_ties_hint')}</p>
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
            // Fallback grades if question.settings.grades is missing (rare edge case)
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
                                <option value="">${t('select_placeholder')}</option>
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
                                <button type="button" class="yna-btn yes" data-value="yes" ${disabledAttr}>${t('yes')}</button>
                                <button type="button" class="yna-btn no" data-value="no" ${disabledAttr}>${t('no')}</button>
                                ${allowAbstain ? `<button type="button" class="yna-btn abstain" data-value="abstain" ${disabledAttr}>${t('abstain')}</button>` : ''}
                            </div>
                        </div>
                    `).join('')}
                    <input type="hidden" class="yna-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'distribution':
            const budget = question.settings?.budget ?? 100;
            const maxPerOption = question.settings?.maxPerOption ?? budget;
            const effectiveMax = Math.min(maxPerOption, budget);
            // Determine stepper increments based on budget
            let bigStep = 1;
            if (budget >= 50) {
                bigStep = 10;
            } else if (budget >= 25) {
                bigStep = 5;
            }
            const showBigSteps = bigStep > 1;

            return `
                <div class="distribution-options" data-budget="${budget}" data-max-per-option="${effectiveMax}">
                    <div class="distribution-budget-display">
                        <span class="budget-label">${t('remaining')}</span>
                        <span class="budget-remaining">${budget}</span>
                        <span class="budget-separator">/</span>
                        <span class="budget-total">${budget}</span>
                        <span class="budget-unit">${t('points')}</span>
                    </div>
                    ${options.map(o => `
                        <div class="distribution-row" data-option-id="${o.id || o._id}">
                            <span class="option-label">${escapeHtml(o.label)}</span>
                            <div class="distribution-controls">
                                ${showBigSteps ? `<button type="button" class="dist-btn dist-minus-big" data-step="-${bigStep}" ${disabledAttr}>-${bigStep}</button>` : ''}
                                <button type="button" class="dist-btn dist-minus" data-step="-1" ${disabledAttr}>-1</button>
                                <input type="number" class="dist-input" value="0" min="0" max="${effectiveMax}" ${disabledAttr}>
                                <button type="button" class="dist-btn dist-plus" data-step="1" ${disabledAttr}>+1</button>
                                ${showBigSteps ? `<button type="button" class="dist-btn dist-plus-big" data-step="${bigStep}" ${disabledAttr}>+${bigStep}</button>` : ''}
                            </div>
                            <div class="distribution-bar-container">
                                <div class="distribution-bar" style="width: 0%"></div>
                            </div>
                        </div>
                    `).join('')}
                    <input type="hidden" class="distribution-value" name="q_${question.id || question._id}">
                </div>
            `;

        case 'section_header':
            return ''; // No input for section headers

        default:
            return `<p class="text-muted">${t('unknown_question_type', { type: question.type })}</p>`;
    }
}

/**
 * Get human-readable label for a question type
 */
export function getQuestionTypeLabel(type) {
    const labels = {
        'single_choice': t('type_single_choice'),
        'approval': t('type_approval'),
        'participatory_budgeting': t('type_participatory_budgeting'),
        'distribution': t('type_distribution'),
        'ranking': t('type_ranking'),
        'ranking_truncated': t('type_ranking_truncated'),
        'ranking_with_ties': t('type_ranking_with_ties'),
        'star': t('type_star'),
        'grade': t('type_grade'),
        'yes_no_abstain': t('type_yes_no_abstain'),
        'text_single': t('type_text_single'),
        'text_multi': t('type_text_multi'),
    };
    return labels[type] || type;
}

/**
 * Question types that require options
 */
export const OPTION_TYPES = [
    'single_choice', 'approval', 'participatory_budgeting', 'distribution', 'ranking', 'ranking_truncated', 'ranking_with_ties', 'star', 'grade', 'yes_no_abstain'
];

/**
 * All available question types
 */
export const QUESTION_TYPES = [
    { value: 'single_choice', label: t('type_single_choice') },
    { value: 'approval', label: t('type_approval') },
    { value: 'participatory_budgeting', label: t('type_participatory_budgeting') },
    { value: 'distribution', label: t('type_distribution') },
    { value: 'ranking', label: t('type_ranking') },
    { value: 'ranking_truncated', label: t('type_ranking_truncated') },
    { value: 'ranking_with_ties', label: t('type_ranking_with_ties') },
    { value: 'star', label: t('type_star') },
    { value: 'grade', label: t('type_grade') },
    { value: 'yes_no_abstain', label: t('type_yes_no_abstain') },
    { value: 'text_single', label: t('type_text_single') },
    { value: 'text_multi', label: t('type_text_multi') },
    { value: 'section_header', label: t('type_section_header') },
];


/**
 * Drag-and-drop polyfill for older browsers
 */
let DragDropTouch;
(function (DragDropTouch_1) {
    'use strict';
    /**
     * Object used to hold the data that is being dragged during drag and drop operations.
     *
     * It may hold one or more data items of different types. For more information about
     * drag and drop operations and data transfer objects, see
     * <a href="https://developer.mozilla.org/en-US/docs/Web/API/DataTransfer">HTML Drag and Drop API</a>.
     *
     * This object is created automatically by the @see:DragDropTouch singleton and is
     * accessible through the @see:dataTransfer property of all drag events.
     */
    let DataTransfer = (function () {
        function DataTransfer() {
            this._dropEffect = 'move';
            this._effectAllowed = 'all';
            this._data = {};
        }
        Object.defineProperty(DataTransfer.prototype, "dropEffect", {
            /**
             * Gets or sets the type of drag-and-drop operation currently selected.
             * The value must be 'none',  'copy',  'link', or 'move'.
             */
            get: function () {
                return this._dropEffect;
            },
            set: function (value) {
                this._dropEffect = value;
            },
            enumerable: true,
            configurable: true
        });
        Object.defineProperty(DataTransfer.prototype, "effectAllowed", {
            /**
             * Gets or sets the types of operations that are possible.
             * Must be one of 'none', 'copy', 'copyLink', 'copyMove', 'link',
             * 'linkMove', 'move', 'all' or 'uninitialized'.
             */
            get: function () {
                return this._effectAllowed;
            },
            set: function (value) {
                this._effectAllowed = value;
            },
            enumerable: true,
            configurable: true
        });
        Object.defineProperty(DataTransfer.prototype, "types", {
            /**
             * Gets an array of strings giving the formats that were set in the @see:dragstart event.
             */
            get: function () {
                return Object.keys(this._data);
            },
            enumerable: true,
            configurable: true
        });
        /**
         * Removes the data associated with a given type.
         *
         * The type argument is optional. If the type is empty or not specified, the data
         * associated with all types is removed. If data for the specified type does not exist,
         * or the data transfer contains no data, this method will have no effect.
         *
         * @param type Type of data to remove.
         */
        DataTransfer.prototype.clearData = function (type) {
            if (type !== null) {
                delete this._data[type.toLowerCase()];
            }
            else {
                this._data = {};
            }
        };
        /**
         * Retrieves the data for a given type, or an empty string if data for that type does
         * not exist or the data transfer contains no data.
         *
         * @param type Type of data to retrieve.
         */
        DataTransfer.prototype.getData = function (type) {
            let lcType = type.toLowerCase(),
                data = this._data[lcType];
            if (lcType === "text" && data == null) {
                data = this._data["text/plain"]; // getData("text") also gets ("text/plain")
            }
            return data || "";
        };
        /**
         * Set the data for a given type.
         *
         * For a list of recommended drag types, please see
         * https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Recommended_Drag_Types.
         *
         * @param type Type of data to add.
         * @param value Data to add.
         */
        DataTransfer.prototype.setData = function (type, value) {
            this._data[type.toLowerCase()] = value;
        };
        /**
         * Set the image to be used for dragging if a custom one is desired.
         *
         * @param img An image element to use as the drag feedback image.
         * @param offsetX The horizontal offset within the image.
         * @param offsetY The vertical offset within the image.
         */
        DataTransfer.prototype.setDragImage = function (img, offsetX, offsetY) {
            let ddt = DragDropTouch._instance;
            ddt._imgCustom = img;
            ddt._imgOffset = { x: offsetX, y: offsetY };
        };
        return DataTransfer;
    }());
    DragDropTouch_1.DataTransfer = DataTransfer;
    /**
     * Defines a class that adds support for touch-based HTML5 drag/drop operations.
     *
     * The @see:DragDropTouch class listens to touch events and raises the
     * appropriate HTML5 drag/drop events as if the events had been caused
     * by mouse actions.
     *
     * The purpose of this class is to enable using existing, standard HTML5
     * drag/drop code on mobile devices running IOS or Android.
     *
     * To use, include the DragDropTouch.js file on the page. The class will
     * automatically start monitoring touch events and will raise the HTML5
     * drag drop events (dragstart, dragenter, dragleave, drop, dragend) which
     * should be handled by the application.
     *
     * For details and examples on HTML drag and drop, see
     * https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Drag_operations.
     */
    let DragDropTouch = (function () {
        /**
         * Initializes the single instance of the @see:DragDropTouch class.
         */
        function DragDropTouch() {
            this._lastClick = 0;
            // enforce singleton pattern
            if (DragDropTouch._instance) {
                throw 'DragDropTouch instance already created.';
            }
            // detect passive event support
            // https://github.com/Modernizr/Modernizr/issues/1894
            let supportsPassive = false;
            document.addEventListener('test', function () { }, {
                get passive() {
                    supportsPassive = true;
                    return true;
                }
            });
            // listen to touch events
            if (navigator.maxTouchPoints) {
                let d = document, 
                    ts = this._touchstart.bind(this), 
                    tm = this._touchmove.bind(this), 
                    te = this._touchend.bind(this), 
                    opt = supportsPassive ? { passive: false, capture: false } : false;
                d.addEventListener('touchstart', ts, opt);
                d.addEventListener('touchmove', tm, opt);
                d.addEventListener('touchend', te);
                d.addEventListener('touchcancel', te);
            }
        }
        /**
         * Gets a reference to the @see:DragDropTouch singleton.
         */
        DragDropTouch.getInstance = function () {
            return DragDropTouch._instance;
        };
        // ** event handlers
        DragDropTouch.prototype._touchstart = function (e) {
            let _this = this;
            if (this._shouldHandle(e)) {
                // clear all variables
                this._reset();
                // get nearest draggable element
                let src = this._closestDraggable(e.target);
                if (src) {
                    // give caller a chance to handle the hover/move events
                    if (!this._dispatchEvent(e, 'mousemove', e.target) &&
                        !this._dispatchEvent(e, 'mousedown', e.target)) {
                        // get ready to start dragging
                        this._dragSource = src;
                        this._ptDown = this._getPoint(e);
                        this._lastTouch = e;

                        // do not prevent default (so input elements keep working)
                        //e.preventDefault();

                        // show context menu if the user hasn't started dragging after a while
                        setTimeout(function () {
                            if (_this._dragSource === src && _this._img === null) {
                                if (_this._dispatchEvent(e, 'contextmenu', src)) {
                                    _this._reset();
                                }
                            }
                        }, DragDropTouch._CTXMENU);
                        if (DragDropTouch._ISPRESSHOLDMODE) {
                            this._pressHoldInterval = setTimeout(function () {
                                _this._isDragEnabled = true;
                                _this._touchmove(e);
                            }, DragDropTouch._PRESSHOLDAWAIT);
                        }
                    }
                }
            }
        };
        DragDropTouch.prototype._touchmove = function (e) {
            if (this._shouldCancelPressHoldMove(e)) {
              this._reset();
              return;
            }
            if (this._shouldHandleMove(e) || this._shouldHandlePressHoldMove(e)) {
                // see if target wants to handle move
                let target = this._getTarget(e);
                if (this._dispatchEvent(e, 'mousemove', target)) {
                    this._lastTouch = e;
                    e.preventDefault();
                    return;
                }
                // start dragging
                if (this._dragSource && !this._img && this._shouldStartDragging(e)) {
                    if (this._dispatchEvent(this._lastTouch, 'dragstart', this._dragSource)) {
                        // target canceled the drag event
                        this._dragSource = null;
                        return;
                    }
                    this._createImage(e);
                    this._dispatchEvent(e, 'dragenter', target);
                }
                // continue dragging
                if (this._img) {
                    this._lastTouch = e;
                    e.preventDefault(); // prevent scrolling
                    this._dispatchEvent(e, 'drag', this._dragSource);
                    if (target !== this._lastTarget) {
                        this._dispatchEvent(this._lastTouch, 'dragleave', this._lastTarget);
                        this._dispatchEvent(e, 'dragenter', target);
                        this._lastTarget = target;
                    }
                    this._moveImage(e);
                    this._isDropZone = this._dispatchEvent(e, 'dragover', target);
                }
            }
        };
        DragDropTouch.prototype._touchend = function (e) {
            if (this._shouldHandle(e)) {
                // see if target wants to handle up
                if (this._dispatchEvent(this._lastTouch, 'mouseup', e.target)) {
                    e.preventDefault();
                    return;
                }
                // user clicked the element but didn't drag, so clear the source and simulate a click
                if (!this._img) {
                    this._dragSource = null;
                    this._dispatchEvent(this._lastTouch, 'click', e.target);
                    this._lastClick = Date.now();
                }
                // finish dragging
                this._destroyImage();
                if (this._dragSource) {
                    if (e.type.indexOf('cancel') < 0 && this._isDropZone) {
                        this._dispatchEvent(this._lastTouch, 'drop', this._lastTarget);
                    }
                    this._dispatchEvent(this._lastTouch, 'dragend', this._dragSource);
                    this._reset();
                }
            }
        };
        // ** utilities
        // ignore events that have been handled or that involve more than one touch
        DragDropTouch.prototype._shouldHandle = function (e) {
            return e &&
                !e.defaultPrevented &&
                e.touches && e.touches.length < 2;
        };

        // use regular condition outside of press & hold mode
        DragDropTouch.prototype._shouldHandleMove = function (e) {
          return !DragDropTouch._ISPRESSHOLDMODE && this._shouldHandle(e);
        };

        // allow to handle moves that involve many touches for press & hold
        DragDropTouch.prototype._shouldHandlePressHoldMove = function (e) {
          return DragDropTouch._ISPRESSHOLDMODE &&
              this._isDragEnabled && e && e.touches && e.touches.length;
        };

        // reset data if user drags without pressing & holding
        DragDropTouch.prototype._shouldCancelPressHoldMove = function (e) {
          return DragDropTouch._ISPRESSHOLDMODE && !this._isDragEnabled &&
              this._getDelta(e) > DragDropTouch._PRESSHOLDMARGIN;
        };

        // start dragging when specified delta is detected
        DragDropTouch.prototype._shouldStartDragging = function (e) {
            let delta = this._getDelta(e);
            return delta > DragDropTouch._THRESHOLD ||
                (DragDropTouch._ISPRESSHOLDMODE && delta >= DragDropTouch._PRESSHOLDTHRESHOLD);
        }

        // clear all members
        DragDropTouch.prototype._reset = function () {
            this._destroyImage();
            this._dragSource = null;
            this._lastTouch = null;
            this._lastTarget = null;
            this._ptDown = null;
            this._isDragEnabled = false;
            this._isDropZone = false;
            this._dataTransfer = new DataTransfer();
            clearInterval(this._pressHoldInterval);
        };
        // get point for a touch event
        DragDropTouch.prototype._getPoint = function (e, page) {
            if (e && e.touches) {
                e = e.touches[0];
            }
            return { x: page ? e.pageX : e.clientX, y: page ? e.pageY : e.clientY };
        };
        // get distance between the current touch event and the first one
        DragDropTouch.prototype._getDelta = function (e) {
            if (DragDropTouch._ISPRESSHOLDMODE && !this._ptDown) { return 0; }
            let p = this._getPoint(e);
            return Math.abs(p.x - this._ptDown.x) + Math.abs(p.y - this._ptDown.y);
        };
        // get the element at a given touch event
        DragDropTouch.prototype._getTarget = function (e) {
            let pt = this._getPoint(e),
                el = document.elementFromPoint(pt.x, pt.y);
            while (el && getComputedStyle(el).pointerEvents == 'none') {
                el = el.parentElement;
            }
            return el;
        };
        // create drag image from source element
        DragDropTouch.prototype._createImage = function (e) {
            // just in case...
            if (this._img) {
                this._destroyImage();
            }
            // create drag image from custom element or drag source
            let src = this._imgCustom || this._dragSource;
            this._img = src.cloneNode(true);
            this._copyStyle(src, this._img);
            this._img.style.top = this._img.style.left = '-9999px';
            // if creating from drag source, apply offset and opacity
            if (!this._imgCustom) {
                let rc = src.getBoundingClientRect(),
                    pt = this._getPoint(e);
                this._imgOffset = { x: pt.x - rc.left, y: pt.y - rc.top };
                this._img.style.opacity = DragDropTouch._OPACITY.toString();
            }
            // add image to document
            this._moveImage(e);
            document.body.appendChild(this._img);
        };
        // dispose of drag image element
        DragDropTouch.prototype._destroyImage = function () {
            if (this._img && this._img.parentElement) {
                this._img.parentElement.removeChild(this._img);
            }
            this._img = null;
            this._imgCustom = null;
        };
        // move the drag image element
        DragDropTouch.prototype._moveImage = function (e) {
            let _this = this;
            requestAnimationFrame(function () {
                if (_this._img) {
                    let pt = _this._getPoint(e, true),
                        s = _this._img.style;
                    s.position = 'absolute';
                    s.pointerEvents = 'none';
                    s.zIndex = '999999';
                    s.left = Math.round(pt.x - _this._imgOffset.x) + 'px';
                    s.top = Math.round(pt.y - _this._imgOffset.y) + 'px';
                }
            });
        };
        // copy properties from an object to another
        DragDropTouch.prototype._copyProps = function (dst, src, props) {
            for (let i = 0; i < props.length; i++) {
                let p = props[i];
                dst[p] = src[p];
            }
        };
        DragDropTouch.prototype._copyStyle = function (src, dst) {
            // remove potentially troublesome attributes
            DragDropTouch._rmvAtts.forEach(function (att) {
                dst.removeAttribute(att);
            });
            // copy canvas content
            if (src instanceof HTMLCanvasElement) {
                let cSrc = src,
                    cDst = dst;
                cDst.width = cSrc.width;
                cDst.height = cSrc.height;
                cDst.getContext('2d').drawImage(cSrc, 0, 0);
            }
            // copy style (without transitions)
            let cs = getComputedStyle(src);
            for (let i = 0; i < cs.length; i++) {
                let key = cs[i];
                if (key.indexOf('transition') < 0) {
                    dst.style[key] = cs[key];
                }
            }
            dst.style.pointerEvents = 'none';
            // and repeat for all children
            for (let i = 0; i < src.children.length; i++) {
                this._copyStyle(src.children[i], dst.children[i]);
            }
        };
        // compute missing offset or layer property for an event
        DragDropTouch.prototype._setOffsetAndLayerProps = function (e, target) {
            let rect = undefined;
            if (e.offsetX === undefined) {
                rect = target.getBoundingClientRect();
                e.offsetX = e.clientX - rect.x;
                e.offsetY = e.clientY - rect.y;
            }
            if (e.layerX === undefined) {
                rect = rect || target.getBoundingClientRect();
                e.layerX = e.pageX - rect.left;
                e.layerY = e.pageY - rect.top;
            }
        }
        DragDropTouch.prototype._dispatchEvent = function (e, type, target) {
            if (e && target) {
                //let evt = document.createEvent('Event'), t = e.touches ? e.touches[0] : e; // deprecated
                //evt.initEvent(type, true, true); // deprecated
                let evt = new Event(type, { bubbles: true, cancelable: true }),
                    touch = e.touches ? e.touches[0] : e;
                evt.button = 0;
                evt.which = evt.buttons = 1;
                this._copyProps(evt, e, DragDropTouch._kbdProps);
                this._copyProps(evt, touch, DragDropTouch._ptProps);
                this._setOffsetAndLayerProps(evt, target);
                evt.dataTransfer = this._dataTransfer;
                target.dispatchEvent(evt);
                return evt.defaultPrevented;
            }
            return false;
        };
        // gets an element's closest draggable ancestor
        // <img> and <a> elements are draggable by default
        DragDropTouch.prototype._closestDraggable = function (e) {
            for (; e; e = e.parentElement) {
                if (/*e.hasAttribute('draggable') &&*/ e.draggable) {
                    return e;
                }
            }
            return null;
        };
        return DragDropTouch;
    }());
    /*private*/ DragDropTouch._instance = new DragDropTouch(); // singleton
    // constants
    DragDropTouch._THRESHOLD = 5; // pixels to move before drag starts
    DragDropTouch._OPACITY = 0.5; // drag image opacity
    DragDropTouch._DBLCLICK = 500; // max ms between clicks in a double click
    DragDropTouch._CTXMENU = 900; // ms to hold before raising 'contextmenu' event
    DragDropTouch._ISPRESSHOLDMODE = false; // decides of press & hold mode presence
    DragDropTouch._PRESSHOLDAWAIT = 400; // ms to wait before press & hold is detected
    DragDropTouch._PRESSHOLDMARGIN = 25; // pixels that finger might shiver while pressing
    DragDropTouch._PRESSHOLDTHRESHOLD = 0; // pixels to move before drag starts
    // copy styles/attributes from drag source to drag image element
    DragDropTouch._rmvAtts = 'id,class,style,draggable'.split(',');
    // synthesize and dispatch an event
    // returns true if the event has been handled (e.preventDefault == true)
    DragDropTouch._kbdProps = 'altKey,ctrlKey,metaKey,shiftKey'.split(',');
    DragDropTouch._ptProps = 'pageX,pageY,clientX,clientY,screenX,screenY,offsetX,offsetY'.split(',');
    DragDropTouch_1.DragDropTouch = DragDropTouch;
})(DragDropTouch || (DragDropTouch = {}));