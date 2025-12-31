/**
 * Dashboard JavaScript
 */

import { api, showToast, setButtonLoading, clearButtonLoading, basePath } from './app.js';

document.addEventListener('DOMContentLoaded', () => {
    // Handle duplicate poll buttons
    document.querySelectorAll('.duplicate-poll-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const publicId = btn.dataset.publicId;
            const adminToken = btn.dataset.adminToken;

            try {
                setButtonLoading(btn);
                const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/duplicate`);
                showToast('Poll duplicated successfully!', 'success');
                // Redirect to the new poll's admin page
                window.location.href = result.admin_url;
            } catch (err) {
                clearButtonLoading(btn);
                showToast('Failed to duplicate poll: ' + err.message, 'error');
            }
        });
    });

    // Handle resend verification email button
    const resendBtn = document.getElementById('resendVerificationBtn');
    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            try {
                setButtonLoading(resendBtn);
                await api.post('/api/auth/resend-verification');
                showToast('Verification email sent! Please check your inbox.', 'success');
                clearButtonLoading(resendBtn);
                resendBtn.textContent = 'Email Sent';
                resendBtn.disabled = true;
            } catch (err) {
                clearButtonLoading(resendBtn);
                showToast('Failed to send verification email: ' + err.message, 'error');
            }
        });
    }

    // View Data Modal
    const viewDataBtn = document.getElementById('viewDataBtn');
    const viewDataModal = document.getElementById('viewDataModal');
    const dataModalBody = document.getElementById('dataModalBody');

    if (viewDataBtn && viewDataModal) {
        viewDataBtn.addEventListener('click', async () => {
            viewDataModal.classList.add('active');
            dataModalBody.innerHTML = '<p>Loading your data...</p>';

            try {
                const result = await api.get('/api/user/data');
                dataModalBody.innerHTML = renderUserData(result);
            } catch (err) {
                dataModalBody.innerHTML = `<p class="error">Failed to load data: ${err.message}</p>`;
            }
        });

        // Close modal handlers
        viewDataModal.querySelectorAll('.modal-close, .modal-close-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', () => viewDataModal.classList.remove('active'));
        });
    }

    // Export Data
    const exportDataBtn = document.getElementById('exportDataBtn');
    if (exportDataBtn) {
        exportDataBtn.addEventListener('click', async () => {
            try {
                setButtonLoading(exportDataBtn);
                const result = await api.get('/api/user/export');

                // Download as JSON file
                const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `my-data-export-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                clearButtonLoading(exportDataBtn);
                showToast('Data exported successfully!', 'success');
            } catch (err) {
                clearButtonLoading(exportDataBtn);
                showToast('Failed to export data: ' + err.message, 'error');
            }
        });
    }

    // Change Password Modal
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const changePasswordModal = document.getElementById('changePasswordModal');
    const changePasswordForm = document.getElementById('changePasswordForm');

    if (changePasswordBtn && changePasswordModal && changePasswordForm) {
        changePasswordBtn.addEventListener('click', () => {
            changePasswordModal.classList.add('active');
            changePasswordForm.reset();
            document.getElementById('changePasswordError').style.display = 'none';
        });

        // Close modal handlers
        changePasswordModal.querySelectorAll('.modal-close, .modal-close-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', () => changePasswordModal.classList.remove('active'));
        });

        // Handle form submission
        changePasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const errorDiv = document.getElementById('changePasswordError');
            const submitBtn = document.getElementById('changePasswordSubmit');

            // Client-side validation
            if (newPassword !== confirmPassword) {
                errorDiv.textContent = 'New passwords do not match';
                errorDiv.style.display = 'block';
                return;
            }

            if (newPassword.length < 8) {
                errorDiv.textContent = 'New password must be at least 8 characters';
                errorDiv.style.display = 'block';
                return;
            }

            try {
                setButtonLoading(submitBtn);
                errorDiv.style.display = 'none';

                await api.put('/api/auth/password', {
                    current_password: currentPassword,
                    new_password: newPassword,
                });

                clearButtonLoading(submitBtn);
                changePasswordModal.classList.remove('active');
                changePasswordForm.reset();
                showToast('Password changed successfully!', 'success');
            } catch (err) {
                clearButtonLoading(submitBtn);
                errorDiv.textContent = err.message || 'Failed to change password';
                errorDiv.style.display = 'block';
            }
        });
    }

    // Delete Account Modal
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    const deleteAccountModal = document.getElementById('deleteAccountModal');
    const deleteAccountBody = document.getElementById('deleteAccountBody');

    if (deleteAccountBtn && deleteAccountModal) {
        deleteAccountBtn.addEventListener('click', async () => {
            deleteAccountModal.classList.add('active');
            deleteAccountBody.innerHTML = '<p>Loading...</p>';

            try {
                const preview = await api.get('/api/user/deletion-preview');
                deleteAccountBody.innerHTML = renderDeletionPreview(preview);
                setupDeletionHandlers(deleteAccountModal, preview);
            } catch (err) {
                deleteAccountBody.innerHTML = `<p class="error">Failed to load: ${err.message}</p>`;
            }
        });

        // Close modal handlers
        deleteAccountModal.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
            el.addEventListener('click', () => deleteAccountModal.classList.remove('active'));
        });
    }
});

/**
 * Render user data for the view data modal
 */
