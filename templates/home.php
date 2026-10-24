<?php
$title = 'Pref.Tools Vote - ' . __('page_home_heading');
$screenshot = static function (string $name, string $alt): void {
    $images = \App\Services\ScreenshotAssets::forLocale($name, \App\i18n\UiLocale::get(), basePath());
    ?>
    <picture class="localized-screenshot" lang="<?= e($images['locale']) ?>">
        <source media="(prefers-color-scheme: dark)" srcset="<?= e($images['dark']['url']) ?>" width="<?= $images['dark']['width'] ?>" height="<?= $images['dark']['height'] ?>">
        <img src="<?= e($images['light']['url']) ?>" width="<?= $images['light']['width'] ?>" height="<?= $images['light']['height'] ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async">
    </picture>
    <?php
};
ob_start();
?>

<div class="container hero-container">
    <section class="hero-enhanced">
        <h1><?= e(__('page_home_heading')) ?></h1>
        <p class="lead"><?= e(__('page_home_intro')) ?> <a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">GitHub</a></p>
        <div class="hero-actions">
            <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large"><?= e(__('page_create_a_poll')) ?></a>
            <a href="#demo" class="btn btn-secondary btn-large"><?= e(__('page_try_the_demo')) ?></a>
        </div>
    </section>
</div>

