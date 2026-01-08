<?php
$title = 'Individual Responses: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/responses-admin.js'];
$extraCss = ['/assets/css/question.css', '/assets/css/poll.css', '/assets/css/responses-admin.css'];
ob_start();
?>

<script>
    window.POLL_DATA = <?= json_encode($poll->toPublicArray()) ?>;
    window.ADMIN_TOKEN = <?= json_encode($adminToken) ?>;
</script>

<div class="responses-admin-container">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>">Poll Admin</a>
            <span class="separator">/</span>
            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/results">Results & Analysis</a>
            <span class="separator">/</span>
            <span class="current">Individual Responses</span>
        </nav>

        <header class="responses-header">
            <div class="responses-header-top">
                <div class="responses-header-title">
                    <span class="responses-label">Individual Responses</span>
                    <h1><?= e($poll->title) ?></h1>
                </div>
                <div class="responses-header-actions">
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>/results" class="btn btn-secondary">Back to Results</a>
                </div>
            </div>
        </header>

        <div class="responses-browser" data-public-id="<?= e($poll->publicId) ?>" data-admin-token="<?= e($adminToken) ?>" data-collect-name="<?= $poll->collectName ? 'true' : 'false' ?>">
            <!-- Navigation bar -->
            <div class="responses-nav">
                <div class="responses-nav-pagination">
                    <button type="button" class="btn btn-icon" id="prevResponse" title="Previous response" aria-label="Previous response" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="responses-nav-position">
                        <label for="responseIndex" class="visually-hidden">Response number</label>
                        <input type="number" id="responseIndex" min="1" value="1" class="response-index-input" aria-label="Response number">
                        <span class="responses-nav-separator">of</span>
                        <span id="totalResponses">0</span>
                    </div>
                    <button type="button" class="btn btn-icon" id="nextResponse" title="Next response" aria-label="Next response" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </div>

                <?php if ($poll->collectName): ?>
                <div class="responses-nav-dropdown">
                    <label for="voterSelect" class="visually-hidden">Select voter</label>
                    <select id="voterSelect" class="voter-select" aria-label="Select voter">
                        <option value="">Select voter...</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="responses-nav-actions">
                    <button type="button" class="btn btn-danger btn-sm" id="deleteResponse" title="Delete this response" aria-label="Delete this response" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        </svg>
                        Delete
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div class="responses-empty" id="emptyState" style="display: none;">
                <p>No responses yet.</p>
                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>" class="btn btn-primary">Back to Poll Admin</a>
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
                <p>Loading responses...</p>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
