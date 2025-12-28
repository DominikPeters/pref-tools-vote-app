<?php
$title = 'Login - Pref.Tools Vote';
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

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <p class="auth-note">
                Creating an account is optional. You can create polls without an account.
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');

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
            const response = await fetch((window.BASE_PATH || '') + '/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('loginEmail').value,
                    password: document.getElementById('loginPassword').value
                })
            });

            const data = await response.json();

            if (data.ok) {
                window.location.href = (window.BASE_PATH || '') + '/dashboard';
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

        try {
            const response = await fetch((window.BASE_PATH || '') + '/api/auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('registerEmail').value,
                    password: password
                })
            });

            const data = await response.json();

            if (data.ok) {
                window.location.href = (window.BASE_PATH || '') + '/dashboard';
            } else {
                errorEl.textContent = data.error || 'Registration failed';
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
