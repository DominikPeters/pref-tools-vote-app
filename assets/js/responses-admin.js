/**
 * Individual Responses Browser
 *
 * Allows poll admins to browse through individual responses one at a time,
 * with navigation controls and the ability to delete responses.
 */

import { api, showToast, showConfirmModal } from './app.js';
import { renderQuestion } from './question-renderer.js';

// ==========================================================================
// State
// ==========================================================================

let responses = [];
let currentIndex = 0;
let poll = null;

// ==========================================================================
// Initialization
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    poll = window.POLL_DATA;
    if (!poll) return;

    initNavigation();
    loadResponses();
});

/**
 * Set up navigation event handlers
 */
function initNavigation() {
    const prevBtn = document.getElementById('prevResponse');
    const nextBtn = document.getElementById('nextResponse');
    const indexInput = document.getElementById('responseIndex');
    const voterSelect = document.getElementById('voterSelect');
    const deleteBtn = document.getElementById('deleteResponse');

    prevBtn?.addEventListener('click', () => navigateTo(currentIndex - 1));
    nextBtn?.addEventListener('click', () => navigateTo(currentIndex + 1));

    indexInput?.addEventListener('change', (e) => {
        const newIndex = parseInt(e.target.value) - 1;
        if (newIndex >= 0 && newIndex < responses.length) {
            navigateTo(newIndex);
        } else {
            e.target.value = currentIndex + 1;
        }
    });

    indexInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.target.blur();
        }
    });

    voterSelect?.addEventListener('change', (e) => {
        const responseId = parseInt(e.target.value);
        if (responseId) {
            const index = responses.findIndex(r => r.id === responseId);
            if (index >= 0) {
                navigateTo(index);
            }
        }
    });

    deleteBtn?.addEventListener('click', deleteCurrentResponse);

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        // Don't interfere if user is typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;

        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            navigateTo(currentIndex - 1);
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            navigateTo(currentIndex + 1);
        }
    });
}

// ==========================================================================
// Data Loading
// ==========================================================================

/**
 * Load all responses from API
 */
async function loadResponses() {
    const container = document.querySelector('.responses-browser');
    container.classList.add('loading');
    container.classList.remove('empty');

    try {
        const publicId = poll.public_id;
        const adminToken = window.ADMIN_TOKEN;

        const result = await api.get(`/api/polls/${publicId}/responses?admin_token=${adminToken}`);
        responses = result.responses || [];

        if (responses.length === 0) {
            container.classList.remove('loading');
            container.classList.add('empty');
            return;
        }

        // Update total count
        document.getElementById('totalResponses').textContent = responses.length;
        document.getElementById('responseIndex').max = responses.length;

        // Populate voter dropdown if names are collected
        populateVoterDropdown();

        // Check for ?r=responseId query parameter
        const urlParams = new URLSearchParams(window.location.search);
        const requestedResponseId = urlParams.get('r');
        let initialIndex = 0;

        if (requestedResponseId) {
            const foundIndex = responses.findIndex(r => r.id === parseInt(requestedResponseId));
            if (foundIndex >= 0) {
                initialIndex = foundIndex;
            }
        }

        // Show the response (first or requested)
        container.classList.remove('loading');
        navigateTo(initialIndex);

    } catch (err) {
        container.classList.remove('loading');
        showToast('Failed to load responses: ' + err.message, 'error');
    }
}

/**
 * Populate the voter name dropdown
 */
function populateVoterDropdown() {
    const select = document.getElementById('voterSelect');
    if (!select) return;

    select.innerHTML = '<option value="">Select voter...</option>';

    responses.forEach((response, index) => {
        const option = document.createElement('option');
        option.value = response.id;
        option.textContent = response.voter_name || `Response ${index + 1}`;
        select.appendChild(option);
    });
}

// ==========================================================================
// Navigation
// ==========================================================================

/**
 * Navigate to a specific response by index
 */
function navigateTo(index) {
    if (index < 0 || index >= responses.length) return;

    currentIndex = index;
    const response = responses[currentIndex];

    // Update URL with response ID for bookmarking/sharing
    updateUrlWithResponseId(response.id);

    // Update navigation controls
    updateNavigationState();

    // Render the response
    renderResponse(response);
}

/**
 * Update the URL query parameter to reflect the current response
 */
function updateUrlWithResponseId(responseId) {
    const url = new URL(window.location.href);
    url.searchParams.set('r', responseId);
    window.history.replaceState({}, '', url.toString());
}

/**
 * Update navigation buttons and input state
 */
function updateNavigationState() {
    const prevBtn = document.getElementById('prevResponse');
    const nextBtn = document.getElementById('nextResponse');
    const indexInput = document.getElementById('responseIndex');
    const voterSelect = document.getElementById('voterSelect');
    const deleteBtn = document.getElementById('deleteResponse');

    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex >= responses.length - 1;
    indexInput.value = currentIndex + 1;
    deleteBtn.disabled = false;

    // Update dropdown selection
    if (voterSelect && responses[currentIndex]) {
        voterSelect.value = responses[currentIndex].id;
    }
}

// ==========================================================================
// Response Rendering
// ==========================================================================

/**
 * Render a single response
 */
function renderResponse(response) {
    renderResponseMeta(response);
    renderResponseForm(response);
}

