<?php

/**
 * German translations for voter-facing strings.
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
    'your_name' => 'Dein Name', // label for voter name input field
    'name_placeholder' => 'Dein Name', // placeholder text in name input

    // Submit buttons
    'submit' => 'Absenden',
    'submit_vote' => 'Stimme abgeben', // primary submit button
    'update_response' => 'Stimme aktualisieren', // when editing existing response
    'submit_disabled_preview' => 'Stimme abgeben (In der Vorschau deaktiviert)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Vorschaumodus – So wird deine Abstimmung den Teilnehmern angezeigt.',
    'poll_not_open' => 'Diese Abstimmung ist noch nicht für Teilnahmen geöffnet.',
    'voting_closed' => 'Abstimmung geschlossen', // banner heading
    'poll_no_longer_accepting' => 'Diese Abstimmung nimmt keine Stimmen mehr an.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Vielen Dank!', // heading after successful submission
    'response_recorded' => 'Deine Stimme wurde erfasst.',
    'can_close_page' => 'Du kannst diese Seite jetzt schließen.',
    'already_submitted_can_update' => 'Du hast bereits eine Stimme abgegeben. Du kannst sie unten aktualisieren.',

    // Validation
    'required_field' => 'Dieses Feld ist erforderlich.',
    'validation_error' => 'Bitte überprüfe deine Angaben und versuche es erneut.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Ergebnisse', // page title and breadcrumb
    'poll' => 'Abstimmung', // breadcrumb link text
    'live_results' => 'Live-Ergebnisse', // badge shown when poll is still open
    'loading_results' => 'Ergebnisse werden geladen...',
    'back_to_poll' => 'Zurück zur Abstimmung', // link to return to voting form
    'no_responses' => 'Noch keine Stimmen.', // empty state message
    'computing_results' => 'Ergebnisse werden berechnet...', // shown while report is calculating
    'unknown_report_type' => 'Unbekannter Berichtstyp: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count Stimme|:count Stimmen', // "5 Stimmen"
    'question_count' => ':count Frage|:count Fragen', // "3 Fragen"
    'closed_on' => 'Geschlossen am :date', // "Geschlossen am 15. Januar 2025"
    'created_on' => 'Erstellt am :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Einzelauswahl',
    'type_approval' => 'Zustimmungswahl (Mehrfachauswahl)',
    'type_participatory_budgeting' => 'Budgetabstimmung',
    'type_distribution' => 'Punkteverteilung',
    'type_ranking' => 'Rangfolge',
    'type_ranking_truncated' => 'Rangfolge (Teilweise)',
    'type_ranking_with_ties' => 'Rangfolge (mit Gleichständen)',
    'type_star' => 'Sternebewertung',
    'type_grade' => 'Notenvergabe',
    'type_yes_no_abstain' => 'Ja / Nein / Enthaltung',
    'type_text_single' => 'Kurztext',
    'type_text_multi' => 'Langtext',
    'type_section_header' => 'Abschnittsüberschrift',

    // Common question labels
    'untitled_question' => 'Frage ohne Titel',
    'section' => 'Abschnitt', // default label for untitled section headers
    'unknown_question_type' => 'Unbekannter Fragetyp: :type',

    // Common question UI
    'other_option' => 'Sonstiges:', // shown before text input for "other" option
    'please_specify' => 'Bitte angeben...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Ziehe zum Sortieren (oben = am besten)', // hint for full ranking
    'ranking_ties_hint' => 'Ziehe zum Sortieren. Einträge in der gleichen Gruppe sind gleichwertig.', // ranking with ties
    'available_options' => 'Verfügbare Optionen', // truncated ranking - source list header
    'your_ranking' => 'Deine Rangfolge', // truncated ranking - target list header
    'drag_to_rank' => 'Ziehe Optionen hierher, um sie zu ranken', // truncated ranking placeholder
    'borda_score_note' => 'Nach Borda-Punkten sortiert (höher = besser)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Auswählen...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Ja',
    'no' => 'Nein',
    'abstain' => 'Enthaltung',

    // Distribution question UI
    'remaining' => 'Verbleibend:', // shows remaining points to allocate
    'points' => 'Punkte', // unit label, e.g., "15 Punkte"

    // Text input placeholders
    'short_answer' => 'Kurzantwort',
    'long_answer' => 'Ausführliche Antwort',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Entwurf',
    'status_open' => 'Offen',
    'status_closed' => 'Geschlossen',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Ergebnisse ansehen', // link to results page
    'report_poll' => 'Abstimmung melden', // abuse report link
    'report_this_poll' => 'Diese Abstimmung melden', // heading for report modal
    'report_guidelines' => 'Wenn du glaubst, dass diese Abstimmung gegen unsere Richtlinien verstößt, teile uns das bitte mit.',
    'report_reason_spam' => 'Spam oder irreführende Inhalte',
    'report_reason_harassment' => 'Belästigung oder Hassrede',
    'report_reason_doxxing' => 'Offenlegung persönlicher Daten (Doxxing)',
    'report_reason_illegal' => 'Illegale Aktivitäten oder Inhalte',
    'report_reason_impersonation' => 'Identitätsdiebstahl oder Betrug',
    'report_reason_phishing' => 'Schadsoftware oder Phishing-Versuch',
    'report_reason_copyright' => 'Urheberrechts- oder Markenrechtsverletzung',
    'report_reason_other' => 'Sonstiges',
    'report_details' => 'Weitere Details',
    'report_optional' => '(optional)',
    'report_placeholder' => 'Bitte gib zusätzlichen Kontext an, der uns bei der Prüfung dieser Meldung helfen könnte...',
    'cancel' => 'Abbrechen',
    'submit_report' => 'Meldung absenden',
    'select_report_reason' => 'Bitte wähle einen Grund für die Meldung',
    'provide_report_details' => 'Bitte gib Details zu deiner Meldung an',
    'report_submitted' => 'Vielen Dank für deine Meldung. Wir werden sie in Kürze prüfen.',
    'share' => 'Teilen',
    'copy_link' => 'Link kopieren',
    'copied' => 'Kopiert!', // toast message after copying
    'undo' => 'Rückgängig',
    'delete' => 'Löschen',
    'logout_failed' => 'Abmeldung fehlgeschlagen',
    'create_poll' => 'Abstimmung erstellen',
    'about' => 'Über',
    'dashboard' => 'Dashboard',
    'sysadmin' => 'Sysadmin',
    'log_out' => 'Abmelden',
    'login' => 'Anmelden',
    'privacy_policy' => 'Datenschutzrichtlinie',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Abstimmung nicht gefunden.',
    'vote_closed' => 'Diese Abstimmung ist geschlossen und nimmt keine Stimmen mehr an.',
    'already_voted' => 'Du hast bereits eine Stimme abgegeben.',
    'error_loading' => 'Fehler beim Laden der Daten. Bitte versuche es erneut.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Gewinner',
    'result_winner_by_rule' => ':rule-Gewinner', // e.g., "Majority Judgment-Gewinner"
    'result_tied' => 'Gleichstand',
    'result_no_winner' => 'Kein Gewinner',
    'no_winner_yet' => 'Noch kein Gewinner ermittelt.', // shown when no responses
    'rule_majority_judgment' => 'Majority Judgment',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Wahlregel',
    'winners' => 'Gewinner',
    'votes' => 'Stimmen',
    'option' => 'Option', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Kandidat', // column header in multi-winner election results
    'count' => 'Anzahl', // column header showing how many voting rules selected a candidate
    'rules' => 'Regeln', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'Größe :size', // inline label with committee size, e.g., "(Größe 5)"
    'no_results_available' => 'Keine Ergebnisse für diese Frage verfügbar.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Stimmenauszählung',
    'report_type_approval_winner' => 'Approval-Gewinner',
    'report_type_borda_scores' => 'Borda-Punkte',
    'report_type_pairwise_margins' => 'Paarweise Vergleiche',
    'report_type_voting_rule_winner' => 'Gewinner nach Wahlregel',
    'report_type_rank_aggregation' => 'Rangaggregation',
    'report_type_multiwinner' => 'Mehrgewinner-Wahlregel',
    'report_type_pb_winner' => 'Budgetabstimmung-Gewinner',
    'report_type_condorcet_winner' => 'Condorcet-Gewinner',
    'report_type_apportionment_winner' => 'Sitzzuteilung',
    'report_type_yna_counts' => 'Ja/Nein/Enthaltung-Auszählung',
    'report_type_majority_judgment' => 'Majority Judgment',
    'report_type_multi_rule_comparison' => 'Regelvergleich',
    'report_type_multi_swf_comparison' => 'Rangaggregationsvergleich',
    'report_type_multiwinner_multi_rule_comparison' => 'Mehrgewinner-Regelvergleich',
    'report_type_apportionment_multi_rule_comparison' => 'Sitzzuteilungsvergleich',
    'report_type_response_matrix' => 'Antwortmatrix',
    'report_type_raw_data_export' => 'Rohdaten exportieren',
    'report_type_text_block' => 'Textblock',

    // Admin report actions
    'add_analysis' => 'Analyse hinzufügen', // button to add a new report
    'drag_to_reorder' => 'Ziehen zum Neuordnen', // tooltip for report drag handle
    'delete_analysis' => 'Analyse löschen', // tooltip for delete button
    'analysis_deleted' => 'Analyse gelöscht', // toast after deletion
    'make_public' => 'Öffentlich machen', // tooltip to show report to voters
    'make_private' => 'Privat machen', // tooltip to hide report from voters
    'settings' => 'Einstellungen', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Keine Regeln ausgewählt oder berechnet.',
    'rules_count' => ':count/:total Regeln', // "3/5 Regeln"
    'winners_by_rule_count' => 'Gewinner nach Regelanzahl',
    'no_results_to_compare' => 'Keine Ergebnisse zum Vergleichen.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count Stimme|:count Stimmen', // "5 Stimmen"
    'seat_count' => ':count Sitz|:count Sitze', // "3 Sitze"

    // Condorcet winner
    'condorcet_winner' => 'Condorcet-Gewinner',
    'no_condorcet_winner' => 'Kein Condorcet-Gewinner',
    'condorcet_explanation' => 'Gewinnt alle Einzelvergleiche gegen andere Optionen',
    'condorcet_cycle' => 'Es gibt einen Zyklus in den paarweisen Präferenzen',

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Noch keine Gewinnerprojekte ermittelt.',
    'total_budget' => 'Gesamtbudget',
    'spent' => 'Ausgegeben',
    'winning_projects' => 'Gewinnerprojekte',
    'avg_voter_approves' => 'Im Durchschnitt stimmt jeder Wähler :count Gewinnerprojekten zu.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Noch kein Gewinnerkomitee ermittelt.',
    'committee_number' => 'Komitee #:num',
    'committee_size' => 'Komiteegröße',
    'tied_committees' => 'Gleichwertige Gewinnerkomitees',
    'winning_committee' => 'Gewinnerkomitee',
    'show_calculation_steps' => 'Berechnungsschritte anzeigen',
    'hide_calculation_steps' => 'Berechnungsschritte ausblenden',
    'tie_showing_first' => 'Gleichstand (erstes Gewinnerkomitee wird angezeigt)',
    'candidate_frequency' => 'Kandidatenhäufigkeit',
    'candidate_frequency_desc' => 'Wie oft jeder Kandidat in einem Gewinnerkomitee über die :count verglichenen Regeln vorkommt.',

    // Apportionment
    'total_seats' => 'Sitze gesamt',
    'no_methods_selected' => 'Keine Methoden zum Vergleich ausgewählt.',
    'apportionment_comparison_desc' => 'Vergleich von :methods Sitzzuteilungsmethoden für :seats Sitze',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'Noch keine Rangfolge ermittelt.',
    'tied_rankings' => 'Es gibt :count gleichwertige optimale Rangfolgen:',
    'ranking_number' => 'Rangfolge #:num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Deine Stimme wurde erfasst.',
    'update_success' => 'Deine Stimme wurde aktualisiert.',
    'edit_response' => 'Deine Stimme bearbeiten',
    'close_vote' => 'Abstimmung schließen',
    'reopen_vote' => 'Abstimmung wieder öffnen',
    'delete_vote' => 'Abstimmung löschen',
    'responses' => 'Stimmen',
];
