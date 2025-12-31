<?php
$title = 'Dashboard - Pref.Tools Vote';
$extraJs = ['/assets/js/dashboard.js'];
ob_start();
?>

<div class="dashboard-container">
    <div class="container">
        <header class="dashboard-header">
            <h1>Your Dashboard</h1>
        </header>

        <?php if (!$user->isEmailVerified()): ?>
        <div class="verification-banner" id="verificationBanner">
            <div class="verification-content">
                <svg class="verification-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <div class="verification-text">
                    <strong>Verify your email address</strong>
                    <span>We've sent a verification link to <strong><?= e($user->email) ?></strong>. Please click the link to verify your email. You'll need to verify before sending poll invitations.</span>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-small" id="resendVerificationBtn">Resend Email</button>
        </div>
        <?php endif; ?>

        <section class="card votes-section">
            <div class="section-header">
                <h2>Your Polls</h2>
                <a href="<?= basePath() ?>/create" class="btn btn-primary">Create New Poll</a>
            </div>
            <?php if (empty($polls)): ?>
                <p class="empty-message">You haven't created any polls yet.</p>
            <?php else: ?>
                <div class="votes-list">
                    <?php foreach ($polls as $poll): ?>
                        <div class="poll-item">
                            <div class="vote-info">
                                <h3 class="poll-title"><?= e($poll->title) ?></h3>
                                <div class="poll-meta">
                                    <span class="status status-<?= e($poll->status) ?>"><?= ucfirst(e($poll->status)) ?></span>
                                    <span class="response-count"><?= $poll->getResponseCount() ?> responses</span>
                                    <span class="created-date"><?= $poll->createdAt->format('M j, Y') ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($poll->adminToken) ?>" class="btn btn-small btn-primary">Manage</a>
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary">View</a>
                                <button type="button" class="btn btn-small btn-secondary duplicate-poll-btn"
                                    data-public-id="<?= e($poll->publicId) ?>"
                                    data-admin-token="<?= e($poll->adminToken) ?>"
                                    data-tooltip="Create a copy of this poll">Duplicate</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card votes-section">
            <h2>Polls You Voted In</h2>
            <?php if (empty($votedPolls)): ?>
                <p class="empty-message">You haven't voted in any polls yet (while logged in).</p>
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
                                <div class="poll-meta">
                                    <span class="status status-<?= e($poll->status) ?>"><?= ucfirst(e($poll->status)) ?></span>
                                    <span class="created-date"><?= $poll->createdAt->format('M j, Y') ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <?php if ($resultsAvailable): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-small btn-secondary">Results</a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="Results not yet available" data-tooltip-pos="left">Results</button>
                                <?php endif; ?>
                                <?php if ($votingOpen): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary">Edit Vote</a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="Voting is closed" data-tooltip-pos="left">Edit Vote</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card account-section">
            <h2>Account</h2>
            <p>Logged in as: <strong><?= e($user->name) ?></strong> &lt;<?= e($user->email) ?>&gt;</p>
            <div class="account-actions">
                <button type="button" class="btn btn-secondary" id="changeNameBtn">Change Name</button>
                <button type="button" class="btn btn-secondary" id="changePasswordBtn">Change Password</button>
                <form action="<?= basePath() ?>/api/auth/logout" method="post" id="logoutForm" style="display: inline;">
                    <button type="submit" class="btn btn-secondary">Log Out</button>
                </form>
            </div>
        </section>

        <section class="card data-section">
            <h2>Your Data</h2>
            <p>Under GDPR, you have the right to access and export all data we have about you.</p>
            <div class="data-actions">
                <button type="button" class="btn btn-secondary" id="viewDataBtn">View My Data</button>
                <button type="button" class="btn btn-secondary" id="exportDataBtn">Export My Data</button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn">Delete Account</button>
            </div>
        </section>

        <!-- View Data Modal -->
        <div class="modal" id="viewDataModal">
            <div class="modal-overlay"></div>
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2>Your Personal Data</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="dataModalBody">
                    <p>Loading...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
                </div>
            </div>
        </div>

        <!-- Delete Account Modal -->
        <div class="modal" id="deleteAccountModal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Delete Your Account</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="deleteAccountBody">
                    <p>Loading...</p>
                </div>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div class="modal" id="changePasswordModal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Change Password</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="current_password" required class="form-control" autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="new_password" required class="form-control" minlength="8" autocomplete="new-password">
                            <p class="help-text">Must be at least 8 characters</p>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" required class="form-control" minlength="8" autocomplete="new-password">
                        </div>
                        <div id="changePasswordError" class="error-message" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" form="changePasswordForm" class="btn btn-primary" id="changePasswordSubmit">Change Password</button>
                </div>
            </div>
        </div>

        <!-- Change Name Modal -->
        <div class="modal" id="changeNameModal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Change Name</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="changeNameForm">
                        <div class="form-group">
                            <label for="newName">New Name</label>
                            <input type="text" id="newName" name="name" required class="form-control" value="<?= e($user->name) ?>" autocomplete="name">
                        </div>
                        <div id="changeNameError" class="error-message" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" form="changeNameForm" class="btn btn-primary" id="changeNameSubmit">Change Name</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