function renderUserData(data) {
    let html = '<div class="user-data-view">';

    // Profile section
    html += `
        <h3>Profile Information</h3>
        <table class="data-table">
            <tr><td>Email</td><td>${escapeHtml(data.user.email)}</td></tr>
            <tr><td>Name</td><td>${escapeHtml(data.user.name)}</td></tr>
            <tr><td>Account Created</td><td>${formatDate(data.user.created_at)}</td></tr>
            <tr><td>Email Verified</td><td>${data.user.email_verified_at ? formatDate(data.user.email_verified_at) : 'Not verified'}</td></tr>
        </table>
    `;

    // Responses section
    html += `<h3>Your Poll Responses (${data.responses.length})</h3>`;
    if (data.responses.length === 0) {
        html += '<p>You have not submitted any poll responses while logged in.</p>';
    } else {
        html += '<div class="responses-list">';
        for (const response of data.responses) {
            html += `
                <div class="response-card ${response.status === 'withdrawn' ? 'withdrawn' : ''}">
                    <h4>${response.poll ? escapeHtml(response.poll.title) : 'Unknown Poll'}</h4>
                    <p class="response-meta">
                        <span class="status status-${response.status}">${response.status}</span>
                        Submitted: ${formatDate(response.created_at)}
                        ${response.withdrawn_at ? `| Withdrawn: ${formatDate(response.withdrawn_at)}` : ''}
                    </p>
                    ${response.voter_name ? `<p>Voter Name: ${escapeHtml(response.voter_name)}</p>` : ''}
                    ${response.ip_address ? `<p>IP Address: ${escapeHtml(response.ip_address)}</p>` : ''}
                    ${response.answers.length > 0 ? `
                        <details>
                            <summary>View Answers (${response.answers.length})</summary>
                            <ul>
                                ${response.answers.map(a => `<li><strong>${escapeHtml(a.question_text || 'Unknown')}</strong>: ${JSON.stringify(a.answer_value)}</li>`).join('')}
                            </ul>
                        </details>
                    ` : ''}
                </div>
            `;
        }
        html += '</div>';
    }

    // Activity logs section
    html += `<h3>Activity Logs (${data.activity_logs.length})</h3>`;
    if (data.activity_logs.length === 0) {
        html += '<p>No activity logs found.</p>';
    } else {
        html += '<table class="data-table"><thead><tr><th>Action</th><th>Date</th><th>IP</th></tr></thead><tbody>';
        for (const log of data.activity_logs.slice(0, 50)) {
            html += `
                <tr>
                    <td>${escapeHtml(log.action)}</td>
                    <td>${formatDate(log.created_at)}</td>
                    <td>${log.ip_address ? escapeHtml(log.ip_address) : '-'}</td>
                </tr>
            `;
        }
        html += '</tbody></table>';
        if (data.activity_logs.length > 50) {
            html += `<p class="text-muted">Showing first 50 of ${data.activity_logs.length} logs.</p>`;
        }
    }

    // Privacy notice
    html += `
        <div class="privacy-notice">
            <h4>About This Data</h4>
            <p>${escapeHtml(data.data_collected.description)}</p>
            <p><strong>IP Addresses:</strong> ${escapeHtml(data.data_collected.ip_addresses)}</p>
        </div>
    `;

    html += '</div>';
    return html;
}

/**
 * Render deletion preview
 */
function renderDeletionPreview(preview) {
    if (preview.is_sysadmin) {
        return `
            <div class="warning-box">
                <p><strong>Cannot delete sysadmin accounts.</strong></p>
                <p>Please have another administrator remove your sysadmin role first.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        `;
    }

    let html = `
        <div class="warning-box">
            <p><strong>Warning:</strong> This action cannot be undone.</p>
        </div>
        <p>You have <strong>${preview.poll_count}</strong> poll(s) that will be affected:</p>
    `;

    if (preview.polls.length > 0) {
        html += '<ul class="poll-list">';
        for (const poll of preview.polls) {
            html += `<li>${escapeHtml(poll.title)} (${poll.response_count} responses)</li>`;
        }
        html += '</ul>';
    }

    html += `
        <form id="deleteAccountForm" class="delete-account-form">
            <div class="form-group">
                <label for="deletePassword">Confirm your password:</label>
                <input type="password" id="deletePassword" name="password" required class="form-control">
            </div>
            <div class="form-group">
                <label>What should happen to your polls?</label>
                <div class="poll-action-buttons">
                    <button type="button" class="btn btn-danger" data-action="delete_all">Delete All Polls</button>
                    <button type="button" class="btn btn-warning" data-action="keep_all">Keep Polls (Unlink from Account)</button>
                </div>
                <p class="help-text">
                    "Delete All" removes your polls and all their responses permanently.<br>
                    "Keep Polls" unlinks them from your account but keeps them accessible via admin links.
                </p>
            </div>
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
        </div>
    `;

    return html;
}

/**
 * Setup deletion handlers
 */
function setupDeletionHandlers(modal, preview) {
    const form = modal.querySelector('#deleteAccountForm');
    if (!form) return;

    const actionButtons = form.querySelectorAll('[data-action]');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const password = form.querySelector('#deletePassword').value;
            if (!password) {
                showToast('Please enter your password', 'error');
                return;
            }

            const pollAction = btn.dataset.action;
            const confirmed = confirm(
                pollAction === 'delete_all'
                    ? `Are you sure you want to delete your account and ALL ${preview.poll_count} polls? This cannot be undone.`
                    : `Are you sure you want to delete your account? Your ${preview.poll_count} polls will be kept but unlinked from your account.`
            );

            if (!confirmed) return;

            try {
                setButtonLoading(btn);
                await api.delete('/api/user', {
                    password: password,
                    poll_action: pollAction,
                });

                showToast('Account deleted successfully', 'success');
                window.location.href = basePath() + '/';
            } catch (err) {
                clearButtonLoading(btn);
                showToast('Failed to delete account: ' + err.message, 'error');
            }
        });
    });

    // Close button
    modal.querySelectorAll('.modal-close-btn').forEach(el => {
        el.addEventListener('click', () => modal.classList.remove('active'));
    });
}

/**
 * Escape HTML
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Format date
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString();
}
