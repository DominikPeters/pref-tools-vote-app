/**
 * Voter Form JavaScript
 *
 * Renders poll questions using the shared question renderer and handles
 * form submission, validation, and progress saving.
 */

import { api, showToast, showConfirmModal, setButtonLoading, clearButtonLoading, escapeHtml } from './app.js';
import { renderQuestion } from './question-renderer.js';
import { initReportButton } from './report.js';

// ==========================================================================
// Initialization
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Render questions if poll data is available
    if (window.POLL_DATA) {
        renderPoll(window.POLL_DATA);
    }

    initForm();
    initSingleChoice();
    initApproval();
    initOtherOptions();
    initRankings();
    initTruncatedRankings();
    initRankingWithTies();
    initStarRatings();
    initGradeButtons();
    initYnaButtons();
    initReportButton(window.POLL_DATA?.public_id);

    // Pre-fill form if editing existing response
    if (window.EXISTING_RESPONSE) {
        prefillForm(window.EXISTING_RESPONSE);
    } else {
        restoreProgress();
    }

    initProgressSaving();
});

/**
 * Render the entire poll using JS
 */
function renderPoll(poll) {
    const container = document.getElementById('questionsContainer');
    if (!container) return;

    let html = '';
    let questionNumber = 0;

    poll.questions.forEach((question) => {
        // Section headers don't get numbered
        if (question.type !== 'section_header') {
            questionNumber++;
        }
        html += renderQuestion(question, {
            disabled: false,
            showNumbers: question.type !== 'section_header',
            questionNumber: questionNumber
        });
    });

    container.innerHTML = html;
}

// ==========================================================================
// Form Handling
// ==========================================================================

function initForm() {
    const form = document.getElementById('pollForm');
    if (!form) return;

    // Don't allow submission in preview mode
    if (window.IS_PREVIEW) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const publicId = form.dataset.publicId;
        const formData = collectFormData();

        const validationError = validateForm(formData);
        if (validationError) {
            showToast(validationError, 'error');
            return;
        }

        // Show confirmation for secret ballot
        if (window.POLL_DATA?.voting_mode === 'secret_ballot') {
            const confirmed = await showConfirmModal({
                title: 'Submit Secret Ballot',
                message: 'This is a secret ballot. Once submitted, you cannot view or change your vote. Are you sure you want to submit?',
                confirmText: 'Submit Vote',
            });
            if (!confirmed) return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');

        try {
            if (submitBtn) setButtonLoading(submitBtn);

            const isEditing = form.dataset.editing === 'true';
            
            // Include token from URL if present
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            const apiUrl = `/api/polls/${publicId}/responses` + (token ? `?token=${encodeURIComponent(token)}` : '');
            
            await api.post(apiUrl, formData);

            clearProgress();

            // Show thank you message immediately (no toast needed)
            const container = document.querySelector('.poll-container');
            const canEdit = window.POLL_DATA?.allow_edit_own || window.POLL_DATA?.allow_edit_any;
            const canViewResults = window.POLL_DATA?.results_viewable;
            const customMessage = window.POLL_DATA?.thank_you_message_html;

            let actionsHtml = '';
            if (canEdit) {
                actionsHtml += `<button type="button" class="btn btn-secondary" onclick="location.reload()">Edit Your Response</button>`;
            }
            if (canViewResults) {
                actionsHtml += `<a href="${window.BASE_PATH}/${publicId}/results" class="btn btn-primary">View Results</a>`;
            }

            // Use custom message if set, otherwise default message
            let messageContent;
            if (customMessage) {
                messageContent = `<div class="thank-you-custom markdown">${customMessage}</div>`;
            } else {
                messageContent = `
                    <h2>Thank you!</h2>
                    <p>Your response has been ${isEditing ? 'updated' : 'recorded'}.</p>
                `;
            }

            container.innerHTML = `
                <div class="container">
                    <div class="card" style="text-align: center;">
                        ${messageContent}
                        ${actionsHtml ? `
                            <div class="thank-you-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                ${actionsHtml}
                            </div>
                        ` : `
                            <p style="color: var(--color-text-muted); margin-top: 1rem;">
                                You can now close this page.
                            </p>
                        `}
                    </div>
                </div>
            `;
        } catch (err) {
            if (submitBtn) clearButtonLoading(submitBtn);
            showToast(err.message, 'error');
        }
    });
}

