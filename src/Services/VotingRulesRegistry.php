<?php

namespace App\Services;

// Load pref_voting library
require_once __DIR__ . '/../../lib/pref_voting/autoload.php';

use PrefVoting\ScoringMethods;
use PrefVoting\C1Methods;
use PrefVoting\MarginBasedMethods;
use PrefVoting\IterativeMethods;
use PrefVoting\GradeMethods;

/**
 * Centralized registry of voting rules organized by profile type.
 *
 * Each rule specifies:
 * - name: Human-readable name
 * - method: Callable that returns the pref_voting voting method
 * - default: Whether this rule should be pre-selected and shown prominently
 */
class VotingRulesRegistry
{
    /**
     * Voting rules for complete linear rankings (Profile)
     * Used for: ranking question type only
     */
    public const RANKING_RULES = [
        // Default rules (shown first, pre-selected in multi-rule comparison)
        'schulze' => [
            'name' => 'Schulze (Beat Path)',
            'description' => 'Finds the candidate with the strongest path of pairwise victories',
            'default' => true,
        ],
        'ranked_pairs' => [
            'name' => 'Ranked Pairs',
            'description' => 'Tideman\'s method: locks in pairwise victories from strongest to weakest',
            'default' => true,
        ],
        'irv' => [
            'name' => 'Instant Runoff (IRV)',
            'description' => 'Eliminates lowest candidates and redistributes votes until majority',
            'default' => true,
        ],
        'borda' => [
            'name' => 'Borda Count',
            'description' => 'Awards points based on position in each ranking',
            'default' => true,
        ],

        // Additional rules
        'plurality' => [
            'name' => 'Plurality',
            'description' => 'Counts only first-place votes',
            'default' => false,
        ],
        'copeland' => [
            'name' => 'Copeland',
            'description' => 'Counts pairwise wins minus losses',
            'default' => false,
        ],
        'minimax' => [
            'name' => 'Minimax',
            'description' => 'Minimizes the worst pairwise defeat',
            'default' => false,
        ],
        'split_cycle' => [
            'name' => 'Split Cycle',
            'description' => 'Defeats only count if not part of a cycle',
            'default' => false,
        ],
        'stable_voting' => [
            'name' => 'Stable Voting',
            'description' => 'Resistant to strategic voting through stability criterion',
            'default' => false,
        ],
        'top_cycle' => [
            'name' => 'Top Cycle (Smith Set)',
            'description' => 'Returns the smallest set that beats all others',
            'default' => false,
        ],
    ];

    /**
     * Voting rules for truncated/incomplete linear rankings (ProfileWithTies)
     * Used for: ranking_truncated question type
     *
     * Note: Excludes borda, plurality, and IRV since they require complete linear rankings.
     * Only includes margin-based methods that work correctly with incomplete ballots.
     */
    public const TRUNCATED_RANKING_RULES = [
        // Default rules (shown first, pre-selected in multi-rule comparison)
        'schulze' => [
            'name' => 'Schulze (Beat Path)',
            'description' => 'Finds the candidate with the strongest path of pairwise victories',
            'default' => true,
        ],
        'ranked_pairs' => [
            'name' => 'Ranked Pairs',
            'description' => 'Tideman\'s method: locks in pairwise victories from strongest to weakest',
            'default' => true,
        ],

        // Additional rules
        'copeland' => [
            'name' => 'Copeland',
            'description' => 'Counts pairwise wins minus losses',
            'default' => false,
        ],
        'minimax' => [
            'name' => 'Minimax',
            'description' => 'Minimizes the worst pairwise defeat',
            'default' => false,
        ],
        'split_cycle' => [
            'name' => 'Split Cycle',
            'description' => 'Defeats only count if not part of a cycle',
            'default' => false,
        ],
        'stable_voting' => [
            'name' => 'Stable Voting',
            'description' => 'Resistant to strategic voting through stability criterion',
            'default' => false,
        ],
        'top_cycle' => [
            'name' => 'Top Cycle (Smith Set)',
            'description' => 'Returns the smallest set that beats all others',
            'default' => false,
        ],
    ];

