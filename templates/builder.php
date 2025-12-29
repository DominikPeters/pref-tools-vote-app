<?php
use App\Services\TurnstileService;

$isEditing = isset($poll) && $poll !== null;
$isLoggedIn = isset($user) && $user;
$title = ($isEditing ? 'Edit: ' . e($poll->title) : 'Create Poll') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/question.css', '/assets/css/builder.css'];
$extraJs = ['/assets/js/builder.js'];

// Turnstile is only needed for anonymous users creating new polls
$needsTurnstile = !$isEditing && !$isLoggedIn && TurnstileService::isConfigured();
$turnstileSiteKey = $needsTurnstile ? TurnstileService::getSiteKey() : '';
ob_start();
?>

<?php if ($isEditing): ?>
<script>
    window.POLL_DATA = <?= json_encode($poll->toAdminArray()) ?>;
    window.ADMIN_TOKEN = <?= json_encode($adminToken) ?>;
</script>
<?php endif; ?>

<?php if ($needsTurnstile): ?>
<script>
    window.TURNSTILE_ENABLED = true;
    window.TURNSTILE_SITE_KEY = <?= json_encode($turnstileSiteKey) ?>;
</script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
<?php else: ?>
<script>
    window.TURNSTILE_ENABLED = false;
</script>
<?php endif; ?>

<div class="builder-container">
    <div class="builder-header">
        <div class="container">
            <?php if ($isEditing): ?>
            <nav class="breadcrumbs">
                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>">Poll Admin</a>
                <span class="separator">/</span>
                <span class="current">Edit Poll</span>
            </nav>
            <?php endif; ?>
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
            <section class="card">
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

            <!-- Voting Mode -->
            <section class="voting-mode-panel card">
                <h2>Voting Mode</h2>
                <p class="section-description">Choose how voters will access your poll and how their votes are recorded.</p>

                <div class="mode-options">
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="open" checked>
                        <div class="mode-card">
                            <h3>Open</h3>
                            <p>Anyone with the link can vote. One vote per browser.</p>
                        </div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="identified">
                        <div class="mode-card">
                            <h3>Identified</h3>
                            <p>One vote per person via access tokens or email invitations. Votes are linked to identity.</p>
                        </div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="secret_ballot">
                        <div class="mode-card">
                            <h3>Secret Ballot</h3>
                            <p>One vote per person, but votes are completely anonymous. Cannot be changed after submission.</p>
                        </div>
                    </label>
                </div>

                <div id="modeLockWarning" class="warning-banner" style="display: none;">
                    <strong>Voting mode is locked.</strong> Responses have been submitted. Delete all responses to change the mode.
                </div>
            </section>

            <!-- Display Options -->
            <section class="settings-panel card">
                <h2>Display Options</h2>

                <div class="settings-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="randomizeOptions">
                        <span>Randomize option order for each voter</span>
                        <span class="info-icon" data-tooltip="Randomizing option order helps reduce position bias in responses" data-tooltip-pos="right">?</span>
                    </label>
                </div>
            </section>

            <?php if ($needsTurnstile): ?>
            <!-- Turnstile container (invisible) -->
            <div id="turnstileContainer" class="turnstile-container"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
