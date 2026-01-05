<?php
$title = 'Access Required - ' . e($poll->title);
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

<style>
.password-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.password-card {
    max-width: 400px;
    text-align: center;
}

.password-card h1 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
}

.password-card .poll-title {
    color: var(--color-primary);
    font-weight: 500;
    margin-bottom: 1rem;
}

.password-card .description {
    color: var(--color-text-muted);
    margin-bottom: 1.5rem;
}

.password-card .error-message {
    background: var(--color-danger-bg, #fef2f2);
    color: var(--color-danger);
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
}

.password-card .form-group {
    margin-bottom: 1rem;
    text-align: left;
}

.password-card label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.password-card input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 1rem;
}

.password-card input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.password-card .btn {
    width: 100%;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
