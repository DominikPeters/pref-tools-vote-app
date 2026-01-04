<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class PairwiseMarginsReport extends BaseReport
{
    public function getType(): string
    {
        return 'pairwise_margins';
    }

    public function getName(): string
    {
        return 'Pairwise Margins';
    }

    public function getDescription(): string
    {
        return 'Graph showing head-to-head margins between candidates';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'diagram-project';
    }

    public function getCategory(): string
    {
        return 'ranking_analysis';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);

        // Get the margin matrix from the profile
        $marginMatrix = $profile->getMarginMatrix();

        $question->loadOptions();
        $candidates = [];
        $indexToOptionId = [];

        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
            $candidates[] = [
                'id' => $option->id,
                'label' => $option->label,
                'index' => $index,
            ];
        }

        // Build edges (only positive margins, from winner to loser)
        $edges = [];
        $n = count($candidates);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $margin = $marginMatrix[$i][$j] ?? 0;

                if ($margin > 0) {
                    // i beats j
                    $edges[] = [
                        'from' => $indexToOptionId[$i],
                        'to' => $indexToOptionId[$j],
                        'margin' => $margin,
                    ];
                } elseif ($margin < 0) {
                    // j beats i
                    $edges[] = [
                        'from' => $indexToOptionId[$j],
                        'to' => $indexToOptionId[$i],
                        'margin' => abs($margin),
                    ];
                }
                // If margin is 0, it's a tie - no edge
            }
        }

        // Check for Condorcet winner
        $condorcetWinner = $profile->condorcetWinner();
        $condorcetWinnerId = null;
        if ($condorcetWinner !== null) {
            $condorcetWinnerId = $indexToOptionId[$condorcetWinner] ?? null;
        }

        return [
            'candidates' => $candidates,
            'edges' => $edges,
            'condorcet_winner' => $condorcetWinnerId,
            'total_responses' => count($responses),
        ];
    }
}
