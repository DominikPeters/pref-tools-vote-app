<?php
$title = 'Access Required - ' . e($poll->title);
$extraCss = ['/assets/css/password.css'];
ob_start();
?>

<div class="password-container">
    <div class="container">
        <div class="password-card card">
            <h1>Password Required</h1>
            <p class="poll-title"><?= e($poll->title) ?></p>
            <p class="description">This poll is password protected. Please enter the password to continue.</p>

            <?php if ($error): ?>
                <div class="error-message"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= basePath() ?>/<?= e($poll->publicId) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="access_password">Password</label>
                    <input type="password" id="access_password" name="access_password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
