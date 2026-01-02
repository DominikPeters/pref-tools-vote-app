<?php
$title = 'About - Pref.Tools Vote';
$extraCss = ['assets/css/about.css'];
ob_start();
?>

<div class="about-container">
    <div class="container">
        <div class="card about-card">
            <h1>About Pref.Tools Vote</h1>

            <section class="about-section about-author">
                <div class="author-content">
                    <img src="<?= basePath() ?>/assets/img/dominik.jpg" alt="Dominik Peters" class="author-photo">
                    <div class="author-text">
                        <p>This web application was developed by <strong>Dominik Peters</strong>, a CNRS researcher at <strong>Universit&eacute; Paris Dauphine</strong>, who has worked extensively in voting theory and computational social choice.</p>
                        <p><a href="https://dominik-peters.de" target="_blank" rel="noopener">dominik-peters.de</a></p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <h2>Development</h2>
                <p>Pref.Tools Vote was developed with the assistance of <strong>Claude Opus 4.5</strong> (Anthropic) and was first published in <strong>January 2026</strong>.</p>
                <p>The backend voting rule computations are powered by adapted versions of:</p>
                <ul>
                    <li><a href="https://github.com/voting-tools/pref_voting/" target="_blank" rel="noopener"><strong>pref_voting</strong></a> &mdash; A Python library for preference voting methods (single winner, rank aggregation, multi winner)</li>
                    <li><a href="https://github.com/martinlackner/abcvoting/" target="_blank" rel="noopener"><strong>abcvoting</strong></a> &mdash; A Python library for approval-based committee voting</li>
                    <li><a href="https://pref.tools/apportionment/" target="_blank" rel="noopener"><strong>pref.tools/apportionment</strong></a> &mdash; A web app for apportionment methods</li>
                    <li><a href="https://equalshares.net/tools/compute/" target="_blank" rel="noopener"><strong>Method of Equal Shares: Online Computation Tool</strong></a> &mdash; A web app for computing the Method of Equal Shares in participatory budgeting</li>
                </ul>
            </section>

            <section class="about-section">
                <h2>Open Source</h2>
                <p>The source code for this application is <strong>MIT licensed</strong> and available on GitHub:</p>
                <p><a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">github.com/DominikPeters/pref-tools-vote-app</a></p>
                <p>Feedback, bug reports, and contributions are welcome!</p>
            </section>

            <section class="about-section">
                <h2>Features</h2>

                <h3>Question Types</h3>
                <p>Pref.Tools Vote supports a variety of input types designed for social choice applications:</p>
                <ul class="feature-list">
                    <li><strong>Single Choice</strong> &mdash; Standard radio button selection</li>
                    <li><strong>Approval Voting</strong> &mdash; Select multiple options (with optional min/max limits)</li>
                    <li><strong>Ranking (Full)</strong> &mdash; Drag-and-drop complete ranking of all options</li>
                    <li><strong>Ranking (Partial)</strong> &mdash; Rank a subset of options (truncated preferences)</li>
                    <li><strong>Ranking with Ties</strong> &mdash; Allow indifference classes in rankings</li>
                    <li><strong>Star Rating</strong> &mdash; Rate options on a configurable star scale (2-10 stars)</li>
                    <li><strong>Grade Voting</strong> &mdash; Assign grades (Excellent, Very Good, Good, Fair, Poor, Reject)</li>
                    <li><strong>Yes / No / Abstain</strong> &mdash; Three-option voting for each alternative</li>
                    <li><strong>Participatory Budgeting</strong> &mdash; Budget allocation with project costs</li>
                    <li><strong>Short Text</strong> &mdash; Single-line free text response</li>
                    <li><strong>Long Text</strong> &mdash; Multi-line free text response</li>
                </ul>

                <h3>Single-Winner Voting Methods</h3>
                <p>For ranking and approval-based questions:</p>
                <ul class="feature-list">
                    <li><strong>Schulze Method</strong> &mdash; Condorcet-consistent method using beat paths</li>
                    <li><strong>Ranked Pairs</strong> &mdash; Lock-in method based on pairwise margins</li>
                    <li><strong>Instant Runoff Voting (IRV)</strong> &mdash; Sequential elimination of last-place candidates</li>
                    <li><strong>Borda Count</strong> &mdash; Positional scoring rule</li>
                    <li><strong>Copeland</strong> &mdash; Count pairwise victories</li>
                    <li><strong>Minimax</strong> &mdash; Minimize worst pairwise defeat</li>
                    <li><strong>Plurality</strong> &mdash; Most first-place votes wins</li>
                    <li><strong>Split Cycle</strong> &mdash; Condorcet method using cycle resolution</li>
                    <li><strong>Stable Voting</strong> &mdash; Defeat-dropping Condorcet method</li>
                    <li><strong>Approval Voting</strong> &mdash; Most approvals wins</li>
                </ul>

                <h3>Multi-Winner &amp; Committee Methods</h3>
                <ul class="feature-list">
                    <li><strong>Proportional Approval Voting (PAV)</strong> &mdash; Thiele method for proportional representation</li>
                    <li><strong>Method of Equal Shares</strong> &mdash; Proportional method for approval ballots</li>
                    <li><strong>Single Transferable Vote (STV)</strong> &mdash; Ranked-choice proportional method</li>
                    <li><strong>Sequential Phragm&eacute;n</strong> &mdash; Load-balancing proportional method</li>
                    <li><strong>Chamberlin-Courant</strong> &mdash; Diversity-maximizing committee selection</li>
                    <li><strong>Apportionment Methods</strong> &mdash; Seat allocation (D'Hondt, Sainte-Lagu&euml;, etc.)</li>
                </ul>

                <h3>Rank Aggregation</h3>
                <ul class="feature-list">
                    <li><strong>Kemeny</strong> &mdash; Optimal ranking minimizing pairwise disagreements</li>
                    <li><strong>Squared Kemeny</strong> &mdash; Variant using squared distances</li>
                    <li><strong>Borda Ranking</strong> &mdash; Ranking by Borda scores</li>
                </ul>

                <h3>Rating &amp; Judgment Methods</h3>
                <ul class="feature-list">
                    <li><strong>Score Voting</strong> &mdash; Sum of ratings</li>
                    <li><strong>STAR Voting</strong> &mdash; Score Then Automatic Runoff</li>
                    <li><strong>Majority Judgment</strong> &mdash; Median-based grade aggregation</li>
                </ul>

                <h3>Participatory Budgeting</h3>
                <ul class="feature-list">
                    <li><strong>Method of Equal Shares</strong> &mdash; Proportional budget allocation</li>
                    <li><strong>Utilitarian Greedy</strong> &mdash; Maximize total utility</li>
                    <li><strong>PaBuLib Export</strong> &mdash; Export data in standard research format</li>
                </ul>

                <h3>Visualizations &amp; Analysis</h3>
                <ul class="feature-list">
                    <li><strong>Pairwise Margins Graph</strong> &mdash; Head-to-head comparison visualization</li>
                    <li><strong>Response Matrix</strong> &mdash; Doodle-style overview of all responses</li>
                    <li><strong>Multi-Rule Comparison</strong> &mdash; Compare outcomes across voting methods</li>
                    <li><strong>Condorcet Winner Detection</strong> &mdash; Identify candidates beating all others</li>
                    <li><strong>Data Export</strong> &mdash; PrefLib and PaBuLib formats for research</li>
                </ul>

                <h3>Poll Configuration</h3>
                <ul class="feature-list">
                    <li><strong>Multiple Questions per Poll</strong> &mdash; Combine different question types</li>
                    <li><strong>Privacy Settings</strong> &mdash; Full transparency, anonymous votes, or private results</li>
                    <li><strong>Access Control</strong> &mdash; Public, password-protected, one-time tokens, or email invitations</li>
                    <li><strong>Voting Modes</strong> &mdash; Open, identified, or secret ballot</li>
                    <li><strong>Response Editing</strong> &mdash; No editing, edit own, or edit any (Doodle-style)</li>
                    <li><strong>Option Randomization</strong> &mdash; Randomize option order to reduce bias</li>
                    <li><strong>Markdown Support</strong> &mdash; Rich text in descriptions and messages</li>
                </ul>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
