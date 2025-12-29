<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;
use PrefVoting\GradeMethods;

class MajorityJudgmentReport extends BaseReport
{
    public function getType(): string
    {
        return 'majority_judgment';
    }

    public function getName(): string
    {
        return 'Majority Judgment';
    }

    public function getDescription(): string
    {
        return 'Shows the Majority Judgment winner with grade distribution bars and median line';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['grade', 'star'];
    }

    public function getIcon(): string
    {
        return 'scale-balanced';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $profile = ProfileBuilder::fromGradeResponses($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);
        $grades = ProfileBuilder::getGradesForQuestion($question);

        // Get winner(s) using Majority Judgment
        $mjMethod = GradeMethods::majorityJudgement();
        $winnerIndices = $mjMethod($profile);

        // Map index back to option ID
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        $winners = [];
        foreach ($winnerIndices as $idx) {
            $optionId = $indexToOptionId[$idx] ?? $idx;
            $winners[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? $profile->cmap[$idx] ?? "Option {$idx}",
            ];
        }

        // Build grade distribution for each option (for visualization)
        $distributions = [];
        foreach ($question->options as $index => $option) {
            $optionId = $option->id;
            $candIdx = $index;

            // Calculate proportion for each grade
            $gradeProportions = [];
            foreach ($grades as $grade) {
                $proportion = $profile->proportion($candIdx, $grade);
                $gradeProportions[] = [
                    'grade' => $grade,
                    'proportion' => $proportion,
                    'percentage' => round($proportion * 100, 1),
                ];
            }

            // Get median grade for this candidate
            $medianGrade = $profile->median($candIdx);

            $distributions[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? "Option {$optionId}",
                'median_grade' => $medianGrade,
                'grade_proportions' => $gradeProportions,
                'is_winner' => in_array($optionId, array_column($winners, 'option_id')),
            ];
        }

        // Sort by median grade (winners should be at top)
        usort($distributions, function ($a, $b) use ($grades) {
            $aIdx = array_search($a['median_grade'], $grades);
            $bIdx = array_search($b['median_grade'], $grades);
            // Lower index = better grade (grades are sorted high to low)
            if ($aIdx === $bIdx) {
                return 0;
            }
            return ($aIdx < $bIdx) ? -1 : 1;
        });

        return [
            'winners' => $winners,
            'is_tie' => count($winners) > 1,
            'grades' => $grades,
            'distributions' => $distributions,
            'total_responses' => $profile->numVoters,
        ];
    }
}
