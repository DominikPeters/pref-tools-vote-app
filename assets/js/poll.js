/**
 * Voter Form JavaScript
 */

import { api, showToast } from './app.js';

document.addEventListener('DOMContentLoaded', () => {
    initForm();
    initRankings();
    initStarRatings();
    initYnaButtons();

    // Pre-fill form if editing existing response
    if (window.EXISTING_RESPONSE) {
        prefillForm(window.EXISTING_RESPONSE);
    } else {
        // Try to restore from localStorage (partial progress)
        restoreProgress();
    }

    // Set up progress saving
    initProgressSaving();
});

function initForm() {
    const form = document.getElementById('pollForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const publicId = form.dataset.publicId;
        const formData = collectFormData();

        // Validate
        const validationError = validateForm(formData);
        if (validationError) {
            showToast(validationError, 'error');
            return;
        }

        try {
            const isEditing = form.dataset.editing === 'true';
            const result = await api.post(`/api/polls/${publicId}/responses`, formData);

            // Clear any saved progress
            clearProgress();

            showToast(isEditing ? 'Response updated!' : 'Vote submitted successfully!', 'success');

            // Show confirmation or redirect
            setTimeout(() => {
                const vote = document.querySelector('.poll-container');
                vote.innerHTML = `
                    <div class="container">
                        <div class="card" style="text-align: center;">
                            <h2>Thank you!</h2>
                            <p>Your response has been ${isEditing ? 'updated' : 'recorded'}.</p>
                            <p style="color: var(--color-text-muted);">
                                You can close this page or <a href="javascript:location.reload()">edit your response</a>.
                            </p>
                        </div>
                    </div>
                `;
            }, 1000);
        } catch (err) {
            showToast(err.message, 'error');
        }
    });
}

function collectFormData() {
    const form = document.getElementById('pollForm');
    const data = {
        answers: {},
    };

    // Voter name
    const nameInput = document.getElementById('voterName');
    if (nameInput) {
        data.voter_name = nameInput.value;
    }

    // Collect answers for each question
    document.querySelectorAll('.question-block').forEach(block => {
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
                answer = selected ? parseInt(selected.value) : null;
                break;

            case 'approval':
                const checked = block.querySelectorAll('input[type="checkbox"]:checked');
                answer = Array.from(checked).map(c => parseInt(c.value));
                break;

            case 'ranking':
                const rankingInput = block.querySelector('.ranking-value');
                if (rankingInput?.value) {
                    answer = JSON.parse(rankingInput.value);
                }
                break;

            case 'star':
                const starInput = block.querySelector('.star-value');
                if (starInput?.value) {
                    answer = JSON.parse(starInput.value);
                }
                break;

            case 'grade':
                answer = {};
                block.querySelectorAll('.grade-select').forEach(select => {
                    const optionId = select.name.match(/\[(\d+)\]/)?.[1];
                    if (optionId && select.value) {
                        answer[optionId] = select.value;
                    }
                });
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
    // Check required questions
    const requiredQuestions = document.querySelectorAll('.question-block .required-marker');

    for (const marker of requiredQuestions) {
        const block = marker.closest('.question-block');
        const questionId = block.dataset.questionId;

        const answer = data.answers[questionId];

        if (answer === null || answer === undefined || answer === '' ||
            (Array.isArray(answer) && answer.length === 0) ||
            (typeof answer === 'object' && !Array.isArray(answer) && Object.keys(answer).length === 0)) {
            return 'Please answer all required questions';
        }
    }

    return null;
}

function initRankings() {
    document.querySelectorAll('.ranking-options').forEach(container => {
        const list = container.querySelector('.ranking-list');
        const input = container.querySelector('.ranking-value');

        // Update hidden input
        const updateValue = () => {
            const order = Array.from(list.querySelectorAll('.ranking-item'))
                .map(item => parseInt(item.dataset.optionId));
            input.value = JSON.stringify(order);
        };

        // Initialize value
        updateValue();

        // Make sortable
        let dragging = null;

        list.querySelectorAll('.ranking-item').forEach(item => {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', () => {
                dragging = item;
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                updateValue();
            });
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);

            if (afterElement) {
                list.insertBefore(dragging, afterElement);
            } else {
                list.appendChild(dragging);
            }
        });
    });
}

function getDragAfterElement(container, y) {
    const elements = [...container.querySelectorAll('.ranking-item:not(.dragging)')];

    return elements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function initStarRatings() {
    document.querySelectorAll('.star-options').forEach(container => {
        const input = container.querySelector('.star-value');
        const ratings = {};

        container.querySelectorAll('.star-rating').forEach(ratingDiv => {
            const optionId = ratingDiv.dataset.optionId;
            const stars = ratingDiv.querySelectorAll('.star');

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    ratings[optionId] = value;

                    // Update visual state
                    stars.forEach(s => {
                        const sValue = parseInt(s.dataset.value);
                        s.classList.toggle('active', sValue <= value);
                    });

                    // Update hidden input
                    input.value = JSON.stringify(ratings);
                });

                // Hover effect
                star.addEventListener('mouseenter', () => {
                    const value = parseInt(star.dataset.value);
                    stars.forEach(s => {
                        const sValue = parseInt(s.dataset.value);
                        s.style.color = sValue <= value ? 'var(--color-warning)' : '';
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
                    values[optionId] = value;

                    // Update visual state
                    buttons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Update hidden input
                    input.value = JSON.stringify(values);
                });
            });
        });
    });
}

function getProgressKey() {
    const form = document.getElementById('pollForm');
    if (!form) return null;
    return `poll_progress_${form.dataset.publicId}`;
}

function initProgressSaving() {
    const form = document.getElementById('pollForm');
    if (!form) return;

    // Don't save progress if we're editing an existing response
    if (form.dataset.editing === 'true') return;

    // Save progress on any input change
    const saveProgress = () => {
        const data = collectFormData();
        const key = getProgressKey();
        if (key) {
            localStorage.setItem(key, JSON.stringify(data));
        }
    };

    // Listen for changes
    form.addEventListener('change', saveProgress);
    form.addEventListener('input', saveProgress);

    // Also save on custom events (for drag-and-drop, stars, etc.)
    document.querySelectorAll('.ranking-list').forEach(list => {
        const observer = new MutationObserver(saveProgress);
        observer.observe(list, { childList: true });
    });
}

function restoreProgress() {
    const key = getProgressKey();
    if (!key) return;

    const saved = localStorage.getItem(key);
    if (!saved) return;

    try {
        const data = JSON.parse(saved);
        // Convert to response format for prefillForm
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

function prefillForm(response) {
    const answers = response.answers || {};

    // Pre-fill voter name
    const nameInput = document.getElementById('voterName');
    if (nameInput && response.voter_name) {
        nameInput.value = response.voter_name;
    }

    // Pre-fill each question
    document.querySelectorAll('.question-block').forEach(block => {
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
                        // Reorder items
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

                        // Update hidden input
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
                    Object.entries(answer).forEach(([optionId, grade]) => {
                        const select = block.querySelector(`select[name*="[${optionId}]"]`);
                        if (select) select.value = grade;
                    });
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
