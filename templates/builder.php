<?php
$isEditing = isset($poll) && $poll !== null;
$title = ($isEditing ? 'Edit: ' . e($poll->title) : 'Create Poll') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/question.css', '/assets/css/builder.css'];
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
            <!-- Poll Metadata -->
            <section class="poll-meta card">
                <div class="form-group">
                    <input type="text" id="pollTitle" class="input-title" placeholder="Untitled Poll" value="">
                </div>
                <div class="form-group">
                    <textarea id="pollDescription" class="input-description" placeholder="Add a description (optional, Markdown supported)"></textarea>
                </div>
            </section>

            <!-- Questions (rendered by JavaScript) -->
            <section class="questions-list" id="questionsList">
                <!-- Questions will be rendered here dynamically -->
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

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
