<?php
$title = 'Sysadmin Dashboard - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1>Sysadmin Dashboard</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin" class="active">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats">Stats</a>
                <a href="<?= basePath() ?>/sysadmin/config">Config</a>
            </nav>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Users</h3>
                <div class="stat-value"><?= $stats['users']['total'] ?></div>
                <div class="stat-detail"><?= $stats['users']['sysadmins'] ?> sysadmin(s)</div>
            </div>
            <div class="stat-card">
                <h3>Polls</h3>
                <div class="stat-value"><?= $stats['polls']['total'] ?></div>
                <div class="stat-detail">
                    <?= $stats['polls']['open'] ?> open,
                    <?= $stats['polls']['draft'] ?> draft,
                    <?= $stats['polls']['closed'] ?> closed
                </div>
            </div>
            <div class="stat-card">
                <h3>Responses</h3>
                <div class="stat-value"><?= $stats['responses']['total'] ?></div>
                <div class="stat-detail">Total votes submitted</div>
            </div>
            <div class="stat-card">
                <h3>Log Entries</h3>
                <div class="stat-value"><?= $stats['logs']['total'] ?></div>
                <div class="stat-detail">Actions recorded</div>
            </div>
        </div>

        <div class="quick-links">
            <h2>Quick Actions</h2>
            <div class="quick-links-grid">
                <a href="<?= basePath() ?>/sysadmin/users" class="quick-link-card">
                    <h4>Manage Users</h4>
                    <p>View, edit roles, and delete user accounts</p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/polls" class="quick-link-card">
                    <h4>Manage Polls</h4>
                    <p>View and delete polls across the system</p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/logs" class="quick-link-card">
                    <h4>Action Log</h4>
                    <p>View all system activity and audit trail</p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/stats" class="quick-link-card">
                    <h4>Statistics</h4>
                    <p>Detailed system statistics and analytics</p>
                </a>
                <a href="<?= basePath() ?>/sysadmin/config" class="quick-link-card">
                    <h4>Site Config</h4>
                    <p>Email, API keys, and site settings</p>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
