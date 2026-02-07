/**
 * Vote Poll Web Component
 *
 * A self-contained Web Component for embedding polls on external websites.
 *
 * Usage:
 *   <script src="https://your-site.com/vote/assets/js/embed/vote-poll.js"></script>
 *   <vote-poll src="https://your-site.com/vote/abc123/embed/xyz789"></vote-poll>
 *
 * Styling via CSS variables:
 *   vote-poll {
 *     --vp-color-primary: #2563eb;
 *     --vp-color-text: #1e293b;
 *     --vp-color-bg: #ffffff;
 *     --vp-font-family: system-ui, sans-serif;
 *     --vp-border-radius: 8px;
 *   }
 */

class VotePoll extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.pollData = null;
        this.translations = {};
        this.siteUrl = '';
        this.resultsUrl = null;
        this.apiBase = '';
    }

    static get observedAttributes() {
        return ['src'];
    }

    connectedCallback() {
        this.render();
        if (this.hasAttribute('src')) {
            this.loadPoll();
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'src' && oldValue !== newValue && this.isConnected) {
            this.loadPoll();
        }
    }

    async loadPoll() {
        const src = this.getAttribute('src');
        if (!src) return;

        this.showLoading();

        try {
            // Convert embed URL to API URL
            // e.g., /abc123/embed/xyz789 -> /api/embed/abc123/xyz789
            const url = new URL(src, window.location.origin);
            const pathMatch = url.pathname.match(/\/([^\/]+)\/embed\/([^\/]+)/);
            if (!pathMatch) {
                throw new Error('Invalid embed URL format');
            }
            const [, publicId, embedToken] = pathMatch;
            this.apiBase = url.origin + url.pathname.replace(`/${publicId}/embed/${embedToken}`, '');

            const apiUrl = `${this.apiBase}/api/embed/${publicId}/${embedToken}`;

            const response = await fetch(apiUrl);
            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to load poll');
            }
            const data = await response.json();

            this.pollData = data.poll;
            this.translations = data.translations || {};
            this.siteUrl = data.site_url;
            this.resultsUrl = data.results_url;
            this.publicId = publicId;
            this.embedToken = embedToken;

            this.renderPoll();
        } catch (error) {
            this.showError(error.message);
        }
    }

    showLoading() {
        this.shadowRoot.innerHTML = `
            <style>${this.getStyles()}</style>
            <div class="loading">Loading poll...</div>
        `;
    }

    showError(message) {
        this.shadowRoot.innerHTML = `
            <style>${this.getStyles()}</style>
            <div class="error">${this.escapeHtml(message)}</div>
        `;
    }

    renderPoll() {
        const questionsHtml = this.renderQuestions();

        this.shadowRoot.innerHTML = `
            <style>${this.getStyles()}</style>
            <div class="poll-container">
                <header class="poll-header">
                    <h1>${this.escapeHtml(this.pollData.title)}</h1>
                    ${this.pollData.description ? `<div class="poll-description">${this.escapeHtml(this.pollData.description)}</div>` : ''}
                </header>
                <form id="pollForm" class="poll-form">
                    ${this.pollData.collect_name ? `
                        <div class="form-group name-field">
                            <label for="voterName">${this.t('your_name')}</label>
                            <input type="text" id="voterName" name="voter_name" required>
                        </div>
                    ` : ''}
                    <div class="questions-container">
                        ${questionsHtml}
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">${this.t('submit_vote')}</button>
                    </div>
                </form>
                <footer class="poll-footer">
                    <a href="${this.siteUrl}" target="_blank" rel="noopener">
                        Powered by Pref.Tools Vote
                    </a>
                </footer>
            </div>
        `;

        this.initForm();
        this.initInteractiveElements();
    }

    renderQuestions() {
        let html = '';
        let questionNumber = 0;

        this.pollData.questions.forEach((question) => {
            if (question.type === 'section_header') {
                html += `<div class="section-header">${this.escapeHtml(question.text)}</div>`;
                return;
            }

            questionNumber++;
            html += `
                <div class="question" data-question-id="${question.id}" data-type="${question.type}">
                    <div class="question-text">
                        <span class="question-number">${questionNumber}.</span>
                        ${this.escapeHtml(question.text)}
                        ${question.required ? '<span class="required">*</span>' : ''}
                    </div>
                    ${question.description ? `<div class="question-description">${this.escapeHtml(question.description)}</div>` : ''}
                    <div class="question-input">
                        ${this.renderQuestionInput(question)}
                    </div>
                </div>
            `;
        });

        return html;
    }

    renderQuestionInput(question) {
        switch (question.type) {
            case 'text_single':
                return `<input type="text" class="text-input" ${question.required ? 'required' : ''}>`;

            case 'text_multi':
                return `<textarea class="text-input textarea" rows="4" ${question.required ? 'required' : ''}></textarea>`;

            case 'single_choice':
                return this.renderSingleChoice(question);

            case 'approval':
                return this.renderApproval(question);

            case 'star':
                return this.renderStar(question);

            case 'grade':
                return this.renderGrade(question);

            case 'yes_no_abstain':
                return this.renderYesNoAbstain(question);

            case 'ranking':
                return this.renderRanking(question);

            default:
                return `<p class="unsupported">This question type (${question.type}) requires the full voting page. <a href="${this.siteUrl}" target="_blank">Vote here</a></p>`;
        }
    }

    renderSingleChoice(question) {
        const options = question.options || [];
        let html = '<div class="radio-options">';
        options.forEach(opt => {
            html += `
                <label class="radio-option">
                    <input type="radio" name="q_${question.id}" value="${opt.id}">
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                </label>
            `;
        });
        html += '</div>';
        return html;
    }

    renderApproval(question) {
        const options = question.options || [];
        const max = question.settings?.max;
        let html = '<div class="checkbox-options">';
        if (max) {
            html = `<p class="constraint-hint">Select up to ${max}</p>` + html;
        }
        options.forEach(opt => {
            html += `
                <label class="checkbox-option">
                    <input type="checkbox" name="q_${question.id}" value="${opt.id}">
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                </label>
            `;
        });
        html += '</div>';
        return html;
    }

    renderStar(question) {
        const options = question.options || [];
        const maxStars = question.settings?.max_stars || 5;
        let html = '<div class="star-options">';
        options.forEach(opt => {
            html += `
                <div class="star-row" data-option-id="${opt.id}">
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                    <div class="stars">
                        ${Array.from({length: maxStars}, (_, i) => `
                            <span class="star" data-value="${i + 1}">★</span>
                        `).join('')}
                    </div>
                </div>
            `;
        });
        html += '<input type="hidden" class="star-value" value="{}">';
        html += '</div>';
        return html;
    }

    renderGrade(question) {
        const options = question.options || [];
        const grades = question.settings?.grades || ['A', 'B', 'C', 'D', 'F'];
        let html = '<div class="grade-options">';
        options.forEach(opt => {
            html += `
                <div class="grade-row" data-option-id="${opt.id}">
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                    <div class="grade-buttons">
                        ${grades.map(g => `<button type="button" class="grade-btn" data-value="${g}">${g}</button>`).join('')}
                    </div>
                </div>
            `;
        });
        html += '<input type="hidden" class="grade-value" value="{}">';
        html += '</div>';
        return html;
    }

    renderYesNoAbstain(question) {
        const options = question.options || [];
        let html = '<div class="yna-options">';
        options.forEach(opt => {
            html += `
                <div class="yna-row" data-option-id="${opt.id}">
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                    <div class="yna-buttons">
                        <button type="button" class="yna-btn yes" data-value="yes">Yes</button>
                        <button type="button" class="yna-btn no" data-value="no">No</button>
                        <button type="button" class="yna-btn abstain" data-value="abstain">Abstain</button>
                    </div>
                </div>
            `;
        });
        html += '<input type="hidden" class="yna-value" value="{}">';
        html += '</div>';
        return html;
    }

    renderRanking(question) {
        const options = question.options || [];
        let html = '<div class="ranking-options">';
        html += '<p class="ranking-hint">Drag to reorder (top = best)</p>';
        html += '<div class="ranking-list">';
        options.forEach((opt, i) => {
            html += `
                <div class="ranking-item" data-option-id="${opt.id}" draggable="true">
                    <span class="rank-number">${i + 1}</span>
                    <span class="option-label">${this.escapeHtml(opt.label)}</span>
                    <span class="drag-handle">⋮⋮</span>
                </div>
            `;
        });
        html += '</div>';
        html += '<input type="hidden" class="ranking-value">';
        html += '</div>';
        return html;
    }

    initForm() {
        const form = this.shadowRoot.getElementById('pollForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.submitResponse();
        });
    }

    initInteractiveElements() {
        this.initStarRatings();
        this.initGradeButtons();
        this.initYnaButtons();
        this.initRankings();
        this.initApprovalMax();
    }

    initStarRatings() {
        this.shadowRoot.querySelectorAll('.star-options').forEach(container => {
            const input = container.querySelector('.star-value');
            const ratings = {};

            container.querySelectorAll('.star-row').forEach(row => {
                const optionId = row.dataset.optionId;
                const stars = row.querySelectorAll('.star');

                stars.forEach(star => {
                    star.addEventListener('click', () => {
                        const value = parseInt(star.dataset.value);
                        if (ratings[optionId] === value) {
                            delete ratings[optionId];
                        } else {
                            ratings[optionId] = value;
                        }

                        // Update visual state
                        stars.forEach(s => {
                            const sValue = parseInt(s.dataset.value);
                            s.classList.toggle('active', sValue <= (ratings[optionId] || 0));
                        });

                        input.value = JSON.stringify(ratings);
                    });
                });
            });
        });
    }

    initGradeButtons() {
        this.shadowRoot.querySelectorAll('.grade-options').forEach(container => {
            const input = container.querySelector('.grade-value');
            const values = {};

            container.querySelectorAll('.grade-row').forEach(row => {
                const optionId = row.dataset.optionId;
                const buttons = row.querySelectorAll('.grade-btn');

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

    initYnaButtons() {
        this.shadowRoot.querySelectorAll('.yna-options').forEach(container => {
            const input = container.querySelector('.yna-value');
            const values = {};

            container.querySelectorAll('.yna-row').forEach(row => {
                const optionId = row.dataset.optionId;
                const buttons = row.querySelectorAll('.yna-btn');

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

    initRankings() {
        this.shadowRoot.querySelectorAll('.ranking-options').forEach(container => {
            const list = container.querySelector('.ranking-list');
            const input = container.querySelector('.ranking-value');
            let draggedItem = null;

            const updateValue = () => {
                const order = Array.from(list.querySelectorAll('.ranking-item'))
                    .map(item => parseInt(item.dataset.optionId));
                input.value = JSON.stringify(order);

                // Update rank numbers
                list.querySelectorAll('.ranking-item').forEach((item, i) => {
                    item.querySelector('.rank-number').textContent = i + 1;
                });
            };

            list.querySelectorAll('.ranking-item').forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    draggedItem = item;
                    item.classList.add('dragging');
                });

                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    updateValue();
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (draggedItem && draggedItem !== item) {
                        const rect = item.getBoundingClientRect();
                        const midY = rect.top + rect.height / 2;
                        if (e.clientY < midY) {
                            list.insertBefore(draggedItem, item);
                        } else {
                            list.insertBefore(draggedItem, item.nextSibling);
                        }
                    }
                });
            });

            updateValue();
        });
    }

    initApprovalMax() {
        this.shadowRoot.querySelectorAll('.checkbox-options').forEach(container => {
            const question = container.closest('.question');
            if (!question) return;

            const questionId = question.dataset.questionId;
            const qData = this.pollData.questions.find(q => String(q.id) === questionId);
            const max = qData?.settings?.max;

            if (max) {
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                const updateState = () => {
                    const checked = container.querySelectorAll('input[type="checkbox"]:checked').length;
                    checkboxes.forEach(cb => {
                        if (!cb.checked) {
                            cb.disabled = checked >= max;
                        }
                    });
                };
                checkboxes.forEach(cb => cb.addEventListener('change', updateState));
            }
        });
    }

    collectFormData() {
        const data = { answers: {} };

        // Voter name
        const nameInput = this.shadowRoot.getElementById('voterName');
        if (nameInput) {
            data.voter_name = nameInput.value;
        }

        // Collect answers
        this.shadowRoot.querySelectorAll('.question').forEach(block => {
            const questionId = block.dataset.questionId;
            const type = block.dataset.type;
            let answer = null;

            switch (type) {
                case 'text_single':
                case 'text_multi':
                    const textInput = block.querySelector('.text-input');
                    answer = textInput?.value || null;
                    break;

                case 'single_choice':
                    const selected = block.querySelector('input[type="radio"]:checked');
                    if (selected) {
                        answer = parseInt(selected.value);
                    }
                    break;

                case 'approval':
                    const checked = block.querySelectorAll('input[type="checkbox"]:checked');
                    answer = Array.from(checked).map(c => parseInt(c.value));
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
                    }
                    break;

                case 'yes_no_abstain':
                    const ynaInput = block.querySelector('.yna-value');
                    if (ynaInput?.value) {
                        answer = JSON.parse(ynaInput.value);
                    }
                    break;

                case 'ranking':
                    const rankInput = block.querySelector('.ranking-value');
                    if (rankInput?.value) {
                        answer = JSON.parse(rankInput.value);
                    }
                    break;
            }

            if (answer !== null && answer !== undefined) {
                data.answers[questionId] = answer;
            }
        });

        return data;
    }

    async submitResponse() {
        const formData = this.collectFormData();
        const submitBtn = this.shadowRoot.querySelector('.btn-submit');

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }

            const apiUrl = `${this.apiBase}/api/embed/${this.publicId}/${this.embedToken}/responses`;

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'Submission failed');
            }

            this.showThankYou(result);
        } catch (error) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = this.t('submit_vote');
            }
            this.showToast(error.message, 'error');
        }
    }

    showThankYou(result) {
        const customMessage = result.thank_you_message;
        const resultsUrl = result.results_url || this.resultsUrl;
        const siteUrl = result.site_url || this.siteUrl;

        this.shadowRoot.innerHTML = `
            <style>${this.getStyles()}</style>
            <div class="thank-you">
                ${customMessage || `<h2>${this.t('thank_you')}</h2><p>${this.t('response_recorded')}</p>`}
                <div class="thank-you-actions">
                    ${resultsUrl ? `<a href="${resultsUrl}" target="_blank" class="btn-link">${this.t('view_results')}</a>` : ''}
                    <a href="${siteUrl}" target="_blank" class="site-link">
                        Visit Pref.Tools Vote
                    </a>
                </div>
            </div>
        `;
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        this.shadowRoot.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    t(key, params = {}) {
        let text = this.translations[key] || key;
        for (const [name, value] of Object.entries(params)) {
            text = text.replace(`:${name}`, String(value));
        }
        return text;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    getStyles() {
        return `
            :host {
                display: block;
                font-family: var(--vp-font-family, system-ui, -apple-system, sans-serif);
                color: var(--vp-color-text, #1e293b);
                background: var(--vp-color-bg, #ffffff);
                border-radius: var(--vp-border-radius, 8px);
                padding: var(--vp-spacing, 1.5rem);
                box-sizing: border-box;
            }

            *, *::before, *::after {
                box-sizing: border-box;
            }

            .poll-container {
                max-width: 100%;
            }

            .poll-header h1 {
                font-size: var(--vp-font-size-title, 1.5rem);
                margin: 0 0 0.5rem 0;
                color: var(--vp-color-text-strong, #0f172a);
            }

            .poll-description {
                color: var(--vp-color-text-muted, #64748b);
                margin-bottom: 1.5rem;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .form-group label {
                display: block;
                font-weight: 500;
                margin-bottom: 0.5rem;
            }

            .form-group input[type="text"] {
                width: 100%;
                padding: 0.5rem 0.75rem;
                border: 1px solid var(--vp-color-border, #e2e8f0);
                border-radius: var(--vp-border-radius, 8px);
                font-size: 1rem;
            }

            .section-header {
                font-size: 1.25rem;
                font-weight: 600;
                margin: 1.5rem 0 1rem 0;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid var(--vp-color-primary, #2563eb);
            }

            .question {
                margin-bottom: 1.5rem;
                padding-bottom: 1.5rem;
                border-bottom: 1px solid var(--vp-color-border, #e2e8f0);
            }

            .question:last-child {
                border-bottom: none;
            }

            .question-text {
                font-weight: 500;
                margin-bottom: 0.5rem;
            }

            .question-number {
                color: var(--vp-color-text-muted, #64748b);
                margin-right: 0.25rem;
            }

            .required {
                color: var(--vp-color-danger, #ef4444);
                margin-left: 0.25rem;
            }

            .question-description {
                color: var(--vp-color-text-muted, #64748b);
                font-size: 0.875rem;
                margin-bottom: 0.75rem;
            }

            .question-input {
                margin-top: 0.75rem;
            }

            .text-input {
                width: 100%;
                padding: 0.5rem 0.75rem;
                border: 1px solid var(--vp-color-border, #e2e8f0);
                border-radius: var(--vp-border-radius, 8px);
                font-size: 1rem;
                font-family: inherit;
            }

            .textarea {
                resize: vertical;
                min-height: 100px;
            }

            .radio-option, .checkbox-option {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 0;
                cursor: pointer;
            }

            input[type="radio"], input[type="checkbox"] {
                accent-color: var(--vp-color-primary, #2563eb);
                width: 1.125rem;
                height: 1.125rem;
            }

            .constraint-hint {
                font-size: 0.875rem;
                color: var(--vp-color-text-muted, #64748b);
                margin-bottom: 0.5rem;
            }

            .star-row, .grade-row, .yna-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                gap: 1rem;
            }

            .stars {
                display: flex;
                gap: 0.25rem;
            }

            .star {
                font-size: 1.5rem;
                color: var(--vp-color-border, #e2e8f0);
                cursor: pointer;
                transition: color 0.15s;
            }

            .star:hover, .star.active {
                color: var(--vp-color-warning, #f59e0b);
            }

            .grade-buttons, .yna-buttons {
                display: flex;
                gap: 0.25rem;
            }

            .grade-btn, .yna-btn {
                padding: 0.375rem 0.75rem;
                border: 1px solid var(--vp-color-border, #e2e8f0);
                background: var(--vp-color-bg, #ffffff);
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.875rem;
                transition: all 0.15s;
            }

            .grade-btn:hover, .yna-btn:hover {
                border-color: var(--vp-color-primary, #2563eb);
            }

            .grade-btn.active, .yna-btn.active {
                background: var(--vp-color-primary, #2563eb);
                border-color: var(--vp-color-primary, #2563eb);
                color: white;
            }

            .yna-btn.yes.active {
                background: #22c55e;
                border-color: #22c55e;
            }

            .yna-btn.no.active {
                background: #ef4444;
                border-color: #ef4444;
            }

            .yna-btn.abstain.active {
                background: #6b7280;
                border-color: #6b7280;
            }

            .ranking-hint {
                font-size: 0.875rem;
                color: var(--vp-color-text-muted, #64748b);
                margin-bottom: 0.5rem;
            }

            .ranking-list {
                border: 1px solid var(--vp-color-border, #e2e8f0);
                border-radius: var(--vp-border-radius, 8px);
                overflow: hidden;
            }

            .ranking-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                background: var(--vp-color-bg, #ffffff);
                border-bottom: 1px solid var(--vp-color-border, #e2e8f0);
                cursor: grab;
            }

            .ranking-item:last-child {
                border-bottom: none;
            }

            .ranking-item.dragging {
                opacity: 0.5;
            }

            .rank-number {
                font-weight: 600;
                color: var(--vp-color-primary, #2563eb);
                min-width: 1.5rem;
            }

            .drag-handle {
                margin-left: auto;
                color: var(--vp-color-text-muted, #64748b);
            }

            .unsupported {
                padding: 1rem;
                background: var(--vp-color-bg-muted, #f8fafc);
                border-radius: var(--vp-border-radius, 8px);
                color: var(--vp-color-text-muted, #64748b);
            }

            .unsupported a {
                color: var(--vp-color-primary, #2563eb);
            }

            .form-actions {
                margin-top: 1.5rem;
            }

            .btn-submit {
                background: var(--vp-color-primary, #2563eb);
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: var(--vp-border-radius, 8px);
                font-size: 1rem;
                cursor: pointer;
                font-weight: 500;
                transition: background 0.15s;
            }

            .btn-submit:hover:not(:disabled) {
                background: var(--vp-color-primary-dark, #1d4ed8);
            }

            .btn-submit:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .poll-footer {
                margin-top: 1.5rem;
                padding-top: 1rem;
                border-top: 1px solid var(--vp-color-border, #e2e8f0);
                text-align: center;
            }

            .poll-footer a {
                color: var(--vp-color-text-muted, #64748b);
                text-decoration: none;
                font-size: 0.875rem;
            }

            .poll-footer a:hover {
                text-decoration: underline;
            }

            .thank-you {
                text-align: center;
                padding: 2rem;
            }

            .thank-you h2 {
                margin: 0 0 0.5rem 0;
            }

            .thank-you-actions {
                margin-top: 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                align-items: center;
            }

            .btn-link {
                background: var(--vp-color-primary, #2563eb);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: var(--vp-border-radius, 8px);
                text-decoration: none;
                font-weight: 500;
            }

            .site-link {
                color: var(--vp-color-text-muted, #64748b);
                font-size: 0.875rem;
                text-decoration: none;
            }

            .site-link:hover {
                text-decoration: underline;
            }

            .loading, .error {
                padding: 2rem;
                text-align: center;
            }

            .error {
                color: var(--vp-color-danger, #ef4444);
            }

            .toast {
                position: fixed;
                bottom: 1rem;
                left: 50%;
                transform: translateX(-50%);
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-size: 0.875rem;
                z-index: 1000;
                animation: slideUp 0.3s ease;
            }

            .toast-error {
                background: var(--vp-color-danger, #ef4444);
            }

            .toast-success {
                background: #22c55e;
            }

            @keyframes slideUp {
                from {
                    transform: translateX(-50%) translateY(1rem);
                    opacity: 0;
                }
                to {
                    transform: translateX(-50%) translateY(0);
                    opacity: 1;
                }
            }
        `;
    }
}

customElements.define('vote-poll', VotePoll);
