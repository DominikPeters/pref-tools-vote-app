<?php
$title = 'Dashboard - Pref.Tools Vote';
$extraJs = ['/assets/js/dashboard.js'];
ob_start();
?>

<div class="dashboard-container">
    <div class="container">
        <header class="dashboard-header">
            <h1>Your Dashboard</h1>
        </header>

        <section class="card votes-section">
            <div class="section-header">
                <h2>Your Polls</h2>
                <a href="<?= basePath() ?>/create" class="btn btn-primary">Create New Poll</a>
            </div>
            <?php if (empty($polls)): ?>
                <p class="empty-message">You haven't created any polls yet.</p>
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
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/admin/<?= e($poll->adminToken) ?>" class="btn btn-small btn-primary">Manage</a>
                                <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card votes-section">
            <h2>Polls You Voted In</h2>
            <?php if (empty($votedPolls)): ?>
                <p class="empty-message">You haven't voted in any polls yet (while logged in).</p>
            <?php else: ?>
                <div class="votes-list">
                    <?php foreach ($votedPolls as $poll): ?>
                        <?php
                        $resultsAvailable = $poll->areResultsViewable();
                        $votingOpen = $poll->isVotingOpen();
                        ?>
                        <div class="poll-item">
                            <div class="vote-info">
                                <h3 class="poll-title"><?= e($poll->title) ?></h3>
                                <div class="poll-meta">
                                    <span class="status status-<?= e($poll->status) ?>"><?= ucfirst(e($poll->status)) ?></span>
                                    <span class="created-date"><?= $poll->createdAt->format('M j, Y') ?></span>
                                </div>
                            </div>
                            <div class="poll-actions">
                                <?php if ($resultsAvailable): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>/results" class="btn btn-small btn-secondary">Results</a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="Results not yet available" data-tooltip-pos="left">Results</button>
                                <?php endif; ?>
                                <?php if ($votingOpen): ?>
                                    <a href="<?= basePath() ?>/<?= e($poll->publicId) ?>" class="btn btn-small btn-secondary">Edit Vote</a>
                                <?php else: ?>
                                    <button class="btn btn-small btn-secondary" disabled data-tooltip="Voting is closed" data-tooltip-pos="left">Edit Vote</button>
                                <?php endif; ?>
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
