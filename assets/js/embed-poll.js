/**
 * Embed Poll JavaScript
 *
 * Simplified version of poll.js for embedded polls.
 * Renders poll questions and handles form submission via the embed API.
 */

import { renderQuestion } from './question-renderer.js';

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
    initDistribution();
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const publicId = window.POLL_DATA?.public_id;
        const embedToken = window.EMBED_TOKEN;
        const formData = collectFormData();

        const validationError = validateForm(formData);
        if (validationError) {
            showMessage(validationError, 'error');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }

            // Use embed API endpoint
            const response = await fetch(`${window.BASE_PATH}/api/embed/${publicId}/${embedToken}/responses`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Failed to submit response');
            }

            // Show thank you message
            showThankYou(result);
        } catch (err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = t('submit_vote');
            }
            showMessage(err.message, 'error');
        }
    });
}

function showThankYou(result) {
    const container = document.querySelector('.poll-container');
    const customMessage = result.thank_you_message;
    const siteUrl = result.site_url || window.SITE_URL;
    const resultsUrl = result.results_url || window.RESULTS_URL;

    let messageContent;
    if (customMessage) {
        messageContent = `<div class="thank-you-custom markdown">${customMessage}</div>`;
    } else {
        messageContent = `
            <h2>${t('thank_you')}</h2>
            <p>${t('response_recorded')}</p>
        `;
    }

    let actionsHtml = '';
    if (resultsUrl) {
        actionsHtml += `<a href="${resultsUrl}" target="_blank" class="btn btn-primary">${t('view_results')}</a>`;
    }
    actionsHtml += `<a href="${siteUrl}" target="_blank" class="site-link">Visit Pref.Tools Vote</a>`;

    container.innerHTML = `
        <div class="container">
            <div class="card" style="text-align: center;">
                ${messageContent}
                <div class="thank-you-actions" style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem; align-items: center;">
                    ${actionsHtml}
                </div>
            </div>
        </div>
    `;
}

