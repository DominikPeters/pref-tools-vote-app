<?php

namespace App\Services;

require_once __DIR__ . '/DistributionProfileBuilder.php';

class DistributionRulesRegistry
{
    public const RULES = [
        'mean' => [
            'name' => 'Mean Rule',
            'description' => 'Simple average of input distributions. Easy to understand but not strategyproof.',
            'default' => true,
        ],
        'median' => [
            'name' => 'Median Rule',
            'description' => 'Moving phantom mechanism with uniform phantom progression. Strategyproof and Pareto efficient.',
            'default' => true,
        ],
        'independent_markets' => [
            'name' => 'Independent Markets',
            'description' => 'Models each alternative as an independent market share. Strategyproof.',
            'default' => true,
        ],
        'ladder' => [
            'name' => 'Ladder Rule',
            'description' => 'Moving phantom mechanism with ladder-shaped progression. Strategyproof and project-fair.',
            'default' => true,
        ],
    ];

    /**
     * Compute a distribution aggregation rule
     *
     * @param string $rule One of: 'mean', 'median', 'independent_markets', 'ladder'
     * @param array $distributions Array of distributions, each [optionId => points/fraction]
     * @return array Aggregated distribution [optionId => fraction]
     */
    public static function compute(string $rule, array $distributions): array
    {
        return \DistributionAggregation::compute($rule, $distributions);
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

    /**
     * Get default rules (for multi-rule comparison)
     */
    public static function getDefaultRules(): array
    {
        $defaults = [];
        foreach (self::localizeMetadata(self::RULES) as $key => $rule) {
            if ($rule['default'] ?? false) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }

    /** Localize display metadata without changing stable rule identifiers or constants. */
    private static function localizeMetadata(array $rules): array
    {
        $labels = [
            'mean' => ['name' => __('dist_rule_mean'), 'description' => __('rule_simple_average_of_input_distributions_easy_to_understand_but_not_strategyproof')],
            'median' => ['name' => __('dist_rule_median'), 'description' => __('rule_moving_phantom_mechanism_with_uniform_phantom_progression_strategyproof_and_pareto_efficient')],
            'independent_markets' => ['name' => __('dist_rule_independent_markets'), 'description' => __('rule_models_each_alternative_as_an_independent_market_share_strategyproof')],
            'ladder' => ['name' => __('dist_rule_ladder'), 'description' => __('rule_moving_phantom_mechanism_with_ladder_shaped_progression_strategyproof_and_project_fair')],
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
