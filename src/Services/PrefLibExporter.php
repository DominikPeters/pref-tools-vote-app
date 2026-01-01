<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

/**
 * Exports poll question responses to PrefLib format.
 *
 * Supports:
 * - SOC (Strict Orders Complete): ranking questions
 * - SOI (Strict Orders Incomplete): ranking_truncated, single_choice
 * - TOI (Tie Orders Incomplete): ranking_with_ties
 * - CAT (Categorical Preferences): approval, yes_no_abstain, grade, star
 *
 * @see https://www.preflib.org/format
 */
class PrefLibExporter
{
    /**
     * Map question types to PrefLib data types
     */
    private const TYPE_MAP = [
        'single_choice' => 'soi',
        'ranking' => 'soc',
        'ranking_truncated' => 'soi',
        'ranking_with_ties' => 'toi',
        'approval' => 'cat',
        'yes_no_abstain' => 'cat',
        'grade' => 'cat',
        'star' => 'cat',
    ];

    /**
     * Export question responses to PrefLib format
     *
     * @param Question $question The question to export
     * @param Response[] $responses Array of Response objects with loaded answers
     * @param array $metadata Optional metadata overrides (title, description, exclude_user_added, etc.)
     * @return string|null PrefLib formatted string, or null if question type not supported
     */
    public static function export(Question $question, array $responses, array $metadata = []): ?string
    {
        $dataType = self::TYPE_MAP[$question->type] ?? null;
        if ($dataType === null) {
            return null;
        }

        $excludeUserAdded = $metadata['exclude_user_added'] ?? false;

        if (in_array($dataType, ['soc', 'soi', 'toi'])) {
            return self::exportOrdinal($question, $responses, $dataType, $metadata, $excludeUserAdded);
        } else {
            return self::exportCategorical($question, $responses, $metadata, $excludeUserAdded);
        }
    }

    /**
     * Get the PrefLib data type for a question
     */
    public static function getDataType(Question $question): ?string
    {
        return self::TYPE_MAP[$question->type] ?? null;
    }

    /**
     * Check if a question type is supported for PrefLib export
     */
    public static function isSupported(Question $question): bool
    {
        return isset(self::TYPE_MAP[$question->type]);
    }

    /**
     * Export ordinal preferences (SOC, SOI, TOI)
     */
    private static function exportOrdinal(
        Question $question,
        array $responses,
        string $dataType,
        array $metadata,
        bool $excludeUserAdded = false
    ): string {
        $question->loadOptions();

        // Filter options if needed
        $options = [];
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $options[] = $option;
        }

        // Build alternative map: option ID -> 1-indexed number
        $altMap = [];
        $altNames = [];
        $idx = 1;
        foreach ($options as $option) {
            $altMap[$option->id] = $idx;
            $altNames[$idx] = $option->label;
            $idx++;
        }

        $numAlternatives = count($options);

        // Collect and aggregate ballots
        $ballots = [];
        $counts = [];

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if ($value === null) {
                continue;
            }

            $ballot = self::formatOrdinalBallot($question, $value, $altMap);
            if ($ballot === null) {
                continue;
            }

