<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

// Load pref_voting library
require_once __DIR__ . '/../../pref_voting/autoload.php';

use PrefVoting\Profile;
use PrefVoting\ProfileWithTies;
use PrefVoting\Ranking;

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
    public static function getOptionLabels(Question $question): array
    {
        $question->loadOptions();
        $labels = [];
        foreach ($question->options as $option) {
            $labels[$option->id] = $option->label;
        }
        return $labels;
    }
}
