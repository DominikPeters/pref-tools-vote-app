<?php
$isEditing = isset($poll) && $poll !== null;
$title = ($isEditing ? 'Edit: ' . e($poll->title) : 'Create Poll') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/builder.css'];
$extraJs = ['/assets/js/builder.js'];
ob_start();
?>

<?php if ($isEditing): ?>
<script>
    window.POLL_DATA = <?= json_encode($poll->toAdminArray()) ?>;
    window.ADMIN_TOKEN = <?= json_encode($adminToken) ?>;
</script>
<?php endif; ?>

<div class="builder-container">
    <div class="builder-header">
        <div class="container">
            <h1><?= $isEditing ? 'Edit Poll' : 'Create a New Poll' ?></h1>
            <div class="builder-actions">
                <button type="button" class="btn btn-secondary" id="previewBtn">Preview</button>
                <?php if ($isEditing): ?>
                <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" id="clearBtn" style="display: none;">Clear</button>
                <?php endif; ?>
                <?php if ($isEditing && $poll->status === 'draft'): ?>
                <button type="button" class="btn btn-primary" id="saveBtn">Update Draft</button>
                <button type="button" class="btn btn-success" id="publishBtn">Publish</button>
                <?php elseif ($isEditing): ?>
                <button type="button" class="btn btn-success" id="publishBtn">Save Changes</button>
                <?php else: ?>
                <?php if (isset($user) && $user): ?>
                <button type="button" class="btn btn-primary" id="saveBtn">Save Draft</button>
                <?php endif; ?>
                <button type="button" class="btn btn-success" id="publishBtn">Publish</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="builder-main">
        <div class="container">
            <!-- Vote Metadata -->
            <section class="poll-meta card">
                <div class="form-group">
                    <input type="text" id="pollTitle" class="input-title" placeholder="Untitled Poll" value="">
                </div>
                <div class="form-group">
                    <textarea id="pollDescription" class="input-description" placeholder="Add a description (optional, Markdown supported)"></textarea>
                </div>
            </section>

            <!-- Questions -->
            <section class="questions-list" id="questionsList">
                <!-- Questions will be added here dynamically -->
            </section>

            <!-- Add Question Button -->
            <div class="add-question-wrapper">
                <button type="button" class="btn btn-add" id="addQuestionBtn">
                    + Add Question
                </button>
            </div>

            <!-- Settings Panel -->
            <section class="settings-panel card">
                <h2>Settings</h2>

                <div class="settings-group">
                    <h3>Voter Information</h3>
                    <label class="checkbox-label">
                        <input type="checkbox" id="collectName">
                        <span>Collect voter names</span>
                    </label>
                </div>

                <div class="settings-group">
                    <h3>Privacy</h3>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="visibility" value="private" checked>
                            <span>Private (only admin can see responses)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visibility" value="anonymous">
                            <span>Anonymous (everyone can see responses, but not who voted)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visibility" value="full">
                            <span>Public (everyone can see who voted what)</span>
                        </label>
                    </div>
                </div>

                <div class="settings-group">
                    <h3>When are results visible?</h3>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="visibilityTiming" value="after_close" checked>
                            <span>After voting closes</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visibilityTiming" value="during">
                            <span>During voting (live results)</span>
                        </label>
                    </div>
                </div>

                <div class="settings-group">
                    <h3>Editing</h3>
                    <label class="checkbox-label">
                        <input type="checkbox" id="allowEditOwn" checked>
                        <span>Voters can edit their own response</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="allowEditAny">
                        <span>Anyone can edit any response (Doodle-style)</span>
                    </label>
                </div>

                <div class="settings-group">
                    <h3>Options Display</h3>
                    <label class="checkbox-label">
                        <input type="checkbox" id="randomizeOptions">
                        <span>Randomize option order for each voter</span>
                    </label>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Question Template (hidden) -->
<template id="questionTemplate">
    <div class="question-card card" data-question-id="">
        <div class="question-header">
            <span class="drag-handle">&#9776;</span>
            <select class="question-type">
                <option value="single_choice">Single Choice</option>
                <option value="approval">Approval (Multiple Choice)</option>
                <option value="ranking">Ranking</option>
                <option value="star">Star Rating</option>
                <option value="grade">Grades</option>
                <option value="yes_no_abstain">Yes / No / Abstain</option>
                <option value="text_single">Short Text</option>
                <option value="text_multi">Long Text</option>
            </select>
            <button type="button" class="btn-icon delete-question" title="Delete question">&times;</button>
        </div>
        <div class="question-body">
            <input type="text" class="question-text" placeholder="Question text">
            <textarea class="question-description" placeholder="Description (optional)"></textarea>
            <div class="options-list">
                <!-- Options will be added here -->
            </div>
            <button type="button" class="btn btn-small add-option">+ Add Option</button>
        </div>
        <div class="question-footer">
            <label class="checkbox-label">
                <input type="checkbox" class="question-required" checked>
                <span>Required</span>
            </label>
        </div>
    </div>
</template>

<!-- Option Template (hidden) -->
<template id="optionTemplate">
    <div class="option-item" data-option-id="">
        <span class="drag-handle-small">&#9776;</span>
        <input type="text" class="option-label" placeholder="Option">
        <button type="button" class="btn-icon delete-option" title="Delete option">&times;</button>
    </div>
</template>

<!-- Preview Modal -->
<div id="previewModal" class="modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Preview</h2>
            <button type="button" class="modal-close" id="closePreview">&times;</button>
        </div>
        <div class="modal-body">
            <div id="previewContent" class="poll-container preview-mode">
                <!-- Preview content will be rendered here -->
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
