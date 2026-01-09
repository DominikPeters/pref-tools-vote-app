<?php
$title = e($poll->title) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/question.css', '/assets/css/poll.css', '/assets/css/report.css'];
$extraJs = ['/assets/js/poll.js'];
$isEditing = isset($existingResponse) && $existingResponse !== null;
$isPreview = $isPreview ?? false;
ob_start();
?>

<script>
    window.POLL_DATA = <?= json_encode($poll->toPublicArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    <?php if ($isEditing): ?>
    window.EXISTING_RESPONSE = <?= json_encode($existingResponse->toArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    <?php endif; ?>
    <?php if ($isPreview): ?>
    window.IS_PREVIEW = true;
    <?php endif; ?>
</script>

<div class="poll-container">
    <div class="container">
        <header class="poll-header">
            <h1><?= e($poll->title) ?></h1>
            <?php if ($poll->description): ?>
                <div class="poll-description markdown">
                    <?= markdown($poll->description) ?>
                </div>
            <?php endif; ?>
            <?php if ($isPreview): ?>
                <div class="poll-status-banner preview">
                    Preview Mode - This is how your poll will appear to voters.
                </div>
            <?php elseif ($poll->status === 'draft'): ?>
                <div class="poll-status-banner draft">
                    This poll is not yet open for submissions.
                </div>
            <?php endif; ?>
        </header>

        <?php if ($poll->status === 'open'): ?>
            <?php if ($hasVoted && !$isEditing): ?>
                <div class="card" style="text-align: center;">
                    <?php if ($poll->thankYouMessage): ?>
                        <div class="thank-you-custom markdown">
                            <?= markdown($poll->thankYouMessage) ?>
                        </div>
                    <?php else: ?>
                        <h2>Thank you!</h2>
                        <p>Your response has already been recorded.</p>
                    <?php endif; ?>
                    <?php if ($poll->areResultsViewable()): ?>
                        <div style="margin-top: 2rem;">
                            <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary">View Results</a>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--color-text-muted); margin-top: 1rem;">
                            You can now close this page.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($isEditing): ?>
                    <div class="editing-banner">
                        You have already submitted a response. You can update it below.
                    </div>
                <?php endif; ?>
                <form id="pollForm" class="poll-form" method="post" data-public-id="<?= e($poll->publicId) ?>" data-editing="<?= $isEditing ? 'true' : 'false' ?>" <?php if ($isEditing): ?>data-response-id="<?= $existingResponse->id ?>"<?php endif; ?>>
                    <?php if ($poll->collectName): ?>
                        <div class="form-group name-field">
                            <label for="voterName">Your Name</label>
                            <input type="text" id="voterName" name="voter_name" required value="<?= !empty($user) ? e($user->name) : '' ?>">
                        </div>
                    <?php endif; ?>

                    <!-- Questions rendered by JavaScript -->
                    <div id="questionsContainer">
                        <!-- Questions will be rendered here -->
                    </div>

                    <div class="form-actions">
                        <?php if ($isPreview): ?>
                        <button type="button" class="btn btn-primary btn-large" disabled>Submit Vote (Disabled in Preview)</button>
                        <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-large"><?= $isEditing ? 'Update Response' : 'Submit Vote' ?></button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="poll-closed-message card">
                <h2>Voting is Closed</h2>
                <p>This poll is no longer accepting responses.</p>
                <?php if ($poll->visibility !== 'private'): ?>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary">View Results</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isPreview): ?>
        <div class="poll-footer">
            <button type="button" class="report-link" id="reportPollBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                    <line x1="4" y1="22" x2="4" y2="15"></line>
                </svg>
                Report this poll
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
