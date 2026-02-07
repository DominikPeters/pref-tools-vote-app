<?php
$title = 'Site Configuration - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1>Site Configuration</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats">Stats</a>
                <a href="<?= basePath() ?>/sysadmin/config" class="active">Config</a>
            </nav>
        </header>

        <form id="configForm" class="config-form">
            <!-- Site Settings -->
            <section class="card config-section">
                <h2>Site Settings</h2>
                <p class="section-description">Basic site branding and access settings.</p>

                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site.name" class="form-control" placeholder="Pref.Tools Vote">
                    <span class="form-hint">Displayed in the header and page titles.</span>
                </div>

                <div class="form-group">
                    <label for="site_logo_url">Logo URL</label>
                    <input type="text" id="site_logo_url" name="site.logo_url" class="form-control" placeholder="https://example.com/logo.png">
                    <span class="form-hint">Optional. URL to your site logo image.</span>
                </div>

                <div class="form-group">
                    <label for="site_footer_text">Footer Text</label>
                    <input type="text" id="site_footer_text" name="site.footer_text" class="form-control" placeholder="">
                    <span class="form-hint">Optional. Custom text displayed in the footer.</span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="site_registration_enabled" name="site.registration_enabled" value="1">
                        <span>Enable user registration</span>
                    </label>
                    <span class="form-hint">When disabled, only existing users can log in.</span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="site_maintenance_mode" name="site.maintenance_mode" value="1">
                        <span>Maintenance mode</span>
                    </label>
                    <span class="form-hint">When enabled, only sysadmins can access the site.</span>
                </div>
            </section>

            <!-- Demo Poll -->
            <section class="card config-section">
                <h2>Demo Poll</h2>
                <p class="section-description">Configure the demo poll shown on the home page.</p>

                <div class="form-group">
                    <label for="demo_poll_id">Demo Poll ID</label>
                    <input type="text" id="demo_poll_id" name="demo.poll_id" class="form-control" placeholder="e.g., ABC123xy">
                    <span class="form-hint">The public ID of the poll to use as the demo. Leave empty to disable the demo.</span>
                </div>
            </section>

            <!-- Session Settings -->
            <section class="card config-section">
                <h2>Session Settings</h2>
                <p class="section-description">User session configuration.</p>

                <div class="form-group">
                    <label for="session_lifetime">Session Lifetime (minutes)</label>
                    <input type="number" id="session_lifetime" name="session.lifetime" class="form-control" min="5" max="10080" placeholder="120">
                    <span class="form-hint">How long users stay logged in. Default is 120 minutes (2 hours). Maximum 10080 (1 week).</span>
                </div>
            </section>

            <!-- Email Configuration -->
            <section class="card config-section">
                <h2>Email Configuration</h2>
                <p class="section-description">SMTP settings for sending emails.</p>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="mail_enabled" name="mail.enabled" value="1">
                        <span>Enable email sending</span>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_from_address">From Address</label>
                        <input type="email" id="mail_from_address" name="mail.from_address" class="form-control" placeholder="noreply@example.com">
                    </div>
                    <div class="form-group">
                        <label for="mail_from_name">From Name</label>
                        <input type="text" id="mail_from_name" name="mail.from_name" class="form-control" placeholder="Pref.Tools Vote">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_smtp_host">SMTP Host</label>
                        <input type="text" id="mail_smtp_host" name="mail.smtp_host" class="form-control" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label for="mail_smtp_port">SMTP Port</label>
                        <input type="number" id="mail_smtp_port" name="mail.smtp_port" class="form-control" placeholder="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_smtp_username">SMTP Username</label>
                        <input type="text" id="mail_smtp_username" name="mail.smtp_username" class="form-control" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="mail_smtp_password">SMTP Password</label>
                        <div class="secret-input-wrapper">
                            <input type="password" id="mail_smtp_password" name="mail.smtp_password" class="form-control" autocomplete="new-password" data-secret="true">
                            <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="mail_smtp_password" style="display: none;">Clear</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mail_smtp_encryption">Encryption</label>
                    <select id="mail_smtp_encryption" name="mail.smtp_encryption" class="form-control">
                        <option value="tls">TLS (Recommended)</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </select>
                </div>

                <div class="form-actions-inline">
                    <button type="button" id="testEmailBtn" class="btn btn-secondary">
                        Send Test Email
                    </button>
                    <span id="testEmailStatus" class="status-message"></span>
                </div>
            </section>

            <!-- API Keys -->
            <section class="card config-section">
                <h2>API Keys</h2>
                <p class="section-description">Third-party service API keys.</p>

                <div class="form-group">
                    <label for="api_openai_key">OpenAI API Key</label>
                    <div class="secret-input-wrapper">
                        <input type="password" id="api_openai_key" name="api.openai_key" class="form-control" autocomplete="new-password" data-secret="true" placeholder="sk-...">
                        <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="api_openai_key" style="display: none;">Clear</button>
                    </div>
                    <span class="form-hint">Used for content moderation. Get your key at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a></span>
                </div>

                <h3>Cloudflare Turnstile</h3>
                <p class="form-hint form-hint-block">
                    Turnstile is a CAPTCHA alternative. Get your keys at
                    <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare Dashboard</a>
                </p>

                <div class="form-group">
                    <label for="api_turnstile_site_key">Site Key</label>
                    <input type="text" id="api_turnstile_site_key" name="api.turnstile_site_key" class="form-control" placeholder="0x...">
                    <span class="form-hint">Public key used in the frontend widget.</span>
                </div>

                <div class="form-group">
                    <label for="api_turnstile_secret_key">Secret Key</label>
                    <div class="secret-input-wrapper">
                        <input type="password" id="api_turnstile_secret_key" name="api.turnstile_secret_key" class="form-control" autocomplete="new-password" data-secret="true" placeholder="0x...">
                        <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="api_turnstile_secret_key" style="display: none;">Clear</button>
                    </div>
                    <span class="form-hint">Private key used for server-side verification.</span>
                </div>
            </section>

            <!-- Content Moderation -->
            <section class="card config-section">
                <h2>Content Moderation</h2>
                <p class="section-description">Automatic content moderation using OpenAI's Moderation API. Requires an OpenAI API key above.</p>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="moderation_enabled" name="moderation.enabled" value="1">
                        <span>Enable content moderation</span>
                    </label>
                    <span class="form-hint">When enabled, poll content is checked for inappropriate material on create/update.</span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="moderation_fail_open" name="moderation.fail_open" value="1">
                        <span>Fail open on API errors</span>
                    </label>
                    <span class="form-hint">If the moderation API fails, allow content to be saved anyway. Disable for stricter enforcement.</span>
                </div>

                <h3>Category Thresholds</h3>
                <p class="form-hint form-hint-block">
                    Adjust how sensitive each category is. Lower values = stricter (block more content). Higher values = more permissive.
                    Range: 0.0 (block everything) to 1.0 (block nothing).
                </p>

                <div class="moderation-thresholds">
                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_sexual">Sexual</label>
                            <input type="number" id="threshold_sexual" name="moderation.threshold.sexual" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_sexual_minors">Sexual/Minors</label>
                            <input type="number" id="threshold_sexual_minors" name="moderation.threshold.sexual_minors" class="form-control" min="0" max="1" step="0.01" placeholder="0.01">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_harassment">Harassment</label>
                            <input type="number" id="threshold_harassment" name="moderation.threshold.harassment" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_harassment_threatening">Harassment/Threatening</label>
                            <input type="number" id="threshold_harassment_threatening" name="moderation.threshold.harassment_threatening" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_hate">Hate</label>
                            <input type="number" id="threshold_hate" name="moderation.threshold.hate" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_hate_threatening">Hate/Threatening</label>
                            <input type="number" id="threshold_hate_threatening" name="moderation.threshold.hate_threatening" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_illicit">Illicit</label>
                            <input type="number" id="threshold_illicit" name="moderation.threshold.illicit" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_illicit_violent">Illicit/Violent</label>
                            <input type="number" id="threshold_illicit_violent" name="moderation.threshold.illicit_violent" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_self_harm">Self-Harm</label>
                            <input type="number" id="threshold_self_harm" name="moderation.threshold.self_harm" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_self_harm_intent">Self-Harm/Intent</label>
                            <input type="number" id="threshold_self_harm_intent" name="moderation.threshold.self_harm_intent" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                        <div class="form-group">
                            <label for="threshold_self_harm_instructions">Self-Harm/Instructions</label>
                            <input type="number" id="threshold_self_harm_instructions" name="moderation.threshold.self_harm_instructions" class="form-control" min="0" max="1" step="0.05" placeholder="0.3">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_violence">Violence</label>
                            <input type="number" id="threshold_violence" name="moderation.threshold.violence" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_violence_graphic">Violence/Graphic</label>
                            <input type="number" id="threshold_violence_graphic" name="moderation.threshold.violence_graphic" class="form-control" min="0" max="1" step="0.05" placeholder="0.6">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Notifications -->
            <section class="card config-section">
                <h2>Notifications</h2>
                <p class="section-description">Where to send system notifications.</p>

                <div class="form-group">
                    <label for="notifications_sysadmin_email">Sysadmin Email</label>
                    <input type="email" id="notifications_sysadmin_email" name="notifications.sysadmin_email" class="form-control" placeholder="admin@example.com">
                    <span class="form-hint">Email address to receive system notifications and test emails.</span>
                </div>
            </section>

            <!-- Save Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveConfigBtn">
                    Save Configuration
                </button>
                <span id="saveStatus" class="status-message"></span>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
