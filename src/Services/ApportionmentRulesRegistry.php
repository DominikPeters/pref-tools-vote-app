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
