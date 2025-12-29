<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class YNACountsReport extends BaseReport
{
    public function getType(): string
    {
        return 'yna_counts';
    }

    public function getName(): string
    {
        return 'Yes/No/Abstain Tallies';
    }

    public function getDescription(): string
    {
        return 'Shows Yes, No, and Abstain vote counts for each option';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['yes_no_abstain'];
    }

    public function getIcon(): string
    {
        return 'check-circle';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $ynaCounts = ProfileBuilder::getYNACounts($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);

        $results = [];
        foreach ($ynaCounts['counts'] as $optionId => $votes) {
            $total = $votes['yes'] + $votes['no'] + $votes['abstain'];
            $results[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? "Option {$optionId}",
                'yes' => $votes['yes'],
                'no' => $votes['no'],
                'abstain' => $votes['abstain'],
                'total' => $total,
                'yes_pct' => $total > 0 ? round(($votes['yes'] / $total) * 100, 1) : 0,
                'no_pct' => $total > 0 ? round(($votes['no'] / $total) * 100, 1) : 0,
                'abstain_pct' => $total > 0 ? round(($votes['abstain'] / $total) * 100, 1) : 0,
            ];
        }

        return [
            'results' => $results,
            'total_responses' => $ynaCounts['total'],
        ];
    }
}
