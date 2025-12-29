<?php
$title = 'Maintenance - Pref.Tools Vote';
ob_start();
?>

<div class="error-container">
    <div class="container">
        <div class="error-card card">
            <h1>Site Under Maintenance</h1>
            <p>We're currently performing scheduled maintenance. Please check back soon.</p>
            <p class="text-muted" style="font-size: 0.875rem; margin-top: 1rem;">If you're an administrator, <a href="<?= basePath() ?>/login">log in</a> to access the site.</p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
