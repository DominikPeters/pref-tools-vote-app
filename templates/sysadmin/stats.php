<?php
$title = 'Statistics - Pref.Tools Vote';
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
            <h1>System Statistics</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats" class="active">Stats</a>
                <a href="<?= basePath() ?>/sysadmin/config">Config</a>
            </nav>
        </header>

        <div class="stats-detail-grid">
            <section class="card">
                <h2>User Statistics</h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt>Total Users</dt>
                        <dd><?= $stats['users']['total'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt>Sysadmins</dt>
                        <dd><?= $stats['users']['sysadmins'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt>Regular Users</dt>
                        <dd><?= $stats['users']['total'] - $stats['users']['sysadmins'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2>Poll Statistics</h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt>Total Polls</dt>
                        <dd><?= $stats['polls']['total'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt>Draft</dt>
                        <dd><?= $stats['polls']['draft'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt>Open</dt>
                        <dd><?= $stats['polls']['open'] ?></dd>
                    </div>
                    <div class="stat-row">
                        <dt>Closed</dt>
                        <dd><?= $stats['polls']['closed'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2>Response Statistics</h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt>Total Responses</dt>
                        <dd><?= $stats['responses']['total'] ?></dd>
                    </div>
                    <?php if ($stats['polls']['total'] > 0): ?>
                    <div class="stat-row">
                        <dt>Avg per Poll</dt>
                        <dd><?= number_format($stats['responses']['total'] / $stats['polls']['total'], 1) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="card">
                <h2>Activity Statistics</h2>
                <dl class="stats-list">
                    <div class="stat-row">
                        <dt>Log Entries</dt>
                        <dd><?= $stats['logs']['total'] ?></dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="card stats-charts-section">
            <div class="stats-charts-header">
                <h2>Activity Over Time</h2>
                <div class="date-range-selector">
                    <button type="button" class="btn btn-sm" data-days="7">7 days</button>
                    <button type="button" class="btn btn-sm active" data-days="30">30 days</button>
                    <button type="button" class="btn btn-sm" data-days="90">90 days</button>
                    <button type="button" class="btn btn-sm" data-days="all">All time</button>
                </div>
            </div>

            <div class="stats-charts-grid">
                <div class="chart-container">
                    <h3>Polls Created</h3>
                    <div id="pollsChart" class="chart"></div>
                </div>
                <div class="chart-container">
                    <h3>Responses Submitted</h3>
                    <div id="responsesChart" class="chart"></div>
                </div>
                <div class="chart-container">
                    <h3>Emails Sent</h3>
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
