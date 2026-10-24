<?php
$title = __('dashboard') . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/dashboard.js'];
ob_start();
?>

<div class="dashboard-container">
    <div class="container">
        <header class="dashboard-header">
            <h1><?= e(__('page_your_dashboard')) ?></h1>
        </header>

        <?php if (!$user->isEmailVerified()): ?>
        <div class="verification-banner" id="verificationBanner">
            <div class="verification-content">
                <svg class="verification-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <div class="verification-text">
                    <strong><?= e(__('page_verify_your_email_address')) ?></strong>
                    <span><?= e(__('page_we_ve_sent_a_verification_link_to_email_please_click_the_link_to_verify_your_e', ['email' => $user->email])) ?></span>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-small" id="resendVerificationBtn"><?= e(__('page_resend_email')) ?></button>
        </div>
        <?php endif; ?>

        <section class="card votes-section">
            <div class="section-header">
                <h2><?= e(__('page_your_polls')) ?></h2>
                <a href="<?= basePath() ?>/create" class="btn btn-primary btn-create-poll"><?= e(__('page_create_new_poll')) ?></a>
            </div>
            <?php if (empty($polls)): ?>
                <p class="empty-message"><?= e(__('page_you_haven_t_created_any_polls_yet')) ?></p>
            <?php else: ?>
                <div class="votes-list">
                    <?php foreach ($polls as $poll): ?>
                        <div class="poll-item">
                            <div class="vote-info">
                                <h3 class="poll-title"><?= e($poll->title) ?></h3>
                                <div class="poll-meta-info">
                                    <span class="status status-<?= e($poll->status) ?>"><?= e(__('status_' . $poll->status)) ?></span>
                                    <span class="response-count"><?= e(__('response_count', ['count' => $poll->getResponseCount()])) ?></span>
                                    <span class="created-date"><?= \App\i18n\LocaleFormat::date($poll->createdAt) ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($poll->adminToken) ?>" class="btn btn-small btn-primary"><?= e(__('page_manage')) ?></a>
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary"><?= e(__('page_view')) ?></a>
                                <button type="button" class="btn btn-small btn-secondary duplicate-poll-btn"
                                    data-public-id="<?= e($poll->publicId) ?>"
                                    data-admin-token="<?= e($poll->adminToken) ?>"
                                    data-tooltip="<?= e(__('page_create_a_copy_of_this_poll')) ?>"><?= e(__('duplicate')) ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card votes-section">
            <h2><?= e(__('page_polls_you_voted_in')) ?></h2>
            <?php if (empty($votedPolls)): ?>
                <p class="empty-message"><?= e(__('page_you_haven_t_voted_in_any_polls_yet_while_logged_in')) ?></p>
            <?php else: ?>
                <div class="votes-list">
                    <?php foreach ($votedPolls as $poll): ?>
                        <?php
                        $resultsAvailable = $poll->areResultsViewable();
                        $votingOpen = $poll->isVotingOpen();
                        ?>
                        <div class="poll-item">
                            <div class="vote-info">
                                <h3 class="poll-title"><?= e($poll->title) ?></h3>
                                <div class="poll-meta-info">
                                    <span class="status status-<?= e($poll->status) ?>"><?= e(__('status_' . $poll->status)) ?></span>
                                    <span class="created-date"><?= \App\i18n\LocaleFormat::date($poll->createdAt) ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <?php if ($resultsAvailable): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-small btn-secondary"><?= e(__('results')) ?></a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="<?= e(__('page_results_not_yet_available')) ?>" data-tooltip-pos="left"><?= e(__('results')) ?></button>
                                <?php endif; ?>
                                <?php if ($votingOpen): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary"><?= e(__('page_edit_vote')) ?></a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="<?= e(__('page_voting_is_closed')) ?>" data-tooltip-pos="left"><?= e(__('page_edit_vote')) ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card account-section">
            <h2><?= e(__('page_account')) ?></h2>
            <p><?= e(__('page_logged_in_as_name_email', ['name' => $user->name, 'email' => $user->email])) ?></p>
            <div class="account-actions">
                <button type="button" class="btn btn-secondary" id="changeNameBtn"><?= e(__('page_change_name')) ?></button>
                <button type="button" class="btn btn-secondary" id="changePasswordBtn"><?= e(__('page_change_password')) ?></button>
                <form action="<?= basePath() ?>/api/auth/logout" method="post" id="logoutForm" class="account-logout-form">
                    <button type="submit" class="btn btn-secondary"><?= e(__('log_out')) ?></button>
                </form>
            </div>
        </section>

        <section class="card data-section">
            <h2><?= e(__('page_your_data')) ?></h2>
            <p><?= e(__('page_under_gdpr_you_have_the_right_to_access_and_export_all_data_we_have_about_you')) ?></p>
            <div class="data-actions">
                <button type="button" class="btn btn-secondary" id="viewDataBtn"><?= e(__('page_view_my_data')) ?></button>
                <button type="button" class="btn btn-secondary" id="exportDataBtn"><?= e(__('page_export_my_data')) ?></button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn"><?= e(__('page_delete_account')) ?></button>
            </div>
        </section>

        <!-- View Data Modal -->
        <div class="modal" id="viewDataModal" role="dialog" aria-modal="true" aria-labelledby="viewDataModalTitle">
            <div class="modal-overlay"></div>
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2 id="viewDataModalTitle"><?= e(__('page_your_personal_data')) ?></h2>
                    <button type="button" class="modal-close" aria-label="<?= e(__('page_close')) ?>">&times;</button>
                </div>
                <div class="modal-body" id="dataModalBody">
                    <p><?= e(__('page_loading')) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn"><?= e(__('page_close')) ?></button>
                </div>
            </div>
        </div>

        <!-- Delete Account Modal -->
        <div class="modal" id="deleteAccountModal" role="dialog" aria-modal="true" aria-labelledby="deleteAccountModalTitle">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="deleteAccountModalTitle"><?= e(__('page_delete_your_account')) ?></h2>
                    <button type="button" class="modal-close" aria-label="<?= e(__('page_close')) ?>">&times;</button>
                </div>
                <div class="modal-body" id="deleteAccountBody">
                    <p><?= e(__('page_loading')) ?></p>
                </div>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div class="modal" id="changePasswordModal" role="dialog" aria-modal="true" aria-labelledby="changePasswordModalTitle">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="changePasswordModalTitle"><?= e(__('page_change_password')) ?></h2>
                    <button type="button" class="modal-close" aria-label="<?= e(__('page_close')) ?>">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm" method="post">
                        <div class="form-group">
                            <label for="currentPassword"><?= e(__('page_current_password')) ?></label>
                            <input type="password" id="currentPassword" name="current_password" required class="form-control" autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label for="newPassword"><?= e(__('page_new_password')) ?></label>
                            <input type="password" id="newPassword" name="new_password" required class="form-control" minlength="8" autocomplete="new-password">
                            <p class="help-text"><?= e(__('page_must_be_at_least_8_characters')) ?></p>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword"><?= e(__('page_confirm_new_password')) ?></label>
                            <input type="password" id="confirmPassword" name="confirm_password" required class="form-control" minlength="8" autocomplete="new-password">
                        </div>
                        <div id="changePasswordError" class="error-message" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn"><?= e(__('cancel')) ?></button>
                    <button type="submit" form="changePasswordForm" class="btn btn-primary" id="changePasswordSubmit"><?= e(__('page_change_password')) ?></button>
                </div>
            </div>
        </div>

        <!-- Change Name Modal -->
        <div class="modal" id="changeNameModal" role="dialog" aria-modal="true" aria-labelledby="changeNameModalTitle">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="changeNameModalTitle"><?= e(__('page_change_name')) ?></h2>
                    <button type="button" class="modal-close" aria-label="<?= e(__('page_close')) ?>">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="changeNameForm" method="post">
                        <div class="form-group">
                            <label for="newName"><?= e(__('page_new_name')) ?></label>
                            <input type="text" id="newName" name="name" required class="form-control" value="<?= e($user->name) ?>" autocomplete="name">
                        </div>
                        <div id="changeNameError" class="error-message" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn"><?= e(__('cancel')) ?></button>
                    <button type="submit" form="changeNameForm" class="btn btn-primary" id="changeNameSubmit"><?= e(__('page_change_name')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
