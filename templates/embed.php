<?php
/**
 * Embed template - Minimal page for iframe embedding
 * Does not use the main layout
 */

use App\i18n\Translator;

$pollLocale = $poll->locale ?? 'en';
Translator::setLocale($pollLocale);
$translations = Translator::getAllTranslations();
?>
<!DOCTYPE html>
<html lang="<?= e($pollLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($poll->title) ?></title>
    <base href="<?= basePath() ?>/">
    <link rel="stylesheet" href="<?= basePath() ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= basePath() ?>/assets/css/question.css">
    <link rel="stylesheet" href="<?= basePath() ?>/assets/css/poll.css">
    <style>
        /* Embed-specific overrides */
        body {
            background: transparent;
            min-height: auto;
        }
        .poll-container {
            padding: 0;
        }
        .container {
            max-width: 100%;
            padding: 0;
        }
        .poll-header {
            margin-bottom: 1rem;
        }
        .poll-footer {
            display: none;
        }
        .embed-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--color-border);
            text-align: center;
        }
        .embed-footer a {
            color: var(--color-text-muted);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .embed-footer a:hover {
            text-decoration: underline;
        }
        <?php if ($isPreview): ?>
        .preview-banner {
            background: var(--color-warning-bg, #fef3c7);
            color: var(--color-warning-text, #92400e);
            padding: 0.5rem 1rem;
            text-align: center;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            border-radius: var(--radius-md);
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <script>
        window.POLL_DATA = <?= json_encode($poll->toPublicArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.LOCALE = <?= json_encode($pollLocale, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.TRANSLATIONS = <?= json_encode($translations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.EMBED_TOKEN = <?= json_encode($embedToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.IS_EMBED = true;
        window.SITE_URL = <?= json_encode(url($poll->publicId), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.RESULTS_URL = <?= json_encode($poll->areResultsViewable() ? url("{$poll->publicId}/results") : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    </script>

    <div class="poll-container">
        <div class="container">
            <?php if ($isPreview): ?>
            <div class="preview-banner">
                This is a preview. Votes submitted here will not be counted.
            </div>
            <?php endif; ?>

            <header class="poll-header">
                <h1><?= e($poll->title) ?></h1>
                <?php if ($poll->description): ?>
                    <div class="poll-description markdown">
                        <?= markdown($poll->description) ?>
                    </div>
                <?php endif; ?>
            </header>

            <form id="pollForm" class="poll-form" method="post" data-public-id="<?= e($poll->publicId) ?>">
                <?php if ($poll->collectName): ?>
                    <div class="form-group name-field">
                        <label for="voterName"><?= __('your_name') ?></label>
                        <input type="text" id="voterName" name="voter_name" required>
                    </div>
                <?php endif; ?>

                <!-- Questions rendered by JavaScript -->
                <div id="questionsContainer">
                    <!-- Questions will be rendered here -->
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large"><?= __('submit_vote') ?></button>
                </div>
            </form>

            <footer class="embed-footer">
                <a href="<?= url($poll->publicId) ?>" target="_blank" rel="noopener">
                    Powered by Pref.Tools Vote
                </a>
            </footer>
        </div>
    </div>

    <script src="<?= basePath() ?>/assets/js/embed-poll.js" type="module"></script>
</body>
</html>
