/**
 * Report Types Registry
 *
 * Maps report types to their renderer modules.
 * Renderers are lazy-loaded for performance.
 */

import { renderChoiceCounts } from './choice-counts.js';
import { renderApprovalWinner } from './approval-winner.js';
import { renderMedian } from './median.js';
import { renderBordaScores } from './borda-scores.js';
import { renderPairwiseMargins } from './pairwise-margins.js';
import { renderVotingRuleWinner } from './voting-rule-winner.js';
import { renderRankAggregation } from './rank-aggregation.js';
import { renderCondorcetWinner } from './condorcet-winner.js';
import { renderYNACounts } from './yna-counts.js';
import { renderMajorityJudgment } from './majority-judgment.js';
import { renderMultiRuleComparison } from './multi-rule-comparison.js';
import { renderMultiSWFComparison } from './multi-swf-comparison.js';
import { renderMultiwinner } from './multiwinner.js';
import { renderMultiwinnerMultiRuleComparison } from './multiwinner-multi-rule-comparison.js';
import { renderPBWinner } from './pb-winner.js';
import { renderApportionmentWinner } from './apportionment-winner.js';
import { renderApportionmentMultiRuleComparison } from './apportionment-multi-rule-comparison.js';
import { renderDistributionAggregation } from './distribution-aggregation.js';
import { renderDistributionMultiRuleComparison } from './distribution-multi-rule-comparison.js';
import { renderResponseMatrix } from './response-matrix.js';
import { renderRawDataExport } from './raw-data-export.js';
import { renderTextBlock } from './text-block.js';
import { t } from '../i18n.js';

const renderers = {
    'choice_counts': renderChoiceCounts,
    'approval_winner': renderApprovalWinner,
    'median': renderMedian,
    'borda_scores': renderBordaScores,
    'pairwise_margins': renderPairwiseMargins,
    'voting_rule_winner': renderVotingRuleWinner,
    'rank_aggregation': renderRankAggregation,
    'multiwinner': renderMultiwinner,
    'pb_winner': renderPBWinner,
    'condorcet_winner': renderCondorcetWinner,
    'apportionment_winner': renderApportionmentWinner,
    'yna_counts': renderYNACounts,
    'majority_judgment': renderMajorityJudgment,
    'multi_rule_comparison': renderMultiRuleComparison,
    'multi_swf_comparison': renderMultiSWFComparison,
    'multiwinner_multi_rule_comparison': renderMultiwinnerMultiRuleComparison,
    'apportionment_multi_rule_comparison': renderApportionmentMultiRuleComparison,
    'distribution_aggregation': renderDistributionAggregation,
    'distribution_multi_rule_comparison': renderDistributionMultiRuleComparison,
    'response_matrix': renderResponseMatrix,
    'raw_data_export': renderRawDataExport,
    'text_block': renderTextBlock,
};

/**
 * Render a report into a container
 * @param {HTMLElement} container - The container to render into
 * @param {Object} report - The report object with cachedResult
 * @param {Object} [context] - Optional context with publicId and adminToken for on-demand fetching
 */
export function renderReport(container, report, context = {}) {
    const renderer = renderers[report.report_type];

    if (!renderer) {
        container.innerHTML = `<p class="report-error">${t('unknown_report_type', { type: report.report_type })}</p>`;
        return;
    }

    if (!report.cached_result) {
        container.innerHTML = `<p class="report-loading">${t('computing_results')}</p>`;
        return;
    }

    // Include report.id in context for renderers that need it
    const fullContext = { ...context, reportId: report.id };

    try {
        renderer(container, report.cached_result, report.config, fullContext);
    } catch (err) {
        console.error('Error rendering report:', err);
        container.innerHTML = `<p class="report-error">${t('error_loading')}</p>`;
    }
}

/**
 * Get report type metadata
 */
export function getReportTypeName(type) {
    const names = {
        'choice_counts': t('report_type_choice_counts'),
        'approval_winner': t('report_type_approval_winner'),
        'median': t('report_type_median'),
        'borda_scores': t('report_type_borda_scores'),
        'pairwise_margins': t('report_type_pairwise_margins'),
        'voting_rule_winner': t('report_type_voting_rule_winner'),
        'rank_aggregation': t('report_type_rank_aggregation'),
        'multiwinner': t('report_type_multiwinner'),
        'pb_winner': t('report_type_pb_winner'),
        'condorcet_winner': t('report_type_condorcet_winner'),
        'apportionment_winner': t('report_type_apportionment_winner'),
        'yna_counts': t('report_type_yna_counts'),
        'majority_judgment': t('report_type_majority_judgment'),
        'multi_rule_comparison': t('report_type_multi_rule_comparison'),
        'multi_swf_comparison': t('report_type_multi_swf_comparison'),
        'multiwinner_multi_rule_comparison': t('report_type_multiwinner_multi_rule_comparison'),
        'apportionment_multi_rule_comparison': t('report_type_apportionment_multi_rule_comparison'),
        'distribution_aggregation': t('report_type_distribution_aggregation'),
        'distribution_multi_rule_comparison': t('report_type_distribution_multi_rule_comparison'),
        'response_matrix': t('report_type_response_matrix'),
        'raw_data_export': t('report_type_raw_data_export'),
        'text_block': t('report_type_text_block'),
    };
    return names[type] || type;
}

export function getReportTypeIcon(type) {
    const icons = {
        'choice_counts': 'chart-bar',
        'approval_winner': 'trophy',
        'median': 'git-commit',
        'borda_scores': 'chart-bar',
        'pairwise_margins': 'diagram-project',
        'voting_rule_winner': 'trophy',
        'rank_aggregation': 'list-ol',
        'multiwinner': 'trophy',
        'pb_winner': 'calculator',
        'condorcet_winner': 'crown',
        'apportionment_winner': 'calculator',
        'yna_counts': 'check-circle',
        'majority_judgment': 'scale-balanced',
        'multi_rule_comparison': 'table',
        'multi_swf_comparison': 'columns',
        'multiwinner_multi_rule_comparison': 'table',
        'apportionment_multi_rule_comparison': 'table',
        'distribution_aggregation': 'chart-pie',
        'distribution_multi_rule_comparison': 'table',
        'response_matrix': 'table-cells',
        'raw_data_export': 'file-export',
        'text_block': 'file-lines',
    };
    return icons[type] || 'chart-bar';
}