    /**
     * Voting rules for rankings with ties (ProfileWithTies)
     * Used for: ranking_with_ties question type
     *
     * Note: Excludes borda, plurality, and IRV since they require complete linear rankings.
     * Only includes margin-based methods that work correctly with tied ballots.
     */
    public const RANKING_WITH_TIES_RULES = [
        // Default rules (shown first, pre-selected in multi-rule comparison)
        'schulze' => [
            'name' => 'Schulze (Beat Path)',
            'description' => 'Finds the candidate with the strongest path of pairwise victories',
            'default' => true,
        ],
        'ranked_pairs' => [
            'name' => 'Ranked Pairs',
            'description' => 'Tideman\'s method: locks in pairwise victories from strongest to weakest',
            'default' => true,
        ],

        // Additional rules
        'copeland' => [
            'name' => 'Copeland',
            'description' => 'Counts pairwise wins minus losses',
            'default' => false,
        ],
        'minimax' => [
            'name' => 'Minimax',
            'description' => 'Minimizes the worst pairwise defeat',
            'default' => false,
        ],
        'split_cycle' => [
            'name' => 'Split Cycle',
            'description' => 'Defeats only count if not part of a cycle',
            'default' => false,
        ],
        'stable_voting' => [
            'name' => 'Stable Voting',
            'description' => 'Resistant to strategic voting through stability criterion',
            'default' => false,
        ],
        'top_cycle' => [
            'name' => 'Top Cycle (Smith Set)',
            'description' => 'Returns the smallest set that beats all others',
            'default' => false,
        ],
    ];

    /**
     * Voting rules for grade profiles (GradeProfile)
     * Used for: grade, star question types
     */
    public const GRADE_RULES = [
        'majority_judgment' => [
            'name' => 'Majority Judgment',
            'description' => 'Winner has the highest median grade with tiebreaker',
            'default' => true,
        ],
        'score_sum' => [
            'name' => 'Score Voting (Sum)',
            'description' => 'Highest total score wins',
            'default' => true,
        ],
        'score_mean' => [
            'name' => 'Score Voting (Mean)',
            'description' => 'Highest average score wins',
            'default' => false,
        ],
        'star' => [
            'name' => 'STAR Voting',
            'description' => 'Top two by score, then pairwise runoff',
            'default' => false,
        ],
    ];

    /**
     * Get the pref_voting method callable for a ranking rule
     */
    public static function getRankingMethod(string $rule): ?callable
    {
        return match ($rule) {
            'plurality' => ScoringMethods::plurality(),
            'borda' => ScoringMethods::borda(),
            'copeland' => C1Methods::copeland(),
            'schulze' => MarginBasedMethods::beatPath(),
            'ranked_pairs' => MarginBasedMethods::rankedPairs(),
            'irv' => IterativeMethods::instantRunoff(),
            'minimax' => MarginBasedMethods::minimax(),
            'split_cycle' => MarginBasedMethods::splitCycle(),
            'stable_voting' => MarginBasedMethods::stableVoting(),
            'top_cycle' => C1Methods::topCycle(),
            default => null,
        };
    }

    /**
     * Get the pref_voting method callable for a grade rule
     */
    public static function getGradeMethod(string $rule): ?callable
    {
        return match ($rule) {
            'majority_judgment' => GradeMethods::majorityJudgement(),
            'score_sum' => GradeMethods::scoreVoting('sum'),
            'score_mean' => GradeMethods::scoreVoting('mean'),
            'star' => GradeMethods::star(),
            default => null,
        };
    }

    /**
     * Get rules for a specific question type
     *
     * @param string $questionType The question type
     * @return array Array of rules with metadata
     */
    public static function getRulesForQuestionType(string $questionType): array
    {
        return match ($questionType) {
            'ranking' => self::localizeMetadata(self::RANKING_RULES),
            'ranking_truncated' => self::localizeMetadata(self::TRUNCATED_RANKING_RULES),
            'ranking_with_ties' => self::localizeMetadata(self::RANKING_WITH_TIES_RULES),
            'grade', 'star' => self::localizeMetadata(self::GRADE_RULES),
            default => [],
        };
    }

