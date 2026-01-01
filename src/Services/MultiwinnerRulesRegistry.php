<?php

namespace App\Services;

// abcvoting is autoloaded in ABCProfileBuilder or can be added here
require_once __DIR__ . '/ABCProfileBuilder.php';
require_once __DIR__ . '/ProfileBuilder.php';

use AbcVoting\SimpleRules;
use AbcVoting\ThieleRules;
use AbcVoting\SequentialRules;
use AbcVoting\ProportionalRules;
use PrefVoting\ProportionalMethods;

/**
 * Registry for Multi-Winner voting rules (including ABC and STV).
 */
class MultiwinnerRulesRegistry
{
    /**
     * Rules using the abcvoting library (for approval-based input)
     */
    public const ABC_RULES = [
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
     * Rules using the pref_voting library (for ranking-based input)
     */
    public const PREF_MULTIWINNER_RULES = [
        'approval_stv' => [
            'name' => 'Approval-STV',
            'description' => 'STV variant specifically designed for rankings with ties',
            'default' => true,
            'only_for' => ['ranking_with_ties'],
        ],
        'stv_scottish' => [
            'name' => 'Scottish STV',
            'description' => 'Standard Single Transferable Vote as used in Scotland',
            'default' => true,
        ],
        'stv_meek' => [
            'name' => 'Meek\'s STV',
            'description' => 'Advanced STV method used in New Zealand, handles ties and truncations gracefully',
            'default' => true,
        ],
        'stv_warren' => [
            'name' => 'Warren\'s STV',
            'description' => 'Variant of Meek\'s method with different surplus distribution',
            'default' => false,
        ],
        'stv_nb' => [
            'name' => 'New Brunswick STV (STV-NB)',
            'description' => 'STV variant used in New Brunswick',
            'default' => false,
        ],
        'stv_wig' => [
            'name' => 'Weighted Inclusive Gregory (WIG)',
            'description' => 'Standard STV variant using WIG surplus transfers',
            'default' => false,
        ],
        'cpo_stv' => [
            'name' => 'CPO-STV',
            'description' => 'Comparison of Pairs of Outcomes STV (Condorcet-consistent multi-winner rule)',
            'default' => false,
        ],
    ];

    /**
     * Combined rules metadata
     */
    public static function getRules(string $type = 'approval'): array
    {
        if ($type === 'approval') {
            return self::ABC_RULES;
        }
        return self::PREF_MULTIWINNER_RULES;
    }

    /**
     * Compute a rule with normalized arguments and return format
     */
    public static function compute(string $rule, $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        // Handle abcvoting rules
        if (isset(self::ABC_RULES[$rule])) {
            $result = match ($rule) {
                'av' => SimpleRules::computeAv($profile, $committeesize, $resolute),
                'sav' => SimpleRules::computeSav($profile, $committeesize, $resolute),
                'pav' => ThieleRules::computePav($profile, $committeesize, $resolute),
                'seqpav' => SequentialRules::computeSeqPav($profile, $committeesize, $resolute, $returnDetailed),
                'seqcc' => SequentialRules::computeSeqCc($profile, $committeesize, $resolute, $returnDetailed),
                'seqslav' => SequentialRules::computeSeqSlav($profile, $committeesize, $resolute, $returnDetailed),
                'seqphragmen' => \AbcVoting\PhragmenRules::computeSeqPhragmen($profile, $committeesize, [], null, $resolute, $returnDetailed),
                'equal-shares' => ProportionalRules::computeEqualShares($profile, $committeesize, $resolute, null, 'seqphragmen', $returnDetailed),
                default => throw new \InvalidArgumentException("Unknown ABC rule: $rule"),
            };

            // Normalize return format for SequentialRules when returnDetailed is true
            if ($returnDetailed && in_array($rule, ['seqpav', 'seqcc', 'seqslav'])) {
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

        // Handle pref_voting rules
        if (isset(self::PREF_MULTIWINNER_RULES[$rule])) {
            $method = self::getPrefMethod($rule, $committeesize);
            if (!$method) {
                throw new \InvalidArgumentException("Unknown pref_voting rule: $rule");
            }

            // pref_voting methods return a single winner (array of candidates)
            // or we might need to handle ties if we had an irresolute version.
            // Currently, these methods return one committee.
            $committee = $method($profile);
            return [$committee];
        }

        throw new \InvalidArgumentException("Unknown rule: $rule");
    }

    /**
     * Get the method callable for a pref_voting multiwinner rule
     */
    private static function getPrefMethod(string $rule, int $committeesize): ?callable
    {
        return match ($rule) {
            'approval_stv' => ProportionalMethods::approvalStv($committeesize),
            'stv_scottish' => ProportionalMethods::stvScottish($committeesize),
            'stv_meek' => ProportionalMethods::stvMeek($committeesize),
            'stv_warren' => ProportionalMethods::stvWarren($committeesize),
            'stv_nb' => ProportionalMethods::stvNb($committeesize),
            'stv_wig' => ProportionalMethods::stvWig($committeesize),
            'cpo_stv' => ProportionalMethods::cpoStv($committeesize),
            default => null,
        };
    }

    /**
     * Check if a rule has an explanation (only supported for some ABC rules currently)
     */
    public static function hasExplanation(string $rule): bool
    {
        return self::ABC_RULES[$rule]['has_explanation'] ?? false;
    }

    /**
     * Get rules formatted for select options
     */
    public static function getRulesAsOptions(string $questionType = 'approval'): array
    {
        $type = in_array($questionType, ['ranking', 'ranking_truncated', 'ranking_with_ties']) ? 'ranking' : 'approval';
        $rules = self::getRules($type);
        
        $options = [];
        foreach ($rules as $key => $rule) {
            // Filter out rules that are only for specific question types
            if (isset($rule['only_for']) && !in_array($questionType, $rule['only_for'])) {
                continue;
            }

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