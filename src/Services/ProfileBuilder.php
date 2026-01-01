<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

// Load pref_voting library
require_once __DIR__ . '/../../pref_voting/autoload.php';

use PrefVoting\Profile;
use PrefVoting\ProfileWithTies;
use PrefVoting\Ranking;
use PrefVoting\GradeProfile;

class ProfileBuilder
{
    /**
     * Build a Profile from ranking responses
     *
     * @param Question $question The ranking question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return Profile|ProfileWithTies
     */
    public static function fromRankingResponses(Question $question, array $responses): Profile|ProfileWithTies
    {
        $question->loadOptions();
        $options = $question->options;

        // Build candidate map: option ID -> index
        $candidateMap = [];
        $candidateNames = [];
        foreach ($options as $index => $option) {
            $candidateMap[$option->id] = $index;
            $candidateNames[$index] = $option->label;
        }

        $rankings = [];
        $rcounts = [];

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if (!is_array($value) || empty($value)) {
                continue;
            }

            // Check if it's a ranking with ties (object format) or linear (array format)
            if ($question->type === 'ranking_with_ties') {
                // Format: { optionId: rank, ... }
                $rankMap = [];
                foreach ($value as $optionId => $rank) {
                    if (isset($candidateMap[$optionId])) {
                        $rankMap[$candidateMap[$optionId]] = (int) $rank;
                    }
                }
                if (!empty($rankMap)) {
                    $key = serialize($rankMap);
                    if (isset($rcounts[$key])) {
                        $rcounts[$key]++;
                    } else {
                        $rankings[] = $rankMap;
                        $rcounts[$key] = 1;
                    }
                }
            } else {
                // Linear ranking: [optionId1, optionId2, ...] in order of preference
                $linear = [];
                foreach ($value as $optionId) {
                    if (isset($candidateMap[$optionId])) {
                        $linear[] = $candidateMap[$optionId];
                    }
                }
                if (!empty($linear)) {
                    $key = serialize($linear);
                    if (isset($rcounts[$key])) {
                        $rcounts[$key]++;
                    } else {
                        $rankings[] = $linear;
                        $rcounts[$key] = 1;
                    }
                }
            }
        }

        // Normalize rcounts to match rankings array indices
        $finalRankings = [];
        $finalCounts = [];
        $idx = 0;
        foreach ($rankings as $ranking) {
            $key = serialize($ranking);
            $finalRankings[] = $ranking;
            $finalCounts[] = $rcounts[$key];
            $idx++;
        }