    /**
     * Get the method callable for a rule, auto-detecting profile type
     */
    public static function getMethod(string $rule, string $questionType): ?callable
    {
        $rules = self::getRulesForQuestionType($questionType);
        if (!isset($rules[$rule])) {
            return null;
        }

        if (in_array($questionType, ['ranking', 'ranking_truncated', 'ranking_with_ties'])) {
            return self::getRankingMethod($rule);
        }

        if (in_array($questionType, ['grade', 'star'])) {
            return self::getGradeMethod($rule);
        }

        return null;
    }

    /**
     * Get default rules for a question type
     */
    public static function getDefaultRules(string $questionType): array
    {
        $rules = self::getRulesForQuestionType($questionType);
        $defaults = [];
        foreach ($rules as $key => $rule) {
            if ($rule['default'] ?? false) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }

    /**
     * Get rules formatted for select options (sorted with defaults first)
     */
    public static function getRulesAsOptions(string $questionType): array
    {
        $rules = self::getRulesForQuestionType($questionType);
        $options = [];

        // Add defaults first
        foreach ($rules as $key => $rule) {
            if ($rule['default'] ?? false) {
                $options[] = [
                    'value' => $key,
                    'label' => $rule['name'],
                    'description' => $rule['description'] ?? '',
                    'default' => true,
                ];
            }
        }

        // Then non-defaults
        foreach ($rules as $key => $rule) {
            if (!($rule['default'] ?? false)) {
                $options[] = [
                    'value' => $key,
                    'label' => $rule['name'],
                    'description' => $rule['description'] ?? '',
                    'default' => false,
                ];
            }
        }

        return $options;
    }

    /**
     * Get the profile type needed for a question type
     */
    public static function getProfileType(string $questionType): string
    {
        return match ($questionType) {
            'ranking', 'ranking_truncated', 'ranking_with_ties' => 'ranking',
            'grade', 'star' => 'grade',
            default => 'unknown',
        };
    }

    /** Localize display metadata without changing stable rule identifiers or constants. */
    private static function localizeMetadata(array $rules): array
    {
        $labels = [
            'schulze' => ['name' => __('rule_schulze_beat_path'), 'description' => __('rule_finds_the_candidate_with_the_strongest_path_of_pairwise_victories')],
            'ranked_pairs' => ['name' => __('rule_ranked_pairs'), 'description' => __('rule_tideman_s_method_locks_in_pairwise_victories_from_strongest_to_weakest')],
            'irv' => ['name' => __('rule_instant_runoff_irv'), 'description' => __('rule_eliminates_lowest_candidates_and_redistributes_votes_until_majority')],
            'borda' => ['name' => __('rule_borda_count'), 'description' => __('rule_awards_points_based_on_position_in_each_ranking')],
            'plurality' => ['name' => __('rule_plurality'), 'description' => __('rule_counts_only_first_place_votes')],
            'copeland' => ['name' => __('rule_copeland'), 'description' => __('rule_counts_pairwise_wins_minus_losses')],
            'minimax' => ['name' => __('rule_minimax'), 'description' => __('rule_minimizes_the_worst_pairwise_defeat')],
            'split_cycle' => ['name' => __('rule_split_cycle'), 'description' => __('rule_defeats_only_count_if_not_part_of_a_cycle')],
            'stable_voting' => ['name' => __('rule_stable_voting'), 'description' => __('rule_resistant_to_strategic_voting_through_stability_criterion')],
            'top_cycle' => ['name' => __('rule_top_cycle_smith_set'), 'description' => __('rule_returns_the_smallest_set_that_beats_all_others')],
            'majority_judgment' => ['name' => __('report_type_majority_judgment'), 'description' => __('rule_winner_has_the_highest_median_grade_with_tiebreaker')],
            'score_sum' => ['name' => __('rule_score_voting_sum'), 'description' => __('rule_highest_total_score_wins')],
            'score_mean' => ['name' => __('rule_score_voting_mean'), 'description' => __('rule_highest_average_score_wins')],
            'star' => ['name' => __('rule_star_voting'), 'description' => __('rule_top_two_by_score_then_pairwise_runoff')],
        ];
        foreach ($rules as $key => &$rule) {
            if (isset($labels[$key])) $rule = array_replace($rule, $labels[$key]);
        }
        return $rules;
    }
}