            // Aggregate identical ballots
            $key = $ballot;
            if (isset($counts[$key])) {
                $counts[$key]++;
            } else {
                $ballots[] = $ballot;
                $counts[$key] = 1;
            }
        }

        $numVoters = array_sum($counts);
        $numUniqueOrders = count($ballots);

        // Build output
        $lines = self::buildOrdinalHeader(
            $dataType,
            $numAlternatives,
            $numVoters,
            $numUniqueOrders,
            $altNames,
            $metadata,
            $question
        );

        // Add ballots
        foreach ($ballots as $ballot) {
            $lines[] = $counts[$ballot] . ': ' . $ballot;
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single ordinal ballot
     *
     * @return string|null Formatted ballot string (e.g., "1, 3, 2" or "1, {2, 3}")
     */
    private static function formatOrdinalBallot(Question $question, mixed $value, array $altMap): ?string
    {
        if ($question->type === 'single_choice') {
            // Single choice: just one alternative
            if (!is_int($value) || !isset($altMap[$value])) {
                return null;
            }
            return (string) $altMap[$value];
        }

        if ($question->type === 'ranking_with_ties') {
            // Format: { optionId: rank, ... }
            if (!is_array($value) || empty($value)) {
                return null;
            }

            // Group alternatives by rank
            $rankGroups = [];
            foreach ($value as $optionId => $rank) {
                if (!isset($altMap[$optionId])) {
                    continue;
                }
                $rank = (int) $rank;
                if (!isset($rankGroups[$rank])) {
                    $rankGroups[$rank] = [];
                }
                $rankGroups[$rank][] = $altMap[$optionId];
            }

            if (empty($rankGroups)) {
                return null;
            }

            // Sort by rank and format
            ksort($rankGroups);
            $parts = [];
            foreach ($rankGroups as $alts) {
                sort($alts);
                if (count($alts) === 1) {
                    $parts[] = (string) $alts[0];
                } else {
                    $parts[] = '{' . implode(', ', $alts) . '}';
                }
            }

            return implode(', ', $parts);
        }

        // Linear ranking: [optionId1, optionId2, ...]
        if (!is_array($value) || empty($value)) {
            return null;
        }

        $alts = [];
        foreach ($value as $optionId) {
            if (isset($altMap[$optionId])) {
                $alts[] = $altMap[$optionId];
            }
        }

        if (empty($alts)) {
            return null;
        }

        return implode(', ', $alts);
    }

    /**
     * Build metadata header for ordinal formats
     */
    private static function buildOrdinalHeader(
        string $dataType,
        int $numAlternatives,
        int $numVoters,
        int $numUniqueOrders,
        array $altNames,
        array $metadata,
        Question $question
    ): array {
        $title = $metadata['title'] ?? $question->text;
        $description = $metadata['description'] ?? ($question->description ?? '');
        $fileName = $metadata['file_name'] ?? 'export.' . $dataType;

        $lines = [
            '# FILE NAME: ' . $fileName,
            '# TITLE: ' . self::sanitizeHeaderValue($title),
            '# DESCRIPTION: ' . self::sanitizeHeaderValue($description),
            '# DATA TYPE: ' . $dataType,
            '# MODIFICATION TYPE: original',
            '# RELATES TO: ',
            '# RELATED FILES: ',
            '# PUBLICATION DATE: ' . date('Y-m-d'),
            '# MODIFICATION DATE: ' . date('Y-m-d'),
            '# NUMBER ALTERNATIVES: ' . $numAlternatives,
            '# NUMBER VOTERS: ' . $numVoters,
            '# NUMBER UNIQUE ORDERS: ' . $numUniqueOrders,
        ];

        foreach ($altNames as $idx => $name) {
            $lines[] = '# ALTERNATIVE NAME ' . $idx . ': ' . self::sanitizeHeaderValue($name);
        }

        return $lines;
    }

    /**
     * Export categorical preferences (CAT)
     */
    private static function exportCategorical(
        Question $question,
        array $responses,
        array $metadata,
        bool $excludeUserAdded = false
    ): string {
        $question->loadOptions();

        // Filter options if needed
        $options = [];
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $options[] = $option;
        }

        // Build alternative map: option ID -> 1-indexed number
        $altMap = [];
        $altNames = [];
        $idx = 1;
        foreach ($options as $option) {
            $altMap[$option->id] = $idx;
            $altNames[$idx] = $option->label;
            $idx++;
        }

        $numAlternatives = count($options);

        // Determine categories based on question type
        $categories = self::getCategoriesForQuestion($question);
        $numCategories = count($categories);

        // Collect and aggregate ballots
        $ballots = [];
        $counts = [];

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if ($value === null) {
                continue;
            }

            $ballot = self::formatCategoricalBallot($question, $value, $altMap, $categories);
            if ($ballot === null) {
                continue;
            }

            // Aggregate identical ballots
            $key = $ballot;
            if (isset($counts[$key])) {
                $counts[$key]++;
            } else {
                $ballots[] = $ballot;
                $counts[$key] = 1;
            }
        }

        $numVoters = array_sum($counts);
        $numUniquePreferences = count($ballots);

        // Build output
        $lines = self::buildCategoricalHeader(
            $numAlternatives,
            $numVoters,
            $numUniquePreferences,
            $numCategories,
            $categories,
            $altNames,
            $metadata,
            $question
        );

        // Add ballots
        foreach ($ballots as $ballot) {
            $lines[] = $counts[$ballot] . ': ' . $ballot;
        }

        return implode("\n", $lines);
    }

    /**
     * Get categories for a categorical question
     *
     * @return array Category names in order (first = best/highest)
     */
    private static function getCategoriesForQuestion(Question $question): array
    {
        switch ($question->type) {
            case 'approval':
                return ['Yes', 'No'];

            case 'yes_no_abstain':
                $allowAbstain = $question->settings['allowAbstain'] ?? true;
                return $allowAbstain ? ['Yes', 'No', 'Abstain'] : ['Yes', 'No'];

            case 'grade':
                return ProfileBuilder::getGradesForQuestion($question);

            case 'star':
                $starCount = $question->settings['starCount'] ?? 5;
                // Stars from highest to lowest (e.g., [5, 4, 3, 2, 1])
                return array_map('strval', range($starCount, 1, -1));

            default:
                return [];
        }
    }

    /**
     * Format a single categorical ballot
     *
     * @return string|null Formatted ballot string (e.g., "1, {2, 3}, {}" or "{1, 2}, {3}")
     */
    private static function formatCategoricalBallot(
        Question $question,
        mixed $value,
        array $altMap,
        array $categories
    ): ?string {
        // Build category -> alternatives mapping
        $categoryAlts = [];
        foreach ($categories as $cat) {
            $categoryAlts[$cat] = [];
        }

        switch ($question->type) {
            case 'approval':
                // Value is array of approved option IDs
                if (!is_array($value)) {
                    return null;
                }
                foreach ($value as $optionId) {
                    if (isset($altMap[$optionId])) {
                        $categoryAlts['Yes'][] = $altMap[$optionId];
                    }
                }
                // Omit unrated alternatives (don't put in 'No' category)
                break;

            case 'yes_no_abstain':
                // Value is { optionId: 'yes'|'no'|'abstain', ... }
                if (!is_array($value)) {
                    return null;
                }
                foreach ($value as $optionId => $vote) {
                    if (!isset($altMap[$optionId])) {
                        continue;
                    }
                    $vote = strtolower((string) $vote);
                    if ($vote === 'yes' || $vote === 'y') {
                        $categoryAlts['Yes'][] = $altMap[$optionId];
                    } elseif ($vote === 'no' || $vote === 'n') {
                        $categoryAlts['No'][] = $altMap[$optionId];
                    } elseif (($vote === 'abstain' || $vote === 'a') && isset($categoryAlts['Abstain'])) {
                        $categoryAlts['Abstain'][] = $altMap[$optionId];
                    }
                    // Omit unrated alternatives
                }
                break;

            case 'grade':
                // Value is { optionId: gradeValue, ... }
                if (!is_array($value)) {
                    return null;
                }
                foreach ($value as $optionId => $gradeValue) {
                    if (!isset($altMap[$optionId])) {
                        continue;
                    }
                    // Match grade case-insensitively
                    $matchedCat = self::matchCategory($gradeValue, $categories);
                    if ($matchedCat !== null) {
                        $categoryAlts[$matchedCat][] = $altMap[$optionId];
                    }
                    // Omit unrated alternatives
                }
                break;

            case 'star':
                // Value is { optionId: starRating, ... }
                if (!is_array($value)) {
                    return null;
                }
                foreach ($value as $optionId => $rating) {
                    if (!isset($altMap[$optionId])) {
                        continue;
                    }
                    $ratingStr = (string) $rating;
                    if (isset($categoryAlts[$ratingStr])) {
                        $categoryAlts[$ratingStr][] = $altMap[$optionId];
                    }
                    // Omit unrated alternatives
                }
                break;

            default:
                return null;
        }

        // Check if any alternatives were categorized
        $hasAny = false;
        foreach ($categoryAlts as $alts) {
            if (!empty($alts)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            return null;
        }

        // Format ballot
        $parts = [];
        foreach ($categories as $cat) {
            $alts = $categoryAlts[$cat];
            sort($alts);
            if (count($alts) === 0) {
                $parts[] = '{}';
            } elseif (count($alts) === 1) {
                $parts[] = (string) $alts[0];
            } else {
                $parts[] = '{' . implode(', ', $alts) . '}';
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Match a category value case-insensitively
     */
    private static function matchCategory(string $value, array $categories): ?string
    {
        // Direct match first
        if (in_array($value, $categories, true)) {
            return $value;
        }

        // Case-insensitive match
        $valueLower = strtolower($value);
        foreach ($categories as $cat) {
            if (strtolower((string) $cat) === $valueLower) {
                return $cat;
            }
        }

        return null;
    }

    /**
     * Build metadata header for CAT format
     */
    private static function buildCategoricalHeader(
        int $numAlternatives,
        int $numVoters,
        int $numUniquePreferences,
        int $numCategories,
        array $categories,
        array $altNames,
        array $metadata,
        Question $question
    ): array {
        $title = $metadata['title'] ?? $question->text;
        $description = $metadata['description'] ?? ($question->description ?? '');
        $fileName = $metadata['file_name'] ?? 'export.cat';

        $lines = [
            '# FILE NAME: ' . $fileName,
            '# TITLE: ' . self::sanitizeHeaderValue($title),
            '# DESCRIPTION: ' . self::sanitizeHeaderValue($description),
            '# DATA TYPE: cat',
            '# MODIFICATION TYPE: original',
            '# RELATES TO: ',
            '# RELATED FILES: ',
            '# PUBLICATION DATE: ' . date('Y-m-d'),
            '# MODIFICATION DATE: ' . date('Y-m-d'),
            '# NUMBER ALTERNATIVES: ' . $numAlternatives,
            '# NUMBER VOTERS: ' . $numVoters,
            '# NUMBER UNIQUE PREFERENCES: ' . $numUniquePreferences,
            '# NUMBER CATEGORIES: ' . $numCategories,
        ];

        foreach ($categories as $idx => $name) {
            $lines[] = '# CATEGORY NAME ' . ($idx + 1) . ': ' . self::sanitizeHeaderValue((string) $name);
        }

        foreach ($altNames as $idx => $name) {
            $lines[] = '# ALTERNATIVE NAME ' . $idx . ': ' . self::sanitizeHeaderValue($name);
        }

        return $lines;
    }

    /**
     * Sanitize a value for inclusion in a header line
     * (removes newlines and other problematic characters)
     */
    private static function sanitizeHeaderValue(string $value): string
    {
        return preg_replace('/[\r\n]+/', ' ', $value);
    }

    /**
     * Get the answer for a specific question from a response
     */
    private static function getAnswerForQuestion(Response $response, int $questionId): ?\App\Models\Answer
    {
        if (empty($response->answers)) {
            $response->loadAnswers();
        }

        foreach ($response->answers as $answer) {
            if ($answer->questionId === $questionId) {
                return $answer;
            }
        }

        return null;
    }
}
