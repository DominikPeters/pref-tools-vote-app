<?php
$title = 'Results: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results.js'];
ob_start();
?>

<div class="results-container">
    <div class="container">
        <header class="results-header">
            <h1>Results</h1>
            <div class="poll-title"><?= e($poll->title) ?></div>
            <?php if ($poll->status === 'open'): ?>
                <div class="live-badge">Live Results</div>
            <?php endif; ?>
        </header>

        <div class="results-content" data-public-id="<?= e($poll->publicId) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading">Loading results...</p>
            </div>
        </div>

        <footer class="results-footer">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-secondary">Back to Poll</a>
        </footer>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
