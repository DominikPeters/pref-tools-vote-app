/**
 * Admin Panel JavaScript
 */

import { api, showToast, showConfirmModal, setButtonLoading, clearButtonLoading, copyToClipboard, basePath } from './app.js';

let pollData = null;
let tokensData = [];
let invitationsData = [];
let mailConfigured = false;
let settingsChanged = false;

document.addEventListener('DOMContentLoaded', async () => {
    const container = document.querySelector('.admin-content');
    if (!container) return;

    const publicId = container.dataset.publicId;
    const adminToken = container.dataset.adminToken;

    // Load poll data first, then responses (so formatAnswers has pollData)
    await loadVote(publicId, adminToken);
    loadResponses(publicId, adminToken);

    // Load access management data if not open mode
    if (document.querySelector('.access-section')) {
        loadTokens(publicId, adminToken);
        loadInvitations(publicId, adminToken);
        initAccessTabs();
        initTokenManagement(publicId, adminToken);
        initInvitationManagement(publicId, adminToken);
    }

    // Initialize settings
    initSettings(publicId, adminToken);

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
        pollData = result.poll;
    } catch (err) {
        showToast('Failed to load poll data', 'error');
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
        // Show a loading placeholder instead of raw JSON
        return '<em class="loading-answers">Loading...</em>';
    }

    // Show a compact summary: number of questions answered
    const answeredCount = pollData.questions.filter(q => {
        const answer = answers[q.id];
        return answer !== undefined && answer !== null && answer !== '';
    }).length;

    const totalQuestions = pollData.questions.filter(q => q.type !== 'section_header').length;

    // Create a compact summary with expandable details
    const summaryItems = pollData.questions.slice(0, 3).map(q => {
        const answer = answers[q.id];
        let displayValue = formatSingleAnswer(q, answer);
        if (displayValue.length > 50) {
            displayValue = displayValue.substring(0, 47) + '...';
        }
        return `<span class="answer-preview">${escapeHtml(q.text.substring(0, 30))}${q.text.length > 30 ? '...' : ''}: ${displayValue}</span>`;
    }).join('');

    const moreCount = pollData.questions.length - 3;
    const moreText = moreCount > 0 ? `<span class="answer-more">+${moreCount} more</span>` : '';

    return `
        <div class="answer-summary">
            <span class="answer-count">${answeredCount}/${totalQuestions} answered</span>
            ${summaryItems}${moreText}
        </div>
    `;
}

function formatSingleAnswer(q, answer) {
    if (answer === undefined || answer === null) {
        return '<em>-</em>';
    } else if (typeof answer === 'string' || typeof answer === 'number') {
        if (q.type === 'single_choice') {
            const option = q.options?.find(o => o.id === answer);
            return option ? escapeHtml(option.label) : String(answer);
        } else {
            return escapeHtml(String(answer));
        }
    } else if (Array.isArray(answer)) {
        const labels = answer.map(id => {
            const option = q.options?.find(o => o.id === id);
            return option ? option.label : id;
        });
        if (labels.length <= 2) {
            return labels.map(l => escapeHtml(l)).join(q.type === 'ranking' ? ' > ' : ', ');
        }
        return `${labels.length} selections`;
    } else if (typeof answer === 'object') {
        const entries = Object.entries(answer);
        if (entries.length <= 2) {
            return entries.map(([optId, value]) => {
                const option = q.options?.find(o => o.id === parseInt(optId));
                const label = option ? option.label : optId;
                return `${escapeHtml(label)}: ${escapeHtml(String(value))}`;
            }).join(', ');
        }
        return `${entries.length} ratings`;
    }
    return '-';
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

// ==========================================================================
// Access Tabs
// ==========================================================================

function initAccessTabs() {
    document.querySelectorAll('.access-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;

            // Update tab buttons
            document.querySelectorAll('.access-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Show/hide tab content
            document.getElementById('tokensTab').style.display = targetTab === 'tokens' ? '' : 'none';
            document.getElementById('invitationsTab').style.display = targetTab === 'invitations' ? '' : 'none';
        });
    });
}

// ==========================================================================
// Token Management
// ==========================================================================

async function loadTokens(publicId, adminToken) {
    try {
        const result = await api.get(`/api/polls/${publicId}/admin/${adminToken}/tokens`);
        tokensData = result.tokens || [];
        renderTokens();
    } catch (err) {
        document.getElementById('tokensList').innerHTML = '<p class="error-message">Failed to load tokens.</p>';
    }
}

