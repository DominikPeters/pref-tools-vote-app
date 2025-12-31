/**
 * Report Types Registry
 *
 * Maps report types to their renderer modules.
 * Renderers are lazy-loaded for performance.
 */

import { renderChoiceCounts } from './choice-counts.js';
import { renderApprovalWinner } from './approval-winner.js';
import { renderBordaScores } from './borda-scores.js';
import { renderPairwiseMargins } from './pairwise-margins.js';
import { renderVotingRuleWinner } from './voting-rule-winner.js';
import { renderRankAggregation } from './rank-aggregation.js';
import { renderCondorcetWinner } from './condorcet-winner.js';
import { renderYNACounts } from './yna-counts.js';
import { renderMajorityJudgment } from './majority-judgment.js';
import { renderMultiRuleComparison } from './multi-rule-comparison.js';
import { renderMultiSWFComparison } from './multi-swf-comparison.js';
import { renderABCWinner } from './abc-winner.js';
import { renderABCMultiRuleComparison } from './abc-multi-rule-comparison.js';
import { renderApportionmentWinner } from './apportionment-winner.js';
import { renderApportionmentMultiRuleComparison } from './apportionment-multi-rule-comparison.js';
import { renderResponseMatrix } from './response-matrix.js';
import { renderRawDataExport } from './raw-data-export.js';
import { renderTextBlock } from './text-block.js';

const renderers = {
    'choice_counts': renderChoiceCounts,
    'approval_winner': renderApprovalWinner,
    'borda_scores': renderBordaScores,
    'pairwise_margins': renderPairwiseMargins,
    'voting_rule_winner': renderVotingRuleWinner,
    'rank_aggregation': renderRankAggregation,
    'abc_winner': renderABCWinner,
    'condorcet_winner': renderCondorcetWinner,
    'apportionment_winner': renderApportionmentWinner,
    'yna_counts': renderYNACounts,
    'majority_judgment': renderMajorityJudgment,
    'multi_rule_comparison': renderMultiRuleComparison,
    'multi_swf_comparison': renderMultiSWFComparison,
    'abc_multi_rule_comparison': renderABCMultiRuleComparison,
    'apportionment_multi_rule_comparison': renderApportionmentMultiRuleComparison,
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
        container.innerHTML = `<p class="report-error">Unknown report type: ${report.report_type}</p>`;
        return;
    }

    if (!report.cached_result) {
        container.innerHTML = '<p class="report-loading">Computing results...</p>';
        return;
    }

    // Include report.id in context for renderers that need it
    const fullContext = { ...context, reportId: report.id };

    try {
        renderer(container, report.cached_result, report.config, fullContext);
    } catch (err) {
        console.error('Error rendering report:', err);
        container.innerHTML = '<p class="report-error">Failed to render report.</p>';
    }
}

/**
 * Get report type metadata
 */
export function getReportTypeName(type) {
    const names = {
        'choice_counts': 'Vote Counts',
        'approval_winner': 'Approval Winner',
        'borda_scores': 'Borda Scores',
        'pairwise_margins': 'Pairwise Margins',
        'voting_rule_winner': 'Voting Rule Winner',
        'rank_aggregation': 'Rank Aggregation',
        'abc_winner': 'ABC Voting Rule Winner',
        'condorcet_winner': 'Condorcet Winner',
        'apportionment_winner': 'Apportionment Rule Winner',
        'yna_counts': 'Yes/No/Abstain Tallies',
        'majority_judgment': 'Majority Judgment',
        'multi_rule_comparison': 'Multi-Rule Comparison',
        'multi_swf_comparison': 'Rank Aggregation Rule Comparison',
        'abc_multi_rule_comparison': 'ABC Multi-Rule Comparison',
        'apportionment_multi_rule_comparison': 'Apportionment Multi-Rule Comparison',
        'response_matrix': 'Response Matrix',
        'raw_data_export': 'Export Raw Vote Data',
        'text_block': 'Text Block',
    };
    return names[type] || type;
}

export function getReportTypeIcon(type) {
    const icons = {
        'choice_counts': 'chart-bar',
        'approval_winner': 'trophy',
        'borda_scores': 'chart-bar',
        'pairwise_margins': 'diagram-project',
        'voting_rule_winner': 'trophy',
        'rank_aggregation': 'list-ol',
        'abc_winner': 'trophy',
        'condorcet_winner': 'crown',
        'apportionment_winner': 'calculator',
        'yna_counts': 'check-circle',
        'majority_judgment': 'scale-balanced',
        'multi_rule_comparison': 'table',
        'multi_swf_comparison': 'columns',
        'abc_multi_rule_comparison': 'table',
        'apportionment_multi_rule_comparison': 'table',
        'response_matrix': 'table-cells',
        'raw_data_export': 'file-export',
        'text_block': 'file-lines',
    };
    return icons[type] || 'chart-bar';
}
