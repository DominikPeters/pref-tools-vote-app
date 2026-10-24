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
            return self::localizeMetadata(self::ABC_RULES);
        }
        return self::localizeMetadata(self::PREF_MULTIWINNER_RULES);
    }

    /**
     * Compute a rule with normalized arguments and return format
     */
    public static function compute(string $rule, $profile, int $committeesize, bool $resolute = true, bool $returnDetailed = false): array
    {
        // Handle abcvoting rules
        if (isset(self::localizeMetadata(self::ABC_RULES)[$rule])) {
            $result = match ($rule) {
                'av' => SimpleRules::computeAv($profile, $committeesize, $resolute),
                'sav' => SimpleRules::computeSav($profile, $committeesize, $resolute),
                'pav' => ThieleRules::computePav($profile, $committeesize, $resolute),
                'seqpav' => SequentialRules::computeSeqPav($profile, $committeesize, $resolute, $returnDetailed),
                'seqcc' => SequentialRules::computeSeqCc($profile, $committeesize, $resolute, $returnDetailed),
                'seqslav' => SequentialRules::computeSeqSlav($profile, $committeesize, $resolute, $returnDetailed),
                'seqphragmen' => \AbcVoting\PhragmenRules::computeSeqPhragmen($profile, $committeesize, [], null, $resolute, $returnDetailed),
                'equal-shares' => ProportionalRules::computeEqualShares($profile, $committeesize, $resolute, null, 'seqphragmen', $returnDetailed),
                default => throw new \InvalidArgumentException(__('error_unknown_abc_rule_rule', ['rule' => $rule])),
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
        if (isset(self::localizeMetadata(self::PREF_MULTIWINNER_RULES)[$rule])) {
            $method = self::getPrefMethod($rule, $committeesize);
            if (!$method) {
                throw new \InvalidArgumentException(__('error_unknown_pref_voting_rule_rule', ['rule' => $rule]));
            }

            // pref_voting methods return a single winner (array of candidates)
            // or we might need to handle ties if we had an irresolute version.
            // Currently, these methods return one committee.
            $committee = $method($profile);
            return [$committee];
        }

        throw new \InvalidArgumentException(__('error_unknown_rule_rule', ['rule' => $rule]));
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
        return self::localizeMetadata(self::ABC_RULES)[$rule]['has_explanation'] ?? false;
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

    /** Localize display metadata without changing stable rule identifiers or constants. */
    private static function localizeMetadata(array $rules): array
    {
        $labels = [
            'av' => ['name' => __('rule_approval_voting_av'), 'description' => __('rule_selects_candidates_with_the_most_approvals')],
            'sav' => ['name' => __('rule_satisfaction_approval_voting_sav'), 'description' => __('rule_weight_of_each_vote_is_divided_by_the_number_of_candidates_approved')],
            'pav' => ['name' => __('rule_proportional_approval_voting_pav'), 'description' => __('rule_proportional_rule_using_thiele_scores_brute_force')],
            'seqpav' => ['name' => __('rule_sequential_pav'), 'description' => __('rule_greedy_approximation_of_pav')],
            'seqcc' => ['name' => __('rule_sequential_chamberlin_courant'), 'description' => __('rule_greedy_approximation_of_cc_maximizes_coverage')],
            'seqslav' => ['name' => __('rule_sequential_sainte_lagu_av'), 'description' => __('rule_greedy_approximation_of_slav')],
            'seqphragmen' => ['name' => __('rule_sequential_phragm_n'), 'description' => __('rule_phragm_n_s_sequential_method_for_proportional_representation')],
            'approval_stv' => ['name' => __('rule_approval_stv'), 'description' => __('rule_stv_variant_specifically_designed_for_rankings_with_ties')],
            'stv_scottish' => ['name' => __('rule_scottish_stv'), 'description' => __('rule_standard_single_transferable_vote_as_used_in_scotland')],
            'stv_meek' => ['name' => __('rule_meek_s_stv'), 'description' => __('rule_advanced_stv_method_used_in_new_zealand_handles_ties_and_truncations_gracefully')],
            'stv_warren' => ['name' => __('rule_warren_s_stv'), 'description' => __('rule_variant_of_meek_s_method_with_different_surplus_distribution')],
            'stv_nb' => ['name' => __('rule_new_brunswick_stv_stv_nb'), 'description' => __('rule_stv_variant_used_in_new_brunswick')],
            'stv_wig' => ['name' => __('rule_weighted_inclusive_gregory_wig'), 'description' => __('rule_standard_stv_variant_using_wig_surplus_transfers')],
            'cpo_stv' => ['name' => __('rule_cpo_stv'), 'description' => __('rule_comparison_of_pairs_of_outcomes_stv_condorcet_consistent_multi_winner_rule')],
        ];
        foreach ($rules as $key => &$rule) {
            if (isset($labels[$key])) $rule = array_replace($rule, $labels[$key]);
        }
        return $rules;
    }
}
