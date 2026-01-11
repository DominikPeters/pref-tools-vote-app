<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

// Include the DistributionAggregation library
require_once __DIR__ . '/../../lib/distribution-aggregation/DistributionAggregation.php';

class DistributionProfileBuilder
{
    /**
     * Default color palette for distribution visualization
     */
    private const DEFAULT_COLORS = [
        '#3498db', // Blue
        '#e74c3c', // Red
        '#2ecc71', // Green
        '#f39c12', // Orange
        '#9b59b6', // Purple
        '#1abc9c', // Teal
        '#e67e22', // Dark Orange
        '#34495e', // Dark Gray
        '#16a085', // Dark Teal
        '#c0392b', // Dark Red
        '#27ae60', // Dark Green
        '#8e44ad', // Dark Purple
        '#2980b9', // Dark Blue
        '#d35400', // Rust
        '#7f8c8d', // Gray
    ];

    /**
     * Convert distribution question responses to normalized distributions
     *
     * @param Question $question The distribution question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return array Array of distributions, each [optionId => fraction]
     */
    public static function fromDistributionResponses(Question $question, array $responses): array
    {
        $question->loadOptions();

        $distributions = [];

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if (!is_array($value) || empty($value)) {
                continue;
            }

            // Convert string keys to integers and ensure numeric values
            $distribution = [];
            foreach ($value as $optionId => $points) {
                $distribution[(int)$optionId] = (float)$points;
            }

            // Skip if distribution is empty or all zeros
            if (array_sum($distribution) <= 0) {
                continue;
            }

            $distributions[] = $distribution;
        }

        return $distributions;
    }

    /**
     * Get option labels for display
     *
     * @param Question $question The distribution question
     * @return array [optionId => label]
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

    /**
     * Get option IDs in order
     *
     * @param Question $question The distribution question
     * @return array Array of option IDs
     */
    public static function getOptionIds(Question $question): array
    {
        $question->loadOptions();

        $ids = [];
        foreach ($question->options as $option) {
            $ids[] = $option->id;
        }

        return $ids;
    }

    /**
     * Get option colors for visualization
     *
     * Uses colors from option features if available, otherwise generates from palette
     *
     * @param Question $question The distribution question
     * @return array [optionId => color]
     */
    public static function getOptionColors(Question $question): array
    {
        $question->loadOptions();

        $colors = [];
        $colorIndex = 0;

        foreach ($question->options as $option) {
            // Check if option has a custom color in features
            if (!empty($option->features['color'])) {
                $colors[$option->id] = $option->features['color'];
            } else {
                // Use default palette, cycling if needed
                $colors[$option->id] = self::DEFAULT_COLORS[$colorIndex % count(self::DEFAULT_COLORS)];
                $colorIndex++;
            }
        }

        return $colors;
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
