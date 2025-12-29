/**
 * Admin Panel JavaScript
 */

import { api, showToast, showConfirmModal, setButtonLoading, clearButtonLoading, copyToClipboard, basePath } from './app.js';

let pollData = null;

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.admin-content');
    if (!container) return;

    const publicId = container.dataset.publicId;
    const adminToken = container.dataset.adminToken;

    // Load initial data
    loadVote(publicId, adminToken);
    loadResponses(publicId, adminToken);

    // Action buttons
    const publishBtn = document.getElementById('publishPoll');
    if (publishBtn) {
        publishBtn.addEventListener('click', () => publishPoll(publicId, adminToken));
    }

    const closeBtn = document.getElementById('closePoll');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => closePoll(publicId, adminToken));
    }

    const reopenBtn = document.getElementById('reopenPoll');
    if (reopenBtn) {
        reopenBtn.addEventListener('click', () => reopenPoll(publicId, adminToken));
    }

    document.getElementById('editPoll')?.addEventListener('click', () => {
        window.location.href = `${basePath}/${publicId}/admin/${adminToken}/edit`;
    });

    document.getElementById('deletePoll')?.addEventListener('click', async () => {
        const confirmed = await showConfirmModal({
            title: 'Delete Poll',
            message: 'Are you sure you want to delete this poll? This action cannot be undone and all responses will be lost.',
            confirmText: 'Delete Poll',
        });
        if (confirmed) {
            deletePoll(publicId, adminToken);
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

    // Auto-select URL on click for easy copying
    document.querySelectorAll('.copy-field input[readonly]').forEach(input => {
        input.addEventListener('click', () => {
            input.select();
        });
        input.addEventListener('focus', () => {
            input.select();
        });
    });

    // Claim poll button
    const claimBtn = document.getElementById('claimPoll');
    if (claimBtn) {
        claimBtn.addEventListener('click', () => claimPoll(publicId, adminToken));
    }
});

async function loadVote(publicId, adminToken) {
    try {
        const result = await api.get(`/api/polls/${publicId}/admin/${adminToken}`);
        pollData = result.vote;
    } catch (err) {
        showToast('Failed to load vote data', 'error');
    }
}

async function loadResponses(publicId, adminToken) {
    const container = document.getElementById('responsesList');
    container.innerHTML = '<p class="loading">Loading responses...</p>';

    try {
        const result = await api.get(`/api/polls/${publicId}/responses?admin_token=${adminToken}`);

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
    if (!pollData || !pollData.questions) {
        return JSON.stringify(answers);
    }

    return pollData.questions.map(q => {
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

async function publishPoll(publicId, adminToken) {
    const btn = document.getElementById('publishPoll');
    try {
        if (btn) setButtonLoading(btn);
        await api.put(`/api/polls/${publicId}/admin/${adminToken}`, { status: 'open' });
        location.reload();
    } catch (err) {
        if (btn) clearButtonLoading(btn);
        showToast(err.message, 'error');
    }
}

async function closePoll(publicId, adminToken) {
    const btn = document.getElementById('closePoll');
    try {
        if (btn) setButtonLoading(btn);
        await api.post(`/api/polls/${publicId}/admin/${adminToken}/close`);
        location.reload();
    } catch (err) {
        if (btn) clearButtonLoading(btn);
        showToast(err.message, 'error');
    }
}

async function reopenPoll(publicId, adminToken) {
    const btn = document.getElementById('reopenPoll');
    try {
        if (btn) setButtonLoading(btn);
        await api.post(`/api/polls/${publicId}/admin/${adminToken}/reopen`);
        location.reload();
    } catch (err) {
        if (btn) clearButtonLoading(btn);
        showToast(err.message, 'error');
    }
}

async function deletePoll(publicId, adminToken) {
    const btn = document.getElementById('deletePoll');
    try {
        if (btn) setButtonLoading(btn);
        await api.delete(`/api/polls/${publicId}/admin/${adminToken}`);
        window.location.href = basePath + '/';
    } catch (err) {
        if (btn) clearButtonLoading(btn);
        showToast(err.message, 'error');
    }
}

async function claimPoll(publicId, adminToken) {
    const btn = document.getElementById('claimPoll');
    try {
        if (btn) setButtonLoading(btn);
        await api.post('/api/user/claim-poll', { public_id: publicId, admin_token: adminToken });
        showToast('Poll claimed to your account', 'success');
        // Reload to update the UI (removes claim button, hides bookmark hint)
        location.reload();
    } catch (err) {
        if (btn) clearButtonLoading(btn);
        showToast(err.message, 'error');
    }
}

async function exportData(publicId, adminToken, format) {
    try {
        const result = await api.get(`/api/polls/${publicId}/admin/${adminToken}/export?format=${format}`);

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
