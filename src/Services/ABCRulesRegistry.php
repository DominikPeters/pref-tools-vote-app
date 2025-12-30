<?php

namespace App\Services;

// abcvoting is autoloaded in ABCProfileBuilder or can be added here
require_once __DIR__ . '/ABCProfileBuilder.php';

use AbcVoting\SimpleRules;
use AbcVoting\ThieleRules;
use AbcVoting\SequentialRules;
use AbcVoting\ProportionalRules;

/**
 * Registry for Multi-Winner (ABC) voting rules.
 */
class ABCRulesRegistry
{
    public const RULES = [
        'av' => [
            'name' => 'Approval Voting (AV)',
            'description' => 'Selects candidates with the most approvals',
            'default' => true,
        ],
        'sav' => [
            'name' => 'Satisfaction Approval Voting (SAV)',
            'description' => 'Weight of each vote is divided by the number of candidates approved',
            'default' => false,
        ],
        'pav' => [
            'name' => 'Proportional Approval Voting (PAV)',
            'description' => 'Proportional rule using Thiele scores (brute force)',
            'default' => true,
        ],
        'seqpav' => [
            'name' => 'Sequential PAV',
            'description' => 'Greedy approximation of PAV',
            'default' => true,
            'has_explanation' => true,
        ],
        'seqcc' => [
            'name' => 'Sequential Chamberlin-Courant',
            'description' => 'Greedy approximation of CC (maximizes coverage)',
            'default' => false,
            'has_explanation' => true,
        ],
        'seqslav' => [
            'name' => 'Sequential Sainte-Laguë AV',
            'description' => 'Greedy approximation of SLAV',
            'default' => false,
            'has_explanation' => true,
        ],
        'seqphragmen' => [
            'name' => 'Sequential Phragmén',
            'description' => 'Phragmén\'s sequential method for proportional representation',
            'default' => false,
            'has_explanation' => true,
        ],
        'equal-shares' => [
            'name' => 'Method of Equal Shares (Rule X)',
            'description' => 'Modern proportional rule based on voter budgets',
            'default' => true,
            'has_explanation' => true,
        ],
    ];

    /**
     * Compute a rule with normalized arguments and return format
     */
    public static function compute(string $rule, \AbcVoting\Profile $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        $result = match ($rule) {
            'av' => SimpleRules::computeAv($profile, $committeesize, $resolute),
            'sav' => SimpleRules::computeSav($profile, $committeesize, $resolute),
            'pav' => ThieleRules::computePav($profile, $committeesize, $resolute),
            'seqpav' => SequentialRules::computeSeqPav($profile, $committeesize, $resolute, $returnDetailed),
            'seqcc' => SequentialRules::computeSeqCc($profile, $committeesize, $resolute, $returnDetailed),
            'seqslav' => SequentialRules::computeSeqSlav($profile, $committeesize, $resolute, $returnDetailed),
            'seqphragmen' => \AbcVoting\PhragmenRules::computeSeqPhragmen($profile, $committeesize, [], null, $resolute, $returnDetailed),
            'equal-shares' => ProportionalRules::computeEqualShares($profile, $committeesize, $resolute, null, 'seqphragmen', $returnDetailed),
            default => throw new \InvalidArgumentException("Unknown rule: $rule"),
        };

        // Normalize return format for SequentialRules when returnDetailed is true
        if ($returnDetailed && in_array($rule, ['seqpav', 'seqcc', 'seqslav'])) {
            // SequentialRules returns ['committees' => [...], 'detailed_info' => [...]]
            // We want [ ['committee' => C1, 'detailed_info' => D], ... ]
            $committees = $result['committees'] ?? [];
            $info = $result['detailed_info'] ?? [];
            $normalized = [];
            foreach ($committees as $committee) {
                $normalized[] = [
                    'committee' => $committee,
                    'detailed_info' => $info,
                ];
            }
            return $normalized;
        }

        return $result;
    }

    /**
     * Get the method callable for an ABC rule
     * 
     * The returned callable expects ($profile, $committeesize, $resolute)
     */
    public static function getMethod(string $rule): ?callable
    {
        return match ($rule) {
            'av' => [SimpleRules::class, 'computeAv'],
            'sav' => [SimpleRules::class, 'computeSav'],
            'pav' => [ThieleRules::class, 'computePav'],
            'seqpav' => [SequentialRules::class, 'computeSeqPav'],
            'seqcc' => [SequentialRules::class, 'computeSeqCc'],
            'seqslav' => [SequentialRules::class, 'computeSeqSlav'],
            'seqphragmen' => [SequentialRules::class, 'computeSeqPhragmen'],
            'equal-shares' => [ProportionalRules::class, 'computeEqualShares'],
            default => null,
        };
    }

    /**
     * Check if a rule has an explanation
     */
    public static function hasExplanation(string $rule): bool
    {
        return self::RULES[$rule]['has_explanation'] ?? false;
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
