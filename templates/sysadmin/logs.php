<?php
$title = __('page_action_log') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_action_log')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs" class="active"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2><?= e(__('page_system_activity')) ?></h2>
                <span class="total-count" id="logCount"><?= e(__('page_loading')) ?></span>
            </div>
            <div class="table-container">
                <table class="data-table" id="logsTable">
                    <thead>
                        <tr>
                            <th><?= e(__('page_time')) ?></th>
                            <th><?= e(__('page_action')) ?></th>
                            <th><?= e(__('page_user')) ?></th>
                            <th><?= e(__('page_poll_id')) ?></th>
                            <th><?= e(__('page_ip_address')) ?></th>
                            <th><?= e(__('page_details')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="loading"><?= e(__('page_loading_logs')) ?></td>
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
            <button class="btn btn-small btn-secondary view-data-btn" style="display:none;"><?= e(__('page_view')) ?></button>
        </td>
    </tr>
</template>

<div class="modal" id="dataModal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= e(__('page_log_details')) ?></h3>
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
