<?php
$title = __('page_site_configuration') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_site_configuration')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config" class="active"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <form id="configForm" class="config-form">
            <!-- Site Settings -->
            <section class="card config-section">
                <h2><?= e(__('page_site_settings')) ?></h2>
                <p class="section-description"><?= e(__('page_basic_site_branding_and_access_settings')) ?></p>

                <div class="form-group">
                    <label for="site_name"><?= e(__('page_site_name')) ?></label>
                    <input type="text" id="site_name" name="site.name" class="form-control" placeholder="Pref.Tools Vote">
                    <span class="form-hint"><?= e(__('page_displayed_in_the_header_and_page_titles')) ?></span>
                </div>

                <div class="form-group">
                    <label for="site_logo_url"><?= e(__('page_logo_url')) ?></label>
                    <input type="text" id="site_logo_url" name="site.logo_url" class="form-control" placeholder="<?= e(__('page_https_example_com_logo_png')) ?>">
                    <span class="form-hint"><?= e(__('page_optional_url_to_your_site_logo_image')) ?></span>
                </div>

                <div class="form-group">
                    <label for="site_footer_text"><?= e(__('page_footer_text')) ?></label>
                    <input type="text" id="site_footer_text" name="site.footer_text" class="form-control" placeholder="">
                    <span class="form-hint"><?= e(__('page_optional_custom_text_displayed_in_the_footer')) ?></span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="site_registration_enabled" name="site.registration_enabled" value="1">
                        <span><?= e(__('page_enable_user_registration')) ?></span>
                    </label>
                    <span class="form-hint"><?= e(__('page_when_disabled_only_existing_users_can_log_in')) ?></span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="site_maintenance_mode" name="site.maintenance_mode" value="1">
                        <span><?= e(__('page_maintenance_mode')) ?></span>
                    </label>
                    <span class="form-hint"><?= e(__('page_when_enabled_only_sysadmins_can_access_the_site')) ?></span>
                </div>
            </section>

            <!-- Demo Poll -->
            <section class="card config-section">
                <h2><?= e(__('page_demo_poll')) ?></h2>
                <p class="section-description"><?= e(__('page_configure_the_demo_poll_shown_on_the_home_page')) ?></p>

                <div class="form-group">
                    <label for="demo_poll_id"><?= e(__('page_demo_poll_id')) ?></label>
                    <input type="text" id="demo_poll_id" name="demo.poll_id" class="form-control" placeholder="<?= e(__('page_e_g_abc123xy')) ?>">
                    <span class="form-hint"><?= e(__('page_the_public_id_of_the_poll_to_use_as_the_demo_leave_empty_to_disable_the_demo')) ?></span>
                </div>
            </section>

            <!-- Session Settings -->
            <section class="card config-section">
                <h2><?= e(__('page_session_settings')) ?></h2>
                <p class="section-description"><?= e(__('page_user_session_configuration')) ?></p>

                <div class="form-group">
                    <label for="session_lifetime"><?= e(__('page_session_lifetime_minutes')) ?></label>
                    <input type="number" id="session_lifetime" name="session.lifetime" class="form-control" min="5" max="10080" placeholder="120">
                    <span class="form-hint"><?= e(__('page_how_long_users_stay_logged_in_default_is_120_minutes_2_hours_maximum_10080_1_w')) ?></span>
                </div>
            </section>

            <!-- Email Configuration -->
            <section class="card config-section">
                <h2><?= e(__('page_email_configuration')) ?></h2>
                <p class="section-description"><?= e(__('page_smtp_settings_for_sending_emails')) ?></p>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="mail_enabled" name="mail.enabled" value="1">
                        <span><?= e(__('page_enable_email_sending')) ?></span>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_from_address"><?= e(__('page_from_address')) ?></label>
                        <input type="email" id="mail_from_address" name="mail.from_address" class="form-control" placeholder="<?= e(__('page_noreply_example_com')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="mail_from_name"><?= e(__('page_from_name')) ?></label>
                        <input type="text" id="mail_from_name" name="mail.from_name" class="form-control" placeholder="Pref.Tools Vote">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_smtp_host"><?= e(__('page_smtp_host')) ?></label>
                        <input type="text" id="mail_smtp_host" name="mail.smtp_host" class="form-control" placeholder="<?= e(__('page_smtp_example_com')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="mail_smtp_port"><?= e(__('page_smtp_port')) ?></label>
                        <input type="number" id="mail_smtp_port" name="mail.smtp_port" class="form-control" placeholder="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mail_smtp_username"><?= e(__('page_smtp_username')) ?></label>
                        <input type="text" id="mail_smtp_username" name="mail.smtp_username" class="form-control" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="mail_smtp_password"><?= e(__('page_smtp_password')) ?></label>
                        <div class="secret-input-wrapper">
                            <input type="password" id="mail_smtp_password" name="mail.smtp_password" class="form-control" autocomplete="new-password" data-secret="true">
                            <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="mail_smtp_password" style="display: none;"><?= e(__('clear')) ?></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mail_smtp_encryption"><?= e(__('page_encryption')) ?></label>
                    <select id="mail_smtp_encryption" name="mail.smtp_encryption" class="form-control">
                        <option value="tls"><?= e(__('page_tls_recommended')) ?></option>
                        <option value="ssl">SSL</option>
                        <option value="none"><?= e(__('page_none')) ?></option>
                    </select>
                </div>

                <div class="form-actions-inline">
                    <button type="button" id="testEmailBtn" class="btn btn-secondary">
                        <?= e(__('page_send_test_email')) ?>
                    </button>
                    <span id="testEmailStatus" class="status-message"></span>
                </div>
            </section>

            <!-- API Keys -->
            <section class="card config-section">
                <h2><?= e(__('page_api_keys')) ?></h2>
                <p class="section-description"><?= e(__('page_third_party_service_api_keys')) ?></p>

                <div class="form-group">
                    <label for="api_openai_key"><?= e(__('page_openai_api_key')) ?></label>
                    <div class="secret-input-wrapper">
                        <input type="password" id="api_openai_key" name="api.openai_key" class="form-control" autocomplete="new-password" data-secret="true" placeholder="sk-...">
                        <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="api_openai_key" style="display: none;"><?= e(__('clear')) ?></button>
                    </div>
                    <span class="form-hint"><?= e(__('page_used_for_content_moderation_get_your_key_at')) ?> <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a></span>
                </div>

                <h3><?= e(__('page_cloudflare_turnstile')) ?></h3>
                <p class="form-hint form-hint-block">
                    <?= e(__('page_turnstile_is_a_captcha_alternative_get_your_keys_at')) ?>
                    <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener"><?= e(__('page_cloudflare_dashboard')) ?></a>
                </p>

                <div class="form-group">
                    <label for="api_turnstile_site_key"><?= e(__('page_site_key')) ?></label>
                    <input type="text" id="api_turnstile_site_key" name="api.turnstile_site_key" class="form-control" placeholder="0x...">
                    <span class="form-hint"><?= e(__('page_public_key_used_in_the_frontend_widget')) ?></span>
                </div>

                <div class="form-group">
                    <label for="api_turnstile_secret_key"><?= e(__('page_secret_key')) ?></label>
                    <div class="secret-input-wrapper">
                        <input type="password" id="api_turnstile_secret_key" name="api.turnstile_secret_key" class="form-control" autocomplete="new-password" data-secret="true" placeholder="0x...">
                        <button type="button" class="btn btn-small btn-secondary clear-secret-btn" data-target="api_turnstile_secret_key" style="display: none;"><?= e(__('clear')) ?></button>
                    </div>
                    <span class="form-hint"><?= e(__('page_private_key_used_for_server_side_verification')) ?></span>
                </div>
            </section>

            <!-- Content Moderation -->
            <section class="card config-section">
                <h2><?= e(__('page_content_moderation')) ?></h2>
                <p class="section-description"><?= e(__('page_automatic_content_moderation_using_openai_s_moderation_api_requires_an_openai')) ?></p>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="moderation_enabled" name="moderation.enabled" value="1">
                        <span><?= e(__('page_enable_content_moderation')) ?></span>
                    </label>
                    <span class="form-hint"><?= e(__('page_when_enabled_poll_content_is_checked_for_inappropriate_material_on_create_upda')) ?></span>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="moderation_fail_open" name="moderation.fail_open" value="1">
                        <span><?= e(__('page_fail_open_on_api_errors')) ?></span>
                    </label>
                    <span class="form-hint"><?= e(__('page_if_the_moderation_api_fails_allow_content_to_be_saved_anyway_disable_for_stric')) ?></span>
                </div>

                <h3><?= e(__('page_category_thresholds')) ?></h3>
                <p class="form-hint form-hint-block">
                    <?= e(__('page_config_moderation_threshold_hint')) ?>
                </p>

                <div class="moderation-thresholds">
                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_sexual"><?= e(__('page_sexual')) ?></label>
                            <input type="number" id="threshold_sexual" name="moderation.threshold.sexual" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_sexual_minors"><?= e(__('page_sexual_minors')) ?></label>
                            <input type="number" id="threshold_sexual_minors" name="moderation.threshold.sexual_minors" class="form-control" min="0" max="1" step="0.01" placeholder="0.01">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_harassment"><?= e(__('page_harassment')) ?></label>
                            <input type="number" id="threshold_harassment" name="moderation.threshold.harassment" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_harassment_threatening"><?= e(__('page_harassment_threatening')) ?></label>
                            <input type="number" id="threshold_harassment_threatening" name="moderation.threshold.harassment_threatening" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_hate"><?= e(__('page_hate')) ?></label>
                            <input type="number" id="threshold_hate" name="moderation.threshold.hate" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_hate_threatening"><?= e(__('page_hate_threatening')) ?></label>
                            <input type="number" id="threshold_hate_threatening" name="moderation.threshold.hate_threatening" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_illicit"><?= e(__('page_illicit')) ?></label>
                            <input type="number" id="threshold_illicit" name="moderation.threshold.illicit" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_illicit_violent"><?= e(__('page_illicit_violent')) ?></label>
                            <input type="number" id="threshold_illicit_violent" name="moderation.threshold.illicit_violent" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_self_harm"><?= e(__('page_self_harm')) ?></label>
                            <input type="number" id="threshold_self_harm" name="moderation.threshold.self_harm" class="form-control" min="0" max="1" step="0.05" placeholder="0.7">
                        </div>
                        <div class="form-group">
                            <label for="threshold_self_harm_intent"><?= e(__('page_self_harm_intent')) ?></label>
                            <input type="number" id="threshold_self_harm_intent" name="moderation.threshold.self_harm_intent" class="form-control" min="0" max="1" step="0.05" placeholder="0.5">
                        </div>
                        <div class="form-group">
                            <label for="threshold_self_harm_instructions"><?= e(__('page_self_harm_instructions')) ?></label>
                            <input type="number" id="threshold_self_harm_instructions" name="moderation.threshold.self_harm_instructions" class="form-control" min="0" max="1" step="0.05" placeholder="0.3">
                        </div>
                    </div>

                    <div class="threshold-group">
                        <div class="form-group">
                            <label for="threshold_violence"><?= e(__('page_violence')) ?></label>
                            <input type="number" id="threshold_violence" name="moderation.threshold.violence" class="form-control" min="0" max="1" step="0.05" placeholder="0.8">
                        </div>
                        <div class="form-group">
                            <label for="threshold_violence_graphic"><?= e(__('page_violence_graphic')) ?></label>
                            <input type="number" id="threshold_violence_graphic" name="moderation.threshold.violence_graphic" class="form-control" min="0" max="1" step="0.05" placeholder="0.6">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Notifications -->
            <section class="card config-section">
                <h2><?= e(__('page_notifications')) ?></h2>
                <p class="section-description"><?= e(__('page_where_to_send_system_notifications')) ?></p>

                <div class="form-group">
                    <label for="notifications_sysadmin_email"><?= e(__('page_sysadmin_email')) ?></label>
                    <input type="email" id="notifications_sysadmin_email" name="notifications.sysadmin_email" class="form-control" placeholder="<?= e(__('page_admin_example_com')) ?>">
                    <span class="form-hint"><?= e(__('page_email_address_to_receive_system_notifications_and_test_emails')) ?></span>
                </div>
            </section>

            <!-- Save Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveConfigBtn">
                    <?= e(__('page_save_configuration')) ?>
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
