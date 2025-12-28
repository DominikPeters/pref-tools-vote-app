<?php
$title = 'Admin: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/admin.js'];
ob_start();
?>

<div class="admin-container">
    <div class="container">
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
                            <a href="<?= e(url($poll->publicId)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" title="Open in new tab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="publicLink">Copy</button>
                        </div>
                    </div>
                    <div class="share-link-group">
                        <label>Admin Link <span class="label-hint">(bookmark this!)</span></label>
                        <div class="copy-field">
                            <input type="text" id="adminLink" readonly value="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>">
                            <a href="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>" target="_blank" class="btn btn-secondary btn-icon-only" title="Open in new tab">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                            <button type="button" class="btn btn-secondary copy-btn" data-target="adminLink">Copy</button>
                        </div>
                    </div>
                </div>
            </section>

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
