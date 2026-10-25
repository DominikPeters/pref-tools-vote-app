<?php
use App\i18n\Languages;
use App\i18n\Translator;
use App\i18n\UiLocale;
use App\Services\MarketingLocale;
$uiLocale = UiLocale::get();
$pageLocale = $pageLocale ?? $uiLocale;
Translator::setLocale($uiLocale);
$marketingMetadata = isset($marketingPage) ? MarketingLocale::metadata($marketingPage, $uiLocale) : null;
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS;
?>
<!DOCTYPE html>
<html lang="<?= e($pageLocale) ?>" dir="<?= Languages::getDirection($pageLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($metaDescription ?? __('page_create_polls_with_advanced_voting_methods_like_schulze_irv_and_majority_judgme')) ?>">
    <meta name="theme-color" content="#4f46e5">
    <meta property="og:title" content="<?= e($title ?? 'Pref.Tools Vote') ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? __('page_smarter_polls_with_advanced_voting_methods_go_beyond_simple_polls_with_ranking')) ?>">
    <meta property="og:type" content="website">
    <?php if ($marketingMetadata !== null): ?>
    <link rel="canonical" href="<?= e($marketingMetadata['canonical']) ?>">
    <?php foreach ($marketingMetadata['alternates'] as $code => $alternateUrl): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($alternateUrl) ?>">
    <?php endforeach; ?>
    <meta property="og:url" content="<?= e($marketingMetadata['canonical']) ?>">
    <meta property="og:locale" content="<?= e($marketingMetadata['og_locale']) ?>">
    <?php endif; ?>
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
            <a href="<?= e(MarketingLocale::path('home')) ?>" class="logo">Pref.Tools Vote</a>
            <nav class="main-nav">
                <?php if (!empty($user)): ?>
                    <a href="<?= basePath() ?>/create"><?= __('create_poll') ?></a>
                <?php endif; ?>
                <a href="<?= e(MarketingLocale::path('about')) ?>"><?= __('about') ?></a>
                <?php if (!empty($user)): ?>
                    <a href="<?= basePath() ?>/dashboard"><?= __('dashboard') ?></a>
                    <?php if ($user->isSysadmin()): ?>
                        <a href="<?= basePath() ?>/sysadmin"><?= __('sysadmin') ?></a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php
                $suggestedLocales = UiLocale::suggestions($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $uiLocale);
                $otherLocales = array_values(array_diff(array_keys(Languages::getAll()), $suggestedLocales));
                ?>
                <details class="language-menu">
                    <summary>
                        <span class="visually-hidden"><?= e(__('interface_language')) ?>: </span>
                        <span lang="<?= e($uiLocale) ?>" dir="<?= e(Languages::getDirection($uiLocale)) ?>"><?= e(Languages::getName($uiLocale)) ?></span>
                        <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </summary>
                    <form method="post" action="<?= basePath() ?>/language" class="language-menu-list">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? (basePath() . '/')) ?>">
                        <?php foreach ([$suggestedLocales, $otherLocales] as $group => $codes): ?>
                            <?php if ($group === 1 && $codes): ?><hr><?php endif; ?>
                            <?php foreach ($codes as $code): ?>
                                <button type="submit" name="locale" value="<?= e($code) ?>" lang="<?= e($code) ?>" dir="<?= e(Languages::getDirection($code)) ?>"<?= $code === $uiLocale ? ' aria-current="true"' : '' ?>><?= e(Languages::getName($code)) ?></button>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </form>
                </details>
                <?php if (!empty($user)): ?>
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
                    <a href="<?= basePath() ?>/create" class="btn btn-primary"><?= __('create_poll') ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer" lang="<?= e($uiLocale) ?>" dir="<?= Languages::getDirection($uiLocale) ?>">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Pref.Tools Vote. <?= e(__('page_a_social_choice_voting_application')) ?> <a href="<?= basePath() ?>/terms"><?= e(__('terms_of_use')) ?></a> &middot; <a href="<?= basePath() ?>/privacy"><?= e(__('privacy_policy')) ?></a> &middot; <a href="<?= basePath() ?>/legal"><?= e(__('legal_notice')) ?></a> &middot; <a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">GitHub</a></p>
            <?php if (isset($marketingPage)): ?>
            <nav class="marketing-languages" aria-label="<?= e(__('interface_language')) ?>">
                <?php foreach (Languages::getAll() as $code => $name): ?>
                <a href="<?= e(MarketingLocale::path($marketingPage, $code)) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" dir="<?= e(Languages::getDirection($code)) ?>"<?= $code === $uiLocale ? ' aria-current="page"' : '' ?>><?= e($name) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
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
