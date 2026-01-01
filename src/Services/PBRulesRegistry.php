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
            default => throw new \InvalidArgumentException("Unknown PB rule: $rule"),
        };
    }

    /**
     * Get rules formatted for select options
     */
    public static function getRulesAsOptions(): array
    {
        $options = [];
        foreach (self::RULES as $key => $rule) {
            $options[] = [
                'value' => $key,
                'label' => $rule['name'],
                'description' => $rule['description'] ?? '',
                'default' => $rule['default'] ?? false,
            ];
        }
        return $options;
    }
}
