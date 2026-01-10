<?php

/**
 * French translations for voter-facing strings.
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
    'your_name' => 'Votre nom', // label for voter name input field
    'name_placeholder' => 'Votre nom', // placeholder text in name input

    // Submit buttons
    'submit' => 'Envoyer',
    'submit_vote' => 'Soumettre le vote', // primary submit button
    'update_response' => 'Modifier le vote', // when editing existing response
    'submit_disabled_preview' => 'Soumettre le vote (Désactivé en aperçu)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Mode aperçu – Voici comment votre vote apparaîtra aux participants.',
    'poll_not_open' => 'Ce vote n\'est pas encore ouvert aux participations.',
    'voting_closed' => 'Vote clôturé', // banner heading
    'poll_no_longer_accepting' => 'Ce vote n\'accepte plus de réponses.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Merci !', // heading after successful submission
    'response_recorded' => 'Votre vote a été enregistré.',
    'can_close_page' => 'Vous pouvez maintenant fermer cette page.',
    'already_submitted_can_update' => 'Vous avez déjà soumis un vote. Vous pouvez le modifier ci-dessous.',

    // Validation
    'required_field' => 'Ce champ est obligatoire.',
    'validation_error' => 'Veuillez vérifier vos réponses et réessayer.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Résultats', // page title and breadcrumb
    'poll' => 'Vote', // breadcrumb link text
    'live_results' => 'Résultats en direct', // badge shown when poll is still open
    'loading_results' => 'Chargement des résultats...',
    'back_to_poll' => 'Retour au vote', // link to return to voting form
    'no_responses' => 'Aucun vote pour le moment.', // empty state message
    'computing_results' => 'Calcul des résultats...', // shown while report is calculating
    'unknown_report_type' => 'Type de rapport inconnu : :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count vote|:count votes', // "5 votes"
    'question_count' => ':count question|:count questions', // "3 questions"
    'closed_on' => 'Clôturé le :date', // "Clôturé le 15 janvier 2025"
    'created_on' => 'Créé le :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Choix unique',
    'type_approval' => 'Vote par approbation (Choix multiples)',
    'type_participatory_budgeting' => 'Budget participatif',
    'type_distribution' => 'Répartition de points',
    'type_ranking' => 'Classement',
    'type_ranking_truncated' => 'Classement (Partiel)',
    'type_ranking_with_ties' => 'Classement (Avec égalités)',
    'type_star' => 'Évaluation par étoiles',
    'type_grade' => 'Attribution de mentions',
    'type_yes_no_abstain' => 'Oui / Non / Abstention',
    'type_text_single' => 'Texte court',
    'type_text_multi' => 'Texte long',
    'type_section_header' => 'En-tête de section',

    // Common question labels
    'untitled_question' => 'Question sans titre',
    'section' => 'Section', // default label for untitled section headers
    'unknown_question_type' => 'Type de question inconnu : :type',

    // Common question UI
    'other_option' => 'Autre :', // shown before text input for "other" option
    'please_specify' => 'Veuillez préciser...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Glissez pour réordonner (haut = meilleur)', // hint for full ranking
    'ranking_ties_hint' => 'Glissez pour réordonner. Les éléments dans le même groupe sont à égalité.', // ranking with ties
    'available_options' => 'Options disponibles', // truncated ranking - source list header
    'your_ranking' => 'Votre classement', // truncated ranking - target list header
    'drag_to_rank' => 'Glissez les options ici pour les classer', // truncated ranking placeholder
    'borda_score_note' => 'Classé par score de Borda (plus élevé = meilleur)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Sélectionner...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Oui',
    'no' => 'Non',
    'abstain' => 'Abstention',

    // Distribution question UI
    'remaining' => 'Restant :', // shows remaining points to allocate
    'points' => 'points', // unit label, e.g., "15 points"

    // Text input placeholders
    'short_answer' => 'Réponse courte',
    'long_answer' => 'Réponse détaillée',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Brouillon',
    'status_open' => 'Ouvert',
    'status_closed' => 'Clôturé',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Voir les résultats', // link to results page
    'report_poll' => 'Signaler ce vote', // abuse report link
    'report_this_poll' => 'Signaler ce vote', // heading for report modal
    'report_guidelines' => 'Si vous pensez que ce vote enfreint nos règles, veuillez nous le signaler.',
    'report_reason_spam' => 'Spam ou contenu trompeur',
    'report_reason_harassment' => 'Harcèlement ou discours haineux',
    'report_reason_doxxing' => 'Divulgation d\'informations personnelles (doxxing)',
    'report_reason_illegal' => 'Activité ou contenu illégal',
    'report_reason_impersonation' => 'Usurpation d\'identité ou fraude',
    'report_reason_phishing' => 'Logiciel malveillant ou tentative de phishing',
    'report_reason_copyright' => 'Violation de droits d\'auteur ou de marque',
    'report_reason_other' => 'Autre',
    'report_details' => 'Détails supplémentaires',
    'report_optional' => '(facultatif)',
    'report_placeholder' => 'Veuillez fournir tout contexte supplémentaire qui pourrait nous aider à examiner ce signalement...',
    'cancel' => 'Annuler',
    'submit_report' => 'Envoyer le signalement',
    'select_report_reason' => 'Veuillez sélectionner une raison pour le signalement',
    'provide_report_details' => 'Veuillez fournir des détails pour votre signalement',
    'report_submitted' => 'Merci pour votre signalement. Nous l\'examinerons sous peu.',
    'share' => 'Partager',
    'copy_link' => 'Copier le lien',
    'copied' => 'Copié !', // toast message after copying
    'undo' => 'Annuler',
    'delete' => 'Supprimer',
    'logout_failed' => 'Échec de la déconnexion',
    'create_poll' => 'Créer un vote',
    'about' => 'À propos',
    'dashboard' => 'Tableau de bord',
    'sysadmin' => 'Administration',
    'log_out' => 'Se déconnecter',
    'login' => 'Se connecter',
    'privacy_policy' => 'Politique de confidentialité',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Vote introuvable.',
    'vote_closed' => 'Ce vote est clôturé et n\'accepte plus de réponses.',
    'already_voted' => 'Vous avez déjà soumis un vote.',
    'error_loading' => 'Erreur lors du chargement des données. Veuillez réessayer.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Gagnant',
    'result_winner_by_rule' => 'Gagnant par :rule', // e.g., "Gagnant par Jugement majoritaire"
    'result_tied' => 'Égalité',
    'result_no_winner' => 'Pas de gagnant',
    'no_winner_yet' => 'Aucun gagnant déterminé pour le moment.', // shown when no responses
    'rule_majority_judgment' => 'Jugement majoritaire',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Méthode de vote',
    'winners' => 'Gagnant(s)',
    'votes' => 'Votes',
    'option' => 'Option', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Candidat', // column header in multi-winner election results
    'count' => 'Nombre', // column header showing how many voting rules selected a candidate
    'rules' => 'Méthodes', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'taille :size', // inline label with committee size, e.g., "(taille 5)"
    'no_results_available' => 'Aucun résultat disponible pour cette question.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Décompte des voix',
    'report_type_approval_winner' => 'Gagnant par approbation',
    'report_type_borda_scores' => 'Scores de Borda',
    'report_type_pairwise_margins' => 'Marges par paires',
    'report_type_voting_rule_winner' => 'Gagnant par méthode de vote',
    'report_type_rank_aggregation' => 'Agrégation de classements',
    'report_type_multiwinner' => 'Méthode multi-gagnants',
    'report_type_pb_winner' => 'Gagnant budget participatif',
    'report_type_condorcet_winner' => 'Gagnant de Condorcet',
    'report_type_apportionment_winner' => 'Répartition des sièges',
    'report_type_yna_counts' => 'Décompte Oui/Non/Abstention',
    'report_type_majority_judgment' => 'Jugement majoritaire',
    'report_type_multi_rule_comparison' => 'Comparaison de méthodes',
    'report_type_multi_swf_comparison' => 'Comparaison d\'agrégations',
    'report_type_multiwinner_multi_rule_comparison' => 'Comparaison multi-gagnants',
    'report_type_apportionment_multi_rule_comparison' => 'Comparaison de répartitions',
    'report_type_response_matrix' => 'Matrice des réponses',
    'report_type_raw_data_export' => 'Exporter les données brutes',
    'report_type_text_block' => 'Bloc de texte',

    // Admin report actions
    'add_analysis' => 'Ajouter une analyse', // button to add a new report
    'drag_to_reorder' => 'Glisser pour réordonner', // tooltip for report drag handle
    'delete_analysis' => 'Supprimer l\'analyse', // tooltip for delete button
    'analysis_deleted' => 'Analyse supprimée', // toast after deletion
    'make_public' => 'Rendre public', // tooltip to show report to voters
    'make_private' => 'Rendre privé', // tooltip to hide report from voters
    'settings' => 'Paramètres', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Aucune méthode sélectionnée ou calculée.',
    'rules_count' => ':count/:total méthodes', // "3/5 méthodes"
    'winners_by_rule_count' => 'Gagnants par nombre de méthodes',
    'no_results_to_compare' => 'Aucun résultat à comparer.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count vote|:count votes', // "5 votes"
    'seat_count' => ':count siège|:count sièges', // "3 sièges"

    // Condorcet winner
    'condorcet_winner' => 'Gagnant de Condorcet',
    'no_condorcet_winner' => 'Pas de gagnant de Condorcet',
    'condorcet_explanation' => 'Bat toutes les autres options en duel',
    'condorcet_cycle' => 'Il y a un cycle dans les préférences par paires',

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Aucun projet gagnant déterminé pour le moment.',
    'total_budget' => 'Budget total',
    'spent' => 'Dépensé',
    'winning_projects' => 'Projets gagnants',
    'avg_voter_approves' => 'En moyenne, chaque votant approuve :count projets gagnants.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Aucun comité gagnant déterminé pour le moment.',
    'committee_number' => 'Comité n°:num',
    'committee_size' => 'Taille du comité',
    'tied_committees' => 'Comités gagnants à égalité',
    'winning_committee' => 'Comité gagnant',
    'show_calculation_steps' => 'Afficher les étapes de calcul',
    'hide_calculation_steps' => 'Masquer les étapes de calcul',
    'tie_showing_first' => 'Égalité (affichage du premier comité gagnant)',
    'candidate_frequency' => 'Fréquence des candidats',
    'candidate_frequency_desc' => 'Fréquence d\'apparition de chaque candidat dans un comité gagnant parmi les :count méthodes comparées.',

    // Apportionment
    'total_seats' => 'Total des sièges',
    'no_methods_selected' => 'Aucune méthode sélectionnée pour la comparaison.',
    'apportionment_comparison_desc' => 'Comparaison de :methods méthodes de répartition pour :seats sièges',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'Aucun classement déterminé pour le moment.',
    'tied_rankings' => 'Il y a :count classements optimaux à égalité :',
    'ranking_number' => 'Classement n°:num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Votre vote a été enregistré.',
    'update_success' => 'Votre vote a été mis à jour.',
    'edit_response' => 'Modifier votre vote',
    'close_vote' => 'Clôturer le vote',
    'reopen_vote' => 'Rouvrir le vote',
    'delete_vote' => 'Supprimer le vote',
    'responses' => 'Votes',
];
