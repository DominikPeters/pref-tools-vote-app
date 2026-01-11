<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\DistributionProfileBuilder;
use App\Services\DistributionRulesRegistry;

class DistributionMultiRuleComparisonReport extends BaseReport
{
    public function getType(): string
    {
        return 'distribution_multi_rule_comparison';
    }

    public function getName(): string
    {
        return 'Distribution Multi-Rule Comparison';
    }

    public function getDescription(): string
    {
        return 'Compares aggregated distributions under multiple budget aggregation rules';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['distribution'];
    }

    public function getIcon(): string
    {
        return 'table';
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
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => 'Aggregation Rules to Compare',
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'distributionRules',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $selectedRules = $config['rules'] ?? [];

        // Use default rules if none selected
        if (empty($selectedRules)) {
            $selectedRules = DistributionRulesRegistry::getDefaultRules();
        }

        // Build distributions from responses
        $distributions = DistributionProfileBuilder::fromDistributionResponses($question, $responses);

        if (empty($distributions)) {
            return ['error' => 'No valid responses for this question.'];
        }

        // Get option metadata
        $optionLabels = DistributionProfileBuilder::getOptionLabels($question);
        $optionColors = DistributionProfileBuilder::getOptionColors($question);
        $optionIds = DistributionProfileBuilder::getOptionIds($question);

        // Compute results for each rule
        $results = [];
        foreach ($selectedRules as $ruleKey) {
            try {
                $aggregatedDist = DistributionRulesRegistry::compute($ruleKey, $distributions);

                // Format distribution with preserved option order
                $distribution = [];
                foreach ($optionIds as $optionId) {
                    $fraction = $aggregatedDist[$optionId] ?? 0.0;
                    $distribution[] = [
                        'option_id' => $optionId,
                        'fraction' => round($fraction, 6),
                        'percentage' => round($fraction * 100, 2),
                    ];
                }

                $results[] = [
                    'rule' => $ruleKey,
                    'rule_name' => DistributionRulesRegistry::RULES[$ruleKey]['name'] ?? $ruleKey,
                    'distribution' => $distribution,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Build options metadata
        $options = [];
        foreach ($optionIds as $optionId) {
            $options[] = [
                'option_id' => $optionId,
                'name' => $optionLabels[$optionId] ?? "Option {$optionId}",
                'color' => $optionColors[$optionId] ?? '#999999',
            ];
        }

        return [
            'results' => $results,
            'options' => $options,
            'total_responses' => count($distributions),
        ];
    }
}
