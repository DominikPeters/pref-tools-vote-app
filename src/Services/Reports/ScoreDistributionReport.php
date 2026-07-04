<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;

class ScoreDistributionReport extends BaseReport
{
    public function getType(): string
    {
        return 'score_distribution';
    }

    public function getName(): string
    {
        return 'Score Distribution';
    }

    public function getDescription(): string
    {
        return 'Shows how many voters gave each score to every option, with averages';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['star', 'grade'];
    }

    public function getIcon(): string
    {
        return 'chart-bar';
    }

    public function getCategory(): string
    {
        return 'vote_tallies';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $isNumeric = $question->type === 'star';
        $grades = ProfileBuilder::getGradesForQuestion($question); // highest first
        $labels = ProfileBuilder::getOptionLabels($question);
        $question->loadOptions();

        $base = [
            'is_numeric' => $isNumeric,
            'grades' => array_values($grades),
            'max_grade' => $isNumeric ? max($grades) : null,
            'options' => [],
            'total_responses' => 0,
        ];

        $profile = ProfileBuilder::fromGradeResponses($question, $responses);
        if ($profile->numVoters === 0) {
            return $base;
        }

        $rows = [];
        foreach ($question->options as $index => $option) {
            $counts = [];
            $numGraded = 0;
            foreach ($grades as $grade) {
                $count = (int) round($profile->proportion($index, $grade) * $profile->numVoters);
                $counts[] = ['grade' => $grade, 'count' => $count];
                $numGraded += $count;
            }

            $hasGrade = $profile->hasGrade($index);
            $rows[] = [
                'option_id' => $option->id,
                'option' => $labels[$option->id] ?? $option->label,
                'counts' => $counts,
                'num_graded' => $numGraded,
                'average' => ($isNumeric && $hasGrade) ? round($profile->avg($index), 2) : null,
                'median' => $hasGrade ? $profile->median($index) : null,
            ];
        }

        // Sort: numeric scores by average, grades by median (best first)
        if ($isNumeric) {
            usort($rows, fn($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));
        } else {
            $gradeRank = array_flip(array_map('strval', $grades)); // lower index = better
            usort($rows, function ($a, $b) use ($gradeRank) {
                $aRank = $gradeRank[(string) $a['median']] ?? PHP_INT_MAX;
                $bRank = $gradeRank[(string) $b['median']] ?? PHP_INT_MAX;
                return $aRank <=> $bRank;
            });
        }

        $base['options'] = $rows;
        $base['total_responses'] = $profile->numVoters;
        return $base;
    }
}
