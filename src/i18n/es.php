<?php

/**
 * Spanish translations for voter-facing strings.
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
    'your_name' => 'Su nombre', // label for voter name input field
    'name_placeholder' => 'Su nombre', // placeholder text in name input

    // Submit buttons
    'submit' => 'Enviar',
    'submit_vote' => 'Enviar voto', // primary submit button
    'update_response' => 'Actualizar respuesta', // when editing existing response
    'submit_disabled_preview' => 'Enviar voto (Desactivado en vista previa)', // in preview mode

    // Status banners
    'preview_mode_message' => 'Modo de vista previa – Así aparecerá su votación a los participantes.',
    'poll_not_open' => 'Esta votación aún no está abierta.',
    'voting_closed' => 'Votación cerrada', // banner heading
    'poll_no_longer_accepting' => 'Esta votación ya no acepta respuestas.', // banner body

    // Success messages (after vote submission)
    'thank_you' => '¡Gracias!', // heading after successful submission
    'response_recorded' => 'Su respuesta ha sido registrada.',
    'can_close_page' => 'Puede cerrar esta página.',
    'already_submitted_can_update' => 'Ya ha enviado una respuesta. Puede actualizarla a continuación.',

    // Validation
    'required_field' => 'Este campo es obligatorio.',
    'validation_error' => 'Por favor, revise sus respuestas e intente de nuevo.',

    // =========================================================================
    // Results Page (templates/results.php, assets/js/results*.js)
    // =========================================================================

    'results' => 'Resultados', // page title and breadcrumb
    'poll' => 'Votación', // breadcrumb link text
    'live_results' => 'Resultados en vivo', // badge shown when poll is still open
    'loading_results' => 'Cargando resultados...',
    'back_to_poll' => 'Volver a la votación', // link to return to voting form
    'no_responses' => 'Aún no hay respuestas.', // empty state message
    'computing_results' => 'Calculando resultados...', // shown while report is calculating
    'unknown_report_type' => 'Tipo de informe desconocido: :type',

    // Summary stats (use :count parameter)
    'response_count' => ':count respuesta|:count respuestas', // "5 respuestas"
    'question_count' => ':count pregunta|:count preguntas', // "3 preguntas"
    'closed_on' => 'Cerrada el :date', // "Cerrada el 15 de enero de 2025"
    'created_on' => 'Creada el :date',

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    // Type labels (shown in builder, possibly in results)
    'type_single_choice' => 'Opción única',
    'type_approval' => 'Votación por aprobación (Opción múltiple)',
    'type_participatory_budgeting' => 'Presupuesto participativo',
    'type_distribution' => 'Distribución de puntos',
    'type_ranking' => 'Clasificación',
    'type_ranking_truncated' => 'Clasificación (Parcial)',
    'type_ranking_with_ties' => 'Clasificación (Con empates)',
    'type_star' => 'Calificación por estrellas',
    'type_grade' => 'Calificaciones',
    'type_yes_no_abstain' => 'Sí / No / Abstención',
    'type_text_single' => 'Texto corto',
    'type_text_multi' => 'Texto largo',
    'type_section_header' => 'Encabezado de sección',

    // Common question labels
    'untitled_question' => 'Pregunta sin título',
    'section' => 'Sección', // default label for untitled section headers
    'unknown_question_type' => 'Tipo de pregunta desconocido: :type',

    // Common question UI
    'other_option' => 'Otro:', // shown before text input for "other" option
    'please_specify' => 'Por favor, especifique...', // placeholder for "other" text input

    // Ranking question UI
    'ranking_hint' => 'Arrastre para reordenar (arriba = mejor)', // hint for full ranking
    'ranking_ties_hint' => 'Arrastre para reordenar. Los elementos en el mismo grupo están empatados.', // ranking with ties
    'available_options' => 'Opciones disponibles', // truncated ranking - source list header
    'your_ranking' => 'Su clasificación', // truncated ranking - target list header
    'drag_to_rank' => 'Arrastre opciones aquí para clasificarlas', // truncated ranking placeholder
    'borda_score_note' => 'Clasificado por puntuación Borda (mayor = mejor)', // legacy results ranking note

    // Grade question UI
    'select_placeholder' => 'Seleccionar...', // dropdown placeholder

    // Yes/No/Abstain buttons
    'yes' => 'Sí',
    'no' => 'No',
    'abstain' => 'Abstención',

    // Distribution question UI
    'remaining' => 'Restante:', // shows remaining points to allocate
    'points' => 'puntos', // unit label, e.g., "15 puntos"

    // Text input placeholders
    'short_answer' => 'Respuesta corta',
    'long_answer' => 'Respuesta detallada',

    // =========================================================================
    // Poll Status
    // =========================================================================

    'status_draft' => 'Borrador',
    'status_open' => 'Abierta',
    'status_closed' => 'Cerrada',

    // =========================================================================
    // Common Actions
    // =========================================================================

    'view_results' => 'Ver resultados', // link to results page
    'report_poll' => 'Reportar esta votación', // abuse report link
    'report_this_poll' => 'Reportar esta votación', // heading for report modal
    'report_guidelines' => 'Si cree que esta votación viola nuestras normas, por favor notifíquenos.',
    'report_reason_spam' => 'Spam o contenido engañoso',
    'report_reason_harassment' => 'Acoso o discurso de odio',
    'report_reason_doxxing' => 'Exposición de información personal (doxxing)',
    'report_reason_illegal' => 'Actividad o contenido ilegal',
    'report_reason_impersonation' => 'Suplantación de identidad o fraude',
    'report_reason_phishing' => 'Malware o intento de phishing',
    'report_reason_copyright' => 'Violación de derechos de autor o marca',
    'report_reason_other' => 'Otro',
    'report_details' => 'Detalles adicionales',
    'report_optional' => '(opcional)',
    'report_placeholder' => 'Por favor, proporcione contexto adicional que pueda ayudarnos a revisar este reporte...',
    'cancel' => 'Cancelar',
    'submit_report' => 'Enviar reporte',
    'select_report_reason' => 'Por favor, seleccione un motivo para el reporte',
    'provide_report_details' => 'Por favor, proporcione detalles para su reporte',
    'report_submitted' => 'Gracias por su reporte. Lo revisaremos pronto.',
    'share' => 'Compartir',
    'copy_link' => 'Copiar enlace',
    'copied' => '¡Copiado!', // toast message after copying
    'undo' => 'Deshacer',
    'delete' => 'Eliminar',
    'logout_failed' => 'Error al cerrar sesión',
    'create_poll' => 'Crear votación',
    'about' => 'Acerca de',
    'dashboard' => 'Panel',
    'sysadmin' => 'Administración',
    'log_out' => 'Cerrar sesión',
    'login' => 'Iniciar sesión',
    'privacy_policy' => 'Política de privacidad',

    // =========================================================================
    // Error Messages
    // =========================================================================

    'vote_not_found' => 'Votación no encontrada.',
    'vote_closed' => 'Esta votación está cerrada y ya no acepta respuestas.',
    'already_voted' => 'Ya ha enviado una respuesta.',
    'error_loading' => 'Error al cargar los datos. Por favor, intente de nuevo.',

    // =========================================================================
    // Voting Rules - Result Labels
    // =========================================================================
    // Note: Rule NAMES and DESCRIPTIONS come from the registry files (PHP).
    // They are the English source of truth. Non-English translations go here
    // using keys like 'rule_{registry_key}' and 'rule_{registry_key}_desc'.
    // JS uses tFallback() to try translation first, then fall back to registry.

    'result_winner' => 'Ganador',
    'result_winner_by_rule' => 'Ganador por :rule', // e.g., "Ganador por Majority Judgment"
    'result_tied' => 'Empate',
    'result_no_winner' => 'Sin ganador',
    'no_winner_yet' => 'Aún no se ha determinado un ganador.', // shown when no responses
    'rule_majority_judgment' => 'Juicio mayoritario',

    // =========================================================================
    // Report UI Strings (assets/js/report-types/*.js)
    // =========================================================================

    // Common report labels (table headers in result reports)
    'voting_rule' => 'Método de votación',
    'winners' => 'Ganador(es)',
    'votes' => 'Votos',
    'option' => 'Opción', // column header for poll choices (e.g., parties in apportionment)
    'candidate' => 'Candidato', // column header in multi-winner election results
    'count' => 'Cantidad', // column header showing how many voting rules selected a candidate
    'rules' => 'Métodos', // column header listing which voting rules selected a candidate
    'committee_size_label' => 'tamaño :size', // inline label with committee size, e.g., "(tamaño 5)"
    'no_results_available' => 'No hay resultados disponibles para esta pregunta.', // shown when no reports exist

    // Report Type Names
    'report_type_choice_counts' => 'Conteo de votos',
    'report_type_approval_winner' => 'Ganador por aprobación',
    'report_type_median' => 'Opción mediana',
    'report_type_borda_scores' => 'Puntuaciones Borda',
    'report_type_pairwise_margins' => 'Márgenes por pares',
    'report_type_voting_rule_winner' => 'Ganador por método',
    'report_type_rank_aggregation' => 'Agregación de clasificaciones',
    'report_type_multiwinner' => 'Método multiganador',
    'report_type_pb_winner' => 'Ganador presupuesto participativo',
    'report_type_condorcet_winner' => 'Ganador de Condorcet',
    'report_type_apportionment_winner' => 'Asignación de escaños',
    'report_type_yna_counts' => 'Recuento Sí/No/Abstención',
    'report_type_majority_judgment' => 'Juicio mayoritario',
    'report_type_multi_rule_comparison' => 'Comparación de métodos',
    'report_type_multi_swf_comparison' => 'Comparación de agregaciones',
    'report_type_multiwinner_multi_rule_comparison' => 'Comparación multiganador',
    'report_type_apportionment_multi_rule_comparison' => 'Comparación de asignaciones',
    'report_type_response_matrix' => 'Matriz de respuestas',
    'report_type_raw_data_export' => 'Exportar datos sin procesar',
    'report_type_text_block' => 'Bloque de texto',

    // Admin report actions
    'add_analysis' => 'Añadir análisis', // button to add a new report
    'drag_to_reorder' => 'Arrastre para reordenar', // tooltip for report drag handle
    'delete_analysis' => 'Eliminar análisis', // tooltip for delete button
    'analysis_deleted' => 'Análisis eliminado', // toast after deletion
    'make_public' => 'Hacer público', // tooltip to show report to voters
    'make_private' => 'Hacer privado', // tooltip to hide report from voters
    'settings' => 'Configuración', // tooltip for report configuration

    // Multi-rule comparison
    'no_rules_selected' => 'No se han seleccionado ni calculado métodos.',
    'rules_count' => ':count/:total métodos', // "3/5 métodos"
    'winners_by_rule_count' => 'Ganadores por cantidad de métodos',
    'no_results_to_compare' => 'No hay resultados para comparar.',

    // Vote/seat counts (with plurals)
    'vote_count' => ':count voto|:count votos', // "5 votos"
    'seat_count' => ':count escaño|:count escaños', // "3 escaños"

    // Condorcet winner
    'condorcet_winner' => 'Ganador de Condorcet',
    'no_condorcet_winner' => 'Sin ganador de Condorcet',
    'condorcet_explanation' => 'Gana a todas las demás opciones en comparaciones directas',
    'condorcet_cycle' => 'Existe un ciclo en las preferencias por pares',

    // Median choice
    'median_choice' => 'Mediana',
    'no_median_yet' => 'Aún no se ha determinado la mediana.',
    'median_interval' => 'Intervalo de mediana', // shown when there are multiple median options (even number of voters)

    // Participatory Budgeting
    'no_winning_projects_yet' => 'Aún no se han determinado proyectos ganadores.',
    'total_budget' => 'Presupuesto total',
    'spent' => 'Gastado',
    'winning_projects' => 'Proyectos ganadores',
    'avg_voter_approves' => 'En promedio, cada votante aprueba :count proyectos ganadores.',

    // Multi-winner / Committee
    'no_winning_committee_yet' => 'Aún no se ha determinado un comité ganador.',
    'committee_number' => 'Comité n.º :num',
    'committee_size' => 'Tamaño del comité',
    'tied_committees' => 'Comités ganadores empatados',
    'winning_committee' => 'Comité ganador',
    'show_calculation_steps' => 'Mostrar pasos de cálculo',
    'hide_calculation_steps' => 'Ocultar pasos de cálculo',
    'tie_showing_first' => 'Empate (mostrando el primer comité ganador)',
    'candidate_frequency' => 'Frecuencia de candidatos',
    'candidate_frequency_desc' => 'Con qué frecuencia aparece cada candidato en un comité ganador entre los :count métodos comparados.',

    // Apportionment
    'total_seats' => 'Total de escaños',
    'no_methods_selected' => 'No se han seleccionado métodos para comparar.',
    'apportionment_comparison_desc' => 'Comparación de :methods métodos de asignación para :seats escaños',

    // Rank aggregation (Social Welfare Functions)
    'no_ranking_yet' => 'Aún no se ha determinado una clasificación.',
    'tied_rankings' => 'Hay :count clasificaciones óptimas empatadas:',
    'ranking_number' => 'Clasificación n.º :num',

    // =========================================================================
    // Legacy/Admin (may not need translation yet)
    // =========================================================================

    'submit_success' => 'Su respuesta ha sido registrada.',
    'update_success' => 'Su respuesta ha sido actualizada.',
    'edit_response' => 'Editar su respuesta',
    'close_vote' => 'Cerrar votación',
    'reopen_vote' => 'Reabrir votación',
    'delete_vote' => 'Eliminar votación',
    'responses' => 'Respuestas',
];
