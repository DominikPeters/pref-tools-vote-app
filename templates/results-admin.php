<?php
use App\i18n\Translator;

$title = __('page_results_title', ['title' => $poll->title]) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/results-admin.js'];
$extraCss = ['/assets/css/results.css'];

// Analysis controls use the creator's interface language, independently of the poll.
$pageLocale = \App\i18n\UiLocale::get();
Translator::setLocale($pageLocale);

ob_start();
?>

<div class="results-container results-admin">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>"><?= e(__('poll_admin')) ?></a>
            <span class="separator">/</span>
            <span class="current"><?= e(__('page_results_analysis')) ?></span>
        </nav>

        <header class="results-header">
            <div class="results-header-top">
                <div class="results-header-title">
                    <span class="results-label"><?= e(__('page_results_analysis')) ?></span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="results-header-actions">
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>" class="btn btn-secondary"><?= e(__('page_back_to_admin')) ?></a>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/responses" class="btn btn-secondary"><?= e(__('page_browse_responses')) ?></a>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary" target="_blank"><?= e(__('page_view_public_results')) ?></a>
                </div>
            </div>
            <div class="results-visibility-bar">
                <div class="visibility-control">
                    <label for="resultsVisibility"><?= e(__('page_results_visibility_7b9259')) ?></label>
                    <?php
                    // Show "anonymous" option only for non-secret polls that collect names
                    $showAnonymousOption = $poll->votingMode !== 'secret_ballot' && $poll->collectName;
                    ?>
                    <select id="resultsVisibility" class="setting-input" data-show-anonymous="<?= $showAnonymousOption ? 'true' : 'false' ?>">
                        <option value="private" <?= $poll->visibility === 'private' ? 'selected' : '' ?>><?= e(__('page_private_admin_only')) ?></option>
                        <?php if ($showAnonymousOption): ?>
                        <option value="anonymous" <?= $poll->visibility === 'anonymous' ? 'selected' : '' ?>><?= e(__('page_public_responses_without_names')) ?></option>
                        <option value="full" <?= $poll->visibility === 'full' ? 'selected' : '' ?>><?= e(__('page_public_responses_with_names')) ?></option>
                        <?php else: ?>
                        <option value="full" <?= $poll->visibility !== 'private' ? 'selected' : '' ?>><?= e(__('page_public')) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </header>

        <div class="results-admin-content" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>">
            <div id="resultsData" class="results-data">
                <p class="loading"><?= e(__('loading_results')) ?></p>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
