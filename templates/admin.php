<?php
$title = 'Admin: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/admin.js'];
ob_start();
?>

<div class="admin-container">
    <div class="container">
        <header class="admin-header">
            <h1>Admin Panel</h1>
            <div class="poll-title"><?= e($poll->title) ?></div>
            <div class="poll-status status-<?= e($poll->status) ?>">
                Status: <?= ucfirst(e($poll->status)) ?>
            </div>
        </header>

        <div class="admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <!-- Quick Actions -->
            <section class="card admin-actions">
                <h2>Actions</h2>
                <div class="action-buttons">
                    <?php if ($poll->status === 'draft'): ?>
                        <button type="button" class="btn btn-success" id="publishPoll">Publish Poll</button>
                    <?php elseif ($poll->status === 'open'): ?>
                        <button type="button" class="btn btn-warning" id="closePoll">Close Voting</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" id="reopenPoll">Reopen Voting</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" id="editPoll">Edit Poll</button>
                    <button type="button" class="btn btn-danger" id="deletePoll">Delete Poll</button>
                </div>
            </section>

            <!-- Share Links -->
            <section class="card share-section">
                <h2>Share</h2>
                <div class="share-links">
                    <div class="share-link-group">
                        <label>Voting Link (for participants):</label>
                        <div class="copy-field">
                            <input type="text" id="publicLink" readonly value="<?= e(url($poll->publicId)) ?>">
                            <button type="button" class="btn btn-small copy-btn" data-target="publicLink">Copy</button>
                        </div>
                    </div>
                    <div class="share-link-group">
                        <label>Admin Link (save this!):</label>
                        <div class="copy-field">
                            <input type="text" id="adminLink" readonly value="<?= e(url($poll->publicId . '/admin/' . $adminToken)) ?>">
                            <button type="button" class="btn btn-small copy-btn" data-target="adminLink">Copy</button>
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
                <h2>Responses</h2>
                <div class="responses-actions">
                    <button type="button" class="btn btn-secondary" id="refreshResponses">Refresh</button>
                    <button type="button" class="btn btn-secondary" id="exportJson">Export JSON</button>
                    <button type="button" class="btn btn-secondary" id="exportCsv">Export CSV</button>
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
