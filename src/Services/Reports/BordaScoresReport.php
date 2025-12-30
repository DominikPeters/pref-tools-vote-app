<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class BordaScoresReport extends BaseReport
{
    public function getType(): string
    {
        return 'borda_scores';
    }

    public function getName(): string
    {
        return 'Borda Scores';
    }

    public function getDescription(): string
    {
        return 'Point totals using the Borda count method';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking'];
    }

    public function getIcon(): string
    {
        return 'chart-bar';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);

        // Get Borda scores from the profile
        $bordaScores = $profile->bordaScores();

        $scores = [];
        $maxScore = 0;

        // Map candidate indices back to option IDs
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        foreach ($bordaScores as $candidateIndex => $score) {
            $optionId = $indexToOptionId[$candidateIndex] ?? $candidateIndex;
            $scores[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? $profile->cmap[$candidateIndex] ?? "Option {$candidateIndex}",
                'score' => $score,
            ];
            $maxScore = max($maxScore, $score);
        }

        // Sort by score descending
        usort($scores, fn($a, $b) => $b['score'] - $a['score']);

        return [
            'scores' => $scores,
            'max_score' => $maxScore,
            'total_responses' => count($responses),
        ];
    }
}
