<?php
$title = __('about') . ' - Pref.Tools Vote';
$extraCss = ['assets/css/about.css'];
ob_start();
?>

<div class="about-container">
    <div class="container">
        <div class="card about-card">
            <h1><?= e(__('page_about_pref_tools_vote')) ?></h1>

            <section class="about-section about-author">
                <div class="author-content">
                    <img src="<?= basePath() ?>/assets/img/dominik.jpg" alt="Dominik Peters" class="author-photo">
                    <div class="author-text">
                        <p><?= e(__('page_this_web_application_was_developed_by_dominik_peters_a_cnrs_researcher_at_univ')) ?></p>
                        <p><a href="https://dominik-peters.de" target="_blank" rel="noopener">dominik-peters.de</a></p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <h2><?= e(__('page_development')) ?></h2>
                <p><?= e(__('page_pref_tools_vote_was_developed_with_the_assistance_of_claude_opus_4_5_anthropic')) ?></p>
                <p><?= e(__('page_the_backend_voting_rule_computations_are_powered_by_adapted_versions_of')) ?></p>
                <ul>
                    <li><a href="https://github.com/voting-tools/pref_voting/" target="_blank" rel="noopener"><strong>pref_voting</strong></a> <?= e(__('page_a_python_library_for_preference_voting_methods_single_winner_rank_aggregation')) ?></li>
                    <li><a href="https://github.com/martinlackner/abcvoting/" target="_blank" rel="noopener"><strong>abcvoting</strong></a> <?= e(__('page_a_python_library_for_approval_based_committee_voting')) ?></li>
                    <li><a href="https://pref.tools/apportionment/" target="_blank" rel="noopener"><strong>pref.tools/apportionment</strong></a> <?= e(__('page_a_web_app_for_apportionment_methods')) ?></li>
                    <li><a href="https://equalshares.net/tools/compute/" target="_blank" rel="noopener"><strong><?= e(__('page_method_of_equal_shares_online_computation_tool')) ?></strong></a> <?= e(__('page_a_web_app_for_computing_the_method_of_equal_shares_in_participatory_budgeting')) ?></li>
                </ul>
            </section>

            <section class="about-section">
                <h2><?= e(__('page_open_source')) ?></h2>
                <p><?= e(__('page_the_source_code_for_this_application_is_mit_licensed_and_available_on_github')) ?></p>
                <p><a href="https://github.com/DominikPeters/pref-tools-vote-app" target="_blank" rel="noopener">github.com/DominikPeters/pref-tools-vote-app</a></p>
                <p><?= e(__('page_feedback_bug_reports_and_contributions_are_welcome')) ?></p>
            </section>

            <section class="about-section">
                <h2><?= e(__('page_features')) ?></h2>

                <h3><?= e(__('page_question_types')) ?></h3>
                <p><?= e(__('page_pref_tools_vote_supports_a_variety_of_input_types_designed_for_social_choice_a')) ?></p>
                <ul class="feature-list">
                    <li><strong><?= e(__('type_single_choice')) ?></strong> <?= e(__('page_standard_radio_button_selection')) ?></li>
                    <li><strong><?= e(__('page_approval_voting')) ?></strong> <?= e(__('page_select_multiple_options_with_optional_min_max_limits')) ?></li>
                    <li><strong><?= e(__('page_ranking_full')) ?></strong> <?= e(__('page_drag_and_drop_complete_ranking_of_all_options')) ?></li>
                    <li><strong><?= e(__('type_ranking_truncated')) ?></strong> <?= e(__('page_rank_a_subset_of_options_truncated_preferences')) ?></li>
                    <li><strong><?= e(__('page_ranking_with_ties')) ?></strong> <?= e(__('page_allow_indifference_classes_in_rankings')) ?></li>
                    <li><strong><?= e(__('type_star')) ?></strong> <?= e(__('page_rate_options_on_a_configurable_star_scale_2_10_stars')) ?></li>
                    <li><strong><?= e(__('page_grade_voting')) ?></strong> <?= e(__('page_assign_grades_excellent_very_good_good_fair_poor_reject')) ?></li>
                    <li><strong><?= e(__('type_yes_no_abstain')) ?></strong> <?= e(__('page_three_option_voting_for_each_alternative')) ?></li>
                    <li><strong><?= e(__('type_participatory_budgeting')) ?></strong> <?= e(__('page_budget_allocation_with_project_costs')) ?></li>
                    <li><strong><?= e(__('type_text_single')) ?></strong> <?= e(__('page_single_line_free_text_response')) ?></li>
                    <li><strong><?= e(__('type_text_multi')) ?></strong> <?= e(__('page_multi_line_free_text_response')) ?></li>
                </ul>

                <h3><?= e(__('page_single_winner_voting_methods')) ?></h3>
                <p><?= e(__('page_for_ranking_and_approval_based_questions')) ?></p>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_schulze_method')) ?></strong> <?= e(__('page_condorcet_consistent_method_using_beat_paths')) ?></li>
                    <li><strong><?= e(__('page_ranked_pairs')) ?></strong> <?= e(__('page_lock_in_method_based_on_pairwise_margins')) ?></li>
                    <li><strong><?= e(__('page_instant_runoff_voting_irv')) ?></strong> <?= e(__('page_sequential_elimination_of_last_place_candidates')) ?></li>
                    <li><strong><?= e(__('page_borda_count')) ?></strong> <?= e(__('page_positional_scoring_rule')) ?></li>
                    <li><strong><?= e(__('page_copeland')) ?></strong> <?= e(__('page_count_pairwise_victories')) ?></li>
                    <li><strong><?= e(__('page_minimax')) ?></strong> <?= e(__('page_minimize_worst_pairwise_defeat')) ?></li>
                    <li><strong><?= e(__('page_plurality')) ?></strong> <?= e(__('page_most_first_place_votes_wins')) ?></li>
                    <li><strong><?= e(__('page_split_cycle')) ?></strong> <?= e(__('page_condorcet_method_using_cycle_resolution')) ?></li>
                    <li><strong><?= e(__('page_stable_voting')) ?></strong> <?= e(__('page_defeat_dropping_condorcet_method')) ?></li>
                    <li><strong><?= e(__('page_approval_voting')) ?></strong> <?= e(__('page_most_approvals_wins')) ?></li>
                </ul>

                <h3><?= e(__('page_multi_winner_committee_methods')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_proportional_approval_voting_pav')) ?></strong> <?= e(__('page_thiele_method_for_proportional_representation')) ?></li>
                    <li><strong><?= e(__('page_method_of_equal_shares')) ?></strong> <?= e(__('page_proportional_method_for_approval_ballots')) ?></li>
                    <li><strong><?= e(__('page_single_transferable_vote_stv')) ?></strong> <?= e(__('page_ranked_choice_proportional_method')) ?></li>
                    <li><strong><?= e(__('page_sequential_phragm_n')) ?></strong> <?= e(__('page_load_balancing_proportional_method')) ?></li>
                    <li><strong><?= e(__('page_chamberlin_courant')) ?></strong> <?= e(__('page_diversity_maximizing_committee_selection')) ?></li>
                    <li><strong><?= e(__('page_apportionment_methods')) ?></strong> <?= e(__('page_seat_allocation_d_hondt_sainte_lagu_etc')) ?></li>
                </ul>

                <h3><?= e(__('report_type_rank_aggregation')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_kemeny')) ?></strong> <?= e(__('page_optimal_ranking_minimizing_pairwise_disagreements')) ?></li>
                    <li><strong><?= e(__('page_squared_kemeny')) ?></strong> <?= e(__('page_variant_using_squared_distances')) ?></li>
                    <li><strong><?= e(__('page_borda_ranking')) ?></strong> <?= e(__('page_ranking_by_borda_scores')) ?></li>
                </ul>

                <h3><?= e(__('page_rating_judgment_methods')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_score_voting')) ?></strong> <?= e(__('page_sum_of_ratings')) ?></li>
                    <li><strong><?= e(__('page_star_voting')) ?></strong> <?= e(__('page_score_then_automatic_runoff')) ?></li>
                    <li><strong><?= e(__('report_type_majority_judgment')) ?></strong> <?= e(__('page_median_based_grade_aggregation')) ?></li>
                </ul>

                <h3><?= e(__('type_participatory_budgeting')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_method_of_equal_shares')) ?></strong> <?= e(__('page_proportional_budget_allocation')) ?></li>
                    <li><strong><?= e(__('page_utilitarian_greedy')) ?></strong> <?= e(__('page_maximize_total_utility')) ?></li>
                    <li><strong><?= e(__('page_pabulib_export')) ?></strong> <?= e(__('page_export_data_in_standard_research_format')) ?></li>
                </ul>

                <h3><?= e(__('page_visualizations_analysis')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_pairwise_margins_graph')) ?></strong> <?= e(__('page_head_to_head_comparison_visualization')) ?></li>
                    <li><strong><?= e(__('report_type_response_matrix')) ?></strong> <?= e(__('page_doodle_style_overview_of_all_responses')) ?></li>
                    <li><strong><?= e(__('report_type_multi_rule_comparison')) ?></strong> <?= e(__('page_compare_outcomes_across_voting_methods')) ?></li>
                    <li><strong><?= e(__('page_condorcet_winner_detection')) ?></strong> <?= e(__('page_identify_candidates_beating_all_others')) ?></li>
                    <li><strong><?= e(__('page_data_export')) ?></strong> <?= e(__('page_preflib_and_pabulib_formats_for_research')) ?></li>
                </ul>

                <h3><?= e(__('page_poll_configuration')) ?></h3>
                <ul class="feature-list">
                    <li><strong><?= e(__('page_multiple_questions_per_poll')) ?></strong> <?= e(__('page_combine_different_question_types')) ?></li>
                    <li><strong><?= e(__('page_privacy_settings')) ?></strong> <?= e(__('page_full_transparency_anonymous_votes_or_private_results')) ?></li>
                    <li><strong><?= e(__('page_access_control')) ?></strong> <?= e(__('page_public_password_protected_one_time_tokens_or_email_invitations')) ?></li>
                    <li><strong><?= e(__('page_voting_modes')) ?></strong> <?= e(__('page_open_identified_or_secret_ballot')) ?></li>
                    <li><strong><?= e(__('page_response_editing')) ?></strong> <?= e(__('page_no_editing_edit_own_or_edit_any_doodle_style')) ?></li>
                    <li><strong><?= e(__('page_option_randomization')) ?></strong> <?= e(__('page_randomize_option_order_to_reduce_bias')) ?></li>
                    <li><strong><?= e(__('page_markdown_support')) ?></strong> <?= e(__('page_rich_text_in_descriptions_and_messages')) ?></li>
                </ul>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
