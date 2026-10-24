<?php
$pageLocale = \App\i18n\Translator::getLocale();
$title = __('page_access_required_title', ['title' => $poll->title]) . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/password.css'];
ob_start();
?>

<div class="password-container">
    <div class="container">
        <div class="password-card card">
            <h1><?= e(__('page_password_required_8cfe20')) ?></h1>
            <p class="poll-title"><?= e($poll->title) ?></p>
            <p class="description"><?= e(__('page_this_poll_is_password_protected_please_enter_the_password_to_continue')) ?></p>

            <?php if ($error): ?>
                <div class="error-message"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= basePath() ?>/<?= e($poll->publicId) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="access_password"><?= e(__('page_password')) ?></label>
                    <input type="password" id="access_password" name="access_password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('submit')) ?></button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
