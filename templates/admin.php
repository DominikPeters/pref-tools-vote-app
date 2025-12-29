<?php
$title = 'Admin: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/admin.js'];
ob_start();
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

            <!-- Settings Summary -->
            <section class="card settings-summary">
                <h2>Settings</h2>
                <dl class="settings-list">
                    <dt>Visibility:</dt>
                    <dd><?= e($poll->visibility) ?> (<?= e($poll->visibilityTiming) ?>)</dd>

                    <dt>Collect Names:</dt>
                    <dd><?= $poll->collectName ? 'Yes' : 'No' ?></dd>

                    <dt>Edit Own Response:</dt>
                    <dd><?= $poll->allowEditOwn ? 'Yes' : 'No' ?></dd>

                    <dt>Edit Any Response:</dt>
                    <dd><?= $poll->allowEditAny ? 'Yes' : 'No' ?></dd>

                    <dt>Randomize Options:</dt>
                    <dd><?= $poll->randomizeOptions ? 'Yes' : 'No' ?></dd>
                </dl>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
