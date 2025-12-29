<?php
use App\Services\TurnstileService;

$title = 'Login - Pref.Tools Vote';
$turnstileSiteKey = TurnstileService::getSiteKey();
$turnstileEnabled = TurnstileService::isConfigured();
ob_start();
?>

<div class="auth-container">
    <div class="container">
        <div class="auth-card card">
            <div class="auth-tabs">
                <button type="button" class="auth-tab active" data-tab="login">Login</button>
                <button type="button" class="auth-tab" data-tab="register">Register</button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="auth-form active" data-tab="login">
                <h2>Welcome Back</h2>

                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <input type="email" id="loginEmail" name="email" required>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>

                <div class="form-error" id="loginError"></div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="auth-form" data-tab="register">
                <h2>Create Account</h2>

                <div class="form-group">
                    <label for="registerEmail">Email</label>
                    <input type="email" id="registerEmail" name="email" required>
                </div>

                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <input type="password" id="registerPassword" name="password" required minlength="8">
                    <small class="form-hint">At least 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="registerPasswordConfirm">Confirm Password</label>
                    <input type="password" id="registerPasswordConfirm" name="password_confirm" required>
                </div>

                <div class="form-error" id="registerError"></div>

                <?php if ($turnstileEnabled): ?>
                <div id="turnstileContainer" class="turnstile-container"></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <p class="auth-note">
                Creating an account is optional. You can create polls without an account.
            </p>
        </div>
    </div>
</div>

<?php if ($turnstileEnabled): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');
    const basePath = window.BASE_PATH || '';

    // Turnstile integration
    const turnstileEnabled = <?= $turnstileEnabled ? 'true' : 'false' ?>;
    const turnstileSiteKey = '<?= e($turnstileSiteKey) ?>';
    let turnstileWidgetId = null;
    let turnstileToken = null;

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
                    headers: { 'Content-Type': 'application/json' },
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

            tabs.forEach(t => t.classList.remove('active'));
            forms.forEach(f => f.classList.remove('active'));

            this.classList.add('active');
            document.querySelector(`.auth-form[data-tab="${target}"]`).classList.add('active');
        });
    });

    // Login form
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const errorEl = document.getElementById('loginError');
        errorEl.textContent = '';

        try {
            const response = await fetch(basePath + '/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                errorEl.textContent = data.error || 'Login failed';
            }
        } catch (err) {
            errorEl.textContent = 'An error occurred. Please try again.';
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
            errorEl.textContent = 'Passwords do not match';
            return;
        }

        // Check Turnstile if enabled
        if (turnstileEnabled && !turnstileToken) {
            errorEl.textContent = 'Please wait for security verification to complete';
            return;
        }

        try {
            const requestBody = {
                email: document.getElementById('registerEmail').value,
                password: password
            };

            // Include Turnstile token if available
            if (turnstileToken) {
                requestBody.turnstile_token = turnstileToken;
            }

            const response = await fetch(basePath + '/api/auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();

            if (data.ok) {
                const redirectTo = returnUrl ? basePath + returnUrl : basePath + '/dashboard';
                await claimAndRedirect(redirectTo);
            } else {
                errorEl.textContent = data.error || 'Registration failed';
                // Reset Turnstile on error
                if (turnstileWidgetId !== null && window.turnstile) {
                    turnstile.reset(turnstileWidgetId);
                    turnstileToken = null;
                }
            }
        } catch (err) {
            errorEl.textContent = 'An error occurred. Please try again.';
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
