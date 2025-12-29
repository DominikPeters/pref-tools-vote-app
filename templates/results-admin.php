<?php
$title = 'Results: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results-admin.js'];
$extraCss = ['/assets/css/results.css'];
ob_start();
?>

<div class="results-container results-admin">
    <div class="container">
        <header class="results-header">
            <h1>Results & Analysis</h1>
            <div class="poll-title"><?= e($poll->title) ?></div>
            <p class="admin-notice">Configure which analyses to show publicly</p>
        </header>

        <div class="results-admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading">Loading results...</p>
            </div>
        </div>

        <footer class="results-footer">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>" class="btn btn-secondary">Back to Admin</a>
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn" target="_blank">View Public Results</a>
        </footer>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
