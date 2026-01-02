<?php
$title = 'Individual Responses: ' . e($poll->title) . ' - Pref.Tools Vote';
$extraJs = ['/assets/js/responses-admin.js'];
$extraCss = ['/assets/css/question.css', '/assets/css/poll.css'];
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
                    <button type="button" class="btn btn-icon" id="prevResponse" title="Previous response" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="responses-nav-position">
                        <input type="number" id="responseIndex" min="1" value="1" class="response-index-input">
                        <span class="responses-nav-separator">of</span>
                        <span id="totalResponses">0</span>
                    </div>
                    <button type="button" class="btn btn-icon" id="nextResponse" title="Next response" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </div>

                <?php if ($poll->collectName): ?>
                <div class="responses-nav-dropdown">
                    <select id="voterSelect" class="voter-select">
                        <option value="">Select voter...</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="responses-nav-actions">
                    <button type="button" class="btn btn-danger btn-sm" id="deleteResponse" title="Delete this response" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

<style>
.responses-admin-container {
    padding-bottom: var(--spacing-xl);
}

.responses-header {
    margin-bottom: var(--spacing-lg);
}

.responses-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.responses-label {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.responses-header h1 {
    margin: var(--spacing-xs) 0 0 0;
}

.responses-header-actions {
    display: flex;
    gap: var(--spacing-sm);
}

/* Navigation bar */
.responses-nav {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    flex-wrap: wrap;
}

.responses-nav-pagination {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.responses-nav-position {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-base);
}

.response-index-input {
    width: 4rem;
    text-align: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-base);
    -moz-appearance: textfield;
}

.response-index-input::-webkit-outer-spin-button,
.response-index-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.responses-nav-separator {
    color: var(--color-text-muted);
    margin: 0 var(--spacing-sm);
}

.responses-nav-dropdown {
    flex: 1;
    min-width: 200px;
}

.voter-select {
    width: 100%;
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-base);
    background: var(--color-bg);
}

.responses-nav-actions {
    margin-left: auto;
}

.btn-icon {
    padding: var(--spacing-xs);
    min-width: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Response display */
.response-display {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    padding-bottom: 0;
}

.response-meta {
    padding-bottom: var(--spacing-md);
    margin-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border);
}

.response-meta-name {
    font-size: var(--font-size-lg);
    font-weight: 600;
    margin-bottom: var(--spacing-xs);
}

.response-meta-time {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
}

.response-form-readonly {
    pointer-events: none;
}

.response-form-readonly .question-display {
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-md) var(--spacing-lg);
}

/* Empty and loading states */
.responses-empty,
.responses-loading {
    text-align: center;
    padding: var(--spacing-xl);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}

.responses-empty p,
.responses-loading p {
    color: var(--color-text-muted);
    margin-bottom: var(--spacing-md);
}

/* Hide response display while loading */
.responses-browser.loading #responseDisplay,
.responses-browser.loading .responses-nav {
    display: none;
}

.responses-browser.loading #loadingState {
    display: block;
}

.responses-browser:not(.loading) #loadingState {
    display: none;
}

.responses-browser.empty #responseDisplay,
.responses-browser.empty .responses-nav {
    display: none;
}

.responses-browser.empty #emptyState {
    display: block;
}

/* Readonly form styles - show selections clearly */
.response-form-readonly input[type="radio"]:checked + span,
.response-form-readonly input[type="checkbox"]:checked + span {
    font-weight: 600;
    color: var(--color-primary);
}

.response-form-readonly .grade-btn.active,
.response-form-readonly .yna-btn.active {
    pointer-events: none;
}

.response-form-readonly .star.active {
    color: var(--color-warning) !important;
}

@media (max-width: 600px) {
    .response-display {
        padding: var(--spacing-md);
    }

    .response-form-readonly .question-display {
        padding: var(--spacing-md);
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
