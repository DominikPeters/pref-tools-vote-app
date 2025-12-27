<?php
$title = e($vote->title) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/vote.css'];
$extraJs = ['/assets/js/vote.js'];
$isEditing = isset($existingResponse) && $existingResponse !== null;
ob_start();
?>

<?php if ($isEditing): ?>
<script>
    window.EXISTING_RESPONSE = <?= json_encode($existingResponse->toArray()) ?>;
</script>
<?php endif; ?>

<div class="vote-container">
    <div class="container">
        <header class="vote-header">
            <h1><?= e($vote->title) ?></h1>
            <?php if ($vote->description): ?>
                <div class="vote-description">
                    <?= nl2br(e($vote->description)) ?>
                </div>
            <?php endif; ?>
            <?php if ($vote->status === 'draft'): ?>
                <div class="vote-status-banner draft">
                    This vote is not yet open for submissions.
                </div>
            <?php endif; ?>
        </header>

        <?php if ($vote->status === 'open'): ?>
            <?php if ($isEditing): ?>
                <div class="editing-banner">
                    You have already submitted a response. You can update it below.
                </div>
            <?php endif; ?>
            <form id="voteForm" class="vote-form" data-public-id="<?= e($vote->publicId) ?>" data-editing="<?= $isEditing ? 'true' : 'false' ?>" <?php if ($isEditing): ?>data-response-id="<?= $existingResponse->id ?>"<?php endif; ?>>
                <?php if ($vote->collectName): ?>
                    <div class="form-group name-field">
                        <label for="voterName">Your Name</label>
                        <input type="text" id="voterName" name="voter_name" required>
                    </div>
                <?php endif; ?>

                <?php foreach ($vote->questions as $question): ?>
                    <div class="question-block card" data-question-id="<?= $question->id ?>" data-type="<?= e($question->type) ?>">
                        <div class="question-text">
                            <?= e($question->text) ?>
                            <?php if ($question->required): ?>
                                <span class="required-marker">*</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($question->description): ?>
                            <div class="question-description">
                                <?= nl2br(e($question->description)) ?>
                            </div>
                        <?php endif; ?>

                        <div class="question-input">
                            <?php
                            $inputName = "answers[{$question->id}]";
                            switch ($question->type):
                                case 'text_single':
                            ?>
                                    <input type="text" name="<?= $inputName ?>" class="form-control" <?= $question->required ? 'required' : '' ?>>
                                <?php break;

                                case 'text_multi': ?>
                                    <textarea name="<?= $inputName ?>" class="form-control" rows="4" <?= $question->required ? 'required' : '' ?>></textarea>
                                <?php break;

                                case 'single_choice': ?>
                                    <div class="radio-options">
                                        <?php foreach ($question->options as $option): ?>
                                            <label class="radio-option">
                                                <input type="radio" name="<?= $inputName ?>" value="<?= $option->id ?>" <?= $question->required ? 'required' : '' ?>>
                                                <span><?= e($option->label) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php break;

                                case 'approval': ?>
                                    <div class="checkbox-options">
                                        <?php foreach ($question->options as $option): ?>
                                            <label class="checkbox-option">
                                                <input type="checkbox" name="<?= $inputName ?>[]" value="<?= $option->id ?>">
                                                <span><?= e($option->label) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php break;

                                case 'ranking': ?>
                                    <div class="ranking-options" data-question-id="<?= $question->id ?>">
                                        <p class="ranking-hint">Drag to reorder (top = best)</p>
                                        <ol class="ranking-list">
                                            <?php foreach ($question->options as $option): ?>
                                                <li class="ranking-item" data-option-id="<?= $option->id ?>">
                                                    <span class="drag-handle">&#9776;</span>
                                                    <span class="option-label"><?= e($option->label) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                        <input type="hidden" name="<?= $inputName ?>" class="ranking-value">
                                    </div>
                                <?php break;

                                case 'star': ?>
                                    <div class="star-options">
                                        <?php foreach ($question->options as $option): ?>
                                            <div class="star-row">
                                                <span class="option-label"><?= e($option->label) ?></span>
                                                <div class="star-rating" data-option-id="<?= $option->id ?>">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <button type="button" class="star" data-value="<?= $i ?>">&#9733;</button>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <input type="hidden" name="<?= $inputName ?>" class="star-value">
                                    </div>
                                <?php break;

                                case 'grade': ?>
                                    <div class="grade-options">
                                        <?php
                                        $grades = ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Reject'];
                                        foreach ($question->options as $option): ?>
                                            <div class="grade-row">
                                                <span class="option-label"><?= e($option->label) ?></span>
                                                <select name="<?= $inputName ?>[<?= $option->id ?>]" class="grade-select">
                                                    <option value="">Select...</option>
                                                    <?php foreach ($grades as $grade): ?>
                                                        <option value="<?= strtolower($grade) ?>"><?= $grade ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php break;

                                case 'yes_no_abstain': ?>
                                    <div class="yna-options">
                                        <?php foreach ($question->options as $option): ?>
                                            <div class="yna-row">
                                                <span class="option-label"><?= e($option->label) ?></span>
                                                <div class="yna-buttons" data-option-id="<?= $option->id ?>">
                                                    <button type="button" class="yna-btn yes" data-value="yes">Yes</button>
                                                    <button type="button" class="yna-btn no" data-value="no">No</button>
                                                    <button type="button" class="yna-btn abstain" data-value="abstain">Abstain</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <input type="hidden" name="<?= $inputName ?>" class="yna-value">
                                    </div>
                                <?php break;

                            endswitch;
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large"><?= $isEditing ? 'Update Response' : 'Submit Vote' ?></button>
                </div>
            </form>
        <?php else: ?>
            <div class="vote-closed-message card">
                <h2>Voting is Closed</h2>
                <p>This vote is no longer accepting responses.</p>
                <?php if ($vote->visibility !== 'private'): ?>
                    <a href="<?= basePath() ?>/<?= e($vote->publicId) ?>/results" class="btn btn-primary">View Results</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
