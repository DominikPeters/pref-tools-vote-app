import { tUi } from './i18n.js';

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');
    const basePath = window.BASE_PATH || '';
    const authMessage = document.getElementById('authMessage');

    // CSRF helper
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content;
    }

    // Turnstile integration
    const turnstileEnabled = window.AUTH_CONFIG.turnstileEnabled;
    const turnstileSiteKey = window.AUTH_CONFIG.turnstileSiteKey;
    let turnstileWidgetId = null;
    let turnstileToken = null;
    let turnstileForgotWidgetId = null;
    let turnstileForgotToken = null;

    function initTurnstile() {
        if (!turnstileEnabled || !window.turnstile || turnstileWidgetId !== null) return;

        const container = document.getElementById('turnstileContainer');
        if (!container) return;

        turnstileWidgetId = turnstile.render(container, {
            sitekey: turnstileSiteKey,
            callback: function(token) {
                turnstileToken = token;
            },
            'refresh-expired': 'auto',
            size: 'invisible'
        });
    }

    function initTurnstileForgot() {
        if (!turnstileEnabled || !window.turnstile || turnstileForgotWidgetId !== null) return;

        const container = document.getElementById('turnstileForgotContainer');
        if (!container) return;

        turnstileForgotWidgetId = turnstile.render(container, {
            sitekey: turnstileSiteKey,
            callback: function(token) {
                turnstileForgotToken = token;
            },
            'refresh-expired': 'auto',
            size: 'invisible'
        });
    }

    // Initialize Turnstile when register email field gains focus
    const registerEmailInput = document.getElementById('registerEmail');
    if (registerEmailInput && turnstileEnabled) {
        registerEmailInput.addEventListener('focus', function() {
            // Wait for Turnstile script to load if needed
            if (window.turnstile) {
                initTurnstile();
            } else {
                // Check periodically for script to load
                const checkInterval = setInterval(function() {
                    if (window.turnstile) {
                        clearInterval(checkInterval);
                        initTurnstile();
                    }
                }, 100);
                // Stop checking after 10 seconds
                setTimeout(function() { clearInterval(checkInterval); }, 10000);
            }
        }, { once: true });
    }

    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const returnUrl = urlParams.get('return');
    const claimPollId = urlParams.get('claim');
    const verifyToken = urlParams.get('verify_token');
    const resetToken = urlParams.get('reset_token');

    // Show message helper
    function showMessage(text, type) {
        authMessage.textContent = text;
        authMessage.className = 'auth-message ' + type;
        authMessage.style.display = 'block';
    }

    // Show specific form
    function showForm(tabName) {
        tabs.forEach(t => t.classList.remove('active'));
        forms.forEach(f => f.classList.remove('active'));

        const tab = document.querySelector(`.auth-tab[data-tab="${tabName}"]`);
        const form = document.querySelector(`.auth-form[data-tab="${tabName}"]`);

        if (tab) tab.classList.add('active');
        if (form) form.classList.add('active');
    }

    // Handle email verification token
    if (verifyToken) {
        (async function() {
            try {
                const response = await fetch(basePath + '/api/auth/verify-email', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ token: verifyToken })
                });

                const data = await response.json();

                if (data.ok) {
                    showMessage(tUi('js_email_verified_successfully_redirecting'), 'success');
                    setTimeout(() => {
                        window.location.href = basePath + '/dashboard';
                    }, 1500);
                } else {
                    showMessage(data.error || tUi('js_invalid_or_expired_verification_link'), 'error');
                }
            } catch (err) {
                showMessage(tUi('js_an_error_occurred_during_verification'), 'error');
            }
        })();
    }

    // Handle password reset token - show reset form
    if (resetToken) {
        showForm('reset');
    }

    // Extract admin token from return URL if it looks like an admin page
    function extractAdminToken(url) {
        const match = url && url.match(/\/([^\/]+)\/admin\/([^\/\?]+)/);
        return match ? { publicId: match[1], adminToken: match[2] } : null;
    }

    // Claim poll and redirect
    async function claimAndRedirect(redirectTo) {
        const adminInfo = extractAdminToken(returnUrl);

        // Only attempt to claim if we have both the claim param and admin info
        if (claimPollId && adminInfo && adminInfo.publicId === claimPollId) {
            try {
                await fetch(basePath + '/api/user/claim-poll', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        public_id: adminInfo.publicId,
                        admin_token: adminInfo.adminToken
                    })
                });
                // Ignore errors - poll may already be claimed or user may not have permission
            } catch (err) {
                // Ignore claim errors
            }
        }

        window.location.href = redirectTo;
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.tab;
            showForm(target);
        });
    });

    // Show forgot password form
    document.getElementById('showForgotPassword').addEventListener('click', function(e) {
        e.preventDefault();
        showForm('forgot');
        if (turnstileEnabled && window.turnstile) {
            initTurnstileForgot();
        }
    });

    // Back to login from forgot password
    document.getElementById('backToLogin').addEventListener('click', function(e) {
        e.preventDefault();
        showForm('login');
    });

    // Login form
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errorEl = document.getElementById('loginError');
        errorEl.textContent = '';

        try {
            const response = await fetch(basePath + '/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    email: document.getElementById('loginEmail').value,
                    password: document.getElementById('loginPassword').value
                })
            });

            const data = await response.json();

            if (data.ok) {
                const redirectTo = returnUrl ? basePath + returnUrl : basePath + '/dashboard';
                await claimAndRedirect(redirectTo);
            } else {
                errorEl.textContent = data.error || tUi('js_login_failed');
            }
        } catch (err) {
            errorEl.textContent = tUi('js_an_error_occurred_please_try_again');
        }
    });

    // Register form
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errorEl = document.getElementById('registerError');
        errorEl.textContent = '';

        const password = document.getElementById('registerPassword').value;
        const confirm = document.getElementById('registerPasswordConfirm').value;

        if (password !== confirm) {
            errorEl.textContent = tUi('js_passwords_do_not_match');
            return;
        }

        // Check Turnstile if enabled
        if (turnstileEnabled && !turnstileToken) {
            errorEl.textContent = tUi('security_verification_wait');
            return;
        }

        try {
            const requestBody = {
                name: document.getElementById('registerName').value,
                email: document.getElementById('registerEmail').value,
                password: password
            };

            // Include Turnstile token if available
            if (turnstileToken) {
                requestBody.turnstile_token = turnstileToken;
            }

            const response = await fetch(basePath + '/api/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();

            if (data.ok) {
                const redirectTo = returnUrl ? basePath + returnUrl : basePath + '/dashboard';
                await claimAndRedirect(redirectTo);
            } else {
                errorEl.textContent = data.error || tUi('js_registration_failed');
                // Reset Turnstile on error
                if (turnstileWidgetId !== null && window.turnstile) {
                    turnstile.reset(turnstileWidgetId);
                    turnstileToken = null;
                }
            }
        } catch (err) {
            errorEl.textContent = tUi('js_an_error_occurred_please_try_again');
        }
    });

    // Forgot password form
    document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errorEl = document.getElementById('forgotError');
        const successEl = document.getElementById('forgotSuccess');
        errorEl.textContent = '';
        successEl.textContent = '';

        try {
            const requestBody = {
                email: document.getElementById('forgotEmail').value
            };

            // Include Turnstile token if available
            if (turnstileForgotToken) {
                requestBody.turnstile_token = turnstileForgotToken;
            }

            const response = await fetch(basePath + '/api/auth/forgot-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();

            if (data.ok) {
                successEl.textContent = data.message || tUi('js_auth_reset_email_sent');
            } else {
                errorEl.textContent = data.error || tUi('js_failed_to_send_reset_email');
                // Reset Turnstile on error
                if (turnstileForgotWidgetId !== null && window.turnstile) {
                    turnstile.reset(turnstileForgotWidgetId);
                    turnstileForgotToken = null;
                }
            }
        } catch (err) {
            errorEl.textContent = tUi('js_an_error_occurred_please_try_again');
        }
    });

    // Reset password form
    document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errorEl = document.getElementById('resetError');
        errorEl.textContent = '';

        const password = document.getElementById('resetPassword').value;
        const confirm = document.getElementById('resetPasswordConfirm').value;

        if (password !== confirm) {
            errorEl.textContent = tUi('js_passwords_do_not_match');
            return;
        }

        try {
            const response = await fetch(basePath + '/api/auth/reset-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    token: resetToken,
                    password: password
                })
            });

            const data = await response.json();

            if (data.ok) {
                showMessage(tUi('js_password_reset_successfully_redirecting'), 'success');
                setTimeout(() => {
                    window.location.href = basePath + '/dashboard';
                }, 1500);
            } else {
                errorEl.textContent = data.error || tUi('js_failed_to_reset_password');
            }
        } catch (err) {
            errorEl.textContent = tUi('js_an_error_occurred_please_try_again');
        }
    });
});
