<?php

/**
 * Dutch translations for voter-facing strings.
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
    'your_name' => 'Je naam', // label for voter name input field
    'name_placeholder' => 'Je naam', // placeholder text in name input

    // Submit buttons
    'submit' => 'Verzenden',
    'submit_vote' => 'Stem uitbrengen', // primary submit button
    'update_response' => 'Antwoord bijwerken', // when editing existing response
    'submit_disabled_preview' => 'Stem uitbrengen (Uitgeschakeld in voorbeeldmodus)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Voorbeeldmodus – Zo zal je stemming eruitzien voor deelnemers.',
    'poll_not_open' => 'Deze stemming is nog niet geopend voor deelname.',
    'voting_closed' => 'Stemming gesloten', // banner heading
    'poll_no_longer_accepting' => 'Deze stemming accepteert geen reacties meer.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Bedankt!', // heading after successful submission
    'response_recorded' => 'Je antwoord is opgeslagen.',
    'can_close_page' => 'Je kunt deze pagina nu sluiten.',
    'already_submitted_can_update' => 'Je hebt al een stem uitgebracht. Je kunt deze hieronder bijwerken.',

    // Validation
    'required_field' => 'Dit veld is verplicht.',
    'validation_error' => 'Controleer je antwoorden en probeer opnieuw.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Resultaten', // page title and breadcrumb
    'poll' => 'Stemming', // breadcrumb link text
    'live_results' => 'Live resultaten', // badge shown when poll is still open
    'loading_results' => 'Resultaten laden...',
    'back_to_poll' => 'Terug naar stemming', // link to return to voting form
    'no_responses' => 'Nog geen reacties.', // empty state message
    'computing_results' => 'Resultaten berekenen...', // shown while report is calculating
    'unknown_report_type' => 'Onbekend rapporttype: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count reactie|:count reacties', // "5 reacties"
    'question_count' => ':count vraag|:count vragen', // "3 vragen"
    'closed_on' => 'Gesloten op :date', // "Gesloten op 15 januari 2025"
    'created_on' => 'Aangemaakt op :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Enkele keuze',
    'type_approval' => 'Goedkeuringsstemming (Meerdere keuzes)',
    'type_participatory_budgeting' => 'Participatief budgetteren',
    'type_distribution' => 'Puntenverdeling',
    'type_ranking' => 'Rangschikking',
    'type_ranking_truncated' => 'Rangschikking (Gedeeltelijk)',
    'type_ranking_with_ties' => 'Rangschikking (Met gelijke stand)',
    'type_star' => 'Sterrenbeoordeling',
    'type_grade' => 'Cijfers',
    'type_yes_no_abstain' => 'Ja / Nee / Onthouding',
    'type_text_single' => 'Korte tekst',
    'type_text_multi' => 'Lange tekst',
    'type_section_header' => 'Sectiekop',

    // Common question labels
    'untitled_question' => 'Vraag zonder titel',
    'section' => 'Sectie', // default label for untitled section headers
    'unknown_question_type' => 'Onbekend vraagtype: :type',

    // Common question UI
    'other_option' => 'Anders:', // shown before text input for "other" option
    'please_specify' => 'Geef aan...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Sleep om te herschikken (boven = beste)', // hint for full ranking
    'ranking_ties_hint' => 'Sleep om te herschikken. Items in dezelfde groep staan gelijk.', // ranking with ties
    'available_options' => 'Beschikbare opties', // truncated ranking - source list header
    'your_ranking' => 'Jouw rangschikking', // truncated ranking - target list header
    'drag_to_rank' => 'Sleep opties hierheen om te rangschikken', // truncated ranking placeholder
    'borda_score_note' => 'Gerangschikt op Borda-score (hoger = beter)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Selecteer...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Ja',
    'no' => 'Nee',
    'abstain' => 'Onthouding',

    // Distribution question UI
    'remaining' => 'Resterend:', // shows remaining points to allocate
    'points' => 'punten', // unit label, e.g., "15 punten"

    // Text input placeholders
    'short_answer' => 'Kort antwoord',
    'long_answer' => 'Uitgebreid antwoord',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Concept',
    'status_open' => 'Open',
    'status_closed' => 'Gesloten',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Bekijk resultaten', // link to results page
    'report_poll' => 'Meld deze stemming', // abuse report link
    'report_this_poll' => 'Meld deze stemming', // heading for report modal
    'report_guidelines' => 'Als je denkt dat deze stemming onze richtlijnen schendt, laat het ons weten.',
    'report_reason_spam' => 'Spam of misleidende inhoud',
    'report_reason_harassment' => 'Intimidatie of haatzaaien',
    'report_reason_doxxing' => 'Blootstelling van persoonlijke informatie (doxxing)',
    'report_reason_illegal' => 'Illegale activiteit of inhoud',
    'report_reason_impersonation' => 'Identiteitsfraude of oplichting',
    'report_reason_phishing' => 'Malware of phishing-poging',
    'report_reason_copyright' => 'Schending van auteursrecht of handelsmerk',
    'report_reason_other' => 'Anders',
    'report_details' => 'Aanvullende details',
    'report_optional' => '(optioneel)',
    'report_placeholder' => 'Geef aanvullende context die ons kan helpen bij het beoordelen van deze melding...',
    'cancel' => 'Annuleren',
    'submit_report' => 'Melding verzenden',
    'select_report_reason' => 'Selecteer een reden voor de melding',
    'provide_report_details' => 'Geef details voor je melding',
    'report_submitted' => 'Bedankt voor je melding. We zullen deze binnenkort beoordelen.',
    'share' => 'Delen',
    'copy_link' => 'Link kopiëren',
    'copied' => 'Gekopieerd!', // toast message after copying
    'undo' => 'Ongedaan maken',
    'delete' => 'Verwijderen',
    'logout_failed' => 'Uitloggen mislukt',
    'create_poll' => 'Stemming aanmaken',
    'about' => 'Over',
    'dashboard' => 'Dashboard',
    'sysadmin' => 'Systeembeheer',
    'log_out' => 'Uitloggen',
    'login' => 'Inloggen',
    'privacy_policy' => 'Privacybeleid',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Stemming niet gevonden.',
    'vote_closed' => 'Deze stemming is gesloten en accepteert geen reacties meer.',
    'already_voted' => 'Je hebt al een stem uitgebracht.',
    'error_loading' => 'Fout bij laden van gegevens. Probeer opnieuw.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Winnaar',
    'result_winner_by_rule' => ':rule-winnaar', // e.g., "Majority Judgment-winnaar"
    'result_tied' => 'Gelijke stand',
    'result_no_winner' => 'Geen winnaar',
    'no_winner_yet' => 'Nog geen winnaar bepaald.', // shown when no responses
    'rule_majority_judgment' => 'Majority Judgment',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Stemmethode',
    'winners' => 'Winnaar(s)',
    'votes' => 'Stemmen',
    'option' => 'Optie', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Kandidaat', // column header in multi-winner election results
    'count' => 'Aantal', // column header showing how many voting rules selected a candidate
    'rules' => 'Methodes', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'grootte :size', // inline label with committee size, e.g., "(grootte 5)"
    'no_results_available' => 'Geen resultaten beschikbaar voor deze vraag.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Stemtelling',
    'report_type_approval_winner' => 'Goedkeuringswinnaar',
    'report_type_median' => 'Mediaan keuze',
    'report_type_borda_scores' => 'Borda-scores',
    'report_type_pairwise_margins' => 'Paarsgewijze marges',
    'report_type_voting_rule_winner' => 'Winnaar per methode',
    'report_type_rank_aggregation' => 'Rangschikkingsaggregatie',
    'report_type_multiwinner' => 'Multi-winnaar methode',
    'report_type_pb_winner' => 'Participatief budget winnaar',
    'report_type_condorcet_winner' => 'Condorcet-winnaar',
    'report_type_apportionment_winner' => 'Zetelverdeling',
    'report_type_yna_counts' => 'Ja/Nee/Onthouding telling',
    'report_type_majority_judgment' => 'Majority Judgment',
    'report_type_multi_rule_comparison' => 'Methodevergelijking',
    'report_type_multi_swf_comparison' => 'Rangschikkingsvergelijking',
    'report_type_multiwinner_multi_rule_comparison' => 'Multi-winnaar vergelijking',
    'report_type_apportionment_multi_rule_comparison' => 'Zetelverdeling vergelijking',
    'report_type_response_matrix' => 'Antwoordmatrix',
    'report_type_raw_data_export' => 'Ruwe data exporteren',
    'report_type_text_block' => 'Tekstblok',

    // Admin report actions
    'add_analysis' => 'Analyse toevoegen', // button to add a new report
    'drag_to_reorder' => 'Sleep om te herschikken', // tooltip for report drag handle
    'delete_analysis' => 'Analyse verwijderen', // tooltip for delete button
    'analysis_deleted' => 'Analyse verwijderd', // toast after deletion
    'make_public' => 'Openbaar maken', // tooltip to show report to voters
    'make_private' => 'Privé maken', // tooltip to hide report from voters
    'settings' => 'Instellingen', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Geen methodes geselecteerd of berekend.',
    'rules_count' => ':count/:total methodes', // "3/5 methodes"
    'winners_by_rule_count' => 'Winnaars per aantal methodes',
    'no_results_to_compare' => 'Geen resultaten om te vergelijken.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count stem|:count stemmen', // "5 stemmen"
    'seat_count' => ':count zetel|:count zetels', // "3 zetels"

    // Condorcet winner
    'condorcet_winner' => 'Condorcet-winnaar',
    'no_condorcet_winner' => 'Geen Condorcet-winnaar',
    'condorcet_explanation' => 'Wint van alle andere opties in directe vergelijkingen',
    'condorcet_cycle' => 'Er is een cyclus in de paarsgewijze voorkeuren',

    // Median choice
    'median_choice' => 'Mediaan',
    'no_median_yet' => 'Nog geen mediaan bepaald.',
    'median_interval' => 'Mediaan interval', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Nog geen winnende projecten bepaald.',
    'total_budget' => 'Totaal budget',
    'spent' => 'Besteed',
    'winning_projects' => 'Winnende projecten',
    'avg_voter_approves' => 'Gemiddeld keurt elke stemmer :count winnende projecten goed.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Nog geen winnende commissie bepaald.',
    'committee_number' => 'Commissie #:num',
    'committee_size' => 'Commissiegrootte',
    'tied_committees' => 'Gelijkstaande winnende commissies',
    'winning_committee' => 'Winnende commissie',
    'show_calculation_steps' => 'Toon berekeningsstappen',
    'hide_calculation_steps' => 'Verberg berekeningsstappen',
    'tie_showing_first' => 'Gelijke stand (eerste winnende commissie getoond)',
    'candidate_frequency' => 'Kandidaatfrequentie',
    'candidate_frequency_desc' => 'Hoe vaak elke kandidaat voorkomt in een winnende commissie over de :count vergeleken methodes.',

    // Apportionment
    'total_seats' => 'Totaal aantal zetels',
    'no_methods_selected' => 'Geen methodes geselecteerd voor vergelijking.',
    'apportionment_comparison_desc' => 'Vergelijking van :methods zetelverdeling methodes voor :seats zetels',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'Nog geen rangschikking bepaald.',
    'tied_rankings' => 'Er zijn :count gelijkstaande optimale rangschikkingen:',
    'ranking_number' => 'Rangschikking #:num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Je antwoord is opgeslagen.',
    'update_success' => 'Je antwoord is bijgewerkt.',
    'edit_response' => 'Bewerk je antwoord',
    'close_vote' => 'Stemming sluiten',
    'reopen_vote' => 'Stemming heropenen',
    'delete_vote' => 'Stemming verwijderen',
    'responses' => 'Reacties',
];
