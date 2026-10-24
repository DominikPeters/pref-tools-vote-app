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
        return __('condorcet_winner');
    }

    public function getDescription(): string
    {
        return __('report_the_option_that_beats_every_other_option_in_head_to_head_comparisons_if_one_exists');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'crown';
    }

    public function getCategory(): string
    {
        return 'ranking_analysis';
    }

    public function getIntent(): string
    {
        return 'who_wins';
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
            'message' => __('report_no_condorcet_winner_exists_there_is_a_cycle_in_pairwise_preferences'),
            'message_key' => 'report_no_condorcet_winner_exists_there_is_a_cycle_in_pairwise_preferences',
            'total_responses' => count($responses),
        ];
    }
}
