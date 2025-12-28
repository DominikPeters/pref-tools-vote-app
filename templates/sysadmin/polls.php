<?php
$title = 'Poll Administration - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1>Poll Administration</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls" class="active">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats">Stats</a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2>All Polls</h2>
                <span class="total-count" id="pollCount">Loading...</span>
            </div>
            <div class="table-container">
                <table class="data-table" id="pollsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Responses</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="loading">Loading polls...</td>
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
            <button class="btn btn-small btn-danger delete-poll-btn">Delete</button>
        </td>
    </tr>
</template>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
