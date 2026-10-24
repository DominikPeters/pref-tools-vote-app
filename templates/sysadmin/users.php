<?php
$title = __('page_user_administration') . ' - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1><?= e(__('page_user_administration')) ?></h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin"><?= e(__('page_overview')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/users" class="active"><?= e(__('page_users')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/polls"><?= e(__('page_polls')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/logs"><?= e(__('page_logs')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/stats"><?= e(__('page_stats')) ?></a>
                <a href="<?= basePath() ?>/sysadmin/config"><?= e(__('page_config')) ?></a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2><?= e(__('page_all_users')) ?></h2>
                <span class="total-count" id="userCount"><?= e(__('page_loading')) ?></span>
            </div>
            <div class="table-container">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= e(__('page_name')) ?></th>
                            <th><?= e(__('page_email')) ?></th>
                            <th><?= e(__('page_role')) ?></th>
                            <th><?= e(__('page_created')) ?></th>
                            <th><?= e(__('page_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="loading"><?= e(__('page_loading_users')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="usersPagination"></div>
        </section>
    </div>
</div>

<template id="userRowTemplate">
    <tr data-user-id="">
        <td class="user-id"></td>
        <td class="user-name"></td>
        <td class="user-email"></td>
        <td class="user-role">
            <select class="role-select">
                <option value="user"><?= e(__('page_user')) ?></option>
                <option value="sysadmin"><?= e(__('sysadmin')) ?></option>
            </select>
        </td>
        <td class="user-created"></td>
        <td class="user-actions">
            <button class="btn btn-small btn-danger delete-user-btn"><?= e(__('delete')) ?></button>
        </td>
    </tr>
</template>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
