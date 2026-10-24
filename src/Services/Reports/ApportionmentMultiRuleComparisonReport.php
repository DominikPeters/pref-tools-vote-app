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
        return __('report_type_apportionment_multi_rule_comparison');
    }

    public function getDescription(): string
    {
        return __('report_compares_seat_allocations_under_different_apportionment_methods');
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

    public function getIntent(): string
    {
        return 'compare_methods';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => __('report_apportionment_methods_to_compare'),
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'apportionmentRules',
                ],
                [
                    'name' => 'seats',
                    'type' => 'number',
                    'label' => __('total_seats'),
                    'required' => true,
                    'default' => 10,
                    'min' => 1,
                ],
                [
                    'name' => 'include_user_options',
                    'type' => 'checkbox',
                    'label' => __('report_include_user_added_other_options'),
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
            return $this->errorResult('error_no_valid_responses_for_this_question');
        }

        $results = [];
        foreach ($selectedRules as $ruleKey) {
            try {
                $res = ApportionmentRulesRegistry::compute($ruleKey, $instance, false);
                $results[] = [
                    'rule' => $ruleKey,
                    'rule_name' => ApportionmentRulesRegistry::getRules()[$ruleKey]['name'] ?? $ruleKey,
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
