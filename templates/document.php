<?php
$title = $documentTitle . ' - Pref.Tools Vote';
ob_start();
?>

<div class="policy-container">
    <div class="container">
        <div class="card">
            <div class="markdown-content" lang="en">
                <?= $content ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
