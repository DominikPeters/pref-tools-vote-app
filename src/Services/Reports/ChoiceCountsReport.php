<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class ChoiceCountsReport extends BaseReport
{
    public function getType(): string
    {
        return 'choice_counts';
    }

    public function getName(): string
    {
        return __('report_type_choice_counts');
    }

    public function getDescription(): string
    {
        return __('report_bar_chart_showing_the_number_of_votes_for_each_option');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['single_choice', 'approval'];
    }

    public function getIcon(): string
    {
        return 'chart-bar';
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
                    'label' => __('report_include_user_added_other_options'),
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

        $scores = [];
        $maxScore = 0;

        foreach ($data['counts'] as $optionId => $count) {
            $scores[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? "Option {$optionId}",
                'count' => $count,
                'percentage' => $data['total'] > 0 ? round(($count / $data['total']) * 100, 1) : 0,
            ];
            $maxScore = max($maxScore, $count);
        }

        // Sort by count descending
        usort($scores, fn($a, $b) => $b['count'] - $a['count']);

        return [
            'scores' => $scores,
            'max_score' => $maxScore,
            'total_responses' => $data['total'],
            'question_type' => $question->type,
        ];
    }
}
