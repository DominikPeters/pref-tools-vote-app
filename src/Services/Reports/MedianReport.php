<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class MedianReport extends BaseReport
{
    public function getType(): string
    {
        return 'median';
    }

    public function getName(): string
    {
        return 'Median Choice';
    }

    public function getDescription(): string
    {
        return 'Finds the median option, assuming options are ordered (e.g., price limits, Likert scales). With single-peaked preferences, the median is the Condorcet winner.';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['single_choice'];
    }

    public function getIcon(): string
    {
        return 'git-commit';
    }

    public function getCategory(): string
    {
        return 'single_winner';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'include_user_options',
                    'type' => 'checkbox',
                    'label' => 'Include user-added "Other" options',
                    'required' => false,
                    'default' => true,
                    'dependsOn' => ['field' => 'question.settings.allowOther', 'value' => true],
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $excludeUserAdded = !($config['include_user_options'] ?? true);
        $data = ProfileBuilder::getApprovalCountsFiltered($question, $responses, $excludeUserAdded);
        $labels = ProfileBuilder::getOptionLabels($question, $excludeUserAdded);

        $totalVotes = $data['total'];

        if ($totalVotes === 0) {
            return [
                'medians' => [],
                'is_tie' => false,
                'total_responses' => 0,
                'question_type' => $question->type,
            ];
        }

        // Get options in their natural order (by option ID, which reflects creation order)
        $orderedOptionIds = array_keys($data['counts']);

        // Build cumulative vote counts
        $cumulative = 0;
        $cumulativeCounts = [];
        foreach ($orderedOptionIds as $optionId) {
            $cumulative += $data['counts'][$optionId];
            $cumulativeCounts[$optionId] = $cumulative;
        }

        // Find median position(s)
        // With n voters:
        // - If n is odd: median is at position (n+1)/2
        // - If n is even: medians are at positions n/2 and n/2+1
        $medians = [];

        if ($totalVotes % 2 === 1) {
            // Odd number of voters: single median
            $medianPosition = ($totalVotes + 1) / 2;
            $medianOptionId = $this->findOptionAtPosition($orderedOptionIds, $cumulativeCounts, $medianPosition);
            $medians[] = [
                'option_id' => $medianOptionId,
                'option' => $labels[$medianOptionId] ?? "Option {$medianOptionId}",
                'count' => $data['counts'][$medianOptionId],
            ];
        } else {
            // Even number of voters: find the two middle positions
            $lowerPosition = $totalVotes / 2;
            $upperPosition = $totalVotes / 2 + 1;

            $lowerOptionId = $this->findOptionAtPosition($orderedOptionIds, $cumulativeCounts, $lowerPosition);
            $upperOptionId = $this->findOptionAtPosition($orderedOptionIds, $cumulativeCounts, $upperPosition);

            if ($lowerOptionId === $upperOptionId) {
                // Both middle voters chose the same option
                $medians[] = [
                    'option_id' => $lowerOptionId,
                    'option' => $labels[$lowerOptionId] ?? "Option {$lowerOptionId}",
                    'count' => $data['counts'][$lowerOptionId],
                ];
            } else {
                // Different options: include all options in the interval
                $inInterval = false;
                foreach ($orderedOptionIds as $optionId) {
                    if ($optionId === $lowerOptionId) {
                        $inInterval = true;
                    }
                    if ($inInterval) {
                        $medians[] = [
                            'option_id' => $optionId,
                            'option' => $labels[$optionId] ?? "Option {$optionId}",
                            'count' => $data['counts'][$optionId],
                        ];
                    }
                    if ($optionId === $upperOptionId) {
                        break;
                    }
                }
            }
        }

        return [
            'medians' => $medians,
            'is_tie' => count($medians) > 1,
            'total_responses' => $totalVotes,
            'question_type' => $question->type,
        ];
    }

    /**
     * Find which option contains the voter at a given position
     */
    private function findOptionAtPosition(array $orderedOptionIds, array $cumulativeCounts, int $position): int
    {
        foreach ($orderedOptionIds as $optionId) {
            if ($cumulativeCounts[$optionId] >= $position) {
                return $optionId;
            }
        }
        // Fallback to last option (shouldn't happen with valid data)
        return end($orderedOptionIds);
    }
}