function collectFormData() {
    const form = document.getElementById('pollForm');
    const data = { answers: {} };

    // Voter name
    const nameInput = document.getElementById('voterName');
    if (nameInput) {
        data.voter_name = nameInput.value;
    }

    // Collect answers for each question
    document.querySelectorAll('.question-display').forEach(block => {
        const questionId = block.dataset.questionId;
        const type = block.dataset.type;

        let answer = null;

        switch (type) {
            case 'text_single':
            case 'text_multi':
                const textInput = block.querySelector('input, textarea');
                answer = textInput?.value || null;
                break;

            case 'single_choice':
                const selected = block.querySelector('input[type="radio"]:checked');
                if (selected) {
                    if (selected.value === '__other__') {
                        // Get the text value for "Other" option
                        const otherText = block.querySelector('.other-text-input')?.value?.trim();
                        answer = otherText ? { other: otherText } : null;
                    } else {
                        answer = parseInt(selected.value);
                    }
                }
                break;

            case 'approval':
                const apChecked = block.querySelectorAll('input[type="checkbox"]:checked');
                const apAnswers = [];
                apChecked.forEach(c => {
                    if (c.value === '__other__') {
                        // Get the text value for "Other" option
                        const otherText = block.querySelector('.other-text-input')?.value?.trim();
                        if (otherText) {
                            apAnswers.push({ other: otherText });
                        }
                    } else {
                        apAnswers.push(parseInt(c.value));
                    }
                });
                answer = apAnswers;
                break;

            case 'participatory_budgeting':
                const checked = block.querySelectorAll('input[type="checkbox"]:checked');
                answer = Array.from(checked).map(c => parseInt(c.value));
                break;

            case 'ranking':
            case 'ranking_truncated':
                const rankingInput = block.querySelector('.ranking-value');
                if (rankingInput?.value) {
                    answer = JSON.parse(rankingInput.value);
                }
                break;

            case 'ranking_with_ties':
                const tiesInput = block.querySelector('.ranking-value');
                if (tiesInput?.value) {
                    answer = JSON.parse(tiesInput.value);
                }
                break;

            case 'star':
                const starInput = block.querySelector('.star-value');
                if (starInput?.value) {
                    answer = JSON.parse(starInput.value);
                }
                break;

            case 'grade':
                // Check if using button mode or select mode
                const gradeInput = block.querySelector('.grade-value');
                if (gradeInput?.value) {
                    answer = JSON.parse(gradeInput.value);
                } else {
                    answer = {};
                    block.querySelectorAll('.grade-select').forEach(select => {
                        const optionId = select.name.match(/\[(\d+)\]/)?.[1];
                        if (optionId && select.value) {
                            answer[optionId] = select.value;
                        }
                    });
                }
                break;

            case 'yes_no_abstain':
                const ynaInput = block.querySelector('.yna-value');
                if (ynaInput?.value) {
                    answer = JSON.parse(ynaInput.value);
                }
                break;
        }

        if (answer !== null) {
            data.answers[questionId] = answer;
        }
    });

    return data;
}

function validateForm(data) {
    const requiredQuestions = document.querySelectorAll('.question-display .required-marker');

    for (const marker of requiredQuestions) {
        const block = marker.closest('.question-display');
        const questionId = block.dataset.questionId;
        const answer = data.answers[questionId];

        if (answer === null || answer === undefined || answer === '' ||
            (Array.isArray(answer) && answer.length === 0) ||
            (typeof answer === 'object' && !Array.isArray(answer) && Object.keys(answer).length === 0)) {
            return 'Please answer all required questions';
        }
    }

    // Validate approval and participatory_budgeting min constraints
    const approvalQuestions = document.querySelectorAll('.question-display[data-type="approval"], .question-display[data-type="participatory_budgeting"]');
    for (const block of approvalQuestions) {
        const questionId = block.dataset.questionId;
        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const min = question?.settings?.min ?? 0;

        if (min > 0) {
            const answer = data.answers[questionId];
            const count = Array.isArray(answer) ? answer.length : 0;
            if (count < min) {
                return `Please select at least ${min} option(s) for "${question.text}"`;
            }
        }
    }

    // Validate ranking_truncated min/max constraints
    const truncatedQuestions = document.querySelectorAll('.question-display[data-type="ranking_truncated"]');
    for (const block of truncatedQuestions) {
        const questionId = block.dataset.questionId;
        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const min = question?.settings?.min ?? 0;
        const max = question?.settings?.max ?? null;

        const answer = data.answers[questionId];
        const count = Array.isArray(answer) ? answer.length : 0;

        if (min > 0 && count < min) {
            return `Please rank at least ${min} option(s) for "${question.text}"`;
        }
        if (max !== null && count > max) {
            return `Please rank at most ${max} option(s) for "${question.text}"`;
        }
    }

    return null;
}

