<?php
$accessTitle = $accessTitle ?? __('page_access_required');
$accessMessage = $accessMessage ?? __('page_this_poll_requires_an_access_link_please_use_the_unique_link_provided_to_you');
$title = $accessTitle . ' - Pref.Tools Vote';
$extraJs = ['assets/js/poll-access.js'];
ob_start();
?>
<div class="container">
    <div class="card">
        <h1><?= e($accessTitle) ?></h1>
        <p><?= e($accessMessage) ?></p>
        <form id="pollAccessForm" method="post" action="<?= e(url($poll->publicId)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="access_token" value="">
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
