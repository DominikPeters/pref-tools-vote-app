<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class CondorcetWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'condorcet_winner';
    }

    public function getName(): string
    {
        return 'Condorcet Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the Condorcet winner (beats all others head-to-head) if one exists';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'crown';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);

        // Get Condorcet winner from profile
        $condorcetWinnerIdx = $profile->condorcetWinner();

        // Map index back to option ID
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        if ($condorcetWinnerIdx !== null) {
            $optionId = $indexToOptionId[$condorcetWinnerIdx] ?? $condorcetWinnerIdx;
            $winnerLabel = $labels[$optionId] ?? $profile->cmap[$condorcetWinnerIdx] ?? "Option {$condorcetWinnerIdx}";

            return [
                'exists' => true,
                'winner' => [
                    'option_id' => $optionId,
                    'option' => $winnerLabel,
                ],
                'total_responses' => count($responses),
            ];
        }

        // No Condorcet winner - find the cycle or explain why
        return [
            'exists' => false,
            'winner' => null,
            'message' => 'No Condorcet winner exists (there is a cycle in pairwise preferences)',
            'total_responses' => count($responses),
        ];
    }
}