// ==========================================================================
// Interactive Question Types
// ==========================================================================

/**
 * Allow clicking on a selected radio to deselect it
 */
function initSingleChoice() {
    document.querySelectorAll('.radio-options').forEach(container => {
        const radios = container.querySelectorAll('input[type="radio"]');

        radios.forEach(radio => {
            // Track whether this radio was already checked before click
            radio.addEventListener('mousedown', () => {
                radio.dataset.wasChecked = radio.checked;
            });

            radio.addEventListener('click', () => {
                if (radio.dataset.wasChecked === 'true') {
                    radio.checked = false;
                }
            });
        });
    });
}

/**
 * Handle approval and participatory_budgeting (checkbox) min/max constraints
 */
function initApproval() {
    document.querySelectorAll('.checkbox-options, .pb-options').forEach(container => {
        const block = container.closest('.question-display');
        if (!block || (block.dataset.type !== 'approval' && block.dataset.type !== 'participatory_budgeting')) return;

        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const questionId = block.dataset.questionId;

        // Get settings from poll data
        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const min = question?.settings?.min ?? 0;
        const max = question?.settings?.max ?? null;

        // Add constraint hint if there are constraints
        if (min > 0 || max !== null) {
            const hint = document.createElement('p');
            hint.className = 'approval-hint';
            let hintText = '';
            if (min > 0 && max !== null) {
                hintText = min === max ? `Select exactly ${min}` : `Select ${min} to ${max}`;
            } else if (min > 0) {
                hintText = `Select at least ${min}`;
            } else if (max !== null) {
                hintText = `Select up to ${max}`;
            }
            hint.textContent = hintText;
            container.parentNode.insertBefore(hint, container);
        }

        // Handle max constraint
        if (max !== null) {
            const updateDisabledState = () => {
                const checkedCount = container.querySelectorAll('input[type="checkbox"]:checked').length;
                checkboxes.forEach(cb => {
                    if (!cb.checked) {
                        cb.disabled = checkedCount >= max;
                    }
                });
            };

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateDisabledState);
            });

            // Initial state
            updateDisabledState();
        }
    });
}

/**
 * Handle "Other" option text inputs for single-choice and approval questions
 * - Auto-select the radio/checkbox when typing in the text input
 * - Enable text input only when the radio/checkbox is selected
 */
