<?php
use App\i18n\Translator;

$title = 'Results: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results.js'];
$extraCss = ['/assets/css/results.css', '/assets/css/report.css'];

// Set locale for this poll and get translations for JavaScript
$pollLocale = $poll->locale ?? 'en';
Translator::setLocale($pollLocale);
$translations = Translator::getAllTranslations();

ob_start();
?>

<script>
    window.LOCALE = <?= json_encode($pollLocale, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    window.TRANSLATIONS = <?= json_encode($translations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>

<div class="results-container">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>"><?= __('poll') ?></a>
            <span class="separator">/</span>
            <span class="current"><?= __('results') ?></span>
        </nav>

        <header class="results-header">
            <h1><?= __('results') ?></h1>
            <div class="poll-title"><?= e($poll->title) ?></div>
            <?php if ($poll->status === 'open'): ?>
                <div class="live-badge"><?= __('live_results') ?></div>
            <?php endif; ?>
        </header>

        <div class="results-content" data-public-id="<?= e($poll->publicId) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading"><?= __('loading_results') ?></p>
            </div>
        </div>

        <footer class="results-footer">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-secondary"><?= __('back_to_poll') ?></a>
            <button type="button" class="report-link" id="reportPollBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                    <line x1="4" y1="22" x2="4" y2="15"></line>
                </svg>
                <?= __('report_poll') ?>
            </button>
        </footer>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
