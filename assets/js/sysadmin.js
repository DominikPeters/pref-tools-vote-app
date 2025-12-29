/**
 * Sysadmin Dashboard JavaScript
 */

import { showConfirmModal, showToast } from './app.js';

const BASE_PATH = window.BASE_PATH || '';

// State
let currentPage = {
    users: 0,
    polls: 0,
    logs: 0
};
const PAGE_SIZE = 50;

// Initialize based on current page
document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;

    if (path.endsWith('/sysadmin/users')) {
        loadUsers();
    } else if (path.endsWith('/sysadmin/polls')) {
        loadPolls();
    } else if (path.endsWith('/sysadmin/logs')) {
        loadLogs();
        initModal();
    } else if (path.endsWith('/sysadmin/config')) {
        initConfig();
    }
});

// ============================================
// API Helpers
// ============================================

async function apiRequest(endpoint, options = {}) {
    const url = `${BASE_PATH}/api/sysadmin${endpoint}`;

    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ============================================
// Users
// ============================================

async function loadUsers(offset = 0) {
    const tbody = document.querySelector('#usersTable tbody');
    const countEl = document.getElementById('userCount');
    const paginationEl = document.getElementById('usersPagination');

    try {
        const data = await apiRequest(`/users?limit=${PAGE_SIZE}&offset=${offset}`);

        countEl.textContent = `${data.total} total`;
        currentPage.users = offset;

        // Clear and populate table
        tbody.innerHTML = '';
        const template = document.getElementById('userRowTemplate');

        for (const user of data.users) {
            const row = template.content.cloneNode(true);
            const tr = row.querySelector('tr');

            tr.dataset.userId = user.id;
            row.querySelector('.user-id').textContent = user.id;
            row.querySelector('.user-email').textContent = user.email;

            const select = row.querySelector('.role-select');
            select.value = user.role;
            select.addEventListener('change', () => updateUserRole(user.id, select.value));

            row.querySelector('.user-created').textContent = formatDate(user.created_at);

            const deleteBtn = row.querySelector('.delete-user-btn');
            deleteBtn.addEventListener('click', () => deleteUser(user.id, user.email));

            tbody.appendChild(row);
        }

        // Render pagination
        renderPagination(paginationEl, data.total, offset, PAGE_SIZE, loadUsers);

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="5" class="loading">Error loading users: ${error.message}</td></tr>`;
    }
}

async function updateUserRole(userId, newRole) {
    try {
        await apiRequest(`/users/${userId}`, {
            method: 'PUT',
            body: JSON.stringify({ role: newRole })
        });
    } catch (error) {
        showToast(`Failed to update role: ${error.message}`, 'error');
        loadUsers(currentPage.users);
    }
}

async function deleteUser(userId, email) {
    const confirmed = await showConfirmModal({
        title: 'Delete User',
        message: `Are you sure you want to delete user "${email}"? This action cannot be undone.`,
        confirmText: 'Delete User',
    });

    if (!confirmed) return;

    try {
        await apiRequest(`/users/${userId}`, { method: 'DELETE' });
        loadUsers(currentPage.users);
    } catch (error) {
        showToast(`Failed to delete user: ${error.message}`, 'error');
    }
}

// ============================================
// Polls
// ============================================

async function loadPolls(offset = 0) {
    const tbody = document.querySelector('#pollsTable tbody');
    const countEl = document.getElementById('pollCount');
    const paginationEl = document.getElementById('pollsPagination');

    try {
        const data = await apiRequest(`/polls?limit=${PAGE_SIZE}&offset=${offset}`);

        countEl.textContent = `${data.total} total`;
        currentPage.polls = offset;

        // Clear and populate table
        tbody.innerHTML = '';
        const template = document.getElementById('pollRowTemplate');

        for (const poll of data.polls) {
            const row = template.content.cloneNode(true);
            const tr = row.querySelector('tr');

            tr.dataset.pollId = poll.id;
            row.querySelector('.poll-id').textContent = poll.id;

            const titleLink = row.querySelector('.poll-link');
            titleLink.textContent = poll.title;
            titleLink.href = `${BASE_PATH}/${poll.public_id}`;

            row.querySelector('.poll-owner').textContent = poll.owner_email || '(anonymous)';

            const statusSpan = row.querySelector('.status');
            statusSpan.textContent = poll.status;
            statusSpan.classList.add(`status-${poll.status}`);

            row.querySelector('.poll-responses').textContent = poll.response_count;
            row.querySelector('.poll-created').textContent = formatDate(poll.created_at);

            const deleteBtn = row.querySelector('.delete-poll-btn');
            deleteBtn.addEventListener('click', () => deletePoll(poll.id, poll.title));

            tbody.appendChild(row);
        }

        // Render pagination
        renderPagination(paginationEl, data.total, offset, PAGE_SIZE, loadPolls);

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading">Error loading polls: ${error.message}</td></tr>`;
    }
}

async function deletePoll(pollId, title) {
    const confirmed = await showConfirmModal({
        title: 'Delete Poll',
        message: `Are you sure you want to delete poll "${title}"? This will also delete all responses. This action cannot be undone.`,
        confirmText: 'Delete Poll',
    });

    if (!confirmed) return;

    try {
        await apiRequest(`/polls/${pollId}`, { method: 'DELETE' });
        loadPolls(currentPage.polls);
    } catch (error) {
        showToast(`Failed to delete poll: ${error.message}`, 'error');
    }
}

// ============================================
// Logs
// ============================================

async function loadLogs(offset = 0) {
    const tbody = document.querySelector('#logsTable tbody');
    const countEl = document.getElementById('logCount');
    const paginationEl = document.getElementById('logsPagination');

    try {
        const data = await apiRequest(`/logs?limit=${PAGE_SIZE}&offset=${offset}`);

        countEl.textContent = `${data.total} total`;
        currentPage.logs = offset;

        // Clear and populate table
        tbody.innerHTML = '';
        const template = document.getElementById('logRowTemplate');

        for (const log of data.logs) {
            const row = template.content.cloneNode(true);

            row.querySelector('.log-time').textContent = formatDateTime(log.created_at);
            row.querySelector('.log-action code').textContent = log.action;
            row.querySelector('.log-user').textContent = log.user_email || (log.user_id ? `User #${log.user_id}` : '-');
            row.querySelector('.log-poll').textContent = log.poll_id || '-';
            row.querySelector('.log-ip').textContent = log.ip_address || '-';

            const viewBtn = row.querySelector('.view-data-btn');
            if (log.data) {
                viewBtn.style.display = 'inline-flex';
                viewBtn.addEventListener('click', () => showLogData(log.data));
            }

            tbody.appendChild(row);
        }

        // Render pagination
        renderPagination(paginationEl, data.total, offset, PAGE_SIZE, loadLogs);

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="loading">Error loading logs: ${error.message}</td></tr>`;
    }
}

function showLogData(data) {
    const modal = document.getElementById('dataModal');
    const content = document.getElementById('dataModalContent');
    content.textContent = JSON.stringify(data, null, 2);
    modal.style.display = 'flex';
}

function initModal() {
    const modal = document.getElementById('dataModal');
    const closeBtn = modal.querySelector('.modal-close');

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });
}

// ============================================
// Helpers
// ============================================

function formatDate(isoString) {
    if (!isoString) return '-';
    const date = new Date(isoString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(isoString) {
    if (!isoString) return '-';
    const date = new Date(isoString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function renderPagination(container, total, offset, pageSize, loadFn) {
    const totalPages = Math.ceil(total / pageSize);
    const currentPageNum = Math.floor(offset / pageSize) + 1;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <button class="prev-btn" ${offset === 0 ? 'disabled' : ''}>Previous</button>
        <span class="page-info">Page ${currentPageNum} of ${totalPages}</span>
        <button class="next-btn" ${offset + pageSize >= total ? 'disabled' : ''}>Next</button>
    `;

    container.querySelector('.prev-btn')?.addEventListener('click', () => {
        loadFn(Math.max(0, offset - pageSize));
    });

    container.querySelector('.next-btn')?.addEventListener('click', () => {
        loadFn(offset + pageSize);
    });
}

// ============================================
// Config
// ============================================

// Track which secret fields have existing values
let secretFieldsWithValues = new Set();

async function initConfig() {
    await loadConfig();
    initConfigForm();
    initSecretFields();
    initTestEmail();
}

async function loadConfig() {
    try {
        const data = await apiRequest('/settings');
        populateConfigForm(data.settings);
    } catch (error) {
        showToast(`Failed to load settings: ${error.message}`, 'error');
    }
}

function populateConfigForm(settings) {
    const form = document.getElementById('configForm');

    for (const [key, value] of Object.entries(settings)) {
        const inputName = key;
        const input = form.querySelector(`[name="${inputName}"]`);

        if (!input) continue;

        if (input.type === 'checkbox') {
            input.checked = value === '1' || value === 'true' || value === true;
        } else if (input.dataset.secret === 'true') {
            // Handle masked secret fields
            if (value && !isMaskedValue(value)) {
                // Actual value (shouldn't happen from API)
                input.value = value;
            } else if (value && isMaskedValue(value)) {
                // Masked value - show it and track that a value exists
                input.value = value;
                input.classList.add('has-value');
                secretFieldsWithValues.add(inputName);
                // Show clear button
                const clearBtn = input.parentElement.querySelector('.clear-secret-btn');
                if (clearBtn) clearBtn.style.display = 'inline-flex';
            } else {
                input.value = '';
                input.classList.remove('has-value');
                secretFieldsWithValues.delete(inputName);
            }
        } else {
            input.value = value || '';
        }
    }
}

function isMaskedValue(value) {
    return typeof value === 'string' && value.startsWith('••');
}

function initConfigForm() {
    const form = document.getElementById('configForm');
    const saveBtn = document.getElementById('saveConfigBtn');
    const saveStatus = document.getElementById('saveStatus');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        saveBtn.disabled = true;
        saveStatus.textContent = 'Saving...';
        saveStatus.className = 'status-message loading';

        try {
            const settings = collectFormSettings(form);
            await apiRequest('/settings', {
                method: 'PUT',
                body: JSON.stringify({ settings })
            });

            saveStatus.textContent = 'Settings saved successfully';
            saveStatus.className = 'status-message success';

            // Reload to get fresh masked values
            await loadConfig();

            setTimeout(() => {
                saveStatus.textContent = '';
            }, 3000);

        } catch (error) {
            saveStatus.textContent = `Error: ${error.message}`;
            saveStatus.className = 'status-message error';
        } finally {
            saveBtn.disabled = false;
        }
    });
}