function initOtherOptions() {
    document.querySelectorAll('.radio-option-other, .checkbox-option-other').forEach(label => {
        const input = label.querySelector('input[type="radio"], input[type="checkbox"]');
        const textInput = label.querySelector('.other-text-input');

        if (!input || !textInput) return;

        // Auto-select when typing in text input
        textInput.addEventListener('focus', () => {
            input.checked = true;
            // Trigger change event for approval max constraint updates
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Update text input state based on radio/checkbox
        const updateTextInputState = () => {
            textInput.disabled = !input.checked;
            if (input.checked) {
                textInput.focus();
            }
        };

        input.addEventListener('change', updateTextInputState);

        // Initial state
        textInput.disabled = !input.checked;
    });
}

function initRankings() {
    document.querySelectorAll('.ranking-options').forEach(container => {
        const list = container.querySelector('.ranking-list');
        const input = container.querySelector('.ranking-value');

        const updateValue = () => {
            const order = Array.from(list.querySelectorAll('.ranking-item'))
                .map(item => parseInt(item.dataset.optionId));
            input.value = JSON.stringify(order);
        };

        // Initialize value
        updateValue();

        // Use SortableJS for rankings
        new Sortable(list, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: () => {
                updateValue();
            }
        });
    });
}

/**
 * Initialize truncated rankings with linked SortableJS lists
 * Users can drag options from "available" to "ranked" and vice versa
 */
function initTruncatedRankings() {
    const ITEM_HEIGHT = 42; // Approximate height of item + gap

    document.querySelectorAll('.ranking-truncated-options').forEach(container => {
        const questionId = container.dataset.questionId;
        const availableList = container.querySelector('.ranking-available-list');
        const rankedList = container.querySelector('.ranking-ranked-list');
        const input = container.querySelector('.ranking-value');
        const placeholder = container.querySelector('.ranking-drop-placeholder');
        const hintEl = container.querySelector('.ranking-truncated-hint');

        // Get settings from poll data
        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const totalOptions = question?.options?.length || 0;
        const min = question?.settings?.min ?? 0;
        const maxSetting = question?.settings?.max; // null means no limit
        const effectiveMax = maxSetting ?? totalOptions;

        // Show constraint hint
        const hasMax = maxSetting !== null && maxSetting !== undefined;
        if (min > 0 || hasMax) {
            let hintText = '';
            if (min > 0 && hasMax) {
                hintText = min === maxSetting ? `Rank exactly ${min}` : `Rank ${min} to ${maxSetting} options`;
            } else if (min > 0) {
                hintText = `Rank at least ${min} options`;
            } else if (hasMax) {
                hintText = `Rank up to ${maxSetting} options`;
            }
            hintEl.textContent = hintText;
            hintEl.style.display = '';
        } else {
            hintEl.textContent = 'Drag options to rank them (top = best)';
            hintEl.style.display = '';
        }

        // Update list min-heights to always show space for one more item (up to limit)
        const updateMinHeights = () => {
            const rankedCount = rankedList.querySelectorAll('.ranking-truncated-item').length;
            const availableCount = availableList.querySelectorAll('.ranking-truncated-item').length;

            // Ranked list: show space for current items + 1, up to max
            const rankedSlots = Math.min(rankedCount + 1, effectiveMax);
            rankedList.style.minHeight = `${rankedSlots * ITEM_HEIGHT}px`;

            // Available list: show space for current items + 1, up to total options
            const availableSlots = Math.min(availableCount + 1, totalOptions);
            availableList.style.minHeight = `${availableSlots * ITEM_HEIGHT}px`;
        };

        const updateValue = () => {
            const order = Array.from(rankedList.querySelectorAll('.ranking-truncated-item'))
                .map(item => parseInt(item.dataset.optionId));
            input.value = order.length > 0 ? JSON.stringify(order) : '';

            // Toggle placeholder visibility
            placeholder.style.display = order.length > 0 ? 'none' : '';

            // Update min-heights after changes
            updateMinHeights();
        };

        // Initialize value and min-heights
        updateValue();

        // Shared group name for linked sorting
        const groupName = `ranking-truncated-${questionId}`;

        // Available list - can pull from ranked, puts into ranked
        new Sortable(availableList, {
            group: groupName,
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: updateValue
        });

        // Ranked list - can pull from available, enforces max via group.put
        new Sortable(rankedList, {
            group: {
                name: groupName,
                // Control whether items can be added to this list
                put: function() {
                    const currentCount = rankedList.querySelectorAll('.ranking-truncated-item').length;
                    return currentCount < effectiveMax;
                }
            },
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: updateValue
        });
    });
}

/**
 * Initialize ranking with ties (weak orders) using native drag and drop API
 * Fully vertical layout: indifference classes stack vertically, items within classes stack vertically
 */
function initRankingWithTies() {
    document.querySelectorAll('.ranking-ties-options').forEach(container => {
        const rankingContainer = container.querySelector('.ranking-ties-container');
        const input = container.querySelector('.ranking-value');
        const items = container.querySelectorAll('.ranking-ties-item');

        // Helper: Create an empty indifference class
        function createEmptyIndifferenceClass() {
            const emptyClass = document.createElement('div');
            emptyClass.classList.add('indifference-class');
            return emptyClass;
        }

        // Helper: Remove all empty indifference classes
        function removeEmptyIndifferenceClasses() {
            const emptyClasses = [...rankingContainer.querySelectorAll('.indifference-class')].filter(ic => {
                return ic.querySelectorAll('.ranking-ties-item').length === 0;
            });
            emptyClasses.forEach(ic => ic.remove());
        }

        // Helper: Update rank labels on all indifference classes
        function updateRankLabels() {
            const classes = rankingContainer.querySelectorAll('.indifference-class:has(.ranking-ties-item)');
            classes.forEach((ic, index) => {
                const rank = index + 1;
                const suffix = rank === 1 ? 'st' : rank === 2 ? 'nd' : rank === 3 ? 'rd' : 'th';
                ic.dataset.rank = `${rank}${suffix}`;
            });
        }

        // Helper: Update hidden input value
        function updateValue() {
            const result = {};
            const classes = rankingContainer.querySelectorAll('.indifference-class');
            let rank = 0;
            classes.forEach(ic => {
                const classItems = ic.querySelectorAll('.ranking-ties-item');
                if (classItems.length > 0) {
                    rank++;
                    classItems.forEach(item => {
                        result[item.dataset.optionId] = rank;
                    });
                }
            });
            input.value = JSON.stringify(result);
            updateRankLabels();
        }

        // Helper: Get indifference class at Y position
        function getIndifferenceClassAtY(y) {
            const classes = [...rankingContainer.querySelectorAll('.indifference-class')];
            let targetClass = classes.find(ic => {
                const rect = ic.getBoundingClientRect();
                return y <= rect.top + rect.height;
            });
            if (!targetClass) {
                targetClass = classes[classes.length - 1];
            }
            return targetClass;
        }

        // Helper: Get item at Y position within an indifference class
        function getItemAtY(indifferenceClass, y) {
            const classItems = [...indifferenceClass.querySelectorAll('.ranking-ties-item:not(.dragging)')];
            let nextItem = classItems.find(item => {
                const rect = item.getBoundingClientRect();
                return y <= rect.top + rect.height / 2;
            });
            return nextItem;
        }

        // Set up drag events on each item
        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                item.classList.add('dragging');
                item.dataset.justStartedDragging = 'true';
                e.dataTransfer.setData('text/plain', item.dataset.optionId);
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', () => {
                removeEmptyIndifferenceClasses();
                item.classList.remove('dragging');
                updateValue();
            });
        });

        // Set up dragover on the container
        rankingContainer.addEventListener('dragover', (e) => {
            const item = rankingContainer.querySelector('.dragging');
            if (!item) return;

            // Check if item originates from this container
            if (!item.closest('.ranking-ties-container') || item.closest('.ranking-ties-container') !== rankingContainer) {
                return;
            }
            e.preventDefault();

            // Find target indifference class
            const indifferenceClass = getIndifferenceClassAtY(e.clientY);
            if (!indifferenceClass) return;

            // Is the target class currently empty?
            const indifferenceClassEmpty = indifferenceClass.querySelectorAll('.ranking-ties-item').length === 0;

            // Check if we stayed in the same class
            const stayedInSameClass = indifferenceClass === item.parentElement;
            const justStartedDragging = item.dataset.justStartedDragging === 'true';
            item.dataset.justStartedDragging = '';

            // Place item within the target class
            const afterElement = getItemAtY(indifferenceClass, e.clientY);
            const shouldAnimate = !stayedInSameClass && !indifferenceClassEmpty;
            const animationOptions = { duration: 70, easing: 'ease-in-out' };

            if (afterElement == null) {
                if (shouldAnimate) {
                    item.animate([{ marginTop: 'var(--indifference-class-gap)' }, { marginTop: '0' }], animationOptions);
                }
                indifferenceClass.appendChild(item);
            } else {
                if (shouldAnimate) {
                    item.animate([{ marginBottom: 'var(--indifference-class-gap)' }, { marginBottom: '0' }], animationOptions);
                }
                indifferenceClass.insertBefore(item, afterElement);
            }

            // Skip unnecessary updates
            if (stayedInSameClass && !justStartedDragging) {
                return;
            }

            // Delete empty indifference classes
            removeEmptyIndifferenceClasses();

            // Create empty drop targets above and below when class has 2+ items
            if (indifferenceClass.querySelectorAll('.ranking-ties-item').length >= 2) {
                // Above
                const aboveEmpty = createEmptyIndifferenceClass();
                rankingContainer.insertBefore(aboveEmpty, indifferenceClass);
                // Below
                const belowEmpty = createEmptyIndifferenceClass();
                rankingContainer.insertBefore(belowEmpty, indifferenceClass.nextElementSibling);
            }

            updateRankLabels();
        });

        // Initialize value and rank labels
        updateValue();
    });
}

