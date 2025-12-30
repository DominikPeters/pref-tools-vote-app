<?php
$title = 'Admin: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/admin.css'];
$extraJs = ['/assets/js/admin.js'];
ob_start();

// Voting mode labels
$votingModeLabels = [
    'open' => 'Open',
    'identified' => 'Identified',
    'secret_ballot' => 'Secret Ballot',
];
$votingModeDescriptions = [
    'open' => 'Anyone with the link can vote. One vote per browser.',
    'identified' => 'One vote per person via access tokens or email invitations. Votes are linked to identity.',
    'secret_ballot' => 'One vote per person, but votes are completely anonymous. Cannot be changed after submission.',
];
?>

<div class="admin-container">
    <div class="container">
        <nav class="breadcrumbs">
            <?php if ($user): ?>
                <a href="<?= basePath() ?>/dashboard">Dashboard</a>
            <?php else: ?>
                <a href="<?= basePath() ?>/">Home</a>
            <?php endif; ?>
            <span class="separator">/</span>
            <span class="current">Poll Admin</span>
        </nav>

        <header class="admin-header">
            <div class="admin-header-top">
                <div class="admin-header-title">
                    <span class="admin-label">Poll Admin</span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="status-control">
                    <span class="status-badge status-<?= e($poll->status) ?>"><?= ucfirst(e($poll->status)) ?></span>
                    <?php if ($poll->status === 'draft'): ?>
                        <button type="button" class="btn btn-success" id="publishPoll">Publish</button>
                    <?php elseif ($poll->status === 'open'): ?>
                        <button type="button" class="btn btn-secondary" id="closePoll">Close Voting</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" id="reopenPoll">Reopen</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-header-actions">
                <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken . '/results')) ?>" class="btn btn-primary">Results & Analysis</a>
                <button type="button" class="btn btn-primary" id="editPoll">Edit Poll</button>
                <button type="button" class="btn btn-outline-danger" id="deletePoll">Delete</button>
            </div>
        </header>

        <div class="admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <!-- Share Links -->
            <section class="card share-section">
                <h2>Share</h2>
                <div class="share-links">
                    <div class="share-link-group">
                        <label>Voting Link <span class="label-hint">(share with participants)</span></label>
                        <div class="copy-field">
                            <input type="text" id="publicLink" readonly value="<?= e(url($poll->publicId)) ?>">
                            <a href="<?= e(url($poll->publicId)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" data-tooltip="Open in new tab" aria-label="Open in new tab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="publicLink">Copy</button>
                        </div>
                    </div>
                    <div class="share-link-group">
                        <label>Admin Link<?php if (!$user || $poll->userId !== $user->id): ?> <span class="label-hint">(bookmark this!)</span><?php endif; ?></label>
                        <div class="copy-field">
                            <input type="text" id="adminLink" readonly value="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>">
                            <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" data-tooltip="Open in new tab" aria-label="Open in new tab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="adminLink">Copy</button>
                            <?php if ($poll->userId === null && $user !== null): ?>
                                <button type="button" class="btn btn-primary" id="claimPoll" data-tooltip="Link this poll to your account">Claim</button>
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
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <div class="claim-cta-text">
                        <h3>Secure access to this poll</h3>
                        <p>This poll isn't linked to an account yet. Log in or create an account to always have access to this admin page from your dashboard.</p>
                    </div>
                    <div class="claim-cta-actions">
                        <a href="<?= e(url('login?return=' . urlencode('/' . $poll->publicId . '/admin/' . $adminToken) . '&claim=' . $poll->publicId)) ?>" class="btn btn-primary">Log In or Register</a>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Statistics -->
            <section class="card stats-section">
                <h2>Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="responseCount"><?= $poll->getResponseCount() ?></div>
                        <div class="stat-label">Responses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= count($poll->questions) ?></div>
                        <div class="stat-label">Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $poll->createdAt->format('M j, Y') ?></div>
                        <div class="stat-label">Created</div>
                    </div>
                </div>
            </section>

            <!-- Voting Mode -->
            <section class="card voting-mode-section">
                <h2>Voting Mode</h2>
                <div class="voting-mode-display">
                    <span class="voting-mode-badge mode-<?= e($poll->votingMode) ?>"><?= e($votingModeLabels[$poll->votingMode] ?? $poll->votingMode) ?></span>
                    <p class="voting-mode-description"><?= e($votingModeDescriptions[$poll->votingMode] ?? '') ?></p>
                    <?php if ($poll->modeLockedAt): ?>
                    <p class="mode-locked-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Voting mode is locked. Responses have been submitted.
                    </p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($poll->votingMode !== 'open'): ?>
            <!-- Access Management -->
            <section class="card access-section">
                <h2>Access Management</h2>
                <p class="section-description">Manage who can vote in this <?= $poll->votingMode === 'secret_ballot' ? 'secret ballot' : 'identified' ?> poll.</p>

                <div class="access-tabs">
                    <button type="button" class="access-tab active" data-tab="invitations">Email Invitations</button>
                    <button type="button" class="access-tab" data-tab="tokens">Manual Access Tokens</button>
                </div>

                <!-- Email Invitations Tab -->
                <div class="access-tab-content" id="invitationsTab">
                    <p class="tab-description">Send voting invitations directly to participants via email. Each invitation contains a unique link that allows a single vote.</p>
                    <div id="mailConfigWarning" class="warning-banner" style="display: none;">
                        <strong>Email not configured.</strong> Ask your system administrator to configure SMTP settings.
                    </div>
                    <div class="invitation-send">
                        <textarea id="invitationEmails" placeholder="Enter email addresses (one per line, or comma-separated)" rows="3"></textarea>
                        <button type="button" class="btn btn-primary" id="sendInvitations">Send Invitations</button>
                    </div>
                    <div id="invitationsList" class="invitations-list">
                        <p class="loading">Loading invitations...</p>
                    </div>
                </div>

                <!-- Manual Access Tokens Tab -->
                <div class="access-tab-content" id="tokensTab" style="display: none;">
                    <p class="tab-description">Generate access tokens and distribute them to voters yourself (e.g., via your own email, messaging app, or printed handouts). Each token allows a single vote.</p>
                    <div class="token-generate">
                        <div class="token-generate-field">
                            <label for="tokenCount">
                                Number of tokens
                            </label>
                            <input type="number" id="tokenCount" min="1" max="100" value="10">
                        </div>
                        <div class="token-generate-field">
                            <label for="tokenLabelPrefix">
                                Label prefix
                                <span class="label-optional">(optional)</span>
                                <span class="info-icon" data-tooltip="Labels help you identify tokens, e.g. 'Board Member' creates tokens labeled 'Board Member 1', 'Board Member 2', etc." data-tooltip-pos="top">?</span>
                            </label>
                            <input type="text" id="tokenLabelPrefix" placeholder="e.g. Board Member">
                        </div>
                        <div class="token-generate-action">
                            <button type="button" class="btn btn-primary" id="generateTokens">Generate Tokens</button>
                        </div>
                    </div>
                    <div id="tokensList" class="tokens-list">
                        <p class="loading">Loading tokens...</p>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Responses -->
            <section class="card responses-section">
                <div class="section-header">
                    <h2>Responses</h2>
                    <div class="responses-actions">
                        <button type="button" class="btn btn-secondary btn-small" id="refreshResponses">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                            Refresh
                        </button>
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary btn-small" id="exportJson">Export JSON</button>
                            <button type="button" class="btn btn-secondary btn-small" id="exportCsv">Export CSV</button>
                        </div>
                    </div>
                </div>
                <p class="responses-hint">Showing a summary of responses. For detailed analysis and charts, visit the <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken . '/results')) ?>">Results & Analysis</a> page.</p>
                <div id="responsesList" class="responses-list">
                    <p class="loading">Loading responses...</p>
                </div>
            </section>

            <!-- Privacy & Display Settings -->
            <section class="card settings-section">
                <div class="section-header">
                    <h2>Privacy & Display Settings</h2>
                    <button type="button" class="btn btn-secondary btn-small" id="saveSettings" style="display: none;">Save Changes</button>
                </div>

                <div class="settings-form">
                    <div class="settings-row">
                        <div class="setting-group">
                            <label for="settingVisibility">Results Visibility</label>
                            <select id="settingVisibility" class="setting-input">
                                <option value="private" <?= $poll->visibility === 'private' ? 'selected' : '' ?>>Private (admin only)</option>
                                <option value="anonymous" <?= $poll->visibility === 'anonymous' ? 'selected' : '' ?>>Anonymous (responses without names)</option>
                                <option value="full" <?= $poll->visibility === 'full' ? 'selected' : '' ?>>Full (responses with names)</option>
                            </select>
                        </div>
                        <div class="setting-group">
                            <label for="settingVisibilityTiming">Show Results</label>
                            <select id="settingVisibilityTiming" class="setting-input">
                                <option value="during" <?= $poll->visibilityTiming === 'during' ? 'selected' : '' ?>>While voting is open</option>
                                <option value="after_close" <?= $poll->visibilityTiming === 'after_close' ? 'selected' : '' ?>>After voting closes</option>
                            </select>
                        </div>
                    </div>

                    <div class="settings-row settings-checkboxes">
                        <label class="checkbox-label <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>">
                            <input type="checkbox" id="settingCollectName" <?= $poll->collectName ? 'checked' : '' ?> <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>>
                            <span>Collect voter name</span>
                            <?php if ($poll->votingMode === 'secret_ballot'): ?>
                            <span class="setting-hint">(disabled for secret ballot)</span>
                            <?php endif; ?>
                        </label>
                        <label class="checkbox-label <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>">
                            <input type="checkbox" id="settingAllowEditOwn" <?= $poll->allowEditOwn ? 'checked' : '' ?> <?= $poll->votingMode === 'secret_ballot' ? 'disabled' : '' ?>>
                            <span>Allow voters to edit their response</span>
                            <?php if ($poll->votingMode === 'secret_ballot'): ?>
                            <span class="setting-hint">(disabled for secret ballot)</span>
                            <?php endif; ?>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="settingAllowEditAny" <?= $poll->allowEditAny ? 'checked' : '' ?>>
                            <span>Allow anyone to edit any response</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="settingRandomizeOptions" <?= $poll->randomizeOptions ? 'checked' : '' ?>>
                            <span>Randomize option order for each voter</span>
                        </label>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
