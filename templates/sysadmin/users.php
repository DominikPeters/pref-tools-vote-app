<?php
$title = 'User Administration - Pref.Tools Vote';
$extraCss = ['/assets/css/sysadmin.css'];
$extraJs = ['/assets/js/sysadmin.js'];
ob_start();
?>

<div class="sysadmin-container">
    <div class="container">
        <header class="sysadmin-header">
            <h1>User Administration</h1>
            <nav class="sysadmin-nav">
                <a href="<?= basePath() ?>/sysadmin">Overview</a>
                <a href="<?= basePath() ?>/sysadmin/users" class="active">Users</a>
                <a href="<?= basePath() ?>/sysadmin/polls">Polls</a>
                <a href="<?= basePath() ?>/sysadmin/logs">Logs</a>
                <a href="<?= basePath() ?>/sysadmin/stats">Stats</a>
            </nav>
        </header>

        <section class="card">
            <div class="section-header">
                <h2>All Users</h2>
                <span class="total-count" id="userCount">Loading...</span>
            </div>
            <div class="table-container">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="loading">Loading users...</td>
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
        <td class="user-email"></td>
        <td class="user-role">
            <select class="role-select">
                <option value="user">User</option>
                <option value="sysadmin">Sysadmin</option>
            </select>
        </td>
        <td class="user-created"></td>
        <td class="user-actions">
            <button class="btn btn-small btn-danger delete-user-btn">Delete</button>
        </td>
    </tr>
</template>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
