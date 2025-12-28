<?php
$title = 'Action Log - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1>Action Log</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs" class="active">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats">Stats</a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2>System Activity</h2>
                <span class="total-count" id="logCount">Loading...</span>
            </div>
            <div class="table-container">
                <table class="data-table" id="logsTable">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Poll ID</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="loading">Loading logs...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="logsPagination"></div>
        </section>
    </div>
</div>

<template id="logRowTemplate">
    <tr>
        <td class="log-time"></td>
        <td class="log-action">
            <code></code>
        </td>
        <td class="log-user"></td>
        <td class="log-poll"></td>
        <td class="log-ip"></td>
        <td class="log-data">
            <button class="btn btn-small btn-secondary view-data-btn" style="display:none;">View</button>
        </td>
    </tr>
</template>

<div class="modal" id="dataModal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Details</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="dataModalContent"></pre>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
