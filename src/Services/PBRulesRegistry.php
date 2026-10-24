<?php

namespace App\Services;

use PB\ParticipatoryBudgetingRules;

/**
 * Registry for Participatory Budgeting voting rules.
 */
class PBRulesRegistry
{
    public const RULES = [
        'mes' => [
            'name' => 'Method of Equal Shares',
            'description' => 'A proportional rule for participatory budgeting based on voter budgets',
            'default' => true,
        ],
        'greedy' => [
            'name' => 'Greedy (Utilitarian)',
            'description' => 'Selects projects with the most approvals until the budget is exhausted',
            'default' => false,
        ],
    ];

    /**
     * Compute a PB rule
     */
    public static function compute(string $rule, array $instance, array $params): array
    {
        $params['rule'] = $rule;
        return match ($rule) {
            'mes', 'greedy' => ParticipatoryBudgetingRules::compute($instance, $params),
            default => throw new \InvalidArgumentException(__('error_unknown_pb_rule_rule', ['rule' => $rule])),
        };
    }

    /**
     * Get rules formatted for select options
     */
    public static function getRulesAsOptions(): array
    {
        $options = [];
        foreach (self::localizeMetadata(self::RULES) as $key => $rule) {
            $options[] = [
                'value' => $key,
                'label' => $rule['name'],
                'description' => $rule['description'] ?? '',
                'default' => $rule['default'] ?? false,
            ];
        }
        return $options;
    }

    /** Localize display metadata without changing stable rule identifiers or constants. */
    private static function localizeMetadata(array $rules): array
    {
        $labels = [
            'mes' => ['name' => __('rule_method_of_equal_shares'), 'description' => __('rule_a_proportional_rule_for_participatory_budgeting_based_on_voter_budgets')],
            'greedy' => ['name' => __('rule_greedy_utilitarian'), 'description' => __('rule_selects_projects_with_the_most_approvals_until_the_budget_is_exhausted')],
        ];
        foreach ($rules as $key => &$rule) {
            if (isset($labels[$key])) $rule = array_replace($rule, $labels[$key]);
        }
        return $rules;
    }

    public static function getRules(): array
    {
        return self::localizeMetadata(self::RULES);
    }
}
