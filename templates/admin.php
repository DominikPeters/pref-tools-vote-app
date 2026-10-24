<?php
$title = __('page_admin_title', ['title' => $poll->title]) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/admin.css'];
$extraJs = ['/assets/js/admin.js'];
ob_start();

// Voting mode labels
$votingModeLabels = [
    'open' => __('voting_mode_open'),
    'identified' => __('voting_mode_identified'),
    'secret_ballot' => __('voting_mode_secret_ballot'),
];
$votingModeDescriptions = [
    'open' => __('voting_mode_open_description'),
    'identified' => __('voting_mode_identified_description'),
    'secret_ballot' => __('page_one_vote_per_person_but_votes_are_completely_anonymous_cannot_be_changed_after'),
];
?>

<script>
    window.EMBED_SCRIPT_URL = <?= json_encode(asset('assets/js/embed/vote-poll.js'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>

<div class="admin-container">
    <div class="container">
        <nav class="breadcrumbs">
            <?php if ($user): ?>
                <a href="<?= basePath() ?>/dashboard"><?= e(__('dashboard')) ?></a>
            <?php else: ?>
                <a href="<?= basePath() ?>/"><?= e(__('page_home')) ?></a>
            <?php endif; ?>
            <span class="separator">/</span>
            <span class="current"><?= e(__('poll_admin')) ?></span>
        </nav>

        <header class="admin-header">
            <div class="admin-header-top">
                <div class="admin-header-title">
                    <span class="admin-label"><?= e(__('poll_admin')) ?></span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="status-control">
                    <span class="status-badge status-<?= e($poll->status) ?>"><?= e(__('status_' . $poll->status)) ?></span>
                    <?php if ($poll->status === 'draft'): ?>
                        <button type="button" class="btn btn-success" id="publishPoll"><?= e(__('publish')) ?></button>
                    <?php elseif ($poll->status === 'open'): ?>
                        <button type="button" class="btn btn-secondary" id="closePoll"><?= e(__('page_close_voting')) ?></button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" id="reopenPoll"><?= e(__('page_reopen')) ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-header-actions">
                <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken . '/results')) ?>" class="btn btn-primary"><?= e(__('page_results_analysis')) ?></a>
                <button type="button" class="btn btn-primary" id="editPoll"><?= e(__('edit_poll')) ?></button>
                <button type="button" class="btn btn-outline-danger" id="deletePoll"><?= e(__('delete')) ?></button>
            </div>
        </header>

        <div class="admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <!-- Share Links -->
            <section class="card share-section">
                <h2><?= e(__('share')) ?></h2>
                <div class="share-links">
                    <div class="share-link-group">
                        <label><?= e(__('page_voting_link')) ?> <span class="label-hint"><?= e(__('page_share_with_participants')) ?></span></label>
                        <div class="copy-field">
                            <input type="text" id="publicLink" readonly value="<?= e(url($poll->publicId)) ?>">
                            <a href="<?= e(url($poll->publicId)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" data-tooltip="<?= e(__('page_open_in_new_tab')) ?>" aria-label="<?= e(__('page_open_in_new_tab')) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="publicLink"><?= e(__('page_copy')) ?></button>
                        </div>
                    </div>
                    <div class="share-link-group">
                        <label><?= e(__('page_admin_link')) ?><?php if (!$user || $poll->userId !== $user->id): ?> <span class="label-hint"><?= e(__('page_bookmark_this')) ?></span><?php endif; ?></label>
                        <div class="copy-field">
                            <input type="text" id="adminLink" readonly value="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>">
                            <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" data-tooltip="<?= e(__('page_open_in_new_tab')) ?>" aria-label="<?= e(__('page_open_in_new_tab')) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="adminLink"><?= e(__('page_copy')) ?></button>
                            <?php if ($poll->userId === null && $user !== null): ?>
                                <button type="button" class="btn btn-primary" id="claimPoll" data-tooltip="<?= e(__('page_link_this_poll_to_your_account_795f8f')) ?>"><?= e(__('page_claim')) ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!$user && $poll->userId === null): ?>
            <!-- CTA: Link poll to account -->
            <section class="card claim-cta-section">
                <div class="claim-cta-content">
                    <div class="claim-cta-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <div class="claim-cta-text">
                        <h3><?= e(__('page_secure_access_to_this_poll')) ?></h3>
                        <p><?= e(__('page_this_poll_isn_t_linked_to_an_account_yet_log_in_or_create_an_account_to_always')) ?></p>
                    </div>
                    <div class="claim-cta-actions">
                        <a href="<?= e(url('login?return=' . urlencode('/' . $poll->publicId . '/admin/' . $adminToken) . '&claim=' . $poll->publicId)) ?>" class="btn btn-primary"><?= e(__('page_log_in_or_register_610e8e')) ?></a>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Statistics -->
            <section class="card stats-section">
                <h2><?= e(__('page_statistics')) ?></h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="responseCount"><?= $poll->getResponseCount() ?></div>
                        <div class="stat-label"><?= e(__('responses')) ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= count($poll->questions) ?></div>
                        <div class="stat-label"><?= e(__('page_questions')) ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= \App\i18n\LocaleFormat::date($poll->createdAt) ?></div>
                        <div class="stat-label"><?= e(__('page_created')) ?></div>
                    </div>
                </div>
            </section>

            <!-- Voting Mode -->
            <section class="card voting-mode-section">
                <h2><?= e(__('voting_mode')) ?></h2>
                <div class="voting-mode-display">
                    <span class="voting-mode-badge mode-<?= e($poll->votingMode) ?>"><?= e($votingModeLabels[$poll->votingMode] ?? $poll->votingMode) ?></span>
                    <p class="voting-mode-description"><?= e($votingModeDescriptions[$poll->votingMode] ?? '') ?></p>
                    <?php if ($poll->modeLockedAt): ?>
                    <p class="mode-locked-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <?= e(__('page_voting_mode_is_locked_responses_have_been_submitted')) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($poll->votingMode !== 'open'): ?>
            <!-- Access Management -->
            <section class="card access-section">
                <h2><?= e(__('page_access_management')) ?></h2>
                <p class="section-description"><?= $poll->votingMode === 'secret_ballot' ? e(__('page_manage_who_can_vote_in_this_secret_ballot_poll')) : e(__('page_manage_who_can_vote_in_this_identified_poll')) ?></p>

                <div class="access-tabs">
                    <button type="button" class="access-tab active" data-tab="invitations"><?= e(__('page_email_invitations')) ?></button>
                    <button type="button" class="access-tab" data-tab="tokens"><?= e(__('page_manual_access_tokens')) ?></button>
                </div>

                <!-- Email Invitations Tab -->
                <div class="access-tab-content" id="invitationsTab">
                    <p class="tab-description"><?= e(__('page_send_voting_invitations_directly_to_participants_via_email_each_invitation_con')) ?></p>
                    
                    <?php if (!$user || $poll->userId === null || $poll->userId !== $user->id): ?>
                        <div class="info-banner">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <div class="info-banner-content">
                                <strong><?= e(__('page_account_required')) ?></strong>
                                <p><?= e(__('page_admin_invitations_registered_only')) ?></p>
                                <?php if (!$user): ?>
                                    <p><?= e(__('page_log_in_or_register_then_link_this_poll_to_your_account_to_use_this_feature')) ?></p>
                                    <a href="<?= e(url('login?return=' . urlencode('/' . $poll->publicId . '/admin/' . $adminToken))) ?>"><?= e(__('page_log_in_or_register')) ?></a>
                                <?php elseif ($poll->userId === null): ?>
                                    <p><?= e(__('page_admin_link_poll_for_invitations')) ?></p>
                                    <button type="button" class="btn-link" id="claimPollInvite"><?= e(__('page_link_this_poll_to_your_account')) ?></button>
                                <?php else: ?>
                                    <p><?= e(__('page_admin_invitations_owner_only')) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="mailConfigWarning" class="warning-banner" style="display: none;">
                            <strong><?= e(__('page_email_not_configured')) ?></strong> <?= e(__('page_ask_your_system_administrator_to_configure_smtp_settings')) ?>
                        </div>
                        <div class="invitation-send">
                            <textarea id="invitationEmails" name="emails" placeholder="<?= e(__('page_enter_email_addresses_one_per_line_or_comma_separated')) ?>" rows="3"></textarea>
                            <button type="button" class="btn btn-primary" id="sendInvitations"><?= e(__('page_send_invitations')) ?></button>
                        </div>
                        <div id="invitationsList" class="invitations-list">
                            <p class="loading"><?= e(__('page_loading_invitations')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Manual Access Tokens Tab -->
                <div class="access-tab-content" id="tokensTab" style="display: none;">
                    <p class="tab-description"><?= e(__('page_generate_access_tokens_and_distribute_them_to_voters_yourself_e_g_via_your_own')) ?></p>
                    <div class="token-generate">
                        <div class="token-generate-field">
                            <label for="tokenCount">
                                <?= e(__('page_number_of_tokens')) ?>
                            </label>
                            <input type="number" id="tokenCount" min="1" max="100" value="10">
                        </div>
                        <div class="token-generate-field">
                            <label for="tokenLabelPrefix">
                                <?= e(__('page_label_prefix')) ?>
                                <span class="label-optional"><?= e(__('report_optional')) ?></span>
                                <span class="info-icon" data-tooltip="<?= e(__('page_labels_help_you_identify_tokens_e_g_board_member_creates_tokens_labeled_board')) ?>" data-tooltip-pos="top">?</span>
                            </label>
                            <input type="text" id="tokenLabelPrefix" placeholder="<?= e(__('page_e_g_board_member')) ?>">
                        </div>
                        <div class="token-generate-action">
                            <button type="button" class="btn btn-primary" id="generateTokens"><?= e(__('page_generate_tokens')) ?></button>
                        </div>
                    </div>
                    <div id="tokensList" class="tokens-list">
                        <p class="loading"><?= e(__('page_loading_tokens')) ?></p>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Responses -->
            <section class="card responses-section">
                <div class="section-header">
                    <h2><?= e(__('responses')) ?></h2>
                    <div class="responses-actions">
                        <button type="button" class="btn btn-secondary btn-small" id="refreshResponses" aria-label="<?= e(__('page_refresh_responses')) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                            <?= e(__('page_refresh')) ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-small" id="exportJson"><?= e(__('page_export_json')) ?></button>
                        <button type="button" class="btn btn-secondary btn-small" id="exportCsv"><?= e(__('page_export_csv')) ?></button>
                        <button type="button" class="btn btn-outline-danger btn-small" id="deleteAllResponses" data-tooltip="<?= e(__('page_permanently_delete_all_responses')) ?>" aria-label="<?= e(__('page_delete_all_responses')) ?>"><?= e(__('page_delete_all')) ?></button>
                    </div>
                </div>
                <p class="responses-hint"><?= e(__('page_showing_a_summary_of_responses_open_results_analysis_for_detailed_analysis_and')) ?> <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken . '/results')) ?>"><?= e(__('page_results_analysis')) ?></a></p>
                <div id="responsesList" class="responses-list">
                    <p class="loading"><?= e(__('page_loading_responses')) ?></p>
                </div>

                <?php if ($user && $poll->userId === $user->id && $user->emailVerifiedAt !== null): ?>
                <div class="responses-notification-setting" id="notificationSetting">
                    <label class="checkbox-label">
                        <input type="checkbox" id="notifyOnResponse" <?= $poll->notifyOnResponse ? 'checked' : '' ?>>
                        <span><?= e(__('page_email_me_when_someone_submits_a_response')) ?></span>
                    </label>
                </div>
                <?php endif; ?>
            </section>

            <!-- Privacy & Display Settings -->
            <section class="card settings-section">
                <div class="section-header">
                    <h2><?= e(__('page_privacy_display_settings')) ?></h2>
                    <button type="button" class="btn btn-secondary btn-small" id="saveSettings" style="display: none;"><?= e(__('save_changes')) ?></button>
                </div>

                <div class="settings-form">
                    <div class="settings-row">
                        <div class="setting-group">
                            <label for="settingVisibility" id="settingVisibilityLabel"><?= e(__('page_results_visibility')) ?></label>
                            <?php
                            // Show "anonymous" option only for non-secret polls that collect names
                            $showAnonymousOption = $poll->votingMode !== 'secret_ballot' && $poll->collectName;
                            ?>
                            <select id="settingVisibility" class="setting-input" data-show-anonymous="<?= $showAnonymousOption ? 'true' : 'false' ?>">
                                <option value="private" <?= $poll->visibility === 'private' ? 'selected' : '' ?>><?= e(__('page_private_admin_only')) ?></option>
                                <?php if ($showAnonymousOption): ?>
                                <option value="anonymous" <?= $poll->visibility === 'anonymous' ? 'selected' : '' ?>><?= e(__('page_public_responses_without_names')) ?></option>
                                <option value="full" <?= $poll->visibility === 'full' ? 'selected' : '' ?>><?= e(__('page_public_responses_with_names')) ?></option>
                                <?php else: ?>
                                <option value="full" <?= $poll->visibility !== 'private' ? 'selected' : '' ?>><?= e(__('page_public')) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="settings-row settings-checkboxes">
                        <label class="checkbox-label <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>">
                            <input type="checkbox" id="settingCollectName" <?= $poll->collectName ? 'checked' : '' ?> <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>>
                            <span><?= e(__('page_collect_voter_name')) ?></span>
                            <?php if ($poll->votingMode === 'secret_ballot'): ?>
                            <span class="setting-hint"><?= e(__('page_disabled_for_secret_ballot')) ?></span>
                            <?php endif; ?>
                        </label>
                        <label class="checkbox-label <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>">
                            <input type="checkbox" id="settingAllowEditOwn" <?= $poll->allowEditOwn ? 'checked' : '' ?> <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>>
                            <span><?= e(__('page_allow_voters_to_edit_their_response')) ?></span>
                            <?php if ($poll->votingMode === 'secret_ballot'): ?>
                            <span class="setting-hint"><?= e(__('page_disabled_for_secret_ballot')) ?></span>
                            <?php endif; ?>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="settingAllowEditAny" <?= $poll->allowEditAny ? 'checked' : '' ?>>
                            <span><?= e(__('page_allow_anyone_to_edit_any_response')) ?></span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="settingRandomizeOptions" <?= $poll->randomizeOptions ? 'checked' : '' ?>>
                            <span><?= e(__('randomize_options_label')) ?></span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- Embed on Your Website -->
            <section class="card settings-section embed-section">
                <div class="section-header">
                    <h2><?= e(__('page_embed_on_your_website')) ?></h2>
                </div>

                <?php if ($poll->votingMode !== 'open'): ?>
                <div class="info-banner">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div class="info-banner-content">
                        <strong><?= e(__('page_not_available_for_this_voting_mode')) ?></strong>
                        <p><?= e(__('page_embedding_is_only_available_for_polls_with_open_voting_mode_anonymous_voting_i')) ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="settings-form">
                    <div class="settings-row settings-checkboxes">
                        <label class="checkbox-label">
                            <input type="checkbox" id="settingAllowEmbedding" <?= $poll->allowEmbedding ? 'checked' : '' ?>>
                            <span><?= e(__('page_allow_embedding_this_poll_on_external_websites')) ?></span>
                        </label>
                    </div>

                    <div id="embedCodeSection" class="embed-code-section" style="<?= $poll->allowEmbedding ? '' : 'display: none;' ?>">
                        <div class="setting-group">
                            <label><?= e(__('page_embed_code')) ?> <span class="label-hint"><?= e(__('page_copy_and_paste_into_your_website')) ?></span></label>
                            <div class="embed-code-container">
                                <textarea id="embedCode" readonly rows="3" class="embed-code-textarea"></textarea>
                                <button type="button" class="btn btn-secondary btn-small copy-embed-btn" data-target="embedCode"><?= e(__('page_copy')) ?></button>
                            </div>
                        </div>

                        <div class="setting-group embed-preview-group">
                            <label><?= e(__('preview')) ?></label>
                            <div id="embedPreview" class="embed-preview">
                                <!-- Preview iframe will be inserted here -->
                            </div>
                        </div>

                        <details class="embed-customization embed-customization-block">
                            <summary><?= e(__('page_customization_options')) ?></summary>
                            <div class="customization-content">
                                <p class="customization-description"><?= e(__('page_use_css_variables_to_customize_the_appearance')) ?></p>
                                <pre><code>&lt;style&gt;
vote-poll {
  --vp-color-primary: #2563eb;
  --vp-color-text: #1e293b;
  --vp-color-bg: #ffffff;
  --vp-font-family: system-ui, sans-serif;
  --vp-border-radius: 8px;
}
&lt;/style&gt;</code></pre>
                            </div>
                        </details>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
