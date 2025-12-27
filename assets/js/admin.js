/**
 * Admin Panel JavaScript
 */

import { api, showToast, copyToClipboard, basePath } from './app.js';

let voteData = null;

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.admin-content');
    if (!container) return;

    const publicId = container.dataset.publicId;
    const adminToken = container.dataset.adminToken;

    // Load initial data
    loadVote(publicId, adminToken);
    loadResponses(publicId, adminToken);

    // Action buttons
    const publishBtn = document.getElementById('publishVote');
    if (publishBtn) {
        publishBtn.addEventListener('click', () => publishVote(publicId, adminToken));
    }

    const closeBtn = document.getElementById('closeVote');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => closeVote(publicId, adminToken));
    }

    const reopenBtn = document.getElementById('reopenVote');
    if (reopenBtn) {
        reopenBtn.addEventListener('click', () => reopenVote(publicId, adminToken));
    }

    document.getElementById('editVote')?.addEventListener('click', () => {
        window.location.href = `${basePath}/${publicId}/admin/${adminToken}/edit`;
    });

    document.getElementById('deleteVote')?.addEventListener('click', () => {
        if (confirm('Are you sure you want to delete this vote? This cannot be undone.')) {
            deleteVote(publicId, adminToken);
        }
    });

    // Refresh responses
    document.getElementById('refreshResponses')?.addEventListener('click', () => {
        loadResponses(publicId, adminToken);
    });

    // Export buttons
    document.getElementById('exportJson')?.addEventListener('click', () => {
        exportData(publicId, adminToken, 'json');
    });

    document.getElementById('exportCsv')?.addEventListener('click', () => {
        exportData(publicId, adminToken, 'csv');
    });

    // Copy buttons
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            if (input) {
                await copyToClipboard(input.value);
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = 'Copy', 2000);
            }
        });
    });
});

async function loadVote(publicId, adminToken) {
    try {
        const result = await api.get(`/api/votes/${publicId}/admin/${adminToken}`);
        voteData = result.vote;
    } catch (err) {
        showToast('Failed to load vote data', 'error');
    }
}

async function loadResponses(publicId, adminToken) {
    const container = document.getElementById('responsesList');
    container.innerHTML = '<p class="loading">Loading responses...</p>';

    try {
        const result = await api.get(`/api/votes/${publicId}/responses?admin_token=${adminToken}`);

        if (result.responses.length === 0) {
            container.innerHTML = '<p class="empty-message">No responses yet.</p>';
            return;
        }

        container.innerHTML = result.responses.map((response, index) => `
            <div class="response-item card">
                <div class="response-header">
                    <span class="response-number">#${index + 1}</span>
                    ${response.voter_name ? `<span class="voter-name">${escapeHtml(response.voter_name)}</span>` : ''}
                    <span class="response-time">${new Date(response.created_at).toLocaleString()}</span>
                </div>
                <div class="response-answers">
                    ${formatAnswers(response.answers)}
                </div>
            </div>
        `).join('');

        // Update count
        document.getElementById('responseCount').textContent = result.responses.length;
    } catch (err) {
        container.innerHTML = '<p class="error-message">Failed to load responses.</p>';
    }
}

function formatAnswers(answers) {
    if (!voteData || !voteData.questions) {
        return JSON.stringify(answers);
    }

    return voteData.questions.map(q => {
        const answer = answers[q.id];
        let displayValue = '';

        if (answer === undefined || answer === null) {
            displayValue = '<em>No answer</em>';
        } else if (typeof answer === 'string' || typeof answer === 'number') {
            // For single choice, find the option label
            if (q.type === 'single_choice') {
                const option = q.options.find(o => o.id === answer);
                displayValue = option ? escapeHtml(option.label) : answer;
            } else {
                displayValue = escapeHtml(String(answer));
            }
        } else if (Array.isArray(answer)) {
            // For approval or ranking
            const labels = answer.map(id => {
                const option = q.options.find(o => o.id === id);
                return option ? option.label : id;
            });
            displayValue = labels.map(l => escapeHtml(l)).join(q.type === 'ranking' ? ' > ' : ', ');
        } else if (typeof answer === 'object') {
            // For grades, stars, yes/no/abstain
            displayValue = Object.entries(answer).map(([optId, value]) => {
                const option = q.options.find(o => o.id === parseInt(optId));
                const label = option ? option.label : optId;
                return `${escapeHtml(label)}: ${escapeHtml(String(value))}`;
            }).join(', ');
        }

        return `
            <div class="answer-row">
                <span class="question-label">${escapeHtml(q.text)}:</span>
                <span class="answer-value">${displayValue}</span>
            </div>
        `;
    }).join('');
}

async function publishVote(publicId, adminToken) {
    try {
        await api.put(`/api/votes/${publicId}/admin/${adminToken}`, { status: 'open' });
        showToast('Vote published!', 'success');
        setTimeout(() => location.reload(), 1000);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function closeVote(publicId, adminToken) {
    try {
        await api.post(`/api/votes/${publicId}/admin/${adminToken}/close`);
        showToast('Voting closed', 'success');
        setTimeout(() => location.reload(), 1000);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function reopenVote(publicId, adminToken) {
    try {
        await api.post(`/api/votes/${publicId}/admin/${adminToken}/reopen`);
        showToast('Voting reopened', 'success');
        setTimeout(() => location.reload(), 1000);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteVote(publicId, adminToken) {
    try {
        await api.delete(`/api/votes/${publicId}/admin/${adminToken}`);
        showToast('Vote deleted', 'success');
        setTimeout(() => window.location.href = basePath + '/', 1000);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function exportData(publicId, adminToken, format) {
    try {
        const result = await api.get(`/api/votes/${publicId}/admin/${adminToken}/export?format=${format}`);

        if (format === 'json') {
            // Download as JSON file
            const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' });
            downloadBlob(blob, `vote-${publicId}.json`);
        } else if (format === 'csv') {
            // Build CSV from headers and rows
            const csv = [
                result.headers.join(','),
                ...result.rows.map(row => row.map(cell =>
                    typeof cell === 'string' && cell.includes(',') ? `"${cell}"` : cell
                ).join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            downloadBlob(blob, `vote-${publicId}.csv`);
        }

        showToast(`Exported as ${format.toUpperCase()}`, 'success');
    } catch (err) {
        showToast('Export failed: ' + err.message, 'error');
    }
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
