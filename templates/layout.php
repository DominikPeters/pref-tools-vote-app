<?php
use App\i18n\Languages;
use App\i18n\Translator;
use App\i18n\UiLocale;
$uiLocale = UiLocale::get();
$pageLocale = $pageLocale ?? $uiLocale;
Translator::setLocale($uiLocale);
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS;
?>
<!DOCTYPE html>
<html lang="<?= e($pageLocale) ?>" dir="<?= Languages::getDirection($pageLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($metaDescription ?? 'Create polls with advanced voting methods like Schulze, IRV, and Majority Judgment. Collect rankings, approval votes, and analyze results with research-grade social choice algorithms.') ?>">
    <meta name="theme-color" content="#4f46e5">
    <meta property="og:title" content="<?= e($title ?? 'Pref.Tools Vote') ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? 'Smarter polls with advanced voting methods. Go beyond simple polls with rankings, approval votes, and research-grade analysis.') ?>">
    <meta property="og:type" content="website">
    <title><?= e($title ?? 'Pref.Tools Vote') ?></title>
    <?= csrf_meta() ?>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= asset(ltrim($css, '/')) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <script>
        window.BASE_PATH = <?= json_encode(basePath(), $jsonFlags) ?>;
        window.I18N = <?= json_encode(Translator::configuration($pageLocale), $jsonFlags) ?>;
        window.UI_I18N = <?= $uiLocale === $pageLocale ? 'window.I18N' : json_encode(Translator::configuration($uiLocale), $jsonFlags) ?>;
        window.LOCALE = window.I18N.locale;
        window.TRANSLATIONS = { ...window.I18N.fallback, ...window.I18N.messages };
    </script>
    <header class="site-header" lang="<?= e($uiLocale) ?>" dir="<?= Languages::getDirection($uiLocale) ?>">
        <div class="container">
            <a href="<?= basePath() ?>/" class="logo">Pref.Tools Vote</a>
            <nav class="main-nav">
                <a href="<?= basePath() ?>/create"<?= empty($user) ? ' class="btn btn-primary"' : '' ?>><?= __('create_poll') ?></a>
                <a href="<?= basePath() ?>/about"><?= __('about') ?></a>
                <?php if (!empty($user)): ?>
                    <a href="<?= basePath() ?>/dashboard"><?= __('dashboard') ?></a>
                    <?php if ($user->isSysadmin()): ?>
                        <a href="<?= basePath() ?>/sysadmin"><?= __('sysadmin') ?></a>
                    <?php endif; ?>
                    <div class="user-menu">
                        <button type="button" class="user-menu-trigger">
                            <span class="user-name"><?= e($user->name) ?></span>
                            <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="user-menu-dropdown">
                            <a href="<?= basePath() ?>/dashboard" class="user-menu-item"><?= __('dashboard') ?></a>
                            <button type="button" class="user-menu-item user-menu-logout"><?= __('log_out') ?></button>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= basePath() ?>/login"><?= __('login') ?></a>
                <?php endif; ?>
                <form method="post" action="<?= basePath() ?>/language" class="language-picker">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? (basePath() . '/')) ?>">
                    <label class="visually-hidden" for="interfaceLocale"><?= e(__('interface_language')) ?></label>
                    <select id="interfaceLocale" name="locale" aria-label="<?= e(__('interface_language')) ?>">
                        <?php foreach (Languages::getAll() as $code => $name): ?>
                            <option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $code === $uiLocale ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-small" aria-label="<?= e(__('change_language')) ?>">✓</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer" lang="<?= e($uiLocale) ?>" dir="<?= Languages::getDirection($uiLocale) ?>">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Pref.Tools Vote. A social choice voting application. <a href="<?= basePath() ?>/privacy"><?= __('privacy_policy') ?></a> &middot; <a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">GitHub</a></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="<?= asset('assets/js/app.js') ?>" type="module"></script>
    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <?php if (str_starts_with($js, 'http://') || str_starts_with($js, 'https://')): ?>
                <script src="<?= $js ?>"></script>
            <?php else: ?>
                <script src="<?= asset(ltrim($js, '/')) ?>" type="module"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
