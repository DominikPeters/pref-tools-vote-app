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
    'type_approval' => 'Approval',
    'type_ranking' => 'Ranking',
    'type_star' => 'Star Rating',
    'type_grade' => 'Grade',
    'type_yes_no_abstain' => 'Yes / No / Abstain',
    'type_text_single' => 'Short Text',
    'type_text_multi' => 'Long Text',

    // Ranking question UI
    'ranking_hint' => 'Drag to reorder (top = best)', // hint for full ranking
    'ranking_ties_hint' => 'Drag to reorder. Items in the same group are tied.', // ranking with ties
    'available_options' => 'Available options', // truncated ranking - source list header
    'your_ranking' => 'Your ranking', // truncated ranking - target list header
    'drag_to_rank' => 'Drag options here to rank them', // truncated ranking placeholder

    // Grade question UI
    'select_placeholder' => 'Select...', // dropdown placeholder
    'grade_excellent' => 'Excellent',
    'grade_very_good' => 'Very Good',
    'grade_good' => 'Good',
    'grade_fair' => 'Fair',
    'grade_poor' => 'Poor',
    'grade_reject' => 'Reject',

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
    'share' => 'Share',
    'copy_link' => 'Copy Link',
    'copied' => 'Copied!', // toast message after copying

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
    'result_tied' => 'Tied',
    'result_no_winner' => 'No winner',
    'no_winner_yet' => 'No winner determined yet.', // shown when no responses

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels
    'voting_rule' => 'Voting Rule',
    'winners' => 'Winner(s)',
    'votes' => 'Votes',
    'option' => 'Option',
    'candidate' => 'Candidate',
    'count' => 'Count',
    'rules' => 'Rules',
    'size' => 'size',

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

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'No ranking determined yet.',
    'tied_rankings' => 'There are :count tied optimal rankings:',
    'ranking_number' => 'Ranking #:num',

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
