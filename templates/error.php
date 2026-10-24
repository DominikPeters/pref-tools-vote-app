<?php
$pageLocale = \App\i18n\Translator::getLocale();
$title = ($title ?? 'Error') . ' - Pref.Tools Vote';
ob_start();
?>

<div class="error-container">
    <div class="container">
        <div class="error-card card">
            <h1><?= e($title ?? 'Error') ?></h1>
            <p><?= e($message ?? 'An error occurred.') ?></p>
            <a href="<?= basePath() ?>/" class="btn btn-primary"><?= e(__('page_go_home')) ?></a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
