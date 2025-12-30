<?php

namespace App\Services;

// Load pref_voting library
require_once __DIR__ . '/../../pref_voting/autoload.php';

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
            'ranking' => self::RANKING_RULES,
            'ranking_truncated' => self::TRUNCATED_RANKING_RULES,
            'ranking_with_ties' => self::RANKING_WITH_TIES_RULES,
            'grade', 'star' => self::GRADE_RULES,
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
}
