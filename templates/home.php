<?php
$title = 'Pref.Tools Vote - Polls with Advanced Voting Methods';
ob_start();
?>

<div class="container">
    <section class="hero-enhanced">
        <span class="hero-tagline">Social Choice Theory Made Accessible</span>
        <h1>Smarter Polls.<br>Better Decisions.</h1>
        <p class="lead">
            Go beyond simple polls. Collect rankings, approval votes, star ratings, and grades &mdash;
            then analyze results with research-grade voting methods like Schulze, IRV, and Majority Judgment.
        </p>
        <div class="hero-actions">
            <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a Poll</a>
            <a href="#demo" class="btn btn-secondary btn-large">Try the Demo</a>
        </div>
    </section>
</div>

<section class="screenshot-gallery">
    <div class="container">
        <h2>Rich Question Types</h2>
        <p class="section-subtitle">
            Capture nuanced preferences with specialized input types designed for group decision-making.
        </p>
    </div>
    <div class="gallery-grid">
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_single_choice.png" alt="Single Choice Question">
            <div class="gallery-item-label">Single Choice</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_approval.png" alt="Approval Voting Question">
            <div class="gallery-item-label">Approval Voting</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking.png" alt="Ranking Question">
            <div class="gallery-item-label">Rankings</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking_truncated.png" alt="Truncated Ranking Question">
            <div class="gallery-item-label">Partial Rankings</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_ranking_with_ties.png" alt="Ranking with Ties Question">
            <div class="gallery-item-label">Rankings with Ties</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_star.png" alt="Star Rating Question">
            <div class="gallery-item-label">Star Ratings</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_grade.png" alt="Grade Question">
            <div class="gallery-item-label">Grades (Verbal)</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_grade_2.png" alt="Grade Question with Symbols">
            <div class="gallery-item-label">Grades (Symbols)</div>
        </div>
        <div class="gallery-item" onclick="openLightbox(this)">
            <img src="<?= basePath() ?>/screenshots/question_yes_no_abstain.png" alt="Yes/No/Abstain Question">
            <div class="gallery-item-label">Yes / No / Abstain</div>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="gallery-lightbox" id="galleryLightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)">&times;</button>
    <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1, event)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
    </button>
    <img id="lightboxImage" src="" alt="">
    <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1, event)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 6 15 12 9 18"/>
        </svg>
    </button>
    <div class="lightbox-counter" id="lightboxCounter"></div>
    <div class="lightbox-label" id="lightboxLabel"></div>
</div>

<section class="analysis-section">
    <div class="container">
        <h2>Powerful Analysis</h2>
        <p class="section-subtitle">
            Don't just count votes. Understand group preferences with algorithms developed by social choice researchers.
        </p>
        <div class="analysis-grid">
            <div class="analysis-category">
                <div class="analysis-category-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3v18h18"/>
                        <path d="M18 17V9"/>
                        <path d="M13 17V5"/>
                        <path d="M8 17v-3"/>
                    </svg>
                </div>
                <h3>Ranking Methods</h3>
                <p>Find winners that best reflect the group's preferences, even when there's no majority.</p>
                <div class="analysis-methods" id="ranking-methods">
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
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <h3>Rating Methods</h3>
                <p>Analyze star ratings and grades with methods designed for evaluative judgments.</p>
                <div class="analysis-methods" id="rating-methods">
                    <span class="analysis-method-tag">Majority Judgment</span>
                    <span class="analysis-method-tag">Score Voting</span>
                    <span class="analysis-method-tag">STAR Voting</span>
                    <span class="analysis-method-tag hidden-method">Mean Score</span>
                    <span class="analysis-method-tag hidden-method">Sum Score</span>
                    <button class="analysis-method-more" onclick="toggleMethods('rating-methods')">
                        +2 more
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
                    <span class="analysis-method-tag">Equal Shares</span>
                    <span class="analysis-method-tag">Seq. Phragm&eacute;n</span>
                    <span class="analysis-method-tag hidden-method">Sequential PAV</span>
                    <span class="analysis-method-tag hidden-method">Chamberlin-Courant</span>
                    <span class="analysis-method-tag hidden-method">SAV</span>
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
                    <span class="analysis-method-tag">Multi-Rule Comparison</span>
                    <span class="analysis-method-tag hidden-method">Condorcet Winner</span>
                    <span class="analysis-method-tag hidden-method">Raw Data Export</span>
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
    <section class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Build Your Poll</h3>
                    <p>Use our drag-and-drop builder. Mix question types, set privacy options, and customize access control.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Collect Responses</h3>
                    <p>Share a link, send email invitations, or generate one-time tokens. No account required for voters.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Analyze Results</h3>
                    <p>Add reports per question. Compare voting rules, visualize pairwise matchups, and export data.</p>
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
                <h3>Try the Demo</h3>
                <p>Vote in a sample poll with multiple question types, then explore the results page to see different analysis methods in action.</p>
                <div class="demo-buttons">
                    <a href="<?= basePath() ?>/demo" class="btn btn-primary btn-large">Take the Demo Poll</a>
                    <a href="<?= basePath() ?>/demo/results" class="btn btn-secondary btn-large">View Demo Results</a>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Ready to Make Better Decisions?</h2>
        <p>Create your first poll in minutes. No account required.</p>
        <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large">Create a Poll</a>
    </section>
</div>

<script>
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
