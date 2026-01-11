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

    /**
     * Get default rules (for multi-rule comparison)
     */
    public static function getDefaultRules(): array
    {
        $defaults = [];
        foreach (self::RULES as $key => $rule) {
            if ($rule['default'] ?? false) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }
}