/**
 * Render response metadata (name, timestamp)
 */
function renderResponseMeta(response) {
    const container = document.getElementById('responseMeta');

    let html = '';

    if (response.voter_name) {
        html += `<div class="response-meta-name">${escapeHtml(response.voter_name)}</div>`;
    }

    if (response.created_at) {
        const date = new Date(response.created_at);
        const formatted = date.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
        html += `<div class="response-meta-time">Submitted: ${formatted}</div>`;
    }

    container.innerHTML = html;
}

/**
 * Render the poll form with this response's answers filled in
 */
function renderResponseForm(response) {
    const container = document.getElementById('responseForm');

    // Render all questions
    let html = '';
    let questionNumber = 0;

    poll.questions.forEach((question) => {
        if (question.type !== 'section_header') {
            questionNumber++;
        }
        html += renderQuestion(question, {
            disabled: true,
            showNumbers: question.type !== 'section_header',
            questionNumber: questionNumber
        });
    });

    container.innerHTML = html;

    // Fill in the answers
    prefillAnswers(response.answers || {});
}

/**
 * Fill in the answers for the current response
 * Adapted from poll.js prefillForm()
 */
function prefillAnswers(answers) {
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
                    if (list) {
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
                    }
                }
                break;

            case 'ranking_truncated':
                if (Array.isArray(answer)) {
                    const availableList = block.querySelector('.ranking-available-list');
                    const rankedList = block.querySelector('.ranking-ranked-list');
                    const placeholder = block.querySelector('.ranking-drop-placeholder');

                    if (availableList && rankedList) {
                        const allItems = [
                            ...Array.from(availableList.querySelectorAll('.ranking-truncated-item')),
                            ...Array.from(rankedList.querySelectorAll('.ranking-truncated-item'))
                        ];
                        const itemMap = {};
                        allItems.forEach(item => {
                            itemMap[item.dataset.optionId] = item;
                        });

                        availableList.innerHTML = '';
                        rankedList.innerHTML = '';

                        const rankedIds = new Set();
                        answer.forEach(id => {
                            if (itemMap[id]) {
                                rankedList.appendChild(itemMap[id]);
                                rankedIds.add(String(id));
                            }
                        });

                        allItems.forEach(item => {
                            if (!rankedIds.has(item.dataset.optionId)) {
                                availableList.appendChild(item);
                            }
                        });

                        if (placeholder) {
                            placeholder.style.display = answer.length > 0 ? 'none' : '';
                        }
                    }
                }
                break;

            case 'ranking_with_ties':
                if (typeof answer === 'object' && !Array.isArray(answer)) {
                    const container = block.querySelector('.ranking-ties-container');

                    if (container) {
                        const allItems = Array.from(container.querySelectorAll('.ranking-ties-item'));
                        const itemMap = {};
                        allItems.forEach(item => {
                            itemMap[item.dataset.optionId] = item;
                        });

                        const rankGroups = {};
                        Object.entries(answer).forEach(([optionId, rank]) => {
                            if (!rankGroups[rank]) {
                                rankGroups[rank] = [];
                            }
                            if (itemMap[optionId]) {
                                rankGroups[rank].push(itemMap[optionId]);
                            }
                        });

                        const sortedRanks = Object.keys(rankGroups).map(Number).sort((a, b) => a - b);

                        container.innerHTML = '';

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
                    }
                }
                break;

            case 'star':
                if (typeof answer === 'object') {
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
                }
                break;

            case 'grade':
                if (typeof answer === 'object') {
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
                    Object.entries(answer).forEach(([optionId, value]) => {
                        const buttonsDiv = block.querySelector(`.yna-buttons[data-option-id="${optionId}"]`);
                        if (buttonsDiv) {
                            const btn = buttonsDiv.querySelector(`.yna-btn[data-value="${value}"]`);
                            if (btn) btn.classList.add('active');
                        }
                    });
                }
                break;
        }
    });
}

// ==========================================================================
// Delete Response
// ==========================================================================

/**
 * Delete the currently displayed response
 */
async function deleteCurrentResponse() {
    if (responses.length === 0) return;

    const response = responses[currentIndex];
    const voterName = response.voter_name || `Response ${currentIndex + 1}`;

    const confirmed = await showConfirmModal({
        title: 'Delete Response',
        message: `Are you sure you want to delete the response from "${voterName}"? This action cannot be undone.`,
        confirmText: 'Delete',
        confirmClass: 'btn-danger'
    });

    if (!confirmed) return;

    try {
        const publicId = poll.public_id;
        await api.delete(`/api/polls/${publicId}/responses/${response.id}`);

        showToast('Response deleted', 'success');

        // Remove from local array
        responses.splice(currentIndex, 1);

        if (responses.length === 0) {
            // No more responses
            document.querySelector('.responses-browser').classList.add('empty');
            return;
        }

        // Update UI
        document.getElementById('totalResponses').textContent = responses.length;
        document.getElementById('responseIndex').max = responses.length;
        populateVoterDropdown();

        // Navigate to next response (or previous if we were at the end)
        if (currentIndex >= responses.length) {
            currentIndex = responses.length - 1;
        }
        navigateTo(currentIndex);

    } catch (err) {
        showToast('Failed to delete response: ' + err.message, 'error');
    }
}

// ==========================================================================
// Utilities
// ==========================================================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
