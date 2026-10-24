<?php

namespace App\Services;

require_once __DIR__ . '/ApportionmentProfileBuilder.php';

use Apportionment\Apportionment;
use Apportionment\Rule;

class ApportionmentRulesRegistry
{
    public const RULES = [
        'hamilton' => [
            'name' => 'Hamilton / Largest Remainder',
            'description' => 'Allocates seats by lower quotas and then by largest remainders',
            'default' => true,
        ],
        'dhondt' => [
            'name' => "D'Hondt / Jefferson",
            'description' => "Divisor method with divisors 1, 2, 3... Favors options with more votes",
            'default' => true,
        ],
        'saintelague' => [
            'name' => 'Sainte-Laguë / Webster',
            'description' => 'Divisor method with divisors 1, 3, 5... Neutral towards vote counts',
            'default' => true,
        ],
        'huntington' => [
            'name' => 'Huntington-Hill',
            'description' => 'Divisor method using geometric means. Used for US House apportionment',
            'default' => false,
        ],
        'dean' => [
            'name' => 'Dean',
            'description' => 'Divisor method using harmonic means',
            'default' => false,
        ],
        'adams' => [
            'name' => 'Adams',
            'description' => 'Divisor method favoring options with fewer votes. Divisors 0, 1, 2...',
            'default' => false,
        ],
        'quota' => [
            'name' => 'Quota',
            'description' => "Balinski and Young's Quota method. Satisfies lower and upper quota",
            'default' => false,
        ],
    ];

    /**
     * Compute an apportionment rule
     */
    public static function compute(string $rule, \Apportionment\Instance $instance, bool $buildExplanation = false): array
    {
        return Apportionment::compute($rule, $instance, $buildExplanation);
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
            'hamilton' => ['name' => __('rule_hamilton_largest_remainder'), 'description' => __('rule_allocates_seats_by_lower_quotas_and_then_by_largest_remainders')],
            'dhondt' => ['name' => __('rule_d_hondt_jefferson'), 'description' => __('rule_divisor_method_with_divisors_1_2_3_favors_options_with_more_votes')],
            'saintelague' => ['name' => __('rule_sainte_lagu_webster'), 'description' => __('rule_divisor_method_with_divisors_1_3_5_neutral_towards_vote_counts')],
            'huntington' => ['name' => __('rule_huntington_hill'), 'description' => __('rule_divisor_method_using_geometric_means_used_for_us_house_apportionment')],
            'dean' => ['name' => __('rule_dean'), 'description' => __('rule_divisor_method_using_harmonic_means')],
            'adams' => ['name' => __('rule_adams'), 'description' => __('rule_divisor_method_favoring_options_with_fewer_votes_divisors_0_1_2')],
            'quota' => ['name' => __('rule_quota'), 'description' => __('rule_balinski_and_young_s_quota_method_satisfies_lower_and_upper_quota')],
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
