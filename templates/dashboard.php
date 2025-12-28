<?php
$title = 'Dashboard - Pref.Tools Vote';
$extraJs = ['/assets/js/dashboard.js'];
ob_start();
?>

<div class="dashboard-container">
    <div class="container">
        <header class="dashboard-header">
            <h1>Your Dashboard</h1>
            <a href="<?= basePath() ?>/create" class="btn btn-primary">Create New Poll</a>
        </header>

        <section class="card votes-section">
            <h2>Your Polls</h2>
            <?php if (empty($polls)): ?>
                <p class="empty-message">You haven't created any votes yet.</p>
            <?php else: ?>
                <div class="votes-list">
                    <?php foreach ($polls as $poll): ?>
                        <div class="poll-item">
                            <div class="vote-info">
                                <h3 class="poll-title"><?= e($poll->title) ?></h3>
                                <div class="poll-meta">
                                    <span class="status status-<?= e($poll->status) ?>"><?= ucfirst(e($poll->status)) ?></span>
                                    <span class="response-count"><?= $poll->getResponseCount() ?> responses</span>
                                    <span class="created-date"><?= $poll->createdAt->format('M j, Y') ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($poll->adminToken) ?>" class="btn btn-small">Manage</a>
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card account-section">
            <h2>Account</h2>
            <p>Logged in as: <strong><?= e($user->email) ?></strong></p>
            <form action="<?= basePath() ?>/api/auth/logout" method="post" id="logoutForm">
                <button type="submit" class="btn btn-secondary">Log Out</button>
            </form>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
