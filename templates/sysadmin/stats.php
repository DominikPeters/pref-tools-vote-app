<?php
$title = __('page_statistics') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/echarts@6.0.0/dist/echarts.min.js',
    '/assets/js/stats-charts.js',
];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_system_statistics')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats" class="active"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <div class="stats-detail-grid">
            <section class="card">
                <h2><?= e(__('page_user_statistics')) ?></h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt><?= e(__('page_total_users')) ?></dt>
                        <dd><?= $stats['users']['total'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt><?= e(__('page_sysadmins')) ?></dt>
                        <dd><?= $stats['users']['sysadmins'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt><?= e(__('page_regular_users')) ?></dt>
                        <dd><?= $stats['users']['total'] - $stats['users']['sysadmins'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2><?= e(__('page_poll_statistics')) ?></h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt><?= e(__('page_total_polls')) ?></dt>
                        <dd><?= $stats['polls']['total'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt><?= e(__('status_draft')) ?></dt>
                        <dd><?= $stats['polls']['draft'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt><?= e(__('voting_mode_open')) ?></dt>
                        <dd><?= $stats['polls']['open'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt><?= e(__('status_closed')) ?></dt>
                        <dd><?= $stats['polls']['closed'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2><?= e(__('page_response_statistics')) ?></h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt><?= e(__('page_total_responses')) ?></dt>
                        <dd><?= $stats['responses']['total'] ?></dd>
                    </div>
                    <?php if ($stats['polls']['total'] > 0): ?>
                    <div class="stat-row">
                        <dt><?= e(__('page_avg_per_poll')) ?></dt>
                        <dd><?= number_format($stats['responses']['total'] / $stats['polls']['total'], 1) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="card">
                <h2><?= e(__('page_activity_statistics')) ?></h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt><?= e(__('page_log_entries')) ?></dt>
                        <dd><?= $stats['logs']['total'] ?></dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="card stats-charts-section">
            <div class="stats-charts-header">
                <h2><?= e(__('page_activity_over_time')) ?></h2>
                <div class="date-range-selector">
                    <button type="button" class="btn btn-sm" data-days="7"><?= e(__('page_7_days')) ?></button>
                    <button type="button" class="btn btn-sm active" data-days="30"><?= e(__('page_30_days')) ?></button>
                    <button type="button" class="btn btn-sm" data-days="90"><?= e(__('page_90_days')) ?></button>
                    <button type="button" class="btn btn-sm" data-days="all"><?= e(__('page_all_time')) ?></button>
                </div>
            </div>

            <div class="stats-charts-grid">
                <div class="chart-container">
                    <h3><?= e(__('page_polls_created')) ?></h3>
                    <div id="pollsChart" class="chart"></div>
                </div>
                <div class="chart-container">
                    <h3><?= e(__('page_responses_submitted')) ?></h3>
                    <div id="responsesChart" class="chart"></div>
                </div>
                <div class="chart-container">
                    <h3><?= e(__('page_emails_sent')) ?></h3>
                    <div id="emailsChart" class="chart"></div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
