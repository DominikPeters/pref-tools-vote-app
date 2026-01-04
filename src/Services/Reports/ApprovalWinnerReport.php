<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class ApprovalWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'approval_winner';
    }

    public function getName(): string
    {
        return 'Approval Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the winner(s) with the most votes';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['single_choice', 'approval'];
    }

    public function getIcon(): string
    {
        return 'trophy';
    }

    public function getCategory(): string
    {
        return 'vote_tallies';
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

        // Find the maximum count
        $maxCount = 0;
        foreach ($data['counts'] as $count) {
            $maxCount = max($maxCount, $count);
        }

        // Find all options with the maximum count (handles ties)
        $winners = [];
        foreach ($data['counts'] as $optionId => $count) {
            if ($count === $maxCount && $maxCount > 0) {
                $winners[] = [
                    'option_id' => $optionId,
                    'option' => $labels[$optionId] ?? "Option {$optionId}",
                    'count' => $count,
                    'percentage' => $data['total'] > 0 ? round(($count / $data['total']) * 100, 1) : 0,
                ];
            }
        }

        return [
            'winners' => $winners,
            'is_tie' => count($winners) > 1,
            'total_responses' => $data['total'],
            'question_type' => $question->type,
        ];
    }
}