<section class="screenshot-gallery">
    <div class="container">
        <h2><?= e(__('page_question_types_6a328b')) ?></h2>
        <p class="section-subtitle">
            <?= e(__('page_nine_formats_from_single_choice_to_full_rankings_and_grades')) ?>
        </p>
    </div>
    <div class="gallery-grid">
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_single_choice', __('page_single_choice_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_single_choice')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_approval', __('page_approval_voting_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_approval_voting_6e0d66')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_ranking', __('page_ranking_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_rankings')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_ranking_truncated', __('page_truncated_ranking_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_partial_rankings')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_ranking_with_ties', __('page_ranking_with_ties_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_rankings_with_ties')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_star', __('page_star_rating_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_star_ratings')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_grade', __('page_grade_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_grades_verbal')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_grade_2', __('page_grade_question_with_symbols')); ?>
            <div class="gallery-item-label"><?= e(__('page_grades_symbols')) ?></div>
        </div>
        <div class="gallery-item" role="button" tabindex="0" onclick="openLightbox(this)" onkeydown="handleGalleryKeydown(event, this)">
            <?php $screenshot('question_yes_no_abstain', __('page_yes_no_abstain_question')); ?>
            <div class="gallery-item-label"><?= e(__('page_yes_no_abstain')) ?></div>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="gallery-lightbox" id="galleryLightbox" role="dialog" aria-modal="true" aria-label="<?= e(__('page_image_lightbox')) ?>" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)" aria-label="<?= e(__('page_close_lightbox')) ?>">&times;</button>
    <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1, event)" aria-label="<?= e(__('page_previous_image')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
    </button>
    <picture>
        <source id="lightboxDarkSource" media="(prefers-color-scheme: dark)">
        <img id="lightboxImage" alt="<?= e(__('page_enlarged_screenshot')) ?>">
    </picture>
    <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1, event)" aria-label="<?= e(__('page_next_image')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
        </svg>
    </button>
    <div class="lightbox-counter" id="lightboxCounter" aria-live="polite"></div>
    <div class="lightbox-label" id="lightboxLabel"></div>
</div>

<section class="analysis-section">
    <div class="container">
        <h2><?= e(__('page_results_and_analysis')) ?></h2>
        <p class="section-subtitle"><?= e(__('page_home_analysis_intro')) ?> <a href="https://github.com/voting-tools/pref_voting" target="_blank" rel="noopener">pref_voting</a> · <a href="https://github.com/martinlackner/abcvoting" target="_blank" rel="noopener">abcvoting</a></p>
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
                <h3><?= e(__('page_single_winner_elections')) ?></h3>
                <p><?= e(__('page_find_winners_that_best_reflect_the_group_s_preferences_even_when_there_s_no_ma')) ?></p>
                <div class="analysis-methods" id="ranking-methods">
                    <span class="analysis-method-tag"><?= e(__('page_approval_voting')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_schulze')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_ranked_pairs')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_irv')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_borda_count')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_copeland')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_minimax')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_plurality')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_split_cycle')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_stable_voting')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_top_cycle')) ?></span>
                    <button class="analysis-method-more" onclick="toggleMethods('ranking-methods')" data-more-label="<?= e(__('page_6_more')) ?>" data-less-label="<?= e(__('page_show_less')) ?>">
                        <?= e(__('page_6_more')) ?>
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
                <h3><?= e(__('page_multi_winner_elections')) ?></h3>
                <p><?= e(__('page_select_committees_or_multiple_winners_with_proportional_representation_rules')) ?></p>
                <div class="analysis-methods" id="multiwinner-methods">
                    <span class="analysis-method-tag"><?= e(__('page_pav')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_method_of_equal_shares')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_stv')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_seq_phragm_n')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_apportionment')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_sequential_pav')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_chamberlin_courant')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_meek_stv')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_sainte_lagu_av')) ?></span>
                    <button class="analysis-method-more" onclick="toggleMethods('multiwinner-methods')" data-more-label="<?= e(__('page_4_more')) ?>" data-less-label="<?= e(__('page_show_less')) ?>">
                        <?= e(__('page_4_more')) ?>
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
                <h3><?= e(__('page_rating_methods')) ?></h3>
                <p><?= e(__('page_analyze_star_ratings_and_grades_with_methods_designed_for_evaluative_judgments')) ?></p>
                <div class="analysis-methods" id="rating-methods">
                    <span class="analysis-method-tag"><?= e(__('page_score_voting')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_star_voting')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('report_type_majority_judgment')) ?></span>
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
                <h3><?= e(__('report_type_rank_aggregation')) ?></h3>
                <p><?= e(__('page_aggregate_individual_rankings_into_a_consensus_group_ranking_using_social_choi')) ?></p>
                <div class="analysis-methods">
                    <span class="analysis-method-tag"><?= e(__('page_kemeny')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_squared_kemeny')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_borda_ranking')) ?></span>
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
                <h3><?= e(__('type_participatory_budgeting')) ?></h3>
                <p><?= e(__('page_allocate_budgets_across_projects_fairly_using_proportional_methods')) ?></p>
                <div class="analysis-methods">
                    <span class="analysis-method-tag"><?= e(__('page_method_of_equal_shares')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_utilitarian_greedy')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_pabulib_export_c77427')) ?></span>
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
                <h3><?= e(__('page_visualizations_comparisons')) ?></h3>
                <p><?= e(__('page_pairwise_margins_response_matrices_and_side_by_side_rule_comparisons')) ?></p>
                <div class="analysis-methods" id="viz-methods">
                    <span class="analysis-method-tag"><?= e(__('report_type_pairwise_margins')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('report_type_response_matrix')) ?></span>
                    <span class="analysis-method-tag"><?= e(__('page_raw_data_export')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('condorcet_winner')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('report_type_multi_rule_comparison')) ?></span>
                    <span class="analysis-method-tag hidden-method"><?= e(__('page_preflib_format')) ?></span>
                    <button class="analysis-method-more" onclick="toggleMethods('viz-methods')" data-more-label="<?= e(__('page_3_more')) ?>" data-less-label="<?= e(__('page_show_less')) ?>">
                        <?= e(__('page_3_more')) ?>
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
        <h2><?= e(__('page_how_it_works')) ?></h2>
        <div class="hiw-rows">

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <span class="hiw-add-btn"><?= e(__('page_add_question')) ?></span>
                        <div class="hiw-tray">
                            <div>
                                <div class="hiw-cat-label"><?= e(__('category_choice')) ?></div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                                    <?= e(__('page_single_choice')) ?>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18"/><polyline points="7 12 10 15 17 8"/></svg>
                                    <?= e(__('page_approval')) ?>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14"/><path d="M8 15V9M12 15v-3M16 15v-5"/></svg>
                                    <?= e(__('page_budgeting')) ?>
                                </div>
                            </div>
                            <div>
                                <div class="hiw-cat-label"><?= e(__('category_ranking')) ?></div>
                                <div class="hiw-type-btn hiw-hovered">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="5"/><rect x="4" y="10" width="16" height="5"/><rect x="4" y="17" width="16" height="5"/></svg>
                                    <?= e(__('category_ranking')) ?>
                                    <span class="hiw-cursor" style="left: 62%; top: 45%;">
                                        <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                                    </span>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="5"/><rect x="4" y="10" width="16" height="5"/><rect x="4" y="17" width="16" height="5" stroke-dasharray="2 2" opacity="0.4"/></svg>
                                    <?= e(__('page_partial_ranking')) ?>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="7" y="2" width="10" height="4"/><rect x="4" y="8" width="7" height="4"/><rect x="13" y="8" width="7" height="4"/><rect x="7" y="14" width="10" height="4"/></svg>
                                    <?= e(__('page_ranking_with_ties_88fc15')) ?>
                                </div>
                            </div>
                            <div class="hiw-cat-rating">
                                <div class="hiw-cat-label"><?= e(__('category_rating')) ?></div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <?= e(__('page_star_rating')) ?>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20L8 4M12 20V10M16 20l4-10"/></svg>
                                    <?= e(__('type_grade')) ?>
                                </div>
                                <div class="hiw-type-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4L19 6"/></svg>
                                    <?= e(__('page_yes_no_abstain')) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>1</em><?= e(__('page_build_the_ballot')) ?></h3>
                    <p><?= e(__('page_nine_question_types_from_single_choice_to_rankings_and_grades_mixed_freely_in')) ?></p>
                </div>
            </div>

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <div class="hiw-label"><?= e(__('page_poll_link')) ?></div>
                        <div class="hiw-link-field">
                            <span class="hiw-url">pref.tools/vote/k3v9q2</span>
                            <span class="hiw-copy"><?= e(__('page_copy')) ?>
                                <span class="hiw-cursor" style="left: 70%; top: 50%;">
                                    <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                                </span>
                            </span>
                        </div>
                        <div class="hiw-label"><?= e(__('page_who_can_vote')) ?></div>
                        <div class="hiw-radio-row"><span class="hiw-radio on"></span><?= e(__('page_anyone_with_the_link')) ?></div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span><?= e(__('page_password_required')) ?></div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span><?= e(__('page_one_time_tokens')) ?></div>
                        <div class="hiw-radio-row"><span class="hiw-radio"></span><?= e(__('page_email_invitations_a11721')) ?></div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>2</em><?= e(__('page_share_it')) ?></h3>
                    <p><?= e(__('page_a_public_link_a_password_one_time_tokens_or_email_invitations_voters_don_t_nee')) ?></p>
                </div>
            </div>

            <div class="hiw-row">
                <div class="hiw-vignette">
                    <div class="hiw-card">
                        <div class="hiw-drawer-head"><?= e(__('page_add_analysis')) ?> <span class="hiw-x">&times;</span></div>
                        <p class="hiw-wizard-q"><?= e(__('page_what_do_you_want_to_show')) ?></p>
                        <div class="hiw-intent-card hiw-hovered">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8M12 17v4M7 4h10v6a5 5 0 0 1-10 0V4z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name"><?= e(__('page_who_wins')) ?></div>
                                <div class="hiw-intent-desc"><?= e(__('page_determine_the_winning_option_using_a_voting_method')) ?></div>
                            </div>
                            <span class="hiw-cursor" style="left: 89%; top: 55%;">
                                <svg viewBox="0 0 24 24"><path d="M5 3l14 9-6.5 1.5L16 20l-2.5 1-3.5-6.5L5 19V3z" fill="#fff" stroke="#191919" stroke-width="1.4" stroke-linejoin="round"/></svg>
                            </span>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-7"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name"><?= e(__('page_how_did_people_vote')) ?></div>
                                <div class="hiw-intent-desc"><?= e(__('page_tallies_tables_and_visualizations_of_the_votes')) ?></div>
                            </div>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="8" r="2.5"/><path d="M17 13.5c2.5 0.5 4 2.7 4 5.5"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name"><?= e(__('page_multiple_winners')) ?></div>
                                <div class="hiw-intent-desc"><?= e(__('page_elect_a_committee_or_divide_seats_among_the_options')) ?></div>
                            </div>
                        </div>
                        <div class="hiw-intent-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M12 6l7 3-7-3-7 3 7-3z"/><path d="M5 9l-2 5a3 3 0 0 0 6 0l-2-5M19 9l-2 5a3 3 0 0 0 6 0l-2-5" transform="translate(0 -1)"/></svg>
                            <div class="hiw-intent-text">
                                <div class="hiw-intent-name"><?= e(__('page_compare_voting_methods')) ?></div>
                                <div class="hiw-intent-desc"><?= e(__('page_see_how_the_outcome_differs_across_voting_rules')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hiw-text">
                    <h3><em>3</em><?= e(__('page_analyze_the_results')) ?></h3>
                    <p><?= e(__('page_pick_analyses_per_question_winners_tallies_committees_and_rule_comparisons')) ?></p>
                </div>
            </div>

        </div>
    </section>

    <section class="demo-section" id="demo">
        <div class="demo-content">
            <div class="demo-preview">
                <div class="demo-card demo-card-poll">
                    <?php $screenshot('full_poll_container', __('page_poll_interface')); ?>
                    <div class="demo-card-label"><?= e(__('page_poll_interface')) ?></div>
                </div>
                <div class="demo-card demo-card-results">
                    <?php $screenshot('full_results_container', __('page_results_analysis')); ?>
                    <div class="demo-card-label"><?= e(__('page_results_analysis')) ?></div>
                </div>
            </div>
            <div class="demo-actions">
                <h3><?= e(__('page_try_the_demo')) ?></h3>
                <p><?= e(__('page_vote_in_a_sample_poll_with_multiple_question_types_then_explore_the_results_pa')) ?></p>
                <div class="demo-buttons">
                    <a href="<?= basePath() ?>/demo" class="btn btn-primary btn-large"><?= e(__('page_take_the_demo_poll')) ?></a>
                    <a href="<?= basePath() ?>/demo/results" class="btn btn-secondary btn-large"><?= e(__('page_view_demo_results')) ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <p><?= e(__('page_create_a_poll_in_minutes_no_account_needed')) ?></p>
        <a href="<?= basePath() ?>/create" class="btn btn-primary btn-large"><?= e(__('page_create_a_poll')) ?></a>
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
    const label = isExpanded ? btn.dataset.lessLabel : btn.dataset.moreLabel;
    const icon = btn.querySelector('svg').cloneNode(true);
    btn.replaceChildren(document.createTextNode(label + ' '), icon);
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

    document.getElementById('lightboxDarkSource').srcset = item.querySelector('source').srcset;
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

</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