function initStarRatings() {
    document.querySelectorAll('.star-options').forEach(container => {
        const input = container.querySelector('.star-value');
        const ratings = {};

        container.querySelectorAll('.star-rating').forEach(ratingDiv => {
            const optionId = ratingDiv.dataset.optionId;
            const stars = ratingDiv.querySelectorAll('.star');

            const updateVisualState = () => {
                const currentRating = ratings[optionId] || 0;
                stars.forEach(s => {
                    const sValue = parseInt(s.dataset.value);
                    s.classList.toggle('active', sValue <= currentRating);
                });
            };

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    // Click on same value clears the rating
                    if (ratings[optionId] === value) {
                        delete ratings[optionId];
                    } else {
                        ratings[optionId] = value;
                    }

                    updateVisualState();
                    input.value = JSON.stringify(ratings);
                });

                // Hover effect - always show preview regardless of current selection
                star.addEventListener('mouseenter', () => {
                    const hoverValue = parseInt(star.dataset.value);
                    stars.forEach(s => {
                        const sValue = parseInt(s.dataset.value);
                        if (sValue <= hoverValue) {
                            s.style.color = 'var(--color-warning)';
                        } else {
                            s.style.color = 'var(--color-border)';
                        }
                    });
                });
            });

            ratingDiv.addEventListener('mouseleave', () => {
                // Restore to actual state
                stars.forEach(s => {
                    s.style.color = '';
                });
            });
        });
    });
}

