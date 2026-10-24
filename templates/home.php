<?php
$title = 'Pref.Tools Vote - Polls with Rankings, Approvals, Ratings, and Grades';
ob_start();
?>

<div class="container hero-container">
    <section class="hero-enhanced">
        <h1>Polls with rankings, approvals,<br>ratings, and grades</h1>
        <p class="lead">
            Create a poll, share a link, and analyze the results with Schulze, ranked pairs, STV,
            majority judgment, and many other voting rules from the social choice literature.
            It's free and <a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">open source</a>, and no account is required.
        </p>
        <div class="hero-actions">
            <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a poll</a>
            <a href="#demo" class="btn btn-secondary btn-large">Try the demo</a>
        </div>
    </section>
</div>

<section class="screenshot-gallery">
    <div class="container">
        <h2>Question types</h2>
        <p class="section-subtitle">
            Nine formats, from single choice to full rankings and grades.
        </p>
    </div>
    <div class="gallery-grid">
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_single_choice.png" alt="Single Choice Question">
            <div class="gallery-item-label">Single choice</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_approval.png" alt="Approval Voting Question">
            <div class="gallery-item-label">Approval voting</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking.png" alt="Ranking Question">
            <div class="gallery-item-label">Rankings</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking_truncated.png" alt="Truncated Ranking Question">
            <div class="gallery-item-label">Partial rankings</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking_with_ties.png" alt="Ranking with Ties Question">
            <div class="gallery-item-label">Rankings with ties</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_star.png" alt="Star Rating Question">
            <div class="gallery-item-label">Star ratings</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_grade.png" alt="Grade Question">
            <div class="gallery-item-label">Grades (verbal)</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_grade_2.png" alt="Grade Question with Symbols">
            <div class="gallery-item-label">Grades (symbols)</div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <img src="<?= basePath() ?>/screenshots/question_yes_no_abstain.png" alt="Yes/No/Abstain Question">
            <div class="gallery-item-label">Yes / no / abstain</div>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="gallery-lightbox" id="galleryLightbox" role="dialog" aria-modal="true" aria-label="Image lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)" aria-label="Close lightbox">&times;</button>
    <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1, event)" aria-label="Previous image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
    </button>
    <img id="lightboxImage" src="" alt="Enlarged screenshot">
    <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1, event)" aria-label="Next image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
        </svg>
    </button>
    <div class="lightbox-counter" id="lightboxCounter" aria-live="polite"></div>
    <div class="lightbox-label" id="lightboxLabel"></div>
</div>

