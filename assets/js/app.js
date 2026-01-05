/**
 * Pref.Tools Vote - Main Application JavaScript
 */

// Get base path for URLs (supports subfolder deployment)
const basePath = window.BASE_PATH || '';

// API Client
export const api = {
    async request(method, url, data = null) {
        // Prepend base path if URL starts with /
        if (url.startsWith('/')) {
            url = basePath + url;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        if (csrfToken) {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        if (data) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Request failed');
        }

        return result;
    },

    get(url) {
        return this.request('GET', url);
    },

    post(url, data) {
        return this.request('POST', url, data);
    },

    put(url, data) {
        return this.request('PUT', url, data);
    },

    delete(url) {
        return this.request('DELETE', url);
    },
};

// Utility functions
export function generateTempId() {
    return 'temp_' + Math.random().toString(36).substr(2, 9);
}

export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Copy to clipboard
export async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textarea);
        return success;
    }
}

// Toast notifications
export function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = message;
    
    const bgColor = type === 'error' ? 'var(--color-danger)' : type === 'success' ? 'var(--color-success)' : 'var(--color-primary)';
    
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 24px;
        background: ${bgColor};
        color: white;
        border-radius: 8px;
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    // Style any links in the toast
    toast.querySelectorAll('a').forEach(a => {
        a.style.color = 'inherit';
        a.style.fontWeight = '600';
        a.style.textDecoration = 'underline';
    });

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Show a toast with an undo button for reversible actions
 * @param {string} message - The message to display
 * @param {Function} onUndo - Callback when undo is clicked
 * @param {number} duration - How long to show the toast (ms)
 * @returns {Object} - Object with dismiss() method
 */
export function showUndoToast(message, onUndo, duration = 5000) {
    const toast = document.createElement('div');
    toast.className = 'toast toast-undo';
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 16px;
        background: var(--color-bg-tooltip);
        color: var(--color-text-tooltip);
        border-radius: 8px;
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    `;

    const messageSpan = document.createElement('span');
    messageSpan.textContent = message;
    toast.appendChild(messageSpan);

    const undoBtn = document.createElement('button');
    undoBtn.textContent = 'Undo';
    undoBtn.style.cssText = `
        background: transparent;
        border: 1px solid var(--color-border-muted);
        color: inherit;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
    `;
    undoBtn.addEventListener('mouseenter', () => {
        undoBtn.style.background = 'rgba(128,128,128,0.1)';
    });
    undoBtn.addEventListener('mouseleave', () => {
        undoBtn.style.background = 'transparent';
    });
    toast.appendChild(undoBtn);

    document.body.appendChild(toast);

    let dismissed = false;
    let undone = false;
    let timeoutId;

    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        clearTimeout(timeoutId);
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    };

    undoBtn.addEventListener('click', () => {
        if (undone) return;
        undone = true;
        onUndo();
        dismiss();
    });

    timeoutId = setTimeout(dismiss, duration);

    return { dismiss, isUndone: () => undone };
}

/**
 * Show a confirmation modal for destructive actions
 * @param {Object} options - Modal configuration
 * @param {string} options.title - Modal title
 * @param {string} options.message - Modal message
 * @param {string} [options.confirmText='Delete'] - Confirm button text
 * @param {string} [options.cancelText='Cancel'] - Cancel button text
 * @param {boolean} [options.danger=true] - Whether to style confirm as danger
 * @returns {Promise<boolean>} - Resolves to true if confirmed, false if cancelled
 */
export function showConfirmModal({ title, message, confirmText = 'Delete', cancelText = 'Cancel', danger = true }) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-modal-overlay';

        overlay.innerHTML = `
            <div class="confirm-modal">
                <div class="confirm-modal-header">
                    <h3>${escapeHtml(title)}</h3>
                </div>
                <div class="confirm-modal-body">
                    <p>${escapeHtml(message)}</p>
                </div>
                <div class="confirm-modal-actions">
                    <button class="btn btn-secondary btn-cancel">${escapeHtml(cancelText)}</button>
                    <button class="btn ${danger ? 'btn-danger' : 'btn-primary'} btn-confirm">${escapeHtml(confirmText)}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const close = (result) => {
            overlay.remove();
            resolve(result);
        };

        overlay.querySelector('.btn-cancel').addEventListener('click', () => close(false));
        overlay.querySelector('.btn-confirm').addEventListener('click', () => close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(false);
        });

        // Focus the cancel button by default (safer)
        overlay.querySelector('.btn-cancel').focus();

        // Handle escape key
        const handleKeydown = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', handleKeydown);
                close(false);
            }
        };
        document.addEventListener('keydown', handleKeydown);
    });
}

// Add toast animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Initialize tooltips - add aria-label for accessibility
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        if (!el.hasAttribute('aria-label')) {
            el.setAttribute('aria-label', el.dataset.tooltip);
        }
    });
}

// Observe DOM for dynamically added tooltips
const tooltipObserver = new MutationObserver((mutations) => {
    mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1) { // Element node
                if (node.hasAttribute?.('data-tooltip') && !node.hasAttribute('aria-label')) {
                    node.setAttribute('aria-label', node.dataset.tooltip);
                }
                node.querySelectorAll?.('[data-tooltip]:not([aria-label])').forEach(el => {
                    el.setAttribute('aria-label', el.dataset.tooltip);
                });
            }
        });
    });
});

// Initialize common elements
document.addEventListener('DOMContentLoaded', () => {
    // Initialize tooltips
    initTooltips();
    tooltipObserver.observe(document.body, { childList: true, subtree: true });

    // Copy buttons
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            if (input) {
                const success = await copyToClipboard(input.value);
                if (success) {
                    const originalText = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(() => btn.textContent = originalText, 2000);
                }
            }
        });
    });

    // Logout form (dashboard)
    const logoutForm = document.getElementById('logoutForm');
    if (logoutForm) {
        logoutForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await api.post('/api/auth/logout');
                window.location.href = basePath + '/';
            } catch (err) {
                showToast('Logout failed', 'error');
            }
        });
    }

    // User menu dropdown
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        const trigger = userMenu.querySelector('.user-menu-trigger');
        const logoutBtn = userMenu.querySelector('.user-menu-logout');

        // Toggle dropdown on trigger click
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('open');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('open');
            }
        });

        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && userMenu.classList.contains('open')) {
                userMenu.classList.remove('open');
            }
        });

        // Handle logout
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                try {
                    await api.post('/api/auth/logout');
                    window.location.href = basePath + '/';
                } catch (err) {
                    showToast('Logout failed', 'error');
                }
            });
        }
    }
});

/**
 * Set a button to loading state
 * @param {HTMLElement} btn - The button element
 */
export function setButtonLoading(btn) {
    btn.classList.add('btn-loading');
    btn.disabled = true;
}

/**
 * Remove loading state from a button
 * @param {HTMLElement} btn - The button element
 */
export function clearButtonLoading(btn) {
    btn.classList.remove('btn-loading');
    btn.disabled = false;
}

// Export basePath for other modules
export { basePath };
