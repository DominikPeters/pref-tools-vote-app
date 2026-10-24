<?php
$title = __('page_sysadmin_dashboard') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_sysadmin_dashboard')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin" class="active"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= e(__('page_users')) ?></h3>
                <div class="stat-value"><?= $stats['users']['total'] ?></div>
                <div class="stat-detail"><?= e(__('page_sysadmin_admin_count', ['count' => (int) $stats['users']['sysadmins']])) ?></div>
            </div>
            <div class="stat-card">
                <h3><?= e(__('page_polls')) ?></h3>
                <div class="stat-value"><?= $stats['polls']['total'] ?></div>
                <div class="stat-detail">
                    <?= e(__('page_sysadmin_poll_status_counts', [
                        'open' => (int) $stats['polls']['open'],
                        'draft' => (int) $stats['polls']['draft'],
                        'closed' => (int) $stats['polls']['closed'],
                    ])) ?>
                </div>
            </div>
            <div class="stat-card">
                <h3><?= e(__('responses')) ?></h3>
                <div class="stat-value"><?= $stats['responses']['total'] ?></div>
                <div class="stat-detail"><?= e(__('page_total_votes_submitted')) ?></div>
            </div>
            <div class="stat-card">
                <h3><?= e(__('page_log_entries')) ?></h3>
                <div class="stat-value"><?= $stats['logs']['total'] ?></div>
                <div class="stat-detail"><?= e(__('page_actions_recorded')) ?></div>
            </div>
        </div>

        <div class="quick-links">
            <h2><?= e(__('page_quick_actions')) ?></h2>
            <div class="quick-links-grid">
                <a href="<?= basePath() ?>/sysadmin/users" class="quick-link-card">
                    <h4><?= e(__('page_manage_users')) ?></h4>
                    <p><?= e(__('page_view_edit_roles_and_delete_user_accounts')) ?></p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/polls" class="quick-link-card">
                    <h4><?= e(__('page_manage_polls')) ?></h4>
                    <p><?= e(__('page_view_and_delete_polls_across_the_system')) ?></p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/logs" class="quick-link-card">
                    <h4><?= e(__('page_action_log')) ?></h4>
                    <p><?= e(__('page_view_all_system_activity_and_audit_trail')) ?></p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/stats" class="quick-link-card">
                    <h4><?= e(__('page_statistics')) ?></h4>
                    <p><?= e(__('page_detailed_system_statistics_and_analytics')) ?></p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/config" class="quick-link-card">
                    <h4><?= e(__('page_site_config')) ?></h4>
                    <p><?= e(__('page_email_api_keys_and_site_settings')) ?></p>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
