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
        return 'Distribution Aggregation';
    }

    public function getDescription(): string
    {
        return 'Aggregates voter distributions into a consensus distribution using budget aggregation rules';
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

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => 'Aggregation Rule',
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
            return ['error' => 'No valid responses for this question.'];
        }

        // Get option metadata
        $optionLabels = DistributionProfileBuilder::getOptionLabels($question);
        $optionColors = DistributionProfileBuilder::getOptionColors($question);
        $optionIds = DistributionProfileBuilder::getOptionIds($question);

        try {
            $aggregatedDist = DistributionRulesRegistry::compute($rule, $distributions);
        } catch (\Exception $e) {
            return ['error' => 'Error computing result: ' . $e->getMessage()];
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
            'rule_name' => DistributionRulesRegistry::RULES[$rule]['name'] ?? $rule,
            'distribution' => $distribution,
            'total_responses' => count($distributions),
        ];
    }
}
