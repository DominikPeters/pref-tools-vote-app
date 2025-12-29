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
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary" target="_blank">View Public Results</a>
                </div>
            </div>
            <p class="admin-notice">Configure which analyses to show publicly</p>
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
