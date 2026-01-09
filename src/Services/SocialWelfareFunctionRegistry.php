<?php

namespace App\Services;

// Load pref_voting library
require_once __DIR__ . '/../../lib/pref_voting/autoload.php';

use PrefVoting\ScoringMethods;
use PrefVoting\C1Methods;
use PrefVoting\IterativeMethods;
use PrefVoting\SocialWelfareFunctions;

/**
 * Centralized registry of Rank Aggregation Rules (Social Welfare Functions) organized by profile type.
 *
 * Each rule specifies:
 * - name: Human-readable name
 * - description: Brief explanation of the method
 * - default: Whether this rule should be pre-selected or shown prominently
 */
class SocialWelfareFunctionRegistry
{
    /**
     * Rank Aggregation Rules for complete linear rankings (Profile)
     * Used for: ranking question type only (for now)
     */
    public const RANKING_SWFS = [
        // Default SWFs (shown first, pre-selected in multi-rule comparison)
        'kemeny_young' => [
            'name' => 'Kemeny-Young',
            'description' => 'Finds the ranking that minimizes the total number of pairwise disagreements with voters',
            'default' => true,
        ],
        'borda_ranking' => [
            'name' => 'Borda Ranking',
            'description' => 'Ranks candidates by their total Borda score',
            'default' => true,
        ],
        'irv_ranking' => [
            'name' => 'Instant Runoff Ranking',
            'description' => 'Ranks candidates in the order they are eliminated in IRV',
            'default' => true,
        ],

        // Additional SWFs
        'copeland_ranking' => [
            'name' => 'Copeland Ranking',
            'description' => 'Ranks candidates by their number of pairwise victories',
            'default' => false,
        ],
        'plurality_ranking' => [
            'name' => 'Plurality Ranking',
            'description' => 'Ranks candidates by their number of first-place votes',
            'default' => false,
        ],
        'anti_plurality_ranking' => [
            'name' => 'Anti-Plurality Ranking',
            'description' => 'Ranks candidates by their number of last-place votes (fewer is better)',
            'default' => false,
        ],
        'squared_kemeny' => [
            'name' => 'Squared Kemeny',
            'description' => 'A variation of Kemeny-Young that uses squared distances',
            'default' => false,
        ],
    ];

    /**
     * Get the pref_voting SWF factory for a ranking SWF
     */
    public static function getRankingMethod(string $swf): ?callable
    {
        return match ($swf) {
            'kemeny_young' => SocialWelfareFunctions::kemenyYoung(),
            'squared_kemeny' => SocialWelfareFunctions::squaredKemeny(),
            'copeland_ranking' => C1Methods::copelandRanking(),
            'irv_ranking' => IterativeMethods::instantRunoffRanking(),
            'plurality_ranking' => ScoringMethods::pluralityRanking(),
            'borda_ranking' => ScoringMethods::bordaRanking(),
            'anti_plurality_ranking' => ScoringMethods::antiPluralityRanking(),
            default => null,
        };
    }

    /**
     * Get SWFs for a specific question type
     *
     * @param string $questionType The question type
     * @return array Array of SWFs with metadata
     */
    public static function getSWFsForQuestionType(string $questionType): array
    {
        return match ($questionType) {
            'ranking' => self::RANKING_SWFS,
            default => [],
        };
    }

    /**
     * Get the SWF factory for a rule, auto-detecting profile type
     */
    public static function getMethod(string $swf, string $questionType): ?callable
    {
        $swfs = self::getSWFsForQuestionType($questionType);
        if (!isset($swfs[$swf])) {
            return null;
        }

        if ($questionType === 'ranking') {
            return self::getRankingMethod($swf);
        }

        return null;
    }

    /**
     * Get default SWFs for a question type
     */
    public static function getDefaultSWFs(string $questionType): array
    {
        $swfs = self::getSWFsForQuestionType($questionType);
        $defaults = [];
        foreach ($swfs as $key => $swf) {
            if ($swf['default'] ?? false) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }

    /**
     * Get SWFs formatted for select options (sorted with defaults first)
     */
    public static function getSWFsAsOptions(string $questionType): array
    {
        $swfs = self::getSWFsForQuestionType($questionType);
        $options = [];

        // Add defaults first
        foreach ($swfs as $key => $swf) {
            if ($swf['default'] ?? false) {
                $options[] = [
                    'value' => $key,
                    'label' => $swf['name'],
                    'description' => $swf['description'] ?? '',
                    'default' => true,
                ];
            }
        }

        // Then non-defaults
        foreach ($swfs as $key => $swf) {
            if (!($swf['default'] ?? false)) {
                $options[] = [
                    'value' => $key,
                    'label' => $swf['name'],
                    'description' => $swf['description'] ?? '',
                    'default' => false,
                ];
            }
        }

        return $options;
    }
}
