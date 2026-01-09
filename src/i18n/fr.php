<?php

/**
 * French translations for voter-facing strings.
 *
 * For voting rules: Only include rules that have well-known French translations.
 * Rules not listed here will fall back to the English name from the registry.
 * Key format: 'rule_{registry_key}' for name, 'rule_{registry_key}_desc' for description.
 */

return [
    // Form labels
    'submit' => 'Soumettre',
    'submit_vote' => 'Soumettre le vote',
    'required_field' => 'Ce champ est obligatoire.',
    'name_placeholder' => 'Votre nom',

    // Success messages
    'submit_success' => 'Votre reponse a ete enregistree.',
    'update_success' => 'Votre reponse a ete mise a jour.',

    // Error messages
    'vote_not_found' => 'Sondage introuvable.',
    'vote_closed' => 'Ce sondage est clos et n\'accepte plus de reponses.',
    'already_voted' => 'Vous avez deja soumis une reponse.',
    'validation_error' => 'Veuillez verifier vos reponses et reessayer.',

    // Question types
    'type_single_choice' => 'Choix unique',
    'type_approval' => 'Approbation',
    'type_ranking' => 'Classement',
    'type_star' => 'Notation par etoiles',
    'type_grade' => 'Mention',
    'type_yes_no_abstain' => 'Oui / Non / Abstention',
    'type_text_single' => 'Texte court',
    'type_text_multi' => 'Texte long',

    // Ranking
    'ranking_hint' => 'Glissez pour reordonner (haut = meilleur)',

    // Grades
    'grade_excellent' => 'Excellent',
    'grade_very_good' => 'Tres bien',
    'grade_good' => 'Bien',
    'grade_fair' => 'Assez bien',
    'grade_poor' => 'Passable',
    'grade_reject' => 'A rejeter',

    // Yes/No/Abstain
    'yes' => 'Oui',
    'no' => 'Non',
    'abstain' => 'Abstention',

    // Status
    'status_draft' => 'Brouillon',
    'status_open' => 'Ouvert',
    'status_closed' => 'Clos',

    // Results
    'results' => 'Resultats',
    'responses' => 'Reponses',
    'no_responses' => 'Aucune reponse pour le moment.',

    // Misc
    'edit_response' => 'Modifier votre reponse',
    'close_vote' => 'Clore le vote',
    'reopen_vote' => 'Rouvrir le vote',
    'delete_vote' => 'Supprimer le sondage',
    'share' => 'Partager',
    'copy_link' => 'Copier le lien',
    'copied' => 'Copie !',

    // =========================================================================
    // Voting Rules (only rules with well-known French translations)
    // =========================================================================
    // Rules not listed here fall back to English from the registry.
    // Key format: 'rule_{registry_key}' and 'rule_{registry_key}_desc'

    // VotingRulesRegistry
    'rule_majority_judgment' => 'Jugement Majoritaire', // French invention (Balinski & Laraki)
    'rule_majority_judgment_desc' => 'Le gagnant a la meilleure mention médiane',
    'rule_plurality' => 'Pluralité',
    'rule_plurality_desc' => 'Ne compte que les premiers choix',
    'rule_score_sum' => 'Vote par note (somme)',
    'rule_score_sum_desc' => 'Le score total le plus élevé gagne',
    'rule_score_mean' => 'Vote par note (moyenne)',
    'rule_score_mean_desc' => 'Le score moyen le plus élevé gagne',

    // MultiwinnerRulesRegistry
    'rule_av' => 'Vote par approbation',
    'rule_av_desc' => 'Les candidats approuvés par le plus de votants gagnent',
    'rule_equal_shares' => 'Méthode des parts égales',
    'rule_equal_shares_desc' => 'Partage équitable du budget entre les électeurs',

    // ApportionmentRulesRegistry
    'rule_hamilton' => 'Hamilton / Plus forts restes',
    'rule_hamilton_desc' => 'Méthode des plus forts restes',
    'rule_quota' => 'Méthode du quota',
    'rule_quota_desc' => 'Attribution proportionnelle par quota',

    // PBRulesRegistry (Participatory Budgeting)
    'rule_mes' => 'Méthode des parts égales',
    'rule_mes_desc' => 'Partage équitable du budget entre les électeurs',
    'rule_greedy' => 'Glouton (utilitariste)',
    'rule_greedy_desc' => 'Sélectionne les projets par ordre de votes décroissant',

    // Result labels
    'result_winner' => 'Gagnant',
    'result_tied' => 'Égalité',
    'result_no_winner' => 'Pas de gagnant',
];