/**
 * Handle grade buttons (for ≤3 grades displayed as buttons)
 */
function initGradeButtons() {
    document.querySelectorAll('.grade-buttons-mode').forEach(container => {
        const input = container.querySelector('.grade-value');
        const values = {};

        container.querySelectorAll('.grade-buttons').forEach(buttonsDiv => {
            const optionId = buttonsDiv.dataset.optionId;
            const buttons = buttonsDiv.querySelectorAll('.grade-btn');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const value = btn.dataset.value;

                    // Click on same value clears the selection
                    if (values[optionId] === value) {
                        delete values[optionId];
                        buttons.forEach(b => b.classList.remove('active'));
                    } else {
                        values[optionId] = value;
                        buttons.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                    }

                    input.value = JSON.stringify(values);
                });
            });
        });
    });
}

function initYnaButtons() {
    document.querySelectorAll('.yna-options').forEach(container => {
        const input = container.querySelector('.yna-value');
        const values = {};

        container.querySelectorAll('.yna-buttons').forEach(buttonsDiv => {
            const optionId = buttonsDiv.dataset.optionId;
            const buttons = buttonsDiv.querySelectorAll('.yna-btn');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const value = btn.dataset.value;

                    // Click on same value clears the selection
                    if (values[optionId] === value) {
                        delete values[optionId];
                        buttons.forEach(b => b.classList.remove('active'));
                    } else {
                        values[optionId] = value;
                        // Update visual state
                        buttons.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                    }

                    input.value = JSON.stringify(values);
                });
            });
        });
    });
}

// ==========================================================================
// Progress Saving
// ==========================================================================

function getProgressKey() {
    const form = document.getElementById('pollForm');
    if (!form) return null;
    return `poll_progress_${form.dataset.publicId}`;
}