<section class="analysis-section">
    <div class="container">
        <h2>Results and analysis</h2>
        <p class="section-subtitle">
            Attach analyses to any question and compare rules side by side. Rule implementations are based on
            the open-source <a href="https://github.com/voting-tools/pref_voting" target="_blank" rel="noopener">pref_voting</a>
            and <a href="https://github.com/martinlackner/abcvoting" target="_blank" rel="noopener">abcvoting</a> libraries.
        </p>
        <div class="analysis-grid">
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3v18h18"/>
                        <path d="M18 17V9"/>
                        <path d="M13 17V5"/>
                        <path d="M8 17v-7"/>
                    </svg>
                </div>
                <h3>Single-Winner Elections</h3>
                <p>Find winners that best reflect the group's preferences, even when there's no majority.</p>
                <div class="analysis-methods" id="ranking-methods">
                    <span class="analysis-method-tag">Approval Voting</span>
                    <span class="analysis-method-tag">Schulze</span>
                    <span class="analysis-method-tag">Ranked Pairs</span>
                    <span class="analysis-method-tag">IRV</span>
                    <span class="analysis-method-tag">Borda Count</span>
                    <span class="analysis-method-tag hidden-method">Copeland</span>
                    <span class="analysis-method-tag hidden-method">Minimax</span>
                    <span class="analysis-method-tag hidden-method">Plurality</span>
                    <span class="analysis-method-tag hidden-method">Split Cycle</span>
                    <span class="analysis-method-tag hidden-method">Stable Voting</span>
                    <span class="analysis-method-tag hidden-method">Top Cycle</span>
                    <button class="analysis-method-more" onclick="toggleMethods('ranking-methods')">
                        +6 more
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="6"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                </div>
                <h3>Multi-Winner Elections</h3>
                <p>Select committees or multiple winners with proportional representation rules.</p>
                <div class="analysis-methods" id="multiwinner-methods">
                    <span class="analysis-method-tag">PAV</span>
                    <span class="analysis-method-tag">Method of Equal Shares</span>
                    <span class="analysis-method-tag">STV</span>
                    <span class="analysis-method-tag">Seq. Phragm&eacute;n</span>
                    <span class="analysis-method-tag">Apportionment</span>
                    <span class="analysis-method-tag hidden-method">Sequential PAV</span>
                    <span class="analysis-method-tag hidden-method">Chamberlin-Courant</span>
                    <span class="analysis-method-tag hidden-method">Meek-STV</span>
                    <span class="analysis-method-tag hidden-method">Sainte-Lagu&euml; AV</span>
                    <button class="analysis-method-more" onclick="toggleMethods('multiwinner-methods')">
                        +4 more
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <h3>Rating Methods</h3>
                <p>Analyze star ratings and grades with methods designed for evaluative judgments.</p>
                <div class="analysis-methods" id="rating-methods">
                    <span class="analysis-method-tag">Score Voting</span>
                    <span class="analysis-method-tag">STAR Voting</span>
                    <span class="analysis-method-tag">Majority Judgment</span>
                </div>
            </div>
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <text x="4" y="8" font-size="6" fill="currentColor" stroke="none" font-weight="bold">1</text>
                        <line x1="10" y1="6" x2="20" y2="6"/>
                        <text x="4" y="14" font-size="6" fill="currentColor" stroke="none" font-weight="bold">2</text>
                        <line x1="10" y1="12" x2="20" y2="12"/>
                        <text x="4" y="20" font-size="6" fill="currentColor" stroke="none" font-weight="bold">3</text>
                        <line x1="10" y1="18" x2="20" y2="18"/>
                    </svg>
                </div>
                <h3>Rank Aggregation</h3>
                <p>Aggregate individual rankings into a consensus group ranking using social choice algorithms.</p>
                <div class="analysis-methods">
                    <span class="analysis-method-tag">Kemeny</span>
                    <span class="analysis-method-tag">Squared Kemeny</span>
                    <span class="analysis-method-tag">Borda Ranking</span>
                </div>
            </div>
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v12"/>
                        <path d="M15 9.5c0-1.5-1.5-2.5-3-2.5s-3 1-3 2.5 1.5 2 3 2.5 3 1 3 2.5-1.5 2.5-3 2.5-3-1-3-2.5"/>
                    </svg>
                </div>
                <h3>Participatory Budgeting</h3>
                <p>Allocate budgets across projects fairly using proportional methods.</p>
                <div class="analysis-methods">
                    <span class="analysis-method-tag">Method of Equal Shares</span>
                    <span class="analysis-method-tag">Utilitarian Greedy</span>
                    <span class="analysis-method-tag">Pabulib Export</span>
                </div>
            </div>
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M3 9h18"/>
                        <path d="M9 3v18"/>
                    </svg>
                </div>
                <h3>Visualizations &amp; Comparisons</h3>
                <p>Pairwise margins, response matrices, and side-by-side rule comparisons.</p>
                <div class="analysis-methods" id="viz-methods">
                    <span class="analysis-method-tag">Pairwise Margins</span>
                    <span class="analysis-method-tag">Response Matrix</span>
                    <span class="analysis-method-tag">Raw Data Export</span>
                    <span class="analysis-method-tag hidden-method">Condorcet Winner</span>
                    <span class="analysis-method-tag hidden-method">Multi-Rule Comparison</span>
                    <span class="analysis-method-tag hidden-method">Preflib Format</span>
                    <button class="analysis-method-more" onclick="toggleMethods('viz-methods')">
                        +3 more
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <section class="hiw">
        <h2>How it works</h2>
        <div class="hiw-rows">

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <span class="hiw-add-btn">+ Add question</span>
                        <div class="hiw-tray">
                            <div>
                                <div class="hiw-cat-label">Choice</div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                                    Single choice
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18"/><polyline points="7 12 10 15 17 8"/></svg>
                                    Approval
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14"/><path d="M8 15V9M12 15v-3M16 15v-5"/></svg>
                                    Budgeting
                                </div>
                            </div>
                            <div>
                                <div class="hiw-cat-label">Ranking</div>
                                <div class="hiw-type-btn hiw-hovered">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="5"/><rect x="4" y="10" width="16" height="5"/><rect x="4" y="17" width="16" height="5"/></svg>
                                    Ranking
                                    <span class="hiw-cursor" style="left: 62%; top: 45%;">
                                        <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                                    </span>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="5"/><rect x="4" y="10" width="16" height="5"/><rect x="4" y="17" width="16" height="5" stroke-dasharray="2 2" opacity="0.4"/></svg>
                                    Partial ranking
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="7" y="2" width="10" height="4"/><rect x="4" y="8" width="7" height="4"/><rect x="13" y="8" width="7" height="4"/><rect x="7" y="14" width="10" height="4"/></svg>
                                    Ranking with ties
                                </div>
                            </div>
                            <div class="hiw-cat-rating">
                                <div class="hiw-cat-label">Rating</div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    Star rating
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20L8 4M12 20V10M16 20l4-10"/></svg>
                                    Grades
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4L19 6"/></svg>
                                    Yes / no / abstain
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>1</em>Build the ballot</h3>
                    <p>Nine question types, from single choice to rankings and grades &mdash; mixed freely in one poll.</p>
                </div>
            </div>

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <div class="hiw-label">Poll link</div>
                        <div class="hiw-link-field">
                            <span class="hiw-url">pref.tools/vote/k3v9q2</span>
                            <span class="hiw-copy">Copy
                                <span class="hiw-cursor" style="left: 70%; top: 50%;">
                                    <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                                </span>
                            </span>
                        </div>
                        <div class="hiw-label">Who can vote</div>
                        <div class="hiw-radio-row"><span class="hiw-radio on"></span>Anyone with the link</div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span>Password required</div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span>One-time tokens</div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span>Email invitations</div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>2</em>Share it</h3>
                    <p>A public link, a password, one-time tokens, or email invitations. Voters don't need an account.</p>
                </div>
            </div>

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <div class="hiw-drawer-head">Add analysis <span class="hiw-x">&times;</span></div>
                        <p class="hiw-wizard-q">What do you want to show?</p>
                        <div class="hiw-intent-card hiw-hovered">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8M12 17v4M7 4h10v6a5 5 0 0 1-10 0V4z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name">Who wins?</div>
                                <div class="hiw-intent-desc">Determine the winning option using a voting method</div>
                            </div>
                            <span class="hiw-cursor" style="left: 89%; top: 55%;">
                                <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                            </span>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-7"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name">How did people vote?</div>
                                <div class="hiw-intent-desc">Tallies, tables, and visualizations of the votes</div>
                            </div>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="8" r="2.5"/><path d="M17 13.5c2.5 0.5 4 2.7 4 5.5"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name">Multiple winners</div>
                                <div class="hiw-intent-desc">Elect a committee or divide seats among the options</div>
                            </div>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M12 6l7 3-7-3-7 3 7-3z"/><path d="M5 9l-2 5a3 3 0 0 0 6 0l-2-5M19 9l-2 5a3 3 0 0 0 6 0l-2-5" transform="translate(0 -1)"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name">Compare voting methods</div>
                                <div class="hiw-intent-desc">See how the outcome differs across voting rules</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>3</em>Analyze the results</h3>
                    <p>Pick analyses per question: winners, tallies, committees, and rule comparisons.</p>
                </div>
            </div>

        </div>
    </section>

    <section class="demo-section" id="demo">
        <div class="demo-content">
            <div class="demo-preview">
                <div class="demo-card demo-card-poll">
                    <img src="<?= basePath() ?>/screenshots/full_poll_container.png" alt="Poll Interface">
                    <div class="demo-card-label">Poll Interface</div>
                </div>
                <div class="demo-card demo-card-results">
                    <img src="<?= basePath() ?>/screenshots/full_results_container.png" alt="Results & Analysis">
                    <div class="demo-card-label">Results & Analysis</div>
                </div>
            </div>
            <div class="demo-actions">
                <h3>Try the demo</h3>
                <p>Vote in a sample poll with multiple question types, then explore the results page to see different analysis methods in action.</p>
                <div class="demo-buttons">
                    <a href="<?= basePath() ?>/demo" class="btn btn-primary btn-large">Take the demo poll</a>
                    <a href="<?= basePath() ?>/demo/results" class="btn btn-secondary btn-large">View demo results</a>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <p>Create a poll in minutes. No account needed.</p>
        <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a poll</a>
    </section>
