<?php
$title = 'Results: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results-admin.js'];
$extraCss = ['/assets/css/results.css'];
ob_start();
?>

<div class="results-container results-admin">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>">Poll Admin</a>
            <span class="separator">/</span>
            <span class="current">Results & Analysis</span>
        </nav>

        <header class="results-header">
            <div class="results-header-top">
                <div class="results-header-title">
                    <span class="results-label">Results & Analysis</span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="results-header-actions">
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>" class="btn btn-secondary">Back to Admin</a>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/responses" class="btn btn-secondary">Browse Responses</a>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary" target="_blank">View Public Results</a>
                </div>
            </div>
            <div class="results-visibility-bar">
                <div class="visibility-control">
                    <label for="resultsVisibility">Results Visibility:</label>
                    <?php
                    // Show "anonymous" option only for non-secret polls that collect names
                    $showAnonymousOption = $poll->votingMode !== 'secret_ballot' && $poll->collectName;
                    ?>
                    <select id="resultsVisibility" class="setting-input" data-show-anonymous="<?= $showAnonymousOption ? 'true' : 'false' ?>">
                        <option value="private" <?= $poll->visibility === 'private' ? 'selected' : '' ?>>Private (admin only)</option>
                        <?php if ($showAnonymousOption): ?>
                        <option value="anonymous" <?= $poll->visibility === 'anonymous' ? 'selected' : '' ?>>Public (responses without names)</option>
                        <option value="full" <?= $poll->visibility === 'full' ? 'selected' : '' ?>>Public (responses with names)</option>
                        <?php else: ?>
                        <option value="full" <?= $poll->visibility !== 'private' ? 'selected' : '' ?>>Public</option>
                        <?php endif; ?>
                    </select>
                </div>
                <p class="admin-notice">Configure results visibility and which analyses to show publicly. Changes to visibility are saved automatically.</p>
            </div>
        </header>

        <div class="results-admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading">Loading results...</p>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
