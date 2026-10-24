<?php
$title = __('page_individual_responses_title', ['title' => $poll->title]) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/responses-admin.js'];
$extraCss = ['/assets/css/question.css', '/assets/css/poll.css', '/assets/css/responses-admin.css'];
ob_start();
?>

<script>
    window.POLL_DATA = <?= json_encode($poll->toPublicArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    window.ADMIN_TOKEN = <?= json_encode($adminToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;</script>

<div class="responses-admin-container">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>"><?= e(__('poll_admin')) ?></a>
            <span class="separator">/</span>
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/results"><?= e(__('page_results_analysis')) ?></a>
            <span class="separator">/</span>
            <span class="current"><?= e(__('page_individual_responses')) ?></span>
        </nav>

        <header class="responses-header">
            <div class="responses-header-top">
                <div class="responses-header-title">
                    <span class="responses-label"><?= e(__('page_individual_responses')) ?></span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="responses-header-actions">
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/results" class="btn btn-secondary"><?= e(__('page_back_to_results')) ?></a>
                </div>
            </div>
        </header>

        <div class="responses-browser" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>" data-collect-name="<?= $poll->collectName ? 'true' : 'false' ?>">
            <!-- Navigation bar -->
            <div class="responses-nav">
                <div class="responses-nav-pagination">
                    <button type="button" class="btn btn-icon" id="prevResponse" title="<?= e(__('page_previous_response')) ?>" aria-label="<?= e(__('page_previous_response')) ?>" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="responses-nav-position">
                        <label for="responseIndex" class="visually-hidden"><?= e(__('page_response_number')) ?></label>
                        <input type="number" id="responseIndex" min="1" value="1" class="response-index-input" aria-label="<?= e(__('page_response_number')) ?>">
                        <span class="responses-nav-separator">/</span>
                        <span id="totalResponses">0</span>
                    </div>
                    <button type="button" class="btn btn-icon" id="nextResponse" title="<?= e(__('page_next_response')) ?>" aria-label="<?= e(__('page_next_response')) ?>" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </div>

                <?php if ($poll->collectName): ?>
                <div class="responses-nav-dropdown">
                    <label for="voterSelect" class="visually-hidden"><?= e(__('page_select_voter')) ?></label>
                    <select id="voterSelect" class="voter-select" aria-label="<?= e(__('page_select_voter')) ?>">
                        <option value=""><?= e(__('page_select_voter_dbcb55')) ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="responses-nav-actions">
                    <button type="button" class="btn btn-danger btn-sm" id="deleteResponse" title="<?= e(__('page_delete_this_response')) ?>" aria-label="<?= e(__('page_delete_this_response')) ?>" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        </svg>
                        <?= e(__('delete')) ?>
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div class="responses-empty" id="emptyState" style="display: none;">
                <p><?= e(__('no_responses')) ?></p>
                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>" class="btn btn-primary"><?= e(__('page_back_to_poll_admin')) ?></a>
            </div>

            <!-- Response display -->
            <div class="response-display" id="responseDisplay">
                <div class="response-meta" id="responseMeta">
                    <!-- Voter name and timestamp will be inserted here -->
                </div>
                <div class="response-form-readonly" id="responseForm">
                    <!-- Questions with answers will be rendered here -->
                </div>
            </div>

            <!-- Loading state -->
            <div class="responses-loading" id="loadingState">
                <p><?= e(__('page_loading_responses')) ?></p>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
