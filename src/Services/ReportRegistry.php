<?php

namespace App\Services;

use App\Services\Reports\BaseReport;

class ReportRegistry
{
    /** @var array<string, BaseReport> */
    private static array $handlers = [];

    /** @var bool */
    private static bool $initialized = false;

    /**
     * Register a report handler
     */
    public static function register(string $type, BaseReport $handler): void
    {
        self::$handlers[$type] = $handler;
    }

    /**
     * Get a report handler by type
     */
    public static function get(string $type): ?BaseReport
    {
        self::ensureInitialized();
        return self::$handlers[$type] ?? null;
    }

    /**
     * Get all report types that support a given question type
     */
    public static function getTypesForQuestionType(string $questionType): array
    {
        self::ensureInitialized();
        $result = [];

        foreach (self::$handlers as $handler) {
            if (in_array($questionType, $handler->getSupportedQuestionTypes())) {
                $result[] = $handler->getMetadata();
            }
        }

        return $result;
    }

    /**
     * Get all registered report types
     */
    public static function all(): array
    {
        self::ensureInitialized();
        return array_values(array_map(fn($h) => $h->getMetadata(), self::$handlers));
    }

    /**
     * Initialize the registry with all report handlers
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        // Register all report types
        // Basic reports for single_choice/approval
        self::register('choice_counts', new Reports\ChoiceCountsReport());
        self::register('approval_winner', new Reports\ApprovalWinnerReport());
        self::register('median', new Reports\MedianReport());

        // Ranking-based reports
        self::register('borda_scores', new Reports\BordaScoresReport());
        self::register('pairwise_margins', new Reports\PairwiseMarginsReport());
        self::register('voting_rule_winner', new Reports\VotingRuleWinnerReport());
        self::register('rank_aggregation', new Reports\RankAggregationReport());
        self::register('multiwinner', new Reports\MultiwinnerReport());
        self::register('pb_winner', new Reports\PBWinnerReport());
        self::register('condorcet_winner', new Reports\CondorcetWinnerReport());
        self::register('apportionment_winner', new Reports\ApportionmentWinnerReport());
        self::register('multi_rule_comparison', new Reports\MultiRuleComparisonReport());
        self::register('multi_swf_comparison', new Reports\MultiSWFComparisonReport());
        self::register('multiwinner_multi_rule_comparison', new Reports\MultiwinnerMultiRuleComparisonReport());
        self::register('apportionment_multi_rule_comparison', new Reports\ApportionmentMultiRuleComparisonReport());

        // Distribution aggregation reports
        self::register('distribution_aggregation', new Reports\DistributionAggregationReport());
        self::register('distribution_multi_rule_comparison', new Reports\DistributionMultiRuleComparisonReport());

        // Grade-based reports
        self::register('majority_judgment', new Reports\MajorityJudgmentReport());

        // Yes/No/Abstain reports
        self::register('yna_counts', new Reports\YNACountsReport());

        // Matrix/table reports
        self::register('response_matrix', new Reports\ResponseMatrixReport());

        // Data export reports
        self::register('raw_data_export', new Reports\RawDataExportReport());

        // Text/narrative reports
        self::register('text_block', new Reports\TextBlockReport());

        self::$initialized = true;
    }
}