function initTokenManagement(publicId, adminToken) {
    document.getElementById('generateTokens')?.addEventListener('click', async () => {
        const countInput = document.getElementById('tokenCount');
        const labelInput = document.getElementById('tokenLabelPrefix');
        const btn = document.getElementById('generateTokens');

        const count = parseInt(countInput.value) || 10;
        const labelPrefix = labelInput.value.trim() || null;

        try {
            setButtonLoading(btn);
            const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/tokens`, {
                count,
                label_prefix: labelPrefix,
            });
            tokensData = [...tokensData, ...result.tokens];
            renderTokens();
            showToast(`Generated ${result.tokens.length} tokens`, 'success');
            labelInput.value = '';
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            clearButtonLoading(btn);
        }
    });
}

function renderTokens() {
    const container = document.getElementById('tokensList');

    if (tokensData.length === 0) {
        container.innerHTML = '<p class="empty-message">No tokens yet. Generate some above.</p>';
        return;
    }

    const usedCount = tokensData.filter(t => t.used_at).length;
    const availableCount = tokensData.length - usedCount;

    container.innerHTML = `
        <div class="tokens-summary">
            <span class="token-stat">${tokensData.length} total</span>
            <span class="token-stat available">${availableCount} available</span>
            <span class="token-stat used">${usedCount} used</span>
        </div>
        <div class="tokens-table-wrapper">
            <table class="tokens-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Label</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${tokensData.map(token => `
                        <tr class="${token.used_at ? 'used' : ''}" data-token-id="${token.id}">
                            <td class="token-value">
                                <code>${escapeHtml(token.token)}</code>
                            </td>
                            <td class="token-label">${escapeHtml(token.label || '-')}</td>
                            <td class="token-status">
                                ${token.used_at
                                    ? `<span class="badge badge-used">Used</span>`
                                    : `<span class="badge badge-available">Available</span>`
                                }
                            </td>
                            <td class="token-actions">
                                <button type="button" class="btn btn-small btn-secondary copy-token" data-url="${escapeHtml(token.url)}">Copy URL</button>
                                ${!token.used_at ? `<button type="button" class="btn btn-small btn-outline-danger delete-token" data-id="${token.id}">Delete</button>` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    // Attach event listeners
    container.querySelectorAll('.copy-token').forEach(btn => {
        btn.addEventListener('click', async () => {
            await copyToClipboard(btn.dataset.url);
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy URL', 2000);
        });
    });

    container.querySelectorAll('.delete-token').forEach(btn => {
        btn.addEventListener('click', async () => {
            const tokenId = btn.dataset.id;
            const publicId = document.querySelector('.admin-content').dataset.publicId;
            const adminToken = document.querySelector('.admin-content').dataset.adminToken;

            try {
                await api.delete(`/api/polls/${publicId}/admin/${adminToken}/tokens/${tokenId}`);
                tokensData = tokensData.filter(t => t.id !== parseInt(tokenId));
                renderTokens();
                showToast('Token deleted', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    });
}

// ==========================================================================
// Invitation Management
// ==========================================================================

async function loadInvitations(publicId, adminToken) {
    try {
        const result = await api.get(`/api/polls/${publicId}/admin/${adminToken}/invitations`);
        invitationsData = result.invitations || [];
        mailConfigured = result.mail_configured;
        renderInvitations();
    } catch (err) {
        document.getElementById('invitationsList').innerHTML = '<p class="error-message">Failed to load invitations.</p>';
    }
}

function initInvitationManagement(publicId, adminToken) {
    document.getElementById('sendInvitations')?.addEventListener('click', async () => {
        const emailsInput = document.getElementById('invitationEmails');
        const btn = document.getElementById('sendInvitations');
        const emails = emailsInput.value.trim();

        if (!emails) {
            showToast('Please enter email addresses', 'error');
            return;
        }

        try {
            setButtonLoading(btn);
            const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/invitations`, { emails });
            invitationsData = result.invitations;
            renderInvitations();

            let message = `Sent ${result.sent_count} invitation(s)`;
            if (result.existing_count > 0) {
                message += `, ${result.existing_count} already existed`;
            }
            if (result.blocked_count > 0) {
                message += `, ${result.blocked_count} blocked (unsubscribed)`;
            }
            if (result.failed_count > 0) {
                message += `, ${result.failed_count} failed`;
            }
            const hasWarnings = result.failed_count > 0 || result.blocked_count > 0;
            showToast(message, hasWarnings ? 'warning' : 'success');
            emailsInput.value = '';
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            clearButtonLoading(btn);
        }
    });
}

function renderInvitations() {
    const container = document.getElementById('invitationsList');
    const warningBanner = document.getElementById('mailConfigWarning');

    // Show warning if mail not configured
    if (warningBanner) {
        warningBanner.style.display = mailConfigured ? 'none' : '';
    }

    // Disable send button if mail not configured
    const sendBtn = document.getElementById('sendInvitations');
    if (sendBtn) {
        sendBtn.disabled = !mailConfigured;
    }

    if (invitationsData.length === 0) {
        container.innerHTML = '<p class="empty-message">No invitations sent yet.</p>';
        return;
    }

    const usedCount = invitationsData.filter(i => i.used_at).length;
    const pendingCount = invitationsData.length - usedCount;

    container.innerHTML = `
        <div class="invitations-summary">
            <span class="invitation-stat">${invitationsData.length} total</span>
            <span class="invitation-stat pending">${pendingCount} pending</span>
            <span class="invitation-stat used">${usedCount} voted</span>
        </div>
        <div class="invitations-table-wrapper">
            <table class="invitations-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${invitationsData.map(inv => `
                        <tr class="${inv.used_at ? 'used' : ''}" data-invitation-id="${inv.id}">
                            <td class="invitation-email">${escapeHtml(inv.email)}</td>
                            <td class="invitation-status">
                                ${inv.used_at
                                    ? `<span class="badge badge-used">Voted</span>`
                                    : `<span class="badge badge-pending">Pending</span>`
                                }
                            </td>
                            <td class="invitation-sent">${new Date(inv.sent_at).toLocaleDateString()}</td>
                            <td class="invitation-actions">
                                <button type="button" class="btn btn-small btn-secondary copy-invitation" data-url="${escapeHtml(inv.url)}">Copy URL</button>
                                ${!inv.used_at ? `
                                    <button type="button" class="btn btn-small btn-secondary resend-invitation" data-id="${inv.id}" ${!mailConfigured ? 'disabled' : ''}>Resend</button>
                                    <button type="button" class="btn btn-small btn-outline-danger delete-invitation" data-id="${inv.id}">Delete</button>
                                ` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    // Attach event listeners
    const publicId = document.querySelector('.admin-content').dataset.publicId;
    const adminToken = document.querySelector('.admin-content').dataset.adminToken;

    container.querySelectorAll('.copy-invitation').forEach(btn => {
        btn.addEventListener('click', async () => {
            await copyToClipboard(btn.dataset.url);
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy URL', 2000);
        });
    });

    container.querySelectorAll('.resend-invitation').forEach(btn => {
        btn.addEventListener('click', async () => {
            const invitationId = btn.dataset.id;
            try {
                setButtonLoading(btn);
                await api.post(`/api/polls/${publicId}/admin/${adminToken}/invitations/${invitationId}/resend`);
                showToast('Invitation resent', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                clearButtonLoading(btn);
            }
        });
    });

    container.querySelectorAll('.delete-invitation').forEach(btn => {
        btn.addEventListener('click', async () => {
            const invitationId = btn.dataset.id;
            try {
                await api.delete(`/api/polls/${publicId}/admin/${adminToken}/invitations/${invitationId}`);
                invitationsData = invitationsData.filter(i => i.id !== parseInt(invitationId));
                renderInvitations();
                showToast('Invitation deleted', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    });
}

// ==========================================================================
// Settings Management
// ==========================================================================

function initSettings(publicId, adminToken) {
    const saveBtn = document.getElementById('saveSettings');
    if (!saveBtn) return;

    const settingInputs = [
        'settingVisibility',
        'settingVisibilityTiming',
        'settingCollectName',
        'settingAllowEditOwn',
        'settingAllowEditAny',
        'settingRandomizeOptions',
    ];

    // Track changes
    settingInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                settingsChanged = true;
                saveBtn.style.display = '';
            });
        }
    });

    // Save button
    saveBtn.addEventListener('click', async () => {
        const data = {
            visibility: document.getElementById('settingVisibility')?.value,
            visibility_timing: document.getElementById('settingVisibilityTiming')?.value,
            collect_name: document.getElementById('settingCollectName')?.checked,
            allow_edit_own: document.getElementById('settingAllowEditOwn')?.checked,
            allow_edit_any: document.getElementById('settingAllowEditAny')?.checked,
            randomize_options: document.getElementById('settingRandomizeOptions')?.checked,
        };

        try {
            setButtonLoading(saveBtn);
            await api.put(`/api/polls/${publicId}/admin/${adminToken}`, data);
            showToast('Settings saved', 'success');
            settingsChanged = false;
            saveBtn.style.display = 'none';
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            clearButtonLoading(saveBtn);
        }
    });
}
