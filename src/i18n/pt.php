<?php

/**
 * Portuguese translations for voter-facing strings.
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
    'your_name' => 'Seu nome', // label for voter name input field
    'name_placeholder' => 'Seu nome', // placeholder text in name input

    // Submit buttons
    'submit' => 'Enviar',
    'submit_vote' => 'Enviar voto', // primary submit button
    'update_response' => 'Atualizar resposta', // when editing existing response
    'submit_disabled_preview' => 'Enviar voto (Desativado na pré-visualização)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Modo de pré-visualização – É assim que sua votação aparecerá para os participantes.',
    'poll_not_open' => 'Esta votação ainda não está aberta.',
    'voting_closed' => 'Votação encerrada', // banner heading
    'poll_no_longer_accepting' => 'Esta votação não está mais aceitando respostas.', // banner body

    // Success messages (after vote submission)
    'thank_you' => 'Obrigado!', // heading after successful submission
    'response_recorded' => 'Sua resposta foi registrada.',
    'can_close_page' => 'Você pode fechar esta página.',
    'already_submitted_can_update' => 'Você já enviou uma resposta. Pode atualizá-la abaixo.',

    // Validation
    'required_field' => 'Este campo é obrigatório.',
    'validation_error' => 'Por favor, verifique suas respostas e tente novamente.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Resultados', // page title and breadcrumb
    'poll' => 'Votação', // breadcrumb link text
    'live_results' => 'Resultados ao vivo', // badge shown when poll is still open
    'loading_results' => 'Carregando resultados...',
    'back_to_poll' => 'Voltar à votação', // link to return to voting form
    'no_responses' => 'Ainda não há respostas.', // empty state message
    'computing_results' => 'Calculando resultados...', // shown while report is calculating
    'unknown_report_type' => 'Tipo de relatório desconhecido: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count resposta|:count respostas', // "5 respostas"
    'question_count' => ':count pergunta|:count perguntas', // "3 perguntas"
    'closed_on' => 'Encerrada em :date', // "Encerrada em 15 de janeiro de 2025"
    'created_on' => 'Criada em :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Escolha única',
    'type_approval' => 'Votação por aprovação (Múltipla escolha)',
    'type_participatory_budgeting' => 'Orçamento participativo',
    'type_distribution' => 'Distribuição de pontos',
    'type_ranking' => 'Classificação',
    'type_ranking_truncated' => 'Classificação (Parcial)',
    'type_ranking_with_ties' => 'Classificação (Com empates)',
    'type_star' => 'Avaliação por estrelas',
    'type_grade' => 'Notas',
    'type_yes_no_abstain' => 'Sim / Não / Abstenção',
    'type_text_single' => 'Texto curto',
    'type_text_multi' => 'Texto longo',
    'type_section_header' => 'Cabeçalho de seção',

    // Common question labels
    'untitled_question' => 'Pergunta sem título',
    'section' => 'Seção', // default label for untitled section headers
    'unknown_question_type' => 'Tipo de pergunta desconhecido: :type',

    // Common question UI
    'other_option' => 'Outro:', // shown before text input for "other" option
    'please_specify' => 'Por favor, especifique...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Arraste para reordenar (topo = melhor)', // hint for full ranking
    'ranking_ties_hint' => 'Arraste para reordenar. Itens no mesmo grupo estão empatados.', // ranking with ties
    'available_options' => 'Opções disponíveis', // truncated ranking - source list header
    'your_ranking' => 'Sua classificação', // truncated ranking - target list header
    'drag_to_rank' => 'Arraste opções para cá para classificá-las', // truncated ranking placeholder
    'borda_score_note' => 'Classificado por pontuação Borda (maior = melhor)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Selecionar...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Sim',
    'no' => 'Não',
    'abstain' => 'Abstenção',

    // Distribution question UI
    'remaining' => 'Restante:', // shows remaining points to allocate
    'points' => 'pontos', // unit label, e.g., "15 pontos"

    // Text input placeholders
    'short_answer' => 'Resposta curta',
    'long_answer' => 'Resposta detalhada',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Rascunho',
    'status_open' => 'Aberta',
    'status_closed' => 'Encerrada',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Ver resultados', // link to results page
    'report_poll' => 'Denunciar esta votação', // abuse report link
    'report_this_poll' => 'Denunciar esta votação', // heading for report modal
    'report_guidelines' => 'Se você acredita que esta votação viola nossas diretrizes, por favor nos informe.',
    'report_reason_spam' => 'Spam ou conteúdo enganoso',
    'report_reason_harassment' => 'Assédio ou discurso de ódio',
    'report_reason_doxxing' => 'Exposição de informações pessoais (doxxing)',
    'report_reason_illegal' => 'Atividade ou conteúdo ilegal',
    'report_reason_impersonation' => 'Falsidade ideológica ou fraude',
    'report_reason_phishing' => 'Malware ou tentativa de phishing',
    'report_reason_copyright' => 'Violação de direitos autorais ou marca registrada',
    'report_reason_other' => 'Outro',
    'report_details' => 'Detalhes adicionais',
    'report_optional' => '(opcional)',
    'report_placeholder' => 'Por favor, forneça contexto adicional que possa nos ajudar a analisar esta denúncia...',
    'cancel' => 'Cancelar',
    'submit_report' => 'Enviar denúncia',
    'select_report_reason' => 'Por favor, selecione um motivo para a denúncia',
    'provide_report_details' => 'Por favor, forneça detalhes para sua denúncia',
    'report_submitted' => 'Obrigado pela sua denúncia. Analisaremos em breve.',
    'share' => 'Compartilhar',
    'copy_link' => 'Copiar link',
    'copied' => 'Copiado!', // toast message after copying
    'undo' => 'Desfazer',
    'delete' => 'Excluir',
    'logout_failed' => 'Falha ao sair',
    'create_poll' => 'Criar votação',
    'about' => 'Sobre',
    'dashboard' => 'Painel',
    'sysadmin' => 'Administração',
    'log_out' => 'Sair',
    'login' => 'Entrar',
    'privacy_policy' => 'Política de privacidade',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Votação não encontrada.',
    'vote_closed' => 'Esta votação está encerrada e não aceita mais respostas.',
    'already_voted' => 'Você já enviou uma resposta.',
    'error_loading' => 'Erro ao carregar dados. Por favor, tente novamente.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Vencedor',
    'result_winner_by_rule' => 'Vencedor por :rule', // e.g., "Vencedor por Majority Judgment"
    'result_tied' => 'Empate',
    'result_no_winner' => 'Sem vencedor',
    'no_winner_yet' => 'Ainda não foi determinado um vencedor.', // shown when no responses
    'rule_majority_judgment' => 'Julgamento majoritário',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Método de votação',
    'winners' => 'Vencedor(es)',
    'votes' => 'Votos',
    'option' => 'Opção', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Candidato', // column header in multi-winner election results
    'count' => 'Quantidade', // column header showing how many voting rules selected a candidate
    'rules' => 'Métodos', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'tamanho :size', // inline label with committee size, e.g., "(tamanho 5)"
    'no_results_available' => 'Não há resultados disponíveis para esta pergunta.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Contagem de votos',
    'report_type_approval_winner' => 'Vencedor por aprovação',
    'report_type_median' => 'Escolha mediana',
    'report_type_borda_scores' => 'Pontuações Borda',
    'report_type_pairwise_margins' => 'Margens por pares',
    'report_type_voting_rule_winner' => 'Vencedor por método',
    'report_type_rank_aggregation' => 'Agregação de classificações',
    'report_type_multiwinner' => 'Método multivencedor',
    'report_type_pb_winner' => 'Vencedor orçamento participativo',
    'report_type_condorcet_winner' => 'Vencedor de Condorcet',
    'report_type_apportionment_winner' => 'Distribuição de cadeiras',
    'report_type_yna_counts' => 'Contagem Sim/Não/Abstenção',
    'report_type_majority_judgment' => 'Julgamento majoritário',
    'report_type_multi_rule_comparison' => 'Comparação de métodos',
    'report_type_multi_swf_comparison' => 'Comparação de agregações',
    'report_type_multiwinner_multi_rule_comparison' => 'Comparação multivencedor',
    'report_type_apportionment_multi_rule_comparison' => 'Comparação de distribuições',
    'report_type_response_matrix' => 'Matriz de respostas',
    'report_type_raw_data_export' => 'Exportar dados brutos',
    'report_type_text_block' => 'Bloco de texto',

    // Admin report actions
    'add_analysis' => 'Adicionar análise', // button to add a new report
    'drag_to_reorder' => 'Arraste para reordenar', // tooltip for report drag handle
    'delete_analysis' => 'Excluir análise', // tooltip for delete button
    'analysis_deleted' => 'Análise excluída', // toast after deletion
    'make_public' => 'Tornar público', // tooltip to show report to voters
    'make_private' => 'Tornar privado', // tooltip to hide report from voters
    'settings' => 'Configurações', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'Nenhum método selecionado ou calculado.',
    'rules_count' => ':count/:total métodos', // "3/5 métodos"
    'winners_by_rule_count' => 'Vencedores por quantidade de métodos',
    'no_results_to_compare' => 'Não há resultados para comparar.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count voto|:count votos', // "5 votos"
    'seat_count' => ':count cadeira|:count cadeiras', // "3 cadeiras"

    // Condorcet winner
    'condorcet_winner' => 'Vencedor de Condorcet',
    'no_condorcet_winner' => 'Sem vencedor de Condorcet',
    'condorcet_explanation' => 'Vence todas as outras opções em comparações diretas',
    'condorcet_cycle' => 'Existe um ciclo nas preferências por pares',

    // Median choice
    'median_choice' => 'Mediana',
    'no_median_yet' => 'A mediana ainda não foi determinada.',
    'median_interval' => 'Intervalo da mediana', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Ainda não foram determinados projetos vencedores.',
    'total_budget' => 'Orçamento total',
    'spent' => 'Gasto',
    'winning_projects' => 'Projetos vencedores',
    'avg_voter_approves' => 'Em média, cada votante aprova :count projetos vencedores.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Ainda não foi determinado um comitê vencedor.',
    'committee_number' => 'Comitê nº :num',
    'committee_size' => 'Tamanho do comitê',
    'tied_committees' => 'Comitês vencedores empatados',
    'winning_committee' => 'Comitê vencedor',
    'show_calculation_steps' => 'Mostrar passos do cálculo',
    'hide_calculation_steps' => 'Ocultar passos do cálculo',
    'tie_showing_first' => 'Empate (mostrando o primeiro comitê vencedor)',
    'candidate_frequency' => 'Frequência de candidatos',
    'candidate_frequency_desc' => 'Com que frequência cada candidato aparece em um comitê vencedor entre os :count métodos comparados.',

    // Apportionment
    'total_seats' => 'Total de cadeiras',
    'no_methods_selected' => 'Nenhum método selecionado para comparação.',
    'apportionment_comparison_desc' => 'Comparação de :methods métodos de distribuição para :seats cadeiras',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'A classificação ainda não foi determinada.',
    'tied_rankings' => 'Existem :count classificações ótimas empatadas:',
    'ranking_number' => 'Classificação nº :num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Sua resposta foi registrada.',
    'update_success' => 'Sua resposta foi atualizada.',
    'edit_response' => 'Editar sua resposta',
    'close_vote' => 'Encerrar votação',
    'reopen_vote' => 'Reabrir votação',
    'delete_vote' => 'Excluir votação',
    'responses' => 'Respostas',
];