function initProgressSaving() {
    const form = document.getElementById('pollForm');
    if (!form) return;

    // Don't save progress if editing existing response
    if (form.dataset.editing === 'true') return;

    const saveProgress = () => {
        const data = collectFormData();
        const key = getProgressKey();
        if (key) {
            localStorage.setItem(key, JSON.stringify(data));
        }
    };

    form.addEventListener('change', saveProgress);
    form.addEventListener('input', saveProgress);

    // Watch for ranking changes
    document.querySelectorAll('.ranking-list').forEach(list => {
        const observer = new MutationObserver(saveProgress);
        observer.observe(list, { childList: true });
    });

    // Watch for truncated ranking changes (both lists)
    document.querySelectorAll('.ranking-available-list, .ranking-ranked-list').forEach(list => {
        const observer = new MutationObserver(saveProgress);
        observer.observe(list, { childList: true });
    });

    // Watch for ranking with ties changes (observe all indifference classes and container)
    document.querySelectorAll('.ranking-ties-container').forEach(container => {
        const observer = new MutationObserver(saveProgress);
        observer.observe(container, { childList: true, subtree: true });
    });
}

function restoreProgress() {
    const key = getProgressKey();
    if (!key) return;

    const saved = localStorage.getItem(key);
    if (!saved) return;

    try {
        const data = JSON.parse(saved);
        prefillForm({
            voter_name: data.voter_name,
            answers: data.answers,
        });
    } catch (e) {
        console.error('Failed to restore progress:', e);
    }
}

function clearProgress() {
    const key = getProgressKey();
    if (key) {
        localStorage.removeItem(key);
    }
}

// ==========================================================================
// Form Prefilling
// ==========================================================================

