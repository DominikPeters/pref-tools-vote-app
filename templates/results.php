<?php
$title = 'Results: ' . e($vote->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results.js'];
ob_start();
?>

<div class="results-container">
    <div class="container">
        <header class="results-header">
            <h1>Results</h1>
            <div class="vote-title"><?= e($vote->title) ?></div>
            <?php if ($vote->status === 'open'): ?>
                <div class="live-badge">Live Results</div>
            <?php endif; ?>
        </header>

        <div class="results-content" data-public-id="<?= e($vote->publicId) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading">Loading results...</p>
            </div>
        </div>

        <footer class="results-footer">
            <a href="<?= basePath() ?>/<?= e($vote->publicId) ?>" class="btn btn-secondary">Back to Vote</a>
        </footer>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
