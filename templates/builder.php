<?php
use App\Services\TurnstileService;
use App\i18n\Languages;
use App\i18n\Translator;
use App\i18n\UiLocale;

$isEditing = isset($poll) && $poll !== null;
$availableLanguages = Languages::getAll();
$builderLocale = UiLocale::get();
$pageLocale = $builderLocale;
$pollLocale = $isEditing ? (Languages::resolve($poll->locale) ?? Languages::getDefault()) : $builderLocale;
Translator::setLocale($builderLocale);
$isLoggedIn = isset($user) && $user;
$title = ($isEditing ? __('edit_poll') . ': ' . $poll->title : __('create_poll')) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/question.css', '/assets/css/builder.css'];
$extraJs = ['/assets/js/builder.js'];

// Turnstile is only needed for anonymous users creating new polls
$needsTurnstile = !$isEditing && !$isLoggedIn && TurnstileService::isConfigured();
$turnstileSiteKey = $needsTurnstile ? TurnstileService::getSiteKey() : '';
ob_start();
?>

<script>
    window.POLL_LOCALE = <?= json_encode($pollLocale) ?>;
    window.POLL_I18N = <?= json_encode(Translator::configuration($pollLocale), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>

<?php if ($isEditing): ?>
<script>
    window.POLL_DATA = <?= json_encode($poll->toAdminArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    window.ADMIN_TOKEN = <?= json_encode($adminToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>
<?php endif; ?>

<?php if ($needsTurnstile): ?>
<script>
    window.TURNSTILE_ENABLED = true;
    window.TURNSTILE_SITE_KEY = <?= json_encode($turnstileSiteKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
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
            <div class="builder-title">
                <?php if ($isEditing): ?>
                <nav class="breadcrumbs">
                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($adminToken) ?>"><?= e(__('poll_admin')) ?></a>
                    <span class="separator">/</span>
                    <span class="current"><?= e(__('edit_poll')) ?></span>
                </nav>
                <?php endif; ?>
                <h1><?= e($isEditing ? __('edit_poll') : __('create_new_poll')) ?></h1>
                <?php if (!$isEditing || $poll->status === 'draft'): ?>
                <p class="terms-note"><?= messageWithLinks('page_terms_agree_publish', ['terms' => [basePath() . '/terms', __('page_terms_link')]]) ?></p>
                <?php endif; ?>
            </div>
            <div class="builder-actions">
                <?php if ($isEditing): ?>
                <button type="button" class="btn btn-secondary" id="cancelBtn"><?= e(__('cancel')) ?></button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" id="clearBtn" style="display: none;"><?= e(__('clear')) ?></button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" id="previewBtn"><?= e(__('preview')) ?></button>
                <?php if ($isEditing && $poll->status === 'draft'): ?>
                <button type="button" class="btn btn-primary" id="saveBtn"><?= e(__('update_draft')) ?></button>
                <button type="button" class="btn btn-success" id="publishBtn"><?= e(__('publish')) ?></button>
                <?php elseif ($isEditing): ?>
                <button type="button" class="btn btn-success" id="publishBtn"><?= e(__('save_changes')) ?></button>
                <?php else: ?>
                <?php if (isset($user) && $user): ?>
                <button type="button" class="btn btn-primary" id="saveBtn"><?= e(__('save_draft')) ?></button>
                <?php endif; ?>
                <button type="button" class="btn btn-success" id="publishBtn"><?= e(__('publish')) ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="builder-main">
        <div class="container">
            <!-- Poll Metadata -->
            <section class="card poll-meta">
                <div class="form-group">
                    <input type="text" id="pollTitle" class="input-title" placeholder="<?= e(__('untitled_poll')) ?>" value="">
                </div>
                <div class="form-group poll-description-group">
                    <button type="button" class="btn-add-description" id="addPollDescriptionBtn">+ <?= e(__('add_description')) ?></button>
                    <textarea id="pollDescription" class="input-description" style="display: none;" placeholder="<?= e(__('description_markdown_placeholder')) ?>"></textarea>
                    <div id="pollDescriptionPreview" class="description-preview markdown" style="display: none;"></div>
                </div>
            </section>

            <!-- Questions (rendered by JavaScript) -->
            <section class="questions-list" id="questionsList">
                <!-- Questions will be rendered here dynamically -->
            </section>

            <!-- Add Question Button & Type Selector Tray -->
            <div class="add-question-wrapper">
                <button type="button" class="btn btn-add" id="addQuestionBtn">
                    + <?= e(__('add_question')) ?>
                </button>
                <div class="question-type-tray" id="questionTypeTray">
                    <!-- Choice -->
                    <div class="type-category">
                        <div class="type-category-label"><?= e(__('category_choice')) ?></div>
                        <button type="button" class="type-btn" data-type="single_choice">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9"/>
                                <circle cx="12" cy="12" r="4" fill="currentColor"/>
                            </svg>
                            <span><?= e(__('type_single_choice')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="approval">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="3"/>
                                <polyline points="7 12 10 15 17 8"/>
                            </svg>
                            <span><?= e(__('type_approval')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="participatory_budgeting">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="M8 15V9M12 15v-3M16 15v-5"/>
                            </svg>
                            <span><?= e(__('type_participatory_budgeting')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="distribution">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 12V3a9 9 0 0 1 7.79 13.5z" fill="currentColor" stroke="none"/>
                                <path d="M12 3v9l7.79 4.5" fill="none"/>
                                <path d="M12 12L4.21 16.5" fill="none"/>
                            </svg>
                            <span><?= e(__('type_distribution')) ?></span>
                        </button>
                    </div>
                    <!-- Ranking -->
                    <div class="type-category">
                        <div class="type-category-label"><?= e(__('category_ranking')) ?></div>
                        <button type="button" class="type-btn" data-type="ranking">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <rect x="4" y="3" width="16" height="5" rx="1"/>
                                <rect x="4" y="10" width="16" height="5" rx="1"/>
                                <rect x="4" y="17" width="16" height="5" rx="1"/>
                            </svg>
                            <span><?= e(__('type_ranking')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="ranking_truncated">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <rect x="4" y="3" width="16" height="5" rx="1"/>
                                <rect x="4" y="10" width="16" height="5" rx="1"/>
                                <rect x="4" y="17" width="16" height="5" rx="1" stroke-dasharray="1 1" opacity="0.4"/>
                            </svg>
                            <span><?= e(__('type_ranking_truncated')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="ranking_with_ties">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <rect x="7" y="2" width="10" height="4" rx="1"/>
                                <rect x="4" y="8" width="7" height="4" rx="1"/>
                                <rect x="13" y="8" width="7" height="4" rx="1"/>
                                <rect x="7" y="14" width="10" height="4" rx="1"/>
                            </svg>
                            <span><?= e(__('type_ranking_with_ties')) ?></span>
                        </button>
                    </div>
                    <!-- Rating -->
                    <div class="type-category">
                        <div class="type-category-label"><?= e(__('category_rating')) ?></div>
                        <button type="button" class="type-btn" data-type="star">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span><?= e(__('type_star')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="grade">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="4" y="4" width="16" height="16" rx="2"/>
                                <text x="8" y="16" font-size="12" font-weight="bold" fill="currentColor" stroke="none">A</text>
                            </svg>
                            <span><?= e(__('type_grade')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="yes_no_abstain">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <circle cx="6.5" cy="12" r="5"/>
                                <polyline points="4.5 12 6 13.5 8.5 10" stroke-width="1.5"/>
                                <circle cx="17.5" cy="12" r="5"/>
                                <path d="M15.5 10l4 4M19.5 10l-4 4" stroke-width="1.5"/>
                            </svg>
                            <span><?= e(__('type_yes_no_abstain')) ?></span>
                        </button>
                    </div>
                    <!-- Other -->
                    <div class="type-category">
                        <div class="type-category-label"><?= e(__('category_other')) ?></div>
                        <button type="button" class="type-btn" data-type="text_single">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="8" width="20" height="8" rx="2"/>
                                <path d="M5 12h10"/>
                            </svg>
                            <span><?= e(__('type_text_single')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="text_multi">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="18" rx="2"/>
                                <path d="M5 7h14M5 11h12M5 15h8"/>
                            </svg>
                            <span><?= e(__('type_text_multi')) ?></span>
                        </button>
                        <button type="button" class="type-btn" data-type="section_header">
                            <svg class="type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <text x="2" y="11" font-size="10" font-weight="bold" fill="currentColor" stroke="none">Ab</text>
                                <path d="M2 15h20" stroke-width="1.5"/>
                                <path d="M2 19h14" stroke-width="1" opacity="0.5"/>
                            </svg>
                            <span><?= e(__('type_section_header')) ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Voting Mode -->
            <section class="voting-mode-panel card">
                <h2><?= e(__('voting_mode')) ?></h2>
                <p class="section-description"><?= e(__('voting_mode_description')) ?></p>

                <div class="mode-options">
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="open" checked>
                        <div class="mode-card">
                            <div class="mode-header">
                                <svg class="mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                </svg>
                                <h3><?= e(__('voting_mode_open')) ?></h3>
                            </div>
                            <p><?= e(__('voting_mode_open_description')) ?></p>
                        </div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="identified">
                        <div class="mode-card">
                            <div class="mode-header">
                                <svg class="mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                <h3><?= e(__('voting_mode_identified')) ?></h3>
                            </div>
                            <p><?= e(__('voting_mode_identified_description')) ?></p>
                        </div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="votingMode" value="secret_ballot">
                        <div class="mode-card">
                            <div class="mode-header">
                                <svg class="mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                                </svg>
                                <h3><?= e(__('voting_mode_secret_ballot')) ?></h3>
                            </div>
                            <p><?= e(__('voting_mode_secret_ballot_description')) ?></p>
                        </div>
                    </label>
                </div>

                <div id="modeLockWarning" class="warning-banner" style="display: none;">
                    <strong><?= e(__('voting_mode_locked')) ?></strong> <?= e(__('voting_mode_locked_description')) ?>
                </div>
            </section>

            <!-- Display Options -->
            <section class="settings-panel card">
                <h2><?= e(__('display_options')) ?></h2>

                <div class="settings-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="randomizeOptions">
                        <span><?= e(__('randomize_options_label')) ?></span>
                        <span class="info-icon" data-tooltip="<?= e(__('randomize_options_tooltip')) ?>" data-tooltip-pos="right">?</span>
                    </label>
                </div>

                <div class="settings-group builder-settings-group-spaced">
                    <label for="pollLocale" class="builder-settings-label"><?= e(__('poll_language')) ?></label>
                    <select id="pollLocale" class="form-select builder-locale-select">
                        <?php foreach ($availableLanguages as $code => $name): ?>
                        <option value="<?= e($code) ?>"><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="info-icon" data-tooltip="<?= e(__('poll_language_tooltip')) ?>" data-tooltip-pos="right">?</span>
                </div>

                <div class="settings-group builder-settings-group-spaced">
                    <button type="button" class="btn btn-secondary btn-small" id="editThankYouBtn">
                        <?= e(__('customize_thank_you_message')) ?>
                    </button>
                    <span id="thankYouStatus" class="status-indicator thank-you-status-indicator" style="display: none;"><?= e(__('custom_message_set')) ?></span>
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