        // Create appropriate profile type
        if ($question->type === 'ranking_with_ties' || $question->type === 'ranking_truncated') {
            // Convert to Ranking objects for ProfileWithTies
            $rankingObjects = [];
            foreach ($finalRankings as $ranking) {
                if (is_array($ranking) && !empty($ranking)) {
                    // Check if it's already a rank map or a linear array
                    if (array_keys($ranking) !== range(0, count($ranking) - 1)) {
                        // It's a rank map {candidate => rank}
                        $rankingObjects[] = new Ranking($ranking);
                    } else {
                        // It's a linear array, convert to rank map
                        $rankMap = [];
                        foreach ($ranking as $position => $candidate) {
                            $rankMap[$candidate] = $position + 1;
                        }
                        $rankingObjects[] = new Ranking($rankMap);
                    }
                }
            }
            return new ProfileWithTies($rankingObjects, $finalCounts, array_keys($candidateNames), $candidateNames);
        } else {
            // Standard linear rankings
            return new Profile($finalRankings, $finalCounts, $candidateNames);
        }
    }

    /**
     * Get approval counts from responses
     *
     * @param Question $question The approval/single_choice question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return array ['counts' => [optionId => count], 'total' => totalResponses]
     */
    public static function getApprovalCounts(Question $question, array $responses): array
    {
        $question->loadOptions();
        $counts = [];

        // Initialize counts for all options
        foreach ($question->options as $option) {
            $counts[$option->id] = 0;
        }

        $totalResponses = 0;

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            $totalResponses++;

            if ($question->type === 'single_choice') {
                // Single choice: value is a single option ID
                if (is_int($value) && isset($counts[$value])) {
                    $counts[$value]++;
                }
            } else {
                // Approval: value is an array of option IDs
                if (is_array($value)) {
                    foreach ($value as $optionId) {
                        if (isset($counts[$optionId])) {
                            $counts[$optionId]++;
                        }
                    }
                }
            }
        }

        return [
            'counts' => $counts,
            'total' => $totalResponses,
        ];
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

    /**
     * Build option ID to label map
     */
    public static function getOptionLabels(Question $question, bool $excludeUserAdded = false): array
    {
        $question->loadOptions();
        $labels = [];
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $labels[$option->id] = $option->label;
        }
        return $labels;
    }

    /**
     * Get approval counts from responses, optionally excluding user-added options
     *
     * @param Question $question The approval/single_choice question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @param bool $excludeUserAdded Whether to exclude user-added options from results
     * @return array ['counts' => [optionId => count], 'total' => totalResponses]
     */
    public static function getApprovalCountsFiltered(Question $question, array $responses, bool $excludeUserAdded = false): array
    {
        $question->loadOptions();
        $counts = [];
        $includedOptionIds = [];

        // Initialize counts for options (optionally excluding user-added)
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $counts[$option->id] = 0;
            $includedOptionIds[] = $option->id;
        }

        $totalResponses = 0;

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            $totalResponses++;

            if ($question->type === 'single_choice') {
                // Single choice: value is a single option ID
                if (is_int($value) && isset($counts[$value])) {
                    $counts[$value]++;
                }
            } else {
                // Approval: value is an array of option IDs
                if (is_array($value)) {
                    foreach ($value as $optionId) {
                        if (isset($counts[$optionId])) {
                            $counts[$optionId]++;
                        }
                    }
                }
            }
        }

        return [
            'counts' => $counts,
            'total' => $totalResponses,
        ];
    }

    /**
     * Grade presets with their grade order (highest to lowest)
     */
    public const GRADE_PRESETS = [
        'default' => ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Reject'],
        'a-f' => ['A', 'B', 'C', 'D', 'E', 'F'],
        'plus-minus' => ['++', '+', '0', '−', '−−'],
        'pass-fail' => ['Pass', 'Fail'],
    ];

    /**
     * Build a GradeProfile from grade or star responses
     *
     * @param Question $question The grade/star question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return GradeProfile
     */
    public static function fromGradeResponses(Question $question, array $responses): GradeProfile
    {
        $question->loadOptions();
        $options = $question->options;

        // Build candidate map: option ID -> index and names
        $candidates = [];
        $cmap = [];
        foreach ($options as $index => $option) {
            $candidates[] = $index;
            $cmap[$index] = $option->label;
        }

        // Determine grades and their order
        $grades = self::getGradesForQuestion($question);
        $gradeOrder = $grades; // First is highest, last is lowest

        // For star ratings, convert to numeric grades (1 to N)
        if ($question->type === 'star') {
            $starCount = $question->settings['starCount'] ?? 5;
            $grades = range($starCount, 1, -1); // e.g., [5, 4, 3, 2, 1] for 5 stars
            $gradeOrder = $grades;
        }

        // Build grade display map
        $gmap = [];
        foreach ($grades as $g) {
            $gmap[$g] = (string) $g;
        }

        // Create option ID to candidate index mapping
        $optionToCandidateMap = [];
        foreach ($options as $index => $option) {
            $optionToCandidateMap[$option->id] = $index;
        }

        // Collect grade functions from responses
        $gradeMaps = [];
        $gcounts = [];

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if (!is_array($value) || empty($value)) {
                continue;
            }

            // Build grade map: candidate index -> grade
            $gradeMap = [];
            foreach ($value as $optionId => $gradeValue) {
                if (!isset($optionToCandidateMap[$optionId])) {
                    continue;
                }
                $candIdx = $optionToCandidateMap[$optionId];

                // For stars, value is already numeric
                if ($question->type === 'star') {
                    if (is_numeric($gradeValue) && in_array((int)$gradeValue, $grades)) {
                        $gradeMap[$candIdx] = (int) $gradeValue;
                    }
                } else {
                    // For grades, we need to match the grade (stored lowercase) to our grade list
                    $matchedGrade = self::matchGrade($gradeValue, $grades);
                    if ($matchedGrade !== null) {
                        $gradeMap[$candIdx] = $matchedGrade;
                    }
                }
            }

            if (empty($gradeMap)) {
                continue;
            }

            // Aggregate identical grade maps
            $key = serialize($gradeMap);
            if (isset($gcounts[$key])) {
                $gcounts[$key]++;
            } else {
                $gradeMaps[] = $gradeMap;
                $gcounts[$key] = 1;
            }
        }

        // Normalize counts to match grade maps array
        $finalCounts = [];
        foreach ($gradeMaps as $gradeMap) {
            $key = serialize($gradeMap);
            $finalCounts[] = $gcounts[$key];
        }

        // Handle empty case
        if (empty($gradeMaps)) {
            $gradeMaps = [];
            $finalCounts = [];
        }

        return new GradeProfile(
            $gradeMaps,
            $grades,
            $finalCounts,
            $candidates,
            $cmap,
            $gmap,
            $gradeOrder
        );
    }

    /**
     * Get the list of grades for a question (in order from highest to lowest)
     */
    public static function getGradesForQuestion(Question $question): array
    {
        if ($question->type === 'star') {
            $starCount = $question->settings['starCount'] ?? 5;
            return range($starCount, 1, -1);
        }

        // For grade questions, check if using a preset or custom grades
        $settings = $question->settings ?? [];
        $preset = $settings['preset'] ?? 'default';

        if ($preset !== 'custom' && isset(self::GRADE_PRESETS[$preset])) {
            return self::GRADE_PRESETS[$preset];
        }

        // Custom grades from settings
        if (isset($settings['grades']) && is_array($settings['grades'])) {
            return $settings['grades'];
        }

        // Default fallback
        return self::GRADE_PRESETS['default'];
    }

    /**
     * Match a stored grade value (often lowercase) to the actual grade in the list
     */
    private static function matchGrade(string $storedValue, array $grades): mixed
    {
        // Direct match first
        if (in_array($storedValue, $grades, true)) {
            return $storedValue;
        }

        // Case-insensitive match
        $storedLower = strtolower($storedValue);
        foreach ($grades as $grade) {
            if (strtolower((string)$grade) === $storedLower) {
                return $grade;
            }
        }

        return null;
    }

    /**
     * Get Yes/No/Abstain counts from responses
     *
     * @param Question $question The yes_no_abstain question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return array ['counts' => [optionId => ['yes' => n, 'no' => n, 'abstain' => n]], 'total' => n]
     */
    public static function getYNACounts(Question $question, array $responses): array
    {
        $question->loadOptions();
        $counts = [];

        // Initialize counts for all options
        foreach ($question->options as $option) {
            $counts[$option->id] = [
                'yes' => 0,
                'no' => 0,
                'abstain' => 0,
            ];
        }

        $totalResponses = 0;

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if (!is_array($value)) {
                continue;
            }

            $totalResponses++;

            foreach ($value as $optionId => $vote) {
                if (!isset($counts[$optionId])) {
                    continue;
                }

                $vote = strtolower((string) $vote);
                if ($vote === 'yes' || $vote === 'y') {
                    $counts[$optionId]['yes']++;
                } elseif ($vote === 'no' || $vote === 'n') {
                    $counts[$optionId]['no']++;
                } elseif ($vote === 'abstain' || $vote === 'a') {
                    $counts[$optionId]['abstain']++;
                }
            }
        }

        return [
            'counts' => $counts,
            'total' => $totalResponses,
        ];
    }
}
