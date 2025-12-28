/**
 * Results Page JavaScript
 */

import { api, showToast } from './app.js';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.results-content');
    if (!container) return;

    const publicId = container.dataset.publicId;

    loadResults(publicId);
});

async function loadResults(publicId) {
    const container = document.getElementById('resultsData');

    try {
        // Load vote data
        const pollResult = await api.get(`/api/polls/${publicId}`);
        const poll = pollResult.poll;

        // Load responses
        const responsesResult = await api.get(`/api/polls/${publicId}/responses`);
        const responses = responsesResult.responses;

        if (responses.length === 0) {
            container.innerHTML = '<p class="empty-message">No responses yet.</p>';
            return;
        }

        // Render results for each question
        container.innerHTML = poll.questions.map(question => {
            return `
                <div class="result-question card">
                    <h3>${escapeHtml(question.text)}</h3>
                    ${question.description ? `<p class="question-description">${escapeHtml(question.description)}</p>` : ''}
                    <div class="result-data">
                        ${renderQuestionResults(question, responses)}
                    </div>
                </div>
            `;
        }).join('');

        // Add response count
        container.insertAdjacentHTML('afterbegin', `
            <div class="results-summary card">
                <p><strong>${responses.length}</strong> responses received</p>
            </div>
        `);
    } catch (err) {
        container.innerHTML = '<p class="error-message">Failed to load results.</p>';
        console.error(err);
    }
}

function renderQuestionResults(question, responses) {
    const answers = responses
        .map(r => r.answers[question.id])
        .filter(a => a !== undefined && a !== null);

    if (answers.length === 0) {
        return '<p class="no-answers">No answers for this question.</p>';
    }

    switch (question.type) {
        case 'text_single':
        case 'text_multi':
            return renderTextResults(answers);

        case 'single_choice':
            return renderChoiceResults(question.options, answers, false);

        case 'approval':
            return renderChoiceResults(question.options, answers, true);

        case 'ranking':
            return renderRankingResults(question.options, answers);

        case 'star':
            return renderStarResults(question.options, answers);

        case 'grade':
            return renderGradeResults(question.options, answers);

        case 'yes_no_abstain':
            return renderYnaResults(question.options, answers);

        default:
            return `<pre>${JSON.stringify(answers, null, 2)}</pre>`;
    }
}

function renderTextResults(answers) {
    return `
        <ul class="text-responses">
            ${answers.map(a => `<li>${escapeHtml(a)}</li>`).join('')}
        </ul>
    `;
}

