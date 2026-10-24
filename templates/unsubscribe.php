<?php
$title = ($action === 'resubscribe' ? __('page_resubscribe') : __('page_unsubscribe')) . ' - Pref.Tools Vote';
ob_start();
?>

<div class="auth-container">
    <div class="container">
        <div class="auth-card card unsubscribe-card">
            <?php if (!$isValid): ?>
                <h2><?= e(__('page_invalid_link')) ?></h2>
                <p><?= e(__('page_this_unsubscribe_link_is_invalid_or_has_expired')) ?></p>
                <p class="unsubscribe-action">
                    <a href="<?= e(url('/')) ?>" class="btn btn-primary"><?= e(__('page_go_to_homepage')) ?></a>
                </p>
            <?php else: ?>
                <div id="loadingState">
                    <h2><?= $action === 'resubscribe' ? __('page_resubscribing') : __('page_unsubscribing') ?></h2>
                    <p><?= e(__('page_please_wait')) ?></p>
                </div>

                <div id="successState" style="display: none;">
                    <h2 id="successTitle"></h2>
                    <p id="successMessage"></p>
                    <p class="unsubscribe-email">
                        <?= e(__('page_email_17507d')) ?> <strong><?= e($email) ?></strong>
                    </p>
                    <div id="toggleAction" class="unsubscribe-action"></div>
                </div>

                <div id="errorState" style="display: none;">
                    <h2><?= e(__('page_something_went_wrong')) ?></h2>
                    <p id="errorMessage"><?= e(__('page_an_error_occurred_please_try_again_later')) ?></p>
                    <p class="unsubscribe-action">
                        <a href="<?= e(url('/')) ?>" class="btn btn-primary"><?= e(__('page_go_to_homepage')) ?></a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isValid): ?>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const basePath = window.BASE_PATH || '';
            const email = <?= json_encode($email, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
            const signature = <?= json_encode($signature, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
            const action = <?= json_encode($action, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
    const loadingState = document.getElementById('loadingState');
    const successState = document.getElementById('successState');
    const errorState = document.getElementById('errorState');

    try {
            const response = await fetch(basePath + '/api/unsubscribe', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ email, signature, action })
            });

        const data = await response.json();

        loadingState.style.display = 'none';

        if (data.success) {
            successState.style.display = 'block';

            if (data.is_unsubscribed) {
                document.getElementById('successTitle').textContent = <?= json_encode(__('page_unsubscribed_successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                document.getElementById('successMessage').textContent =
                    <?= json_encode(__('page_you_will_no_longer_receive_invitation_emails_from_pref_tools_vote'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;

                // Show resubscribe option
                const resubscribeUrl = basePath + '/unsubscribe?' + new URLSearchParams({
                    email: email,
                    sig: signature,
                    action: 'resubscribe'
                }).toString();
                const prompt = document.createElement('p');
                prompt.className = 'unsubscribe-toggle-label';
                prompt.textContent = <?= json_encode(__('page_changed_your_mind'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                const link = document.createElement('a');
                link.className = 'btn btn-secondary';
                link.href = resubscribeUrl;
                link.textContent = <?= json_encode(__('page_resubscribe'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                document.getElementById('toggleAction').replaceChildren(prompt, link);
            } else {
                document.getElementById('successTitle').textContent = <?= json_encode(__('page_resubscribed_successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                document.getElementById('successMessage').textContent =
                    <?= json_encode(__('page_you_will_now_receive_invitation_emails_from_pref_tools_vote'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;

                // Show unsubscribe option
                const unsubscribeUrl = basePath + '/unsubscribe?' + new URLSearchParams({
                    email: email,
                    sig: signature,
                    action: 'unsubscribe'
                }).toString();
                const prompt = document.createElement('p');
                prompt.className = 'unsubscribe-toggle-label';
                prompt.textContent = <?= json_encode(__('page_changed_your_mind'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                const link = document.createElement('a');
                link.className = 'btn btn-secondary';
                link.href = unsubscribeUrl;
                link.textContent = <?= json_encode(__('page_unsubscribe'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
                document.getElementById('toggleAction').replaceChildren(prompt, link);
            }
        } else {
            errorState.style.display = 'block';
            document.getElementById('errorMessage').textContent = data.error || <?= json_encode(__('page_an_error_occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        }
    } catch (err) {
        loadingState.style.display = 'none';
        errorState.style.display = 'block';
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
