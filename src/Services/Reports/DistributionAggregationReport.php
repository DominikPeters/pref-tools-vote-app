<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\DistributionProfileBuilder;
use App\Services\DistributionRulesRegistry;

class DistributionAggregationReport extends BaseReport
{
    public function getType(): string
    {
        return 'distribution_aggregation';
    }

    public function getName(): string
    {
        return __('report_type_distribution_aggregation');
    }

    public function getDescription(): string
    {
        return __('report_combines_everyone_s_allocations_into_a_single_collective_distribution');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['distribution'];
    }

    public function getIcon(): string
    {
        return 'chart-pie';
    }

    public function getCategory(): string
    {
        return 'distribution_aggregation';
    }

    public function getIntent(): string
    {
        return 'who_wins';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => __('report_aggregation_rule'),
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'distributionRules',
                    'default' => 'mean',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $rule = $config['rule'] ?? 'mean';

        // Build distributions from responses
        $distributions = DistributionProfileBuilder::fromDistributionResponses($question, $responses);

        if (empty($distributions)) {
            return $this->errorResult('error_no_valid_responses_for_this_question');
        }

        // Get option metadata
        $optionLabels = DistributionProfileBuilder::getOptionLabels($question);
        $optionColors = DistributionProfileBuilder::getOptionColors($question);
        $optionIds = DistributionProfileBuilder::getOptionIds($question);

        try {
            $aggregatedDist = DistributionRulesRegistry::compute($rule, $distributions);
        } catch (\Exception $e) {
            return $this->errorResult('error_error_computing_result_detail', ['detail' => $e->getMessage()]);
        }

        // Format the distribution for output
        $distribution = [];
        foreach ($optionIds as $optionId) {
            $fraction = $aggregatedDist[$optionId] ?? 0.0;
            $distribution[] = [
                'option_id' => $optionId,
                'option' => $optionLabels[$optionId] ?? "Option {$optionId}",
                'fraction' => round($fraction, 6),
                'percentage' => round($fraction * 100, 2),
                'color' => $optionColors[$optionId] ?? '#999999',
            ];
        }

        // Sort by fraction descending
        usort($distribution, function ($a, $b) {
            return $b['fraction'] <=> $a['fraction'];
        });

        return [
            'rule' => $rule,
            'rule_name' => DistributionRulesRegistry::getRules()[$rule]['name'] ?? $rule,
            'distribution' => $distribution,
            'total_responses' => count($distributions),
        ];
    }
}
