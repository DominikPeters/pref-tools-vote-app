<?php
$title = e($poll->title) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/question.css', '/assets/css/poll.css'];
$extraJs = ['/assets/js/poll.js'];
$isEditing = isset($existingResponse) && $existingResponse !== null;
ob_start();
?>

<script>
    window.POLL_DATA = <?= json_encode($poll->toPublicArray()) ?>;
    <?php if ($isEditing): ?>
    window.EXISTING_RESPONSE = <?= json_encode($existingResponse->toArray()) ?>;
    <?php endif; ?>
</script>

<div class="poll-container">
    <div class="container">
        <header class="poll-header">
            <h1><?= e($poll->title) ?></h1>
            <?php if ($poll->description): ?>
                <div class="poll-description">
                    <?= nl2br(e($poll->description)) ?>
                </div>
            <?php endif; ?>
            <?php if ($poll->status === 'draft'): ?>
                <div class="poll-status-banner draft">
                    This poll is not yet open for submissions.
                </div>
            <?php endif; ?>
        </header>

        <?php if ($poll->status === 'open'): ?>
            <?php if ($isEditing): ?>
                <div class="editing-banner">
                    You have already submitted a response. You can update it below.
                </div>
            <?php endif; ?>
            <form id="pollForm" class="poll-form" data-public-id="<?= e($poll->publicId) ?>" data-editing="<?= $isEditing ? 'true' : 'false' ?>" <?php if ($isEditing): ?>data-response-id="<?= $existingResponse->id ?>"<?php endif; ?>>
                <?php if ($poll->collectName): ?>
                    <div class="form-group name-field">
                        <label for="voterName">Your Name</label>
                        <input type="text" id="voterName" name="voter_name" required>
                    </div>
                <?php endif; ?>

                <!-- Questions rendered by JavaScript -->
                <div id="questionsContainer">
                    <!-- Questions will be rendered here -->
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large"><?= $isEditing ? 'Update Response' : 'Submit Vote' ?></button>
                </div>
            </form>
        <?php else: ?>
            <div class="poll-closed-message card">
                <h2>Voting is Closed</h2>
                <p>This poll is no longer accepting responses.</p>
                <?php if ($poll->visibility !== 'private'): ?>
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-primary">View Results</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
