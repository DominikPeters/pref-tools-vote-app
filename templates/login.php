<?php
use App\Services\TurnstileService;

$title = __('js_login_pref_tools_vote');
$extraJs = ['/assets/js/auth.js'];
$turnstileSiteKey = TurnstileService::getSiteKey();
$turnstileEnabled = TurnstileService::isConfigured();
ob_start();
?>

<div class="auth-container">
    <div class="container">
        <div class="auth-card card">
            <!-- Success/Error message for verification/reset -->
            <div id="authMessage" class="auth-message" style="display: none;"></div>

            <div class="auth-tabs">
                <button type="button" class="auth-tab active" data-tab="login"><?= e(__('login')) ?></button>
                <button type="button" class="auth-tab" data-tab="register"><?= e(__('js_register')) ?></button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="auth-form active" data-tab="login" method="post">
                <h2><?= e(__('js_welcome_back')) ?></h2>

                <div class="form-group">
                    <label for="loginEmail"><?= e(__('js_email')) ?></label>
                    <input type="email" id="loginEmail" name="email" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="loginPassword"><?= e(__('js_password')) ?></label>
                    <input type="password" id="loginPassword" name="password" required autocomplete="current-password">
                </div>

                <div class="form-error" id="loginError"></div>

                <button type="submit" class="btn btn-primary btn-block"><?= e(__('login')) ?></button>

                <p class="auth-forgot-link">
                    <a href="#" id="showForgotPassword"><?= e(__('js_forgot_your_password')) ?></a>
                </p>
            </form>

            <!-- Forgot Password Form -->
            <form id="forgotPasswordForm" class="auth-form" data-tab="forgot" method="post">
                <h2><?= e(__('js_reset_password')) ?></h2>
                <p class="auth-subtitle"><?= e(__('js_auth_reset_email_hint')) ?></p>

                <div class="form-group">
                    <label for="forgotEmail"><?= e(__('js_email')) ?></label>
                    <input type="email" id="forgotEmail" name="email" required autocomplete="username">
                </div>

                <div class="form-error" id="forgotError"></div>
                <div class="form-success" id="forgotSuccess"></div>

                <?php if ($turnstileEnabled): ?>
                <div id="turnstileForgotContainer" class="turnstile-container"></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block"><?= e(__('js_send_reset_link')) ?></button>

                <p class="auth-back-link">
                    <a href="#" id="backToLogin"><?= e(__('js_back_to_login')) ?></a>
                </p>
            </form>

            <!-- Reset Password Form (shown via URL param) -->
            <form id="resetPasswordForm" class="auth-form" data-tab="reset" method="post">
                <h2><?= e(__('js_set_new_password')) ?></h2>
                <p class="auth-subtitle"><?= e(__('js_enter_your_new_password_below')) ?></p>

                <div class="form-group">
                    <label for="resetPassword"><?= e(__('js_new_password')) ?></label>
                    <input type="password" id="resetPassword" name="password" required minlength="8" autocomplete="new-password">
                    <small class="form-hint"><?= e(__('js_at_least_8_characters')) ?></small>
                </div>

                <div class="form-group">
                    <label for="resetPasswordConfirm"><?= e(__('js_confirm_new_password')) ?></label>
                    <input type="password" id="resetPasswordConfirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                </div>

                <div class="form-error" id="resetError"></div>

                <button type="submit" class="btn btn-primary btn-block"><?= e(__('js_reset_password')) ?></button>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="auth-form" data-tab="register" method="post">
                <h2><?= e(__('js_create_account')) ?></h2>

                <div class="form-group">
                    <label for="registerName"><?= e(__('js_full_name')) ?></label>
                    <input type="text" id="registerName" name="name" required autocomplete="name">
                </div>

                <div class="form-group">
                    <label for="registerEmail"><?= e(__('js_email')) ?></label>
                    <input type="email" id="registerEmail" name="email" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="registerPassword"><?= e(__('js_password')) ?></label>
                    <input type="password" id="registerPassword" name="password" required minlength="8" autocomplete="new-password">
                    <small class="form-hint"><?= e(__('js_at_least_8_characters')) ?></small>
                </div>

                <div class="form-group">
                    <label for="registerPasswordConfirm"><?= e(__('js_confirm_password')) ?></label>
                    <input type="password" id="registerPasswordConfirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                </div>

                <div class="form-error" id="registerError"></div>

                <?php if ($turnstileEnabled): ?>
                <div id="turnstileContainer" class="turnstile-container"></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block"><?= e(__('js_create_account')) ?></button>

                <p class="terms-note"><?= messageWithLinks('page_terms_agree_account', ['terms' => [basePath() . '/terms', __('page_terms_link')]]) ?></p>
            </form>

            <p class="auth-note">
                <?= e(__('js_auth_optional_account')) ?>
            </p>
        </div>
    </div>
</div>

<?php if ($turnstileEnabled): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
<?php endif; ?>

<script>
window.AUTH_CONFIG = <?= json_encode([
    'turnstileEnabled' => $turnstileEnabled,
    'turnstileSiteKey' => $turnstileSiteKey,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>


<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
