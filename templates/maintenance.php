<?php
$title = __('page_maintenance') . ' - Pref.Tools Vote';
ob_start();
?>

<div class="error-container">
    <div class="container">
        <div class="error-card card">
            <h1><?= e(__('page_site_under_maintenance')) ?></h1>
            <p><?= e(__('page_we_re_currently_performing_scheduled_maintenance_please_check_back_soon')) ?></p>
            <p class="text-muted maintenance-admin-note"><?= e(__('page_administrators_can_log_in_to_access_the_site')) ?> <a href="<?= basePath() ?>/login"><?= e(__('page_log_in_c18984')) ?></a></p>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