function renderChoiceResults(options, answers, isApproval) {
    const counts = {};
    options.forEach(o => counts[o.id] = 0);

    if (isApproval) {
        answers.forEach(arr => {
            if (Array.isArray(arr)) {
                arr.forEach(id => {
                    if (counts[id] !== undefined) counts[id]++;
                });
            }
        });
    } else {
        answers.forEach(id => {
            if (counts[id] !== undefined) counts[id]++;
        });
    }

    const total = answers.length;
    const maxCount = Math.max(...Object.values(counts));

    return `
        <div class="bar-chart">
            ${options.map(option => {
                const count = counts[option.id];
                const percent = total > 0 ? (count / total * 100).toFixed(1) : 0;
                const barWidth = maxCount > 0 ? (count / maxCount * 100) : 0;

                return `
                    <div class="bar-row">
                        <div class="bar-label">${escapeHtml(option.label)}</div>
                        <div class="bar-container">
                            <div class="bar" style="width: ${barWidth}%"></div>
                        </div>
                        <div class="bar-value">${count} (${percent}%)</div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderRankingResults(options, answers) {
    // Calculate Borda scores
    const scores = {};
    options.forEach(o => scores[o.id] = 0);

    const n = options.length;
    answers.forEach(ranking => {
        if (Array.isArray(ranking)) {
            ranking.forEach((id, index) => {
                if (scores[id] !== undefined) {
                    scores[id] += (n - index - 1);
                }
            });
        }
    });

    // Sort by score
    const sorted = options.slice().sort((a, b) => scores[b.id] - scores[a.id]);
    const maxScore = Math.max(...Object.values(scores));

    return `
        <p class="result-note">Ranked by Borda score (higher = better)</p>
        <div class="bar-chart">
            ${sorted.map((option, rank) => {
                const score = scores[option.id];
                const barWidth = maxScore > 0 ? (score / maxScore * 100) : 0;

                return `
                    <div class="bar-row">
                        <div class="bar-rank">#${rank + 1}</div>
                        <div class="bar-label">${escapeHtml(option.label)}</div>
                        <div class="bar-container">
                            <div class="bar" style="width: ${barWidth}%"></div>
                        </div>
                        <div class="bar-value">${score} pts</div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderStarResults(options, answers) {
    // Calculate average rating for each option
    const sums = {};
    const counts = {};
    options.forEach(o => {
        sums[o.id] = 0;
        counts[o.id] = 0;
    });

    answers.forEach(ratings => {
        if (typeof ratings === 'object') {
            Object.entries(ratings).forEach(([id, rating]) => {
                const numId = parseInt(id);
                if (sums[numId] !== undefined) {
                    sums[numId] += rating;
                    counts[numId]++;
                }
            });
        }
    });

    return `
        <div class="star-results">
            ${options.map(option => {
                const avg = counts[option.id] > 0
                    ? (sums[option.id] / counts[option.id]).toFixed(1)
                    : 0;
                const stars = Math.round(avg);

                return `
                    <div class="star-result-row">
                        <div class="option-label">${escapeHtml(option.label)}</div>
                        <div class="star-display">
                            ${'★'.repeat(stars)}${'☆'.repeat(5 - stars)}
                        </div>
                        <div class="star-avg">${avg} / 5</div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderGradeResults(options, answers) {
    const grades = ['excellent', 'very good', 'good', 'fair', 'poor', 'reject'];
    const gradeCounts = {};

    options.forEach(o => {
        gradeCounts[o.id] = {};
        grades.forEach(g => gradeCounts[o.id][g] = 0);
    });

    answers.forEach(gradeMap => {
        if (typeof gradeMap === 'object') {
            Object.entries(gradeMap).forEach(([id, grade]) => {
                const numId = parseInt(id);
                if (gradeCounts[numId] && gradeCounts[numId][grade] !== undefined) {
                    gradeCounts[numId][grade]++;
                }
            });
        }
    });

    return `
        <div class="grade-results">
            ${options.map(option => {
                const counts = gradeCounts[option.id];
                const total = Object.values(counts).reduce((a, b) => a + b, 0);

                return `
                    <div class="grade-result-row">
                        <div class="option-label">${escapeHtml(option.label)}</div>
                        <div class="grade-bars">
                            ${grades.map(grade => {
                                const count = counts[grade];
                                const width = total > 0 ? (count / total * 100) : 0;
                                return `<div class="grade-segment grade-${grade.replace(' ', '-')}"
                                            style="width: ${width}%"
                                            title="${grade}: ${count}"></div>`;
                            }).join('')}
                        </div>
                    </div>
                `;
            }).join('')}
            <div class="grade-legend">
                ${grades.map(g => `<span class="legend-item grade-${g.replace(' ', '-')}">${g}</span>`).join('')}
            </div>
        </div>
    `;
}

function renderYnaResults(options, answers) {
    const counts = {};
    options.forEach(o => {
        counts[o.id] = { yes: 0, no: 0, abstain: 0 };
    });

    answers.forEach(voteMap => {
        if (typeof voteMap === 'object') {
            Object.entries(voteMap).forEach(([id, vote]) => {
                const numId = parseInt(id);
                if (counts[numId] && counts[numId][vote] !== undefined) {
                    counts[numId][vote]++;
                }
            });
        }
    });

    return `
        <div class="yna-results">
            ${options.map(option => {
                const c = counts[option.id];
                const total = c.yes + c.no + c.abstain;

                return `
                    <div class="yna-result-row">
                        <div class="option-label">${escapeHtml(option.label)}</div>
                        <div class="yna-counts">
                            <span class="yna-yes">Yes: ${c.yes}</span>
                            <span class="yna-no">No: ${c.no}</span>
                            <span class="yna-abstain">Abstain: ${c.abstain}</span>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
