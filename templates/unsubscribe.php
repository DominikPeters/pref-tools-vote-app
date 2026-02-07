<?php
$title = ($action === 'resubscribe' ? 'Resubscribe' : 'Unsubscribe') . ' - Pref.Tools Vote';
ob_start();
?>

<div class="auth-container">
    <div class="container">
        <div class="auth-card card unsubscribe-card">
            <?php if (!$isValid): ?>
                <h2>Invalid Link</h2>
                <p>This unsubscribe link is invalid or has expired.</p>
                <p class="unsubscribe-action">
                    <a href="<?= e(url('/')) ?>" class="btn btn-primary">Go to Homepage</a>
                </p>
            <?php else: ?>
                <div id="loadingState">
                    <h2><?= $action === 'resubscribe' ? 'Resubscribing...' : 'Unsubscribing...' ?></h2>
                    <p>Please wait...</p>
                </div>

                <div id="successState" style="display: none;">
                    <h2 id="successTitle"></h2>
                    <p id="successMessage"></p>
                    <p class="unsubscribe-email">
                        Email: <strong><?= e($email) ?></strong>
                    </p>
                    <div id="toggleAction" class="unsubscribe-action"></div>
                </div>

                <div id="errorState" style="display: none;">
                    <h2>Something Went Wrong</h2>
                    <p id="errorMessage">An error occurred. Please try again later.</p>
                    <p class="unsubscribe-action">
                        <a href="<?= e(url('/')) ?>" class="btn btn-primary">Go to Homepage</a>
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
                document.getElementById('successTitle').textContent = 'Unsubscribed Successfully';
                document.getElementById('successMessage').textContent =
                    'You will no longer receive invitation emails from Pref.Tools Vote.';

                // Show resubscribe option
                const resubscribeUrl = basePath + '/unsubscribe?' + new URLSearchParams({
                    email: email,
                    sig: signature,
                    action: 'resubscribe'
                }).toString();
                document.getElementById('toggleAction').innerHTML =
                    '<p class="unsubscribe-toggle-label">Changed your mind?</p>' +
                    '<a href="' + resubscribeUrl + '" class="btn btn-secondary">Resubscribe</a>';
            } else {
                document.getElementById('successTitle').textContent = 'Resubscribed Successfully';
                document.getElementById('successMessage').textContent =
                    'You will now receive invitation emails from Pref.Tools Vote.';

                // Show unsubscribe option
                const unsubscribeUrl = basePath + '/unsubscribe?' + new URLSearchParams({
                    email: email,
                    sig: signature,
                    action: 'unsubscribe'
                }).toString();
                document.getElementById('toggleAction').innerHTML =
                    '<p class="unsubscribe-toggle-label">Changed your mind?</p>' +
                    '<a href="' + unsubscribeUrl + '" class="btn btn-secondary">Unsubscribe</a>';
            }
        } else {
            errorState.style.display = 'block';
            document.getElementById('errorMessage').textContent = data.error || 'An error occurred';
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
