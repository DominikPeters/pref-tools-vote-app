<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ParticipatoryBudgetingProfileBuilder;
use App\Services\PBRulesRegistry;

class PBWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'pb_winner';
    }

    public function getName(): string
    {
        return 'Participatory Budgeting Rule Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the winning projects under a selected participatory budgeting rule (e.g., Method of Equal Shares)';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['participatory_budgeting'];
    }

    public function getIcon(): string
    {
        return 'calculator';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'total_budget',
                    'type' => 'number',
                    'label' => 'Total Budget',
                    'required' => true,
                    'default' => 1000,
                    'min' => 0,
                ],
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => 'Voting Rule',
                    'required' => true,
                    'options' => PBRulesRegistry::getRulesAsOptions(),
                    'default' => 'mes',
                ],
                [
                    'name' => 'completion',
                    'type' => 'select',
                    'label' => 'Completion Method',
                    'required' => true,
                    'dependsOn' => ['field' => 'rule', 'value' => 'mes'],
                    'options' => [
                        ['value' => 'none', 'label' => 'None (just MES)'],
                        ['value' => 'utilitarian', 'label' => 'Utilitarian'],
                        ['value' => 'add1', 'label' => 'Add1 (Budget Increment)'],
                        ['value' => 'add1u', 'label' => 'Add1 + Utilitarian'],
                    ],
                    'default' => 'utilitarian',
                ],
                [
                    'name' => 'tie_breaking',
                    'type' => 'select',
                    'label' => 'Tie-breaking',
                    'required' => true,
                    'options' => [
                        ['value' => 'maxVotes', 'label' => 'Higher vote count'],
                        ['value' => 'minCost', 'label' => 'Lower cost'],
                        ['value' => 'maxCost', 'label' => 'Higher cost'],
                    ],
                    'default' => 'maxVotes',
                ],
                [
                    'name' => 'comparison',
                    'type' => 'select',
                    'label' => 'Comparison Step',
                    'required' => true,
                    'dependsOn' => ['field' => 'rule', 'value' => 'mes'],
                    'options' => [
                        ['value' => 'none', 'label' => 'None'],
                        ['value' => 'satisfaction', 'label' => 'Voter Satisfaction'],
                        ['value' => 'exclusionRatio', 'label' => 'Exclusion Ratio'],
                    ],
                    'default' => 'none',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $totalBudget = (float) ($config['total_budget'] ?? 0);
        $rule = $config['rule'] ?? 'mes';
        
        $params = [
            'rule' => $rule,
            'completion' => $config['completion'] ?? 'utilitarian',
            'tieBreaking' => [$config['tie_breaking'] ?? 'maxVotes'],
            'comparison' => $config['comparison'] ?? 'none',
            'accuracy' => 'floats',
            'increment' => 0.01,
            'add1options' => ['exhaustive'], // Default to exhaustive Add1 if used
        ];

        $instance = ParticipatoryBudgetingProfileBuilder::buildInstance($question, $responses, $totalBudget);
        
        if (empty($instance['voterIds'])) {
            return ['error' => 'No valid responses for this question.'];
        }

        try {
            $result = PBRulesRegistry::compute($rule, $instance, $params);
        } catch (\Exception $e) {
            return ['error' => 'Error computing result: ' . $e->getMessage()];
        }

        $winners = $result['winners'];
        $notes = $result['notes'];

        $optionLabels = ParticipatoryBudgetingProfileBuilder::getOptionLabels($question);
        $question->loadOptions();
        $optionMap = [];
        foreach ($question->options as $option) {
            $optionMap[$option->id] = $option;
        }

        $formattedWinners = [];
        foreach ($winners as $optionId) {
            $option = $optionMap[$optionId] ?? null;
            $formattedWinners[] = [
                'option_id' => $optionId,
                'option' => $optionLabels[$optionId] ?? "Option {$optionId}",
                'cost' => $option ? (float)($option->features['cost'] ?? 0) : 0,
            ];
        }

        return [
            'rule' => $rule,
            'rule_name' => PBRulesRegistry::RULES[$rule]['name'] ?? $rule,
            'total_budget' => $totalBudget,
            'winners' => $formattedWinners,
            'notes' => $notes,
            'total_responses' => count($instance['voterIds']),
        ];
    }
}
