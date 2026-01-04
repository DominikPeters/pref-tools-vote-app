<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ApportionmentProfileBuilder;
use App\Services\ApportionmentRulesRegistry;

class ApportionmentMultiRuleComparisonReport extends BaseReport
{
    public function getType(): string
    {
        return 'apportionment_multi_rule_comparison';
    }

    public function getName(): string
    {
        return 'Apportionment Multi-Rule Comparison';
    }

    public function getDescription(): string
    {
        return 'Compares seat allocations under multiple apportionment methods';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['single_choice'];
    }

    public function getIcon(): string
    {
        return 'table';
    }

    public function getCategory(): string
    {
        return 'apportionment';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => 'Apportionment Methods to Compare',
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'apportionmentRules',
                ],
                [
                    'name' => 'seats',
                    'type' => 'number',
                    'label' => 'Total Seats',
                    'required' => true,
                    'default' => 10,
                    'min' => 1,
                ],
                [
                    'name' => 'include_user_options',
                    'type' => 'checkbox',
                    'label' => 'Include user-added "Other" options',
                    'required' => false,
                    'default' => true,
                    'dependsOn' => ['field' => 'question.settings.allowOther', 'value' => true],
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $seats = (int) ($config['seats'] ?? 10);
        $selectedRules = $config['rules'] ?? [];
        $excludeUserAdded = !($config['include_user_options'] ?? true);

        if (empty($selectedRules)) {
            foreach (ApportionmentRulesRegistry::RULES as $key => $rule) {
                if ($rule['default'] ?? false) {
                    $selectedRules[] = $key;
                }
            }
        }

        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, $responses, $seats, $excludeUserAdded);
        if (array_sum($instance->votes) === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        $results = [];
        foreach ($selectedRules as $ruleKey) {
            try {
                $res = ApportionmentRulesRegistry::compute($ruleKey, $instance, false);
                $results[] = [
                    'rule' => $ruleKey,
                    'rule_name' => ApportionmentRulesRegistry::RULES[$ruleKey]['name'] ?? $ruleKey,
                    'allocation' => $res['representatives'],
                    'ties' => $res['ties'] ?? false,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        $options = [];
        foreach ($instance->votes as $i => $votes) {
            $options[] = [
                'name' => $instance->partyNames[$i],
                'votes' => $votes,
            ];
        }

        return [
            'results' => $results,
            'options' => $options,
            'seats' => $seats,
            'total_votes' => array_sum($instance->votes),
        ];
    }
}
