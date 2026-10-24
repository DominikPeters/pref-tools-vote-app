import { tUi, tUiHtml, uiTranslator } from './i18n.js';
/**
 * Dashboard JavaScript
 */

import { api, showToast, setButtonLoading, clearButtonLoading, basePath } from './app.js';

document.addEventListener('DOMContentLoaded', () => {
    // Check if just verified
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('verified') === '1') {
        showToast(tUi('js_email_verified_successfully'), 'success');
        // Clean up URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }

    // Handle duplicate poll buttons
    document.querySelectorAll('.duplicate-poll-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const publicId = btn.dataset.publicId;
            const adminToken = btn.dataset.adminToken;

            try {
                setButtonLoading(btn);
                const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/duplicate`);
                showToast(tUi('js_poll_duplicated_successfully'), 'success');
                // Redirect to the new poll's admin page
                window.location.href = result.admin_url;
            } catch (err) {
                clearButtonLoading(btn);
                showToast(tUi('js_failed_to_duplicate_poll_error', { error: err.message }), 'error');
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
                showToast(tUi('js_verification_email_sent_please_check_your_inbox'), 'success');
                clearButtonLoading(resendBtn);
                resendBtn.textContent = tUi('js_email_sent');
                resendBtn.disabled = true;
            } catch (err) {
                clearButtonLoading(resendBtn);
                showToast(tUi('js_failed_to_send_verification_email_error', { error: err.message }), 'error');
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
            dataModalBody.innerHTML = `<p>${tUiHtml('js_loading_your_data')}</p>`;

            try {
                const result = await api.get('/api/user/data');
                dataModalBody.innerHTML = renderUserData(result);
            } catch (err) {
                dataModalBody.innerHTML = `<p class="error">${tUiHtml('js_failed_to_load_data_error', { error: err.message })}</p>`;
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
                showToast(tUi('js_data_exported_successfully'), 'success');
            } catch (err) {
                clearButtonLoading(exportDataBtn);
                showToast(tUi('js_failed_to_export_data_error', { error: err.message }), 'error');
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
                errorDiv.textContent = tUi('js_new_passwords_do_not_match');
                errorDiv.style.display = 'block';
                return;
            }

            if (newPassword.length < 8) {
                errorDiv.textContent = tUi('js_new_password_must_be_at_least_8_characters');
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
                showToast(tUi('js_password_changed_successfully'), 'success');
            } catch (err) {
                clearButtonLoading(submitBtn);
                errorDiv.textContent = err.message || tUi('js_failed_to_change_password');
                errorDiv.style.display = 'block';
            }
        });
    }

    // Change Name Modal
    const changeNameBtn = document.getElementById('changeNameBtn');
    const changeNameModal = document.getElementById('changeNameModal');
    const changeNameForm = document.getElementById('changeNameForm');

    if (changeNameBtn && changeNameModal && changeNameForm) {
        changeNameBtn.addEventListener('click', () => {
            changeNameModal.classList.add('active');
            document.getElementById('changeNameError').style.display = 'none';
        });

        // Close modal handlers
        changeNameModal.querySelectorAll('.modal-close, .modal-close-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', () => changeNameModal.classList.remove('active'));
        });

        // Handle form submission
        changeNameForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const newName = document.getElementById('newName').value.trim();
            const errorDiv = document.getElementById('changeNameError');
            const submitBtn = document.getElementById('changeNameSubmit');

            // Client-side validation
            if (!newName) {
                errorDiv.textContent = tUi('js_please_enter_a_name');
                errorDiv.style.display = 'block';
                return;
            }

            try {
                setButtonLoading(submitBtn);
                errorDiv.style.display = 'none';

                await api.put('/api/user/name', {
                    name: newName,
                });

                clearButtonLoading(submitBtn);
                changeNameModal.classList.remove('active');
                showToast(tUi('js_name_changed_successfully'), 'success');
                // Reload to update the displayed name
                location.reload();
            } catch (err) {
                clearButtonLoading(submitBtn);
                errorDiv.textContent = err.message || tUi('js_failed_to_change_name');
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
            deleteAccountBody.innerHTML = `<p>${tUiHtml('js_loading')}</p>`;

            try {
                const preview = await api.get('/api/user/deletion-preview');
                deleteAccountBody.innerHTML = renderDeletionPreview(preview);
                setupDeletionHandlers(deleteAccountModal, preview);
            } catch (err) {
                deleteAccountBody.innerHTML = `<p class="error">${tUiHtml('js_failed_to_load_error', { error: err.message })}</p>`;
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
        <h3>${tUiHtml('js_profile_information')}</h3>
        <table class="data-table">
            <tr><td>${tUiHtml('js_email')}</td><td>${escapeHtml(data.user.email)}</td></tr>
            <tr><td>${tUiHtml('js_name')}</td><td>${escapeHtml(data.user.name)}</td></tr>
            <tr><td>${tUiHtml('js_account_created')}</td><td>${formatDate(data.user.created_at)}</td></tr>
            <tr><td>${tUiHtml('js_email_verified')}</td><td>${data.user.email_verified_at ? formatDate(data.user.email_verified_at) : tUiHtml('js_not_verified')}</td></tr>
        </table>
    `;

    // Responses section
    html += `<h3>${tUiHtml('js_your_poll_responses_count', { count: data.responses.length })}</h3>`;
    if (data.responses.length === 0) {
        html += `<p>${tUiHtml('js_dashboard_no_responses')}</p>`;
    } else {
        html += '<div class="responses-list">';
        for (const response of data.responses) {
            html += `
                <div class="response-card ${response.status === 'withdrawn' ? 'withdrawn' : ''}">
                    <h4>${response.poll ? escapeHtml(response.poll.title) : tUiHtml('js_unknown_poll')}</h4>
                    <p class="response-meta">
                        <span class="status status-${response.status}">${tUiHtml(response.status === 'withdrawn' ? 'js_response_withdrawn' : 'js_response_active')}</span>
                        ${tUiHtml('js_submitted_date', { date: formatDate(response.created_at) })}
                        ${response.withdrawn_at ? `${tUiHtml('js_withdrawn_date', { date: formatDate(response.withdrawn_at) })}` : ''}
                    </p>
                    ${response.voter_name ? `<p>${tUiHtml('js_voter_name_name', { name: response.voter_name })}</p>` : ''}
                    ${response.ip_address ? `<p>${tUiHtml('js_ip_address_ip', { ip: response.ip_address })}</p>` : ''}
                    ${response.answers.length > 0 ? `
                        <details>
                            <summary>${tUiHtml('js_dashboard_view_answers', { count: response.answers.length })}</summary>
                            <ul>
                                ${response.answers.map(a => `<li><strong>${escapeHtml(a.question_text || tUi('js_unknown'))}</strong>: ${escapeHtml(JSON.stringify(a.answer_value))}</li>`).join('')}
                            </ul>
                        </details>
                    ` : ''}
                </div>
            `;
        }
        html += '</div>';
    }

    // Activity logs section
    html += `<h3>${tUiHtml('js_activity_logs_count', { count: data.activity_logs.length })}</h3>`;
    if (data.activity_logs.length === 0) {
        html += `<p>${tUiHtml('js_no_activity_logs_found')}</p>`;
    } else {
        html += `<table class="data-table"><thead><tr><th>${tUiHtml('js_action')}</th><th>${tUiHtml('js_date')}</th><th>${tUiHtml('js_ip')}</th></tr></thead><tbody>`;
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
            html += `<p class="text-muted">${tUiHtml('js_dashboard_logs_truncated', { limit: 50, count: data.activity_logs.length })}</p>`;
        }
    }

    // Privacy notice
    html += `
        <div class="privacy-notice">
            <h4>${tUiHtml('js_about_this_data')}</h4>
            <p>${escapeHtml(data.data_collected.description)}</p>
            <p><strong>${tUiHtml('js_ip_addresses')}</strong> ${escapeHtml(data.data_collected.ip_addresses)}</p>
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
                <p><strong>${tUiHtml('js_cannot_delete_sysadmin_accounts')}</strong></p>
                <p>${tUiHtml('js_dashboard_sysadmin_removal_required')}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">${tUiHtml('js_close')}</button>
            </div>
        `;
    }

    let html = `
        <div class="warning-box">
            <p><strong>${tUiHtml('js_warning_this_action_cannot_be_undone')}</strong></p>
        </div>
        <p>${tUiHtml('js_dashboard_affected_polls', { count: preview.poll_count })}</p>
    `;

    if (preview.polls.length > 0) {
        html += '<ul class="poll-list">';
        for (const poll of preview.polls) {
            html += `<li>${escapeHtml(poll.title)} (${tUiHtml('response_count', { count: poll.response_count })})</li>`;
        }
        html += '</ul>';
    }

    html += `
        <form id="deleteAccountForm" class="delete-account-form">
            <div class="form-group">
                <label for="deletePassword">${tUiHtml('js_confirm_your_password')}</label>
                <input type="password" id="deletePassword" name="password" required class="form-control">
            </div>
            <div class="form-group">
                <label>${tUiHtml('js_what_should_happen_to_your_polls')}</label>
                <div class="poll-action-buttons">
                    <button type="button" class="btn btn-danger" data-action="delete_all">${tUiHtml('js_delete_all_polls')}</button>
                    <button type="button" class="btn btn-warning" data-action="keep_all">${tUiHtml('js_keep_polls_unlink_from_account')}</button>
                </div>
                <p class="help-text">
                    ${tUiHtml('js_dashboard_delete_polls_explanation')}<br>
                    ${tUiHtml('js_dashboard_keep_polls_explanation')}
                </p>
            </div>
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn">${tUiHtml('cancel')}</button>
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
                showToast(tUi('js_please_enter_your_password'), 'error');
                return;
            }

            const pollAction = btn.dataset.action;
            const confirmed = confirm(
                pollAction === 'delete_all'
                    ? tUi('js_dashboard_delete_account_and_polls_confirm', { count: preview.poll_count })
                    : tUi('js_dashboard_delete_account_keep_polls_confirm', { count: preview.poll_count })
            );

            if (!confirmed) return;

            try {
                setButtonLoading(btn);
                await api.delete('/api/user', {
                    password: password,
                    poll_action: pollAction,
                });

                showToast(tUi('js_account_deleted_successfully'), 'success');
                window.location.href = basePath() + '/';
            } catch (err) {
                clearButtonLoading(btn);
                showToast(tUi('js_failed_to_delete_account_error', { error: err.message }), 'error');
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
    return date.toLocaleString(uiTranslator.locale);
}
