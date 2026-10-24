<?php
$title = __('page_poll_administration') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_poll_administration')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls" class="active"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2><?= e(__('page_all_polls')) ?></h2>
                <span class="total-count" id="pollCount"><?= e(__('page_loading')) ?></span>
            </div>
            <div class="table-container">
                <table class="data-table" id="pollsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= e(__('page_title')) ?></th>
                            <th><?= e(__('page_owner')) ?></th>
                            <th><?= e(__('page_status')) ?></th>
                            <th><?= e(__('responses')) ?></th>
                            <th><?= e(__('page_created')) ?></th>
                            <th><?= e(__('page_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="loading"><?= e(__('page_loading_polls')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="pollsPagination"></div>
        </section>
    </div>
</div>

<template id="pollRowTemplate">
    <tr data-poll-id="">
        <td class="poll-id"></td>
        <td class="poll-title">
            <a href="" class="poll-link"></a>
        </td>
        <td class="poll-owner"></td>
        <td class="poll-status">
            <span class="status"></span>
        </td>
        <td class="poll-responses"></td>
        <td class="poll-created"></td>
        <td class="poll-actions">
            <button class="btn btn-small btn-danger delete-poll-btn"><?= e(__('delete')) ?></button>
        </td>
    </tr>
</template>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