function showMessage(message, type) {
    // Simple toast-like message for embeds
    const existing = document.querySelector('.embed-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `embed-toast embed-toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        background: ${type === 'error' ? '#ef4444' : '#22c55e'};
        color: white;
        font-size: 0.875rem;
        z-index: 1000;
    `;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 5000);
}

function t(key, params = {}) {
    let text = window.TRANSLATIONS?.[key] || key;
    for (const [name, value] of Object.entries(params)) {
        text = text.replace(`:${name}`, String(value));
    }
    return text;
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

            case 'distribution':
                const distInput = block.querySelector('.distribution-value');
                if (distInput?.value) {
                    answer = JSON.parse(distInput.value);
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
            return t('required_question_error') || 'Please answer all required questions';
        }
    }

    // Validate approval min constraints
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

    return null;
}

// ==========================================================================
// Interactive Question Types
// ==========================================================================

function initSingleChoice() {
    document.querySelectorAll('.radio-options').forEach(container => {
        const radios = container.querySelectorAll('input[type="radio"]');

        radios.forEach(radio => {
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

function initApproval() {
    document.querySelectorAll('.checkbox-options, .pb-options').forEach(container => {
        const block = container.closest('.question-display');
        if (!block || (block.dataset.type !== 'approval' && block.dataset.type !== 'participatory_budgeting')) return;

        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const questionId = block.dataset.questionId;

        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const min = question?.settings?.min ?? 0;
        const max = question?.settings?.max ?? null;

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

            updateDisabledState();
        }
    });
}

function initOtherOptions() {
    document.querySelectorAll('.radio-option-other, .checkbox-option-other').forEach(label => {
        const input = label.querySelector('input[type="radio"], input[type="checkbox"]');
        const textInput = label.querySelector('.other-text-input');

        if (!input || !textInput) return;

        textInput.addEventListener('focus', () => {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        const updateTextInputState = () => {
            textInput.disabled = !input.checked;
            if (input.checked) {
                textInput.focus();
            }
        };

        input.addEventListener('change', updateTextInputState);
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

        updateValue();

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

function initTruncatedRankings() {
    const ITEM_HEIGHT = 42;

    document.querySelectorAll('.ranking-truncated-options').forEach(container => {
        const questionId = container.dataset.questionId;
        const availableList = container.querySelector('.ranking-available-list');
        const rankedList = container.querySelector('.ranking-ranked-list');
        const input = container.querySelector('.ranking-value');
        const placeholder = container.querySelector('.ranking-drop-placeholder');
        const hintEl = container.querySelector('.ranking-truncated-hint');

        const question = window.POLL_DATA?.questions?.find(q => String(q.id) === questionId);
        const totalOptions = question?.options?.length || 0;
        const min = question?.settings?.min ?? 0;
        const maxSetting = question?.settings?.max;
        const effectiveMax = maxSetting ?? totalOptions;

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

        const updateMinHeights = () => {
            const rankedCount = rankedList.querySelectorAll('.ranking-truncated-item').length;
            const availableCount = availableList.querySelectorAll('.ranking-truncated-item').length;
            const rankedSlots = Math.min(rankedCount + 1, effectiveMax);
            rankedList.style.minHeight = `${rankedSlots * ITEM_HEIGHT}px`;
            const availableSlots = Math.min(availableCount + 1, totalOptions);
            availableList.style.minHeight = `${availableSlots * ITEM_HEIGHT}px`;
        };

        const updateValue = () => {
            const order = Array.from(rankedList.querySelectorAll('.ranking-truncated-item'))
                .map(item => parseInt(item.dataset.optionId));
            input.value = order.length > 0 ? JSON.stringify(order) : '';
            placeholder.style.display = order.length > 0 ? 'none' : '';
            updateMinHeights();
        };

        updateValue();

        const groupName = `ranking-truncated-${questionId}`;

        new Sortable(availableList, {
            group: groupName,
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: updateValue
        });

        new Sortable(rankedList, {
            group: {
                name: groupName,
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

function initRankingWithTies() {
    document.querySelectorAll('.ranking-ties-options').forEach(container => {
        const rankingContainer = container.querySelector('.ranking-ties-container');
        const input = container.querySelector('.ranking-value');
        const items = container.querySelectorAll('.ranking-ties-item');

        function createEmptyIndifferenceClass() {
            const emptyClass = document.createElement('div');
            emptyClass.classList.add('indifference-class');
            return emptyClass;
        }

        function removeEmptyIndifferenceClasses() {
            const emptyClasses = [...rankingContainer.querySelectorAll('.indifference-class')].filter(ic => {
                return ic.querySelectorAll('.ranking-ties-item').length === 0;
            });
            emptyClasses.forEach(ic => ic.remove());
        }

        function updateRankLabels() {
            const classes = rankingContainer.querySelectorAll('.indifference-class:has(.ranking-ties-item)');
            classes.forEach((ic, index) => {
                const rank = index + 1;
                const suffix = rank === 1 ? 'st' : rank === 2 ? 'nd' : rank === 3 ? 'rd' : 'th';
                ic.dataset.rank = `${rank}${suffix}`;
            });
        }

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

        function getItemAtY(indifferenceClass, y) {
            const classItems = [...indifferenceClass.querySelectorAll('.ranking-ties-item:not(.dragging)')];
            let nextItem = classItems.find(item => {
                const rect = item.getBoundingClientRect();
                return y <= rect.top + rect.height / 2;
            });
            return nextItem;
        }

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

        rankingContainer.addEventListener('dragover', (e) => {
            const item = rankingContainer.querySelector('.dragging');
            if (!item) return;

            if (!item.closest('.ranking-ties-container') || item.closest('.ranking-ties-container') !== rankingContainer) {
                return;
            }
            e.preventDefault();

            const indifferenceClass = getIndifferenceClassAtY(e.clientY);
            if (!indifferenceClass) return;

            const indifferenceClassEmpty = indifferenceClass.querySelectorAll('.ranking-ties-item').length === 0;
            const stayedInSameClass = indifferenceClass === item.parentElement;
            const justStartedDragging = item.dataset.justStartedDragging === 'true';
            item.dataset.justStartedDragging = '';

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

            if (stayedInSameClass && !justStartedDragging) {
                return;
            }

            removeEmptyIndifferenceClasses();

            if (indifferenceClass.querySelectorAll('.ranking-ties-item').length >= 2) {
                const aboveEmpty = createEmptyIndifferenceClass();
                rankingContainer.insertBefore(aboveEmpty, indifferenceClass);
                const belowEmpty = createEmptyIndifferenceClass();
                rankingContainer.insertBefore(belowEmpty, indifferenceClass.nextElementSibling);
            }

            updateRankLabels();
        });

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
                    if (ratings[optionId] === value) {
                        delete ratings[optionId];
                    } else {
                        ratings[optionId] = value;
                    }
                    updateVisualState();
                    input.value = JSON.stringify(ratings);
                });

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
                stars.forEach(s => {
                    s.style.color = '';
                });
            });
        });
    });
}

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

function initDistribution() {
    document.querySelectorAll('.distribution-options').forEach(container => {
        const budget = parseInt(container.dataset.budget) || 100;
        const maxPerOption = parseInt(container.dataset.maxPerOption) || budget;
        const input = container.querySelector('.distribution-value');
        const remainingDisplay = container.querySelector('.budget-remaining');
        const values = {};

        container.querySelectorAll('.distribution-row').forEach(row => {
            const optionId = row.dataset.optionId;
            values[optionId] = 0;
        });

        const getTotalUsed = () => Object.values(values).reduce((sum, v) => sum + v, 0);
        const getRemaining = () => budget - getTotalUsed();

        const updateDisplay = () => {
            const remaining = getRemaining();
            remainingDisplay.textContent = remaining;
            remainingDisplay.classList.toggle('budget-empty', remaining === 0);
            remainingDisplay.classList.toggle('budget-over', remaining < 0);

            container.querySelectorAll('.distribution-row').forEach(row => {
                const optionId = row.dataset.optionId;
                const value = values[optionId] || 0;
                const bar = row.querySelector('.distribution-bar');
                const distInput = row.querySelector('.dist-input');

                const percentage = budget > 0 ? (value / budget) * 100 : 0;
                if (bar) bar.style.width = `${percentage}%`;

                distInput.value = value;
                row.classList.toggle('has-points', value > 0);
                distInput.classList.toggle('has-value', value > 0);

                row.querySelectorAll('.dist-btn').forEach(btn => {
                    const step = parseInt(btn.dataset.step);
                    if (step > 0) {
                        const newValue = value + step;
                        const wouldExceedBudget = newValue > value + remaining;
                        const wouldExceedMax = newValue > maxPerOption;
                        btn.disabled = wouldExceedBudget || wouldExceedMax;
                    } else {
                        btn.disabled = value + step < 0;
                    }
                });
            });

            input.value = JSON.stringify(values);
        };

        const setValue = (optionId, newValue) => {
            newValue = Math.max(0, Math.min(newValue, maxPerOption));
            const currentValue = values[optionId] || 0;
            const delta = newValue - currentValue;
            if (delta > 0 && delta > getRemaining()) {
                newValue = currentValue + getRemaining();
            }
            values[optionId] = newValue;
            updateDisplay();
        };

        container.querySelectorAll('.distribution-row').forEach(row => {
            const optionId = row.dataset.optionId;
            const distInput = row.querySelector('.dist-input');
            const buttons = row.querySelectorAll('.dist-btn');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const step = parseInt(btn.dataset.step);
                    const currentValue = values[optionId] || 0;
                    setValue(optionId, currentValue + step);
                });
            });

            distInput.addEventListener('input', (e) => {
                const newValue = parseInt(e.target.value) || 0;
                setValue(optionId, newValue);
            });

            distInput.addEventListener('blur', (e) => {
                e.target.value = values[optionId] || 0;
            });
        });

        updateDisplay();
    });
}
