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
        return __('report_participatory_budgeting_rule_winner');
    }

    public function getDescription(): string
    {
        return __('report_shows_which_projects_are_funded_within_the_budget_under_a_participatory_budgeting_rule_e_g_method_of');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['participatory_budgeting'];
    }

    public function getIcon(): string
    {
        return 'calculator';
    }

    public function getCategory(): string
    {
        return 'participatory_budgeting';
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
                    'name' => 'total_budget',
                    'type' => 'number',
                    'label' => __('total_budget'),
                    'required' => true,
                    'default' => 1000,
                    'min' => 0,
                ],
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => __('voting_rule'),
                    'required' => true,
                    'options' => PBRulesRegistry::getRulesAsOptions(),
                    'default' => 'mes',
                ],
                [
                    'name' => 'completion',
                    'type' => 'select',
                    'label' => __('report_completion_method'),
                    'required' => true,
                    'dependsOn' => ['field' => 'rule', 'value' => 'mes'],
                    'options' => [
                        ['value' => 'none', 'label' => __('report_none_just_mes')],
                        ['value' => 'utilitarian', 'label' => __('report_utilitarian')],
                        ['value' => 'add1', 'label' => __('report_add1_budget_increment')],
                        ['value' => 'add1u', 'label' => __('report_add1_utilitarian')],
                    ],
                    'default' => 'utilitarian',
                ],
                [
                    'name' => 'tie_breaking',
                    'type' => 'select',
                    'label' => __('report_tie_breaking'),
                    'required' => true,
                    'options' => [
                        ['value' => 'maxVotes', 'label' => __('report_higher_vote_count')],
                        ['value' => 'minCost', 'label' => __('report_lower_cost')],
                        ['value' => 'maxCost', 'label' => __('report_higher_cost')],
                    ],
                    'default' => 'maxVotes',
                ],
                [
                    'name' => 'comparison',
                    'type' => 'select',
                    'label' => __('report_comparison_step'),
                    'required' => true,
                    'dependsOn' => ['field' => 'rule', 'value' => 'mes'],
                    'options' => [
                        ['value' => 'none', 'label' => __('report_none')],
                        ['value' => 'satisfaction', 'label' => __('report_voter_satisfaction')],
                        ['value' => 'exclusionRatio', 'label' => __('report_exclusion_ratio')],
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
            return $this->errorResult('error_no_valid_responses_for_this_question');
        }

        try {
            $result = PBRulesRegistry::compute($rule, $instance, $params);
        } catch (\Exception $e) {
            return $this->errorResult('error_error_computing_result_detail', ['detail' => $e->getMessage()]);
        }

        $winners = $result['winners'];
        $notes = $result['notes'];
        if (isset($notes['comparison_counts'])) {
            $notes['comparison_key'] = 'explanation_pb_comparison';
            $notes['comparison_params'] = [
                'greedy' => (int) $notes['comparison_counts']['prefers_greedy'],
                'mes' => (int) $notes['comparison_counts']['prefers_mes'],
            ];
        }

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
            'rule_name' => PBRulesRegistry::getRules()[$rule]['name'] ?? $rule,
            'total_budget' => $totalBudget,
            'winners' => $formattedWinners,
            'notes' => $notes,
            'total_responses' => count($instance['voterIds']),
        ];
    }
}
