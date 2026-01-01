<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

class ParticipatoryBudgetingProfileBuilder
{
    /**
     * Build an instance data for ParticipatoryBudgetingRules
     *
     * @param Question $question The PB question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @param float $budget Total budget
     * @return array
     */
    public static function buildInstance(Question $question, array $responses, float $budget): array
    {
        $question->loadOptions();
        $options = $question->options;

        $projectIds = [];
        $costs = [];
        $approvers = [];

        foreach ($options as $option) {
            $id = (string) $option->id;
            $projectIds[] = $id;
            $costs[$id] = (float) ($option->features['cost'] ?? 0);
            $approvers[$id] = [];
        }

        $voterIds = [];
        foreach ($responses as $response) {
            $voterId = (string) $response->id;
            $voterIds[] = $voterId;

            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if ($value === null || !is_array($value)) {
                continue;
            }

            foreach ($value as $optionId) {
                $optionIdStr = (string) $optionId;
                if (isset($approvers[$optionIdStr])) {
                    $approvers[$optionIdStr][] = $voterId;
                }
            }
        }

        return [
            'voterIds' => $voterIds,
            'projectIds' => $projectIds,
            'costs' => $costs,
            'approvers' => $approvers,
            'budget' => $budget,
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
