<?php

/**
 * English translations for voter-facing strings.
 *
 * Guidelines for adding new keys:
 * - Use snake_case for key names
 * - Group related keys under section comments
 * - Add inline comments to clarify context/usage
 * - For plurals, use "singular|plural" syntax: ':count item|:count items'
 * - Parameters use :name syntax: 'Hello :name'
 */

return [
    // =========================================================================
    // Poll Form (templates/poll.php)
    // =========================================================================

    // Form labels and placeholders
    'your_name' => 'Your Name', // label for voter name input field
    'name_placeholder' => 'Your name', // placeholder text in name input

    // Submit buttons
    'submit' => 'Submit',
    'submit_vote' => 'Submit Vote', // primary submit button
    'update_response' => 'Update Response', // when editing existing response
    'submit_disabled_preview' => 'Submit Vote (Disabled in Preview)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Preview Mode - This is how your poll will appear to voters.',
    'poll_not_open' => 'This poll is not yet open for submissions.',
    'voting_closed' => 'Voting is Closed', // banner heading
    'poll_no_longer_accepting' => 'This poll is no longer accepting responses.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Thank you!', // heading after successful submission
    'response_recorded' => 'Your response has been recorded.',
    'can_close_page' => 'You can now close this page.',
    'already_submitted_can_update' => 'You have already submitted a response. You can update it below.',

    // Validation
    'required_field' => 'This field is required.',
    'validation_error' => 'Please check your answers and try again.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Results', // page title and breadcrumb
    'poll' => 'Poll', // breadcrumb link text
    'live_results' => 'Live Results', // badge shown when poll is still open
    'loading_results' => 'Loading results...',
    'back_to_poll' => 'Back to Poll', // link to return to voting form
    'no_responses' => 'No responses yet.', // empty state message
    'computing_results' => 'Computing results...', // shown while report is calculating
    'unknown_report_type' => 'Unknown report type: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count response|:count responses', // "5 responses"
    'question_count' => ':count question|:count questions', // "3 questions"
    'closed_on' => 'Closed :date', // "Closed January 15, 2025"
    'created_on' => 'Created :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Single Choice',
    'type_approval' => 'Approval (Multiple Choice)',
    'type_participatory_budgeting' => 'Participatory Budgeting',
    'type_distribution' => 'Distribution (Point Voting)',
    'type_ranking' => 'Ranking',
    'type_ranking_truncated' => 'Ranking (Partial)',
    'type_ranking_with_ties' => 'Ranking (With Ties)',
    'type_star' => 'Star Rating',
    'type_grade' => 'Grades',
    'type_yes_no_abstain' => 'Yes / No / Abstain',
    'type_text_single' => 'Short Text',
    'type_text_multi' => 'Long Text',
    'type_section_header' => 'Section Header',

    // Common question labels
    'untitled_question' => 'Untitled Question',
    'section' => 'Section', // default label for untitled section headers
    'unknown_question_type' => 'Unknown question type: :type',

    // Common question UI
    'other_option' => 'Other:', // shown before text input for "other" option
    'please_specify' => 'Please specify...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Drag to reorder (top = best)', // hint for full ranking
    'ranking_ties_hint' => 'Drag to reorder. Items in the same group are tied.', // ranking with ties
    'available_options' => 'Available options', // truncated ranking - source list header
    'your_ranking' => 'Your ranking', // truncated ranking - target list header
    'drag_to_rank' => 'Drag options here to rank them', // truncated ranking placeholder
    'borda_score_note' => 'Ranked by Borda score (higher = better)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Select...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Yes',
    'no' => 'No',
    'abstain' => 'Abstain',

    // Distribution question UI
    'remaining' => 'Remaining:', // shows remaining points to allocate
    'points' => 'points', // unit label, e.g., "15 points"

    // Text input placeholders
    'short_answer' => 'Short answer',
    'long_answer' => 'Long answer',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Draft',
    'status_open' => 'Open',
    'status_closed' => 'Closed',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'View Results', // link to results page
    'report_poll' => 'Report this poll', // abuse report link
    'report_this_poll' => 'Report this poll', // heading for report modal
    'report_guidelines' => 'If you believe this poll violates our guidelines, please let us know.',
    'report_reason_spam' => 'Spam or misleading content',
    'report_reason_harassment' => 'Harassment or hate speech',
    'report_reason_doxxing' => 'Personal information exposure (doxxing)',
    'report_reason_illegal' => 'Illegal activity or content',
    'report_reason_impersonation' => 'Impersonation or fraud',
    'report_reason_phishing' => 'Malware or phishing attempt',
    'report_reason_copyright' => 'Copyright or trademark violation',
    'report_reason_other' => 'Other',
    'report_details' => 'Additional details',
    'report_optional' => '(optional)',
    'report_placeholder' => 'Please provide any additional context that might help us review this report...',
    'cancel' => 'Cancel',
    'submit_report' => 'Submit Report',
    'select_report_reason' => 'Please select a reason for reporting',
    'provide_report_details' => 'Please provide details for your report',
    'report_submitted' => 'Thank you for your report. We will review it shortly.',
    'share' => 'Share',
    'copy_link' => 'Copy Link',
    'copied' => 'Copied!', // toast message after copying
    'undo' => 'Undo',
    'delete' => 'Delete',
    'logout_failed' => 'Logout failed',
    'create_poll' => 'Create Poll',
    'about' => 'About',
    'dashboard' => 'Dashboard',
    'sysadmin' => 'Sysadmin',
    'log_out' => 'Log Out',
    'login' => 'Login',
    'privacy_policy' => 'Privacy Policy',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Poll not found.',
    'vote_closed' => 'This poll is closed and no longer accepting responses.',
    'already_voted' => 'You have already submitted a response.',
    'error_loading' => 'Error loading data. Please try again.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go in
    // fr.php etc. using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Winner',
    'result_winner_by_rule' => ':rule Winner', // e.g., "Majority Judgment Winner"
    'result_tied' => 'Tied',
    'result_no_winner' => 'No winner',
    'no_winner_yet' => 'No winner determined yet.', // shown when no responses
    'rule_majority_judgment' => 'Majority Judgment',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Voting Rule',
    'rule' => 'Rule', // generic column header for rule name in comparison tables
    'winners' => 'Winner(s)',
    'votes' => 'Votes',
    'option' => 'Option', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Candidate', // column header in multi-winner election results
    'count' => 'Count', // column header showing how many voting rules selected a candidate
    'rules' => 'Rules', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'size :size', // inline label with committee size, e.g., "(size 5)"
    'no_results_available' => 'No results available for this question.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Vote Counts',
    'report_type_approval_winner' => 'Approval Winner',
    'report_type_median' => 'Median Choice',
    'report_type_borda_scores' => 'Borda Scores',
    'report_type_pairwise_margins' => 'Pairwise Margins',
    'report_type_voting_rule_winner' => 'Voting Rule Winner',
    'report_type_rank_aggregation' => 'Rank Aggregation',
    'report_type_multiwinner' => 'Multi-Winner Voting Rule Winner',
    'report_type_pb_winner' => 'PB Voting Rule Winner',
    'report_type_condorcet_winner' => 'Condorcet Winner',
    'report_type_apportionment_winner' => 'Apportionment Rule Winner',
    'report_type_yna_counts' => 'Yes/No/Abstain Tallies',
    'report_type_majority_judgment' => 'Majority Judgment',
    'report_type_score_distribution' => 'Score Distribution', // histogram of scores/grades per option
    'report_type_text_responses' => 'Text Answers', // list of free-text answers
    'report_type_multi_rule_comparison' => 'Multi-Rule Comparison',
    'report_type_multi_swf_comparison' => 'Rank Aggregation Rule Comparison',
    'report_type_multiwinner_multi_rule_comparison' => 'Multi-Winner Multi-Rule Comparison',
    'report_type_apportionment_multi_rule_comparison' => 'Apportionment Multi-Rule Comparison',
    'report_type_distribution_aggregation' => 'Distribution Aggregation', // aggregates voter point distributions into consensus
    'report_type_distribution_multi_rule_comparison' => 'Distribution Multi-Rule Comparison', // compares multiple distribution aggregation rules
    'report_type_response_matrix' => 'Response Matrix',
    'report_type_raw_data_export' => 'Export Raw Vote Data',
    'report_type_text_block' => 'Text Block',

    // Admin report actions
    'add_analysis' => 'Add Analysis', // button to add a new report
    'drag_to_reorder' => 'Drag to reorder', // tooltip for report drag handle
    'delete_analysis' => 'Delete analysis', // tooltip for delete button
    'analysis_deleted' => 'Analysis deleted', // toast after deletion
    'make_public' => 'Make public', // tooltip to show report to voters
    'make_private' => 'Make private', // tooltip to hide report from voters
    'settings' => 'Settings', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'No rules selected or computed.',
    'rules_count' => ':count/:total rules', // "3/5 rules"
    'winners_by_rule_count' => 'Winners by Rule Count',
    'no_results_to_compare' => 'No results to compare.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count vote|:count votes', // "5 votes"
    'seat_count' => ':count seat|:count seats', // "3 seats"

    // Condorcet winner
    'condorcet_winner' => 'Condorcet Winner',
    'no_condorcet_winner' => 'No Condorcet Winner',
    'condorcet_explanation' => 'Beats all other options in head-to-head matchups',
    'condorcet_cycle' => 'There is a cycle in pairwise preferences',

    // Median choice
    'median_choice' => 'Median',
    'no_median_yet' => 'No median determined yet.',
    'median_interval' => 'Median interval', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'No winning projects determined yet.',
    'total_budget' => 'Total Budget',
    'spent' => 'Spent',
    'winning_projects' => 'Winning Projects',
    'avg_voter_approves' => 'On average, each voter approves :count winning projects.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'No winning committee determined yet.',
    'committee_number' => 'Committee #:num',
    'committee_size' => 'Committee Size',
    'tied_committees' => 'Tied winning committees',
    'winning_committee' => 'Winning Committee',
    'show_calculation_steps' => 'Show Calculation Steps',
    'hide_calculation_steps' => 'Hide Calculation Steps',
    'tie_showing_first' => 'Tied (showing first winning committee)',
    'candidate_frequency' => 'Candidate Frequency',
    'candidate_frequency_desc' => 'How often each candidate appears in a winning committee across the :count rules compared.',

    // Apportionment
    'total_seats' => 'Total Seats',
    'no_methods_selected' => 'No methods selected for comparison.',
    'apportionment_comparison_desc' => 'Comparison of :methods apportionment methods for :seats seats',

    // Distribution Aggregation
    // These rules aggregate voter point distributions (from "distribution" question type)
    // into a single consensus distribution. Used in budget/resource allocation scenarios.
    'dist_rule_mean' => 'Mean Rule', // simple average of all voter distributions
    'dist_rule_median' => 'Median Rule', // moving phantom mechanism with uniform phantom progression
    'dist_rule_independent_markets' => 'Independent Markets', // moving phantom mechanism modeling independent market shares
    'dist_rule_ladder' => 'Ladder Rule', // moving phantom mechanism with ladder-shaped progression
    'percentage' => 'Percentage', // column header for distribution results table

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'No ranking determined yet.',
    'tied_rankings' => 'There are :count tied optimal rankings:',
    'ranking_number' => 'Ranking #:num',

    // =========================================================================
    // Poll Builder (templates/builder.php, assets/js/builder.js)
    // =========================================================================

    'poll_admin' => 'Poll Admin', // breadcrumb link to the poll admin panel
    'edit_poll' => 'Edit Poll',
    'create_new_poll' => 'Create a New Poll',
    'clear' => 'Clear', // clear the local draft form
    'preview' => 'Preview', // preview poll before publishing
    'publish' => 'Publish',
    'save' => 'Save', // save modal changes
    'save_draft' => 'Save Draft',
    'update_draft' => 'Update Draft',
    'save_changes' => 'Save Changes',
    'untitled_poll' => 'Untitled Poll',
    'add_description' => 'Add description',
    'description_markdown_placeholder' => 'Description (optional, Markdown supported)',
    'description_optional_placeholder' => 'Description (optional)',
    'description_supports_markdown_placeholder' => 'Description (supports Markdown)',
    'add_question' => 'Add Question',
    'add_option' => 'Add Option',
    'category_choice' => 'Choice',
    'category_ranking' => 'Ranking',
    'category_rating' => 'Rating',
    'category_other' => 'Other',
    'voting_mode' => 'Voting Mode',
    'voting_mode_description' => 'Choose how voters will access your poll and how their votes are recorded.',
    'voting_mode_open' => 'Open',
    'voting_mode_open_description' => 'Anyone with the link can vote. One vote per browser.',
    'voting_mode_identified' => 'Identified',
    'voting_mode_identified_description' => 'One vote per person via access tokens or email invitations. Votes are linked to identity.',
    'voting_mode_secret_ballot' => 'Secret Ballot',
    'voting_mode_secret_ballot_description' => 'One vote per person, but votes are completely anonymous.',
    'voting_mode_locked' => 'Voting mode is locked.',
    'voting_mode_locked_description' => 'Responses have been submitted. Delete all responses to change the mode.',
    'display_options' => 'Display Options',
    'randomize_options_label' => 'Randomize option order for each voter',
    'randomize_options_tooltip' => 'Randomizing option order helps reduce position bias in responses',
    'poll_language' => 'Poll Language',
    'poll_language_tooltip' => 'The language used for system text shown to voters (buttons, labels, etc.)',
    'customize_thank_you_message' => 'Customize Thank You Message',
    'custom_message_set' => 'Custom message set',
    'clear_poll' => 'Clear Poll',
    'clear_poll_confirm' => 'Are you sure you want to clear all questions and settings? This cannot be undone.',
    'clear_all' => 'Clear All',
    'allow_other_option' => 'Allow "Other" option',
    'allow_other' => 'Allow "Other"',
    'allow_abstain' => 'Allow "Abstain"',
    'min_label' => 'Min:',
    'max_label' => 'Max:',
    'all_placeholder' => 'All',
    'currency_label' => 'Currency:',
    'stars_label' => 'Stars:',
    'scale_label' => 'Scale:',
    'budget_label' => 'Budget:',
    'max_per_option_label' => 'Max per option:',
    'spread_across_at_least_label' => 'Spread across at least:',
    'require_using_all_points' => 'Require using all points',
    'grade_preset_default' => 'Excellent -> Reject',
    'grade_preset_a_f' => 'A - F',
    'grade_preset_plus_minus' => '++ / + / 0 / - / --',
    'grade_preset_pass_fail' => 'Pass / Fail',
    'grade_preset_custom' => 'Custom...',
    'grade_excellent' => 'Excellent',
    'grade_very_good' => 'Very Good',
    'grade_good' => 'Good',
    'grade_fair' => 'Fair',
    'grade_poor' => 'Poor',
    'grade_reject' => 'Reject',
    'grade_pass' => 'Pass',
    'grade_fail' => 'Fail',
    'custom_grades_placeholder' => 'Comma separated, e.g. Excellent, Good, Poor',
    'duplicate' => 'Duplicate',
    'delete_question' => 'Delete question',
    'delete_option' => 'Delete option',
    'move_up' => 'Move up',
    'move_down' => 'Move down',
    'section_title_placeholder' => 'Section title',
    'question_text_placeholder' => 'Question text',
    'required' => 'Required',
    'always_parenthetical' => '(always)',
    'project_name_placeholder' => 'Project name',
    'cost_label' => 'Cost:',
    'option_number' => 'Option :number',
    'question_deleted' => 'Question deleted',
    'option_deleted' => 'Option deleted',
    'need_at_least_two_options' => 'Need at least 2 options',
    'copy_suffix' => ' (copy)',
    'saved_view_dashboard' => 'Saved! View in <a href=":url">Dashboard</a>',
    'please_add_title' => 'Please add a title',
    'please_add_question' => 'Please add at least one question',
    'please_fill_question_texts' => 'Please fill in all question texts',
    'security_verification_wait' => 'Please wait for security verification to complete',
    'please_add_title_before_preview' => 'Please add a title before previewing',
    'please_add_question_before_preview' => 'Please add at least one question before previewing',
    'failed_open_preview' => 'Failed to open preview',
    'thank_you_modal_description' => 'Shown to voters after they submit. Supports Markdown formatting.',
    'thank_you_placeholder' => "Thank you for voting!\n\nYour response has been recorded.",
    'reset_to_default' => 'Reset to Default',
    'default_thank_you_preview' => 'Default message: "Thank you! Your response has been recorded."',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Your response has been recorded.',
    'update_success' => 'Your response has been updated.',
    'edit_response' => 'Edit your response',
    'close_vote' => 'Close voting',
    'reopen_vote' => 'Reopen voting',
    'delete_vote' => 'Delete poll',
    'responses' => 'Responses',
];
