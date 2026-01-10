<?php

/**
 * Polish translations for voter-facing strings.
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
    'your_name' => 'Twoje imię', // label for voter name input field
    'name_placeholder' => 'Twoje imię', // placeholder text in name input

    // Submit buttons
    'submit' => 'Wyślij',
    'submit_vote' => 'Oddaj głos', // primary submit button
    'update_response' => 'Zaktualizuj odpowiedź', // when editing existing response
    'submit_disabled_preview' => 'Oddaj głos (Wyłączone w podglądzie)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Tryb podglądu – Tak będzie wyglądać Twoje głosowanie dla uczestników.',
    'poll_not_open' => 'To głosowanie nie jest jeszcze otwarte.',
    'voting_closed' => 'Głosowanie zamknięte', // banner heading
    'poll_no_longer_accepting' => 'To głosowanie nie przyjmuje już odpowiedzi.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Dziękujemy!', // heading after successful submission
    'response_recorded' => 'Twoja odpowiedź została zapisana.',
    'can_close_page' => 'Możesz teraz zamknąć tę stronę.',
    'already_submitted_can_update' => 'Już oddałeś głos. Możesz go zaktualizować poniżej.',

    // Validation
    'required_field' => 'To pole jest wymagane.',
    'validation_error' => 'Sprawdź swoje odpowiedzi i spróbuj ponownie.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Wyniki', // page title and breadcrumb
    'poll' => 'Głosowanie', // breadcrumb link text
    'live_results' => 'Wyniki na żywo', // badge shown when poll is still open
    'loading_results' => 'Ładowanie wyników...',
    'back_to_poll' => 'Wróć do głosowania', // link to return to voting form
    'no_responses' => 'Brak odpowiedzi.', // empty state message
    'computing_results' => 'Obliczanie wyników...', // shown while report is calculating
    'unknown_report_type' => 'Nieznany typ raportu: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count odpowiedź|:count odpowiedzi', // "5 odpowiedzi"
    'question_count' => ':count pytanie|:count pytania', // "3 pytania"
    'closed_on' => 'Zamknięte :date', // "Zamknięte 15 stycznia 2025"
    'created_on' => 'Utworzone :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Wybór pojedynczy',
    'type_approval' => 'Głosowanie aprobujące (Wielokrotny wybór)',
    'type_participatory_budgeting' => 'Budżet partycypacyjny',
    'type_distribution' => 'Rozkład punktów',
    'type_ranking' => 'Ranking',
    'type_ranking_truncated' => 'Ranking (Częściowy)',
    'type_ranking_with_ties' => 'Ranking (Z remisami)',
    'type_star' => 'Ocena gwiazdkowa',
    'type_grade' => 'Oceny',
    'type_yes_no_abstain' => 'Tak / Nie / Wstrzymuję się',
    'type_text_single' => 'Krótki tekst',
    'type_text_multi' => 'Długi tekst',
    'type_section_header' => 'Nagłówek sekcji',

    // Common question labels
    'untitled_question' => 'Pytanie bez tytułu',
    'section' => 'Sekcja', // default label for untitled section headers
    'unknown_question_type' => 'Nieznany typ pytania: :type',

    // Common question UI
    'other_option' => 'Inne:', // shown before text input for "other" option
    'please_specify' => 'Proszę określić...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Przeciągnij, aby zmienić kolejność (góra = najlepsze)', // hint for full ranking
    'ranking_ties_hint' => 'Przeciągnij, aby zmienić kolejność. Elementy w tej samej grupie są równorzędne.', // ranking with ties
    'available_options' => 'Dostępne opcje', // truncated ranking - source list header
    'your_ranking' => 'Twój ranking', // truncated ranking - target list header
    'drag_to_rank' => 'Przeciągnij opcje tutaj, aby je uszeregować', // truncated ranking placeholder
    'borda_score_note' => 'Uszeregowane według wyniku Borda (wyższy = lepszy)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Wybierz...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Tak',
    'no' => 'Nie',
    'abstain' => 'Wstrzymuję się',

    // Distribution question UI
    'remaining' => 'Pozostało:', // shows remaining points to allocate
    'points' => 'punktów', // unit label, e.g., "15 punktów"

    // Text input placeholders
    'short_answer' => 'Krótka odpowiedź',
    'long_answer' => 'Rozwinięta odpowiedź',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Szkic',
    'status_open' => 'Otwarte',
    'status_closed' => 'Zamknięte',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Zobacz wyniki', // link to results page
    'report_poll' => 'Zgłoś to głosowanie', // abuse report link
    'report_this_poll' => 'Zgłoś to głosowanie', // heading for report modal
    'report_guidelines' => 'Jeśli uważasz, że to głosowanie narusza nasze zasady, proszę nas o tym powiadomić.',
    'report_reason_spam' => 'Spam lub wprowadzająca w błąd treść',
    'report_reason_harassment' => 'Nękanie lub mowa nienawiści',
    'report_reason_doxxing' => 'Ujawnienie danych osobowych (doxxing)',
    'report_reason_illegal' => 'Nielegalna działalność lub treść',
    'report_reason_impersonation' => 'Podszywanie się lub oszustwo',
    'report_reason_phishing' => 'Złośliwe oprogramowanie lub phishing',
    'report_reason_copyright' => 'Naruszenie praw autorskich lub znaków towarowych',
    'report_reason_other' => 'Inne',
    'report_details' => 'Dodatkowe szczegóły',
    'report_optional' => '(opcjonalne)',
    'report_placeholder' => 'Proszę podać dodatkowy kontekst, który może pomóc w rozpatrzeniu tego zgłoszenia...',
    'cancel' => 'Anuluj',
    'submit_report' => 'Wyślij zgłoszenie',
    'select_report_reason' => 'Proszę wybrać powód zgłoszenia',
    'provide_report_details' => 'Proszę podać szczegóły zgłoszenia',
    'report_submitted' => 'Dziękujemy za zgłoszenie. Rozpatrzymy je wkrótce.',
    'share' => 'Udostępnij',
    'copy_link' => 'Skopiuj link',
    'copied' => 'Skopiowano!', // toast message after copying
    'undo' => 'Cofnij',
    'delete' => 'Usuń',
    'logout_failed' => 'Wylogowanie nie powiodło się',
    'create_poll' => 'Utwórz głosowanie',
    'about' => 'O nas',
    'dashboard' => 'Panel',
    'sysadmin' => 'Administrator',
    'log_out' => 'Wyloguj',
    'login' => 'Zaloguj',
    'privacy_policy' => 'Polityka prywatności',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Nie znaleziono głosowania.',
    'vote_closed' => 'To głosowanie jest zamknięte i nie przyjmuje już odpowiedzi.',
    'already_voted' => 'Już oddałeś głos.',
    'error_loading' => 'Błąd ładowania danych. Spróbuj ponownie.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Zwycięzca',
    'result_winner_by_rule' => 'Zwycięzca wg :rule', // e.g., "Zwycięzca wg Majority Judgment"
    'result_tied' => 'Remis',
    'result_no_winner' => 'Brak zwycięzcy',
    'no_winner_yet' => 'Nie ustalono jeszcze zwycięzcy.', // shown when no responses
    'rule_majority_judgment' => 'Majority Judgment',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Metoda głosowania',
    'winners' => 'Zwycięzca(y)',
    'votes' => 'Głosy',
    'option' => 'Opcja', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Kandydat', // column header in multi-winner election results
    'count' => 'Liczba', // column header showing how many voting rules selected a candidate
    'rules' => 'Metody', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'wielkość :size', // inline label with committee size, e.g., "(wielkość 5)"
    'no_results_available' => 'Brak wyników dla tego pytania.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Liczba głosów',
    'report_type_approval_winner' => 'Zwycięzca aprobujący',
    'report_type_median' => 'Mediana wyboru',
    'report_type_borda_scores' => 'Wyniki Borda',
    'report_type_pairwise_margins' => 'Marginesy parami',
    'report_type_voting_rule_winner' => 'Zwycięzca wg metody',
    'report_type_rank_aggregation' => 'Agregacja rankingów',
    'report_type_multiwinner' => 'Metoda wielozwycięska',
    'report_type_pb_winner' => 'Zwycięzca budżetu partycypacyjnego',
    'report_type_condorcet_winner' => 'Zwycięzca Condorceta',
    'report_type_apportionment_winner' => 'Podział mandatów',
    'report_type_yna_counts' => 'Podsumowanie Tak/Nie/Wstrzymuję się',
    'report_type_majority_judgment' => 'Majority Judgment',
    'report_type_multi_rule_comparison' => 'Porównanie metod',
    'report_type_multi_swf_comparison' => 'Porównanie agregacji rankingów',
    'report_type_multiwinner_multi_rule_comparison' => 'Porównanie metod wielozwycięskich',
    'report_type_apportionment_multi_rule_comparison' => 'Porównanie metod podziału',
    'report_type_response_matrix' => 'Macierz odpowiedzi',
    'report_type_raw_data_export' => 'Eksportuj surowe dane',
    'report_type_text_block' => 'Blok tekstowy',

    // Admin report actions
    'add_analysis' => 'Dodaj analizę', // button to add a new report
    'drag_to_reorder' => 'Przeciągnij, aby zmienić kolejność', // tooltip for report drag handle
    'delete_analysis' => 'Usuń analizę', // tooltip for delete button
    'analysis_deleted' => 'Analiza usunięta', // toast after deletion
    'make_public' => 'Ustaw jako publiczne', // tooltip to show report to voters
    'make_private' => 'Ustaw jako prywatne', // tooltip to hide report from voters
    'settings' => 'Ustawienia', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Nie wybrano ani nie obliczono żadnych metod.',
    'rules_count' => ':count/:total metod', // "3/5 metod"
    'winners_by_rule_count' => 'Zwycięzcy według liczby metod',
    'no_results_to_compare' => 'Brak wyników do porównania.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count głos|:count głosów', // "5 głosów"
    'seat_count' => ':count mandat|:count mandatów', // "3 mandaty"

    // Condorcet winner
    'condorcet_winner' => 'Zwycięzca Condorceta',
    'no_condorcet_winner' => 'Brak zwycięzcy Condorceta',
    'condorcet_explanation' => 'Wygrywa ze wszystkimi innymi opcjami w bezpośrednich porównaniach',
    'condorcet_cycle' => 'W preferencjach parami występuje cykl',

    // Median choice
    'median_choice' => 'Mediana',
    'no_median_yet' => 'Mediana nie została jeszcze ustalona.',
    'median_interval' => 'Przedział mediany', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Nie ustalono jeszcze zwycięskich projektów.',
    'total_budget' => 'Całkowity budżet',
    'spent' => 'Wydane',
    'winning_projects' => 'Zwycięskie projekty',
    'avg_voter_approves' => 'Średnio każdy głosujący popiera :count zwycięskich projektów.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Nie ustalono jeszcze zwycięskiego komitetu.',
    'committee_number' => 'Komitet nr :num',
    'committee_size' => 'Wielkość komitetu',
    'tied_committees' => 'Zwycięskie komitety ex aequo',
    'winning_committee' => 'Zwycięski komitet',
    'show_calculation_steps' => 'Pokaż kroki obliczeń',
    'hide_calculation_steps' => 'Ukryj kroki obliczeń',
    'tie_showing_first' => 'Remis (pokazano pierwszy zwycięski komitet)',
    'candidate_frequency' => 'Częstotliwość kandydatów',
    'candidate_frequency_desc' => 'Jak często każdy kandydat pojawia się w zwycięskim komitecie wśród :count porównywanych metod.',

    // Apportionment
    'total_seats' => 'Łączna liczba mandatów',
    'no_methods_selected' => 'Nie wybrano metod do porównania.',
    'apportionment_comparison_desc' => 'Porównanie :methods metod podziału dla :seats mandatów',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'Ranking nie został jeszcze ustalony.',
    'tied_rankings' => 'Istnieje :count optymalnych rankingów ex aequo:',
    'ranking_number' => 'Ranking nr :num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Twoja odpowiedź została zapisana.',
    'update_success' => 'Twoja odpowiedź została zaktualizowana.',
    'edit_response' => 'Edytuj swoją odpowiedź',
    'close_vote' => 'Zamknij głosowanie',
    'reopen_vote' => 'Otwórz ponownie głosowanie',
    'delete_vote' => 'Usuń głosowanie',
    'responses' => 'Odpowiedzi',
];