function collectFormSettings(form) {
    const settings = {};
    const formData = new FormData(form);

    // Get all inputs including unchecked checkboxes
    const inputs = form.querySelectorAll('input, select, textarea');

    for (const input of inputs) {
        const name = input.name;
        if (!name) continue;

        if (input.type === 'checkbox') {
            settings[name] = input.checked ? '1' : '0';
        } else if (input.dataset.secret === 'true') {
            // Only include secret fields if they've been modified
            const value = input.value;
            if (value && !isMaskedValue(value)) {
                // User entered a new value
                settings[name] = value;
            }
            // If masked or empty, don't include (keep existing value)
        } else {
            settings[name] = input.value;
        }
    }

    return settings;
}

function initSecretFields() {
    // Handle clear buttons for secret fields
    document.querySelectorAll('.clear-secret-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            if (input) {
                input.value = '';
                input.classList.remove('has-value');
                input.type = 'password';
                input.focus();
                btn.style.display = 'none';
                secretFieldsWithValues.delete(input.name);
            }
        });
    });

    // When user focuses on a masked secret field, clear it for new input
    document.querySelectorAll('input[data-secret="true"]').forEach(input => {
        input.addEventListener('focus', () => {
            if (isMaskedValue(input.value)) {
                input.value = '';
                input.classList.remove('has-value');
            }
        });

        // When user leaves the field empty after clearing, restore masked indicator
        input.addEventListener('blur', () => {
            if (input.value === '' && secretFieldsWithValues.has(input.name)) {
                // User cleared it but didn't enter new value - will keep existing on save
                input.placeholder = 'Leave empty to keep existing';
            }
        });
    });
}

function initTestEmail() {
    const testBtn = document.getElementById('testEmailBtn');
    const testStatus = document.getElementById('testEmailStatus');

    if (!testBtn) return;

    testBtn.addEventListener('click', async () => {
        testBtn.disabled = true;
        testStatus.textContent = 'Sending...';
        testStatus.className = 'status-message loading';

        try {
            const data = await apiRequest('/settings/test-email', {
                method: 'POST'
            });

            testStatus.textContent = data.message || 'Test email sent!';
            testStatus.className = 'status-message success';

        } catch (error) {
            testStatus.textContent = `Error: ${error.message}`;
            testStatus.className = 'status-message error';
        } finally {
            testBtn.disabled = false;
        }
    });
}