function prefillForm(response) {
    const answers = response.answers || {};

    // Pre-fill voter name
    const nameInput = document.getElementById('voterName');
    if (nameInput && response.voter_name) {
        nameInput.value = response.voter_name;
    }

    // Pre-fill each question
    document.querySelectorAll('.question-display').forEach(block => {
        const questionId = block.dataset.questionId;
        const type = block.dataset.type;
        const answer = answers[questionId];

        if (answer === undefined || answer === null) return;

        switch (type) {
            case 'text_single':
            case 'text_multi':
                const textInput = block.querySelector('input, textarea');
                if (textInput) textInput.value = answer;
                break;

            case 'single_choice':
                const radio = block.querySelector(`input[type="radio"][value="${answer}"]`);
                if (radio) radio.checked = true;
                break;

            case 'approval':
            case 'participatory_budgeting':
                if (Array.isArray(answer)) {
                    answer.forEach(id => {
                        const checkbox = block.querySelector(`input[type="checkbox"][value="${id}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
                break;

            case 'ranking':
                if (Array.isArray(answer)) {
                    const list = block.querySelector('.ranking-list');
                    const input = block.querySelector('.ranking-value');
                    if (list && input) {
                        const items = Array.from(list.querySelectorAll('.ranking-item'));
                        const itemMap = {};
                        items.forEach(item => {
                            itemMap[item.dataset.optionId] = item;
                        });

                        list.innerHTML = '';
                        answer.forEach(id => {
                            if (itemMap[id]) {
                                list.appendChild(itemMap[id]);
                            }
                        });

                        input.value = JSON.stringify(answer);
                    }
                }
                break;

            case 'ranking_truncated':
                if (Array.isArray(answer)) {
                    const availableList = block.querySelector('.ranking-available-list');
                    const rankedList = block.querySelector('.ranking-ranked-list');
                    const input = block.querySelector('.ranking-value');
                    const placeholder = block.querySelector('.ranking-drop-placeholder');

                    if (availableList && rankedList && input) {
                        // Gather all items from both lists
                        const allItems = [
                            ...Array.from(availableList.querySelectorAll('.ranking-truncated-item')),
                            ...Array.from(rankedList.querySelectorAll('.ranking-truncated-item'))
                        ];
                        const itemMap = {};
                        allItems.forEach(item => {
                            itemMap[item.dataset.optionId] = item;
                        });

                        // Clear both lists
                        availableList.innerHTML = '';
                        rankedList.innerHTML = '';

                        // Move answered items to ranked list in order
                        const rankedIds = new Set();
                        answer.forEach(id => {
                            if (itemMap[id]) {
                                rankedList.appendChild(itemMap[id]);
                                rankedIds.add(String(id));
                            }
                        });

                        // Move remaining items to available list
                        allItems.forEach(item => {
                            if (!rankedIds.has(item.dataset.optionId)) {
                                availableList.appendChild(item);
                            }
                        });

                        input.value = JSON.stringify(answer);

                        // Toggle placeholder visibility
                        if (placeholder) {
                            placeholder.style.display = answer.length > 0 ? 'none' : '';
                        }
                    }
                }
                break;

            case 'ranking_with_ties':
                if (typeof answer === 'object' && !Array.isArray(answer)) {
                    const container = block.querySelector('.ranking-ties-container');
                    const input = block.querySelector('.ranking-value');

                    if (container && input) {
                        // Get all items
                        const allItems = Array.from(container.querySelectorAll('.ranking-ties-item'));
                        const itemMap = {};
                        allItems.forEach(item => {
                            itemMap[item.dataset.optionId] = item;
                        });

                        // Group items by rank
                        const rankGroups = {};
                        Object.entries(answer).forEach(([optionId, rank]) => {
                            if (!rankGroups[rank]) {
                                rankGroups[rank] = [];
                            }
                            if (itemMap[optionId]) {
                                rankGroups[rank].push(itemMap[optionId]);
                            }
                        });

                        // Sort ranks and rebuild container
                        const sortedRanks = Object.keys(rankGroups).map(Number).sort((a, b) => a - b);

                        // Clear container
                        container.innerHTML = '';

                        // Create indifference classes for each rank group
                        sortedRanks.forEach((rank, index) => {
                            const indifferenceClass = document.createElement('div');
                            indifferenceClass.classList.add('indifference-class');
                            const suffix = rank === 1 ? 'st' : rank === 2 ? 'nd' : rank === 3 ? 'rd' : 'th';
                            indifferenceClass.dataset.rank = `${index + 1}${suffix}`;

                            rankGroups[rank].forEach(item => {
                                indifferenceClass.appendChild(item);
                            });

                            container.appendChild(indifferenceClass);
                        });

                        input.value = JSON.stringify(answer);
                    }
                }
                break;

            case 'star':
                if (typeof answer === 'object') {
                    const input = block.querySelector('.star-value');
                    Object.entries(answer).forEach(([optionId, rating]) => {
                        const ratingDiv = block.querySelector(`.star-rating[data-option-id="${optionId}"]`);
                        if (ratingDiv) {
                            const stars = ratingDiv.querySelectorAll('.star');
                            stars.forEach(star => {
                                const value = parseInt(star.dataset.value);
                                star.classList.toggle('active', value <= rating);
                            });
                        }
                    });
                    if (input) input.value = JSON.stringify(answer);
                }
                break;

            case 'grade':
                if (typeof answer === 'object') {
                    // Check if using button mode or select mode
                    const gradeInput = block.querySelector('.grade-value');
                    if (gradeInput) {
                        // Button mode
                        Object.entries(answer).forEach(([optionId, grade]) => {
                            const buttonsDiv = block.querySelector(`.grade-buttons[data-option-id="${optionId}"]`);
                            if (buttonsDiv) {
                                const btn = buttonsDiv.querySelector(`.grade-btn[data-value="${grade}"]`);
                                if (btn) btn.classList.add('active');
                            }
                        });
                        gradeInput.value = JSON.stringify(answer);
                    } else {
                        // Select mode
                        Object.entries(answer).forEach(([optionId, grade]) => {
                            const select = block.querySelector(`select[name*="[${optionId}]"]`);
                            if (select) select.value = grade;
                        });
                    }
                }
                break;

            case 'yes_no_abstain':
                if (typeof answer === 'object') {
                    const input = block.querySelector('.yna-value');
                    Object.entries(answer).forEach(([optionId, value]) => {
                        const buttonsDiv = block.querySelector(`.yna-buttons[data-option-id="${optionId}"]`);
                        if (buttonsDiv) {
                            const btn = buttonsDiv.querySelector(`.yna-btn[data-value="${value}"]`);
                            if (btn) btn.classList.add('active');
                        }
                    });
                    if (input) input.value = JSON.stringify(answer);
                }
                break;
        }
    });
}

