<?php
$title = 'Pref.Tools Vote - Create and Share Polls';
ob_start();
?>

<div class="container">
    <section class="hero">
        <h1>Create and Share Polls</h1>
        <p class="lead">
            A powerful voting application with support for multiple voting methods:
            rankings, approval voting, star ratings, and more.
        </p>
        <div class="hero-actions">
            <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a Poll</a>
            <a href="#demo" class="btn btn-secondary btn-large">See Demo</a>
        </div>
    </section>

    <section class="features" id="features">
        <h2>Voting Methods</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <h3>Single Choice</h3>
                <p>Classic voting: choose one option from a list.</p>
            </div>
            <div class="feature-card">
                <h3>Approval Voting</h3>
                <p>Approve as many options as you like.</p>
            </div>
            <div class="feature-card">
                <h3>Rankings</h3>
                <p>Rank options from best to worst. Supports ties and partial rankings.</p>
            </div>
            <div class="feature-card">
                <h3>Star Rating</h3>
                <p>Rate each option on a scale (e.g., 1-5 stars).</p>
            </div>
            <div class="feature-card">
                <h3>Grades</h3>
                <p>Assign grades like Excellent, Good, Fair, Poor (Majority Judgment).</p>
            </div>
            <div class="feature-card">
                <h3>Yes/No/Abstain</h3>
                <p>Simple approval or rejection for each option.</p>
            </div>
        </div>
    </section>

    <section class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Create Your Poll</h3>
                <p>Use our intuitive form builder to create questions with any voting method.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Share the Link</h3>
                <p>Send the voting link to participants. No account required to vote.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>View Results</h3>
                <p>See responses in real-time or after closing. Export data for analysis.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Ready to Start?</h2>
        <p>Create your first vote in minutes. No account required.</p>
        <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a Poll</a>
    </section>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