</div>

<script>
// Keyboard handler for gallery items (accessibility)
function handleGalleryKeydown(event, element) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openLightbox(element);
    }
}

// Toggle methods expansion
function toggleMethods(containerId) {
    const container = document.getElementById(containerId);
    const btn = container.querySelector('.analysis-method-more');
    const isExpanded = container.classList.toggle('expanded');

    const hiddenCount = container.querySelectorAll('.hidden-method').length;
    if (isExpanded) {
        btn.innerHTML = `Show less <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>`;
    } else {
        btn.innerHTML = `+${hiddenCount} more <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>`;
    }
}

// Lightbox functionality
let galleryItems = [];
let currentIndex = 0;

function initGallery() {
    galleryItems = Array.from(document.querySelectorAll('.gallery-item'));
}

function openLightbox(item) {
    initGallery();
    currentIndex = galleryItems.indexOf(item);
    showCurrentImage();

    const lightbox = document.getElementById('galleryLightbox');
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function showCurrentImage() {
    const item = galleryItems[currentIndex];
    const img = item.querySelector('img');
    const label = item.querySelector('.gallery-item-label');

    const lightboxImg = document.getElementById('lightboxImage');
    const lightboxLabel = document.getElementById('lightboxLabel');
    const lightboxCounter = document.getElementById('lightboxCounter');

    lightboxImg.src = img.src;
    lightboxImg.alt = img.alt;
    lightboxLabel.textContent = label.textContent;
    lightboxCounter.textContent = `${currentIndex + 1} / ${galleryItems.length}`;
}

function navigateLightbox(direction, event) {
    if (event) {
        event.stopPropagation();
    }

    currentIndex += direction;

    // Wrap around
    if (currentIndex < 0) {
        currentIndex = galleryItems.length - 1;
    } else if (currentIndex >= galleryItems.length) {
        currentIndex = 0;
    }

    showCurrentImage();
}

function closeLightbox(event) {
    // Only close if clicking on the overlay or close button, not the image or nav buttons
    if (event.target.id === 'galleryLightbox' || event.target.classList.contains('lightbox-close')) {
        const lightbox = document.getElementById('galleryLightbox');
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('galleryLightbox');
    if (!lightbox.classList.contains('active')) return;

    if (e.key === 'Escape') {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    } else if (e.key === 'ArrowLeft') {
        navigateLightbox(-1);
    } else if (e.key === 'ArrowRight') {
        navigateLightbox(1);
    }
});

// Dark mode image switching
function updateImagesForTheme() {
    const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const screenshots = document.querySelectorAll('img[src*="/screenshots/"]');
    
    screenshots.forEach(img => {
        let src = img.src;
        // Skip images already in the correct state
        if (isDarkMode && src.includes('-dark.png')) return;
        if (!isDarkMode && !src.includes('-dark.png')) return;
        
        if (isDarkMode) {
            img.src = src.replace('.png', '-dark.png');
        } else {
            img.src = src.replace('-dark.png', '.png');
        }
    });
}

// Initial check
updateImagesForTheme();

// Watch for system theme changes
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateImagesForTheme);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
