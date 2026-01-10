<?php

/**
 * Italian translations for voter-facing strings.
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
    'your_name' => 'Il tuo nome', // label for voter name input field
    'name_placeholder' => 'Il tuo nome', // placeholder text in name input

    // Submit buttons
    'submit' => 'Invia',
    'submit_vote' => 'Invia voto', // primary submit button
    'update_response' => 'Aggiorna risposta', // when editing existing response
    'submit_disabled_preview' => 'Invia voto (Disattivato in anteprima)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Modalità anteprima – Ecco come apparirà la tua votazione ai partecipanti.',
    'poll_not_open' => 'Questa votazione non è ancora aperta.',
    'voting_closed' => 'Votazione chiusa', // banner heading
    'poll_no_longer_accepting' => 'Questa votazione non accetta più risposte.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Grazie!', // heading after successful submission
    'response_recorded' => 'La tua risposta è stata registrata.',
    'can_close_page' => 'Puoi chiudere questa pagina.',
    'already_submitted_can_update' => 'Hai già inviato una risposta. Puoi aggiornarla qui sotto.',

    // Validation
    'required_field' => 'Questo campo è obbligatorio.',
    'validation_error' => 'Controlla le tue risposte e riprova.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Risultati', // page title and breadcrumb
    'poll' => 'Votazione', // breadcrumb link text
    'live_results' => 'Risultati in tempo reale', // badge shown when poll is still open
    'loading_results' => 'Caricamento risultati...',
    'back_to_poll' => 'Torna alla votazione', // link to return to voting form
    'no_responses' => 'Ancora nessuna risposta.', // empty state message
    'computing_results' => 'Calcolo dei risultati...', // shown while report is calculating
    'unknown_report_type' => 'Tipo di report sconosciuto: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count risposta|:count risposte', // "5 risposte"
    'question_count' => ':count domanda|:count domande', // "3 domande"
    'closed_on' => 'Chiusa il :date', // "Chiusa il 15 gennaio 2025"
    'created_on' => 'Creata il :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Scelta singola',
    'type_approval' => 'Voto per approvazione (Scelta multipla)',
    'type_participatory_budgeting' => 'Bilancio partecipativo',
    'type_distribution' => 'Distribuzione punti',
    'type_ranking' => 'Classifica',
    'type_ranking_truncated' => 'Classifica (Parziale)',
    'type_ranking_with_ties' => 'Classifica (Con parità)',
    'type_star' => 'Valutazione a stelle',
    'type_grade' => 'Voti',
    'type_yes_no_abstain' => 'Sì / No / Astensione',
    'type_text_single' => 'Testo breve',
    'type_text_multi' => 'Testo lungo',
    'type_section_header' => 'Intestazione di sezione',

    // Common question labels
    'untitled_question' => 'Domanda senza titolo',
    'section' => 'Sezione', // default label for untitled section headers
    'unknown_question_type' => 'Tipo di domanda sconosciuto: :type',

    // Common question UI
    'other_option' => 'Altro:', // shown before text input for "other" option
    'please_specify' => 'Specifica...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Trascina per riordinare (in alto = migliore)', // hint for full ranking
    'ranking_ties_hint' => 'Trascina per riordinare. Gli elementi nello stesso gruppo sono a pari merito.', // ranking with ties
    'available_options' => 'Opzioni disponibili', // truncated ranking - source list header
    'your_ranking' => 'La tua classifica', // truncated ranking - target list header
    'drag_to_rank' => 'Trascina le opzioni qui per classificarle', // truncated ranking placeholder
    'borda_score_note' => 'Classificato per punteggio Borda (più alto = migliore)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Seleziona...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Sì',
    'no' => 'No',
    'abstain' => 'Astensione',

    // Distribution question UI
    'remaining' => 'Rimanente:', // shows remaining points to allocate
    'points' => 'punti', // unit label, e.g., "15 punti"

    // Text input placeholders
    'short_answer' => 'Risposta breve',
    'long_answer' => 'Risposta dettagliata',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Bozza',
    'status_open' => 'Aperta',
    'status_closed' => 'Chiusa',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Vedi risultati', // link to results page
    'report_poll' => 'Segnala questa votazione', // abuse report link
    'report_this_poll' => 'Segnala questa votazione', // heading for report modal
    'report_guidelines' => 'Se ritieni che questa votazione violi le nostre linee guida, segnalacelo.',
    'report_reason_spam' => 'Spam o contenuto ingannevole',
    'report_reason_harassment' => 'Molestie o incitamento all\'odio',
    'report_reason_doxxing' => 'Esposizione di informazioni personali (doxxing)',
    'report_reason_illegal' => 'Attività o contenuto illegale',
    'report_reason_impersonation' => 'Furto d\'identità o frode',
    'report_reason_phishing' => 'Malware o tentativo di phishing',
    'report_reason_copyright' => 'Violazione di copyright o marchio',
    'report_reason_other' => 'Altro',
    'report_details' => 'Dettagli aggiuntivi',
    'report_optional' => '(opzionale)',
    'report_placeholder' => 'Fornisci contesto aggiuntivo che possa aiutarci a esaminare questa segnalazione...',
    'cancel' => 'Annulla',
    'submit_report' => 'Invia segnalazione',
    'select_report_reason' => 'Seleziona un motivo per la segnalazione',
    'provide_report_details' => 'Fornisci dettagli per la tua segnalazione',
    'report_submitted' => 'Grazie per la tua segnalazione. La esamineremo a breve.',
    'share' => 'Condividi',
    'copy_link' => 'Copia link',
    'copied' => 'Copiato!', // toast message after copying
    'undo' => 'Annulla',
    'delete' => 'Elimina',
    'logout_failed' => 'Disconnessione fallita',
    'create_poll' => 'Crea votazione',
    'about' => 'Informazioni',
    'dashboard' => 'Pannello',
    'sysadmin' => 'Amministrazione',
    'log_out' => 'Esci',
    'login' => 'Accedi',
    'privacy_policy' => 'Informativa sulla privacy',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Votazione non trovata.',
    'vote_closed' => 'Questa votazione è chiusa e non accetta più risposte.',
    'already_voted' => 'Hai già inviato una risposta.',
    'error_loading' => 'Errore nel caricamento dei dati. Riprova.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Vincitore',
    'result_winner_by_rule' => 'Vincitore per :rule', // e.g., "Vincitore per Majority Judgment"
    'result_tied' => 'Parità',
    'result_no_winner' => 'Nessun vincitore',
    'no_winner_yet' => 'Nessun vincitore determinato ancora.', // shown when no responses
    'rule_majority_judgment' => 'Giudizio maggioritario',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Metodo di voto',
    'winners' => 'Vincitore/i',
    'votes' => 'Voti',
    'option' => 'Opzione', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Candidato', // column header in multi-winner election results
    'count' => 'Conteggio', // column header showing how many voting rules selected a candidate
    'rules' => 'Metodi', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'dimensione :size', // inline label with committee size, e.g., "(dimensione 5)"
    'no_results_available' => 'Nessun risultato disponibile per questa domanda.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Conteggio voti',
    'report_type_approval_winner' => 'Vincitore per approvazione',
    'report_type_median' => 'Scelta mediana',
    'report_type_borda_scores' => 'Punteggi Borda',
    'report_type_pairwise_margins' => 'Margini a coppie',
    'report_type_voting_rule_winner' => 'Vincitore per metodo',
    'report_type_rank_aggregation' => 'Aggregazione classifiche',
    'report_type_multiwinner' => 'Metodo multi-vincitore',
    'report_type_pb_winner' => 'Vincitore bilancio partecipativo',
    'report_type_condorcet_winner' => 'Vincitore di Condorcet',
    'report_type_apportionment_winner' => 'Assegnazione seggi',
    'report_type_yna_counts' => 'Conteggio Sì/No/Astensione',
    'report_type_majority_judgment' => 'Giudizio maggioritario',
    'report_type_multi_rule_comparison' => 'Confronto metodi',
    'report_type_multi_swf_comparison' => 'Confronto aggregazioni',
    'report_type_multiwinner_multi_rule_comparison' => 'Confronto multi-vincitore',
    'report_type_apportionment_multi_rule_comparison' => 'Confronto assegnazioni',
    'report_type_response_matrix' => 'Matrice risposte',
    'report_type_raw_data_export' => 'Esporta dati grezzi',
    'report_type_text_block' => 'Blocco di testo',

    // Admin report actions
    'add_analysis' => 'Aggiungi analisi', // button to add a new report
    'drag_to_reorder' => 'Trascina per riordinare', // tooltip for report drag handle
    'delete_analysis' => 'Elimina analisi', // tooltip for delete button
    'analysis_deleted' => 'Analisi eliminata', // toast after deletion
    'make_public' => 'Rendi pubblico', // tooltip to show report to voters
    'make_private' => 'Rendi privato', // tooltip to hide report from voters
    'settings' => 'Impostazioni', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Nessun metodo selezionato o calcolato.',
    'rules_count' => ':count/:total metodi', // "3/5 metodi"
    'winners_by_rule_count' => 'Vincitori per numero di metodi',
    'no_results_to_compare' => 'Nessun risultato da confrontare.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count voto|:count voti', // "5 voti"
    'seat_count' => ':count seggio|:count seggi', // "3 seggi"

    // Condorcet winner
    'condorcet_winner' => 'Vincitore di Condorcet',
    'no_condorcet_winner' => 'Nessun vincitore di Condorcet',
    'condorcet_explanation' => 'Batte tutte le altre opzioni nei confronti diretti',
    'condorcet_cycle' => 'C\'è un ciclo nelle preferenze a coppie',

    // Median choice
    'median_choice' => 'Mediana',
    'no_median_yet' => 'La mediana non è ancora stata determinata.',
    'median_interval' => 'Intervallo della mediana', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Nessun progetto vincitore determinato ancora.',
    'total_budget' => 'Budget totale',
    'spent' => 'Speso',
    'winning_projects' => 'Progetti vincitori',
    'avg_voter_approves' => 'In media, ogni votante approva :count progetti vincitori.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Nessun comitato vincitore determinato ancora.',
    'committee_number' => 'Comitato n. :num',
    'committee_size' => 'Dimensione comitato',
    'tied_committees' => 'Comitati vincitori in parità',
    'winning_committee' => 'Comitato vincitore',
    'show_calculation_steps' => 'Mostra passaggi di calcolo',
    'hide_calculation_steps' => 'Nascondi passaggi di calcolo',
    'tie_showing_first' => 'Parità (mostrato il primo comitato vincitore)',
    'candidate_frequency' => 'Frequenza candidati',
    'candidate_frequency_desc' => 'Quanto spesso ogni candidato appare in un comitato vincitore tra i :count metodi confrontati.',

    // Apportionment
    'total_seats' => 'Seggi totali',
    'no_methods_selected' => 'Nessun metodo selezionato per il confronto.',
    'apportionment_comparison_desc' => 'Confronto di :methods metodi di assegnazione per :seats seggi',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'La classifica non è ancora stata determinata.',
    'tied_rankings' => 'Ci sono :count classifiche ottimali in parità:',
    'ranking_number' => 'Classifica n. :num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'La tua risposta è stata registrata.',
    'update_success' => 'La tua risposta è stata aggiornata.',
    'edit_response' => 'Modifica la tua risposta',
    'close_vote' => 'Chiudi votazione',
    'reopen_vote' => 'Riapri votazione',
    'delete_vote' => 'Elimina votazione',
    'responses' => 'Risposte',
];
