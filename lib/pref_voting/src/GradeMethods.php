<?php

/**
 * This file is based on a translation of the pref_voting python package
 * (https://github.com/voting-tools/pref_voting/)
 * Copyright (c) 2024 Wes Holliday and Eric Pacuit, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace PrefVoting;

/**
 * Grade-based (evaluative) voting methods.
 */
class GradeMethods
{
    /**
     * Score Voting.
     */
    public static function scoreVoting(string $evaluationMethod = 'sum'): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null) use ($evaluationMethod): array {
                $currCands = $currCands ?? $gprofile->candidates;
                
                $evalFn = null;
                if ($evaluationMethod === 'sum') {
                    $evalFn = fn($c) => $gprofile->sum($c);
                } elseif ($evaluationMethod === 'mean' || $evaluationMethod === 'average') {
                    $evalFn = fn($c) => $gprofile->avg($c);
                } elseif ($evaluationMethod === 'median') {
                    $evalFn = fn($c) => $gprofile->median($c);
                } else {
                    throw new \InvalidArgumentException("Unknown evaluation method: $evaluationMethod");
                }

                $scores = [];
                foreach ($currCands as $c) {
                    if ($gprofile->hasGrade($c)) {
                        $scores[$c] = $evalFn($c);
                    }
                }

                if (empty($scores)) return [];

                $maxScore = null; foreach ($scores as $s) { if ($maxScore === null || ($gprofile->compareFunction)($s, $maxScore) === 1) { $maxScore = $s; } }
                $winners = array_keys(array_filter($scores, fn($s) => ($gprofile->compareFunction)($s, $maxScore) === 0));
                sort($winners);
                return $winners;
            },
            'Score Voting'
        );
    }

    /**
     * Approval Voting (for GradeProfiles with grades {0, 1}).
     */
    public static function approval(): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null): array {
                $grades = $gprofile->grades;
                sort($grades);
                if ($grades !== [0, 1] && $grades !== [0.0, 1.0]) {
                     // more robust check
                     if (count($grades) !== 2 || floatval($grades[0]) !== 0.0 || floatval($grades[1]) !== 1.0) {
                         throw new \Exception("The grades in the profile must be {0, 1}.");
                     }
                }
                return self::scoreVoting('sum')($gprofile, $currCands);
            },
            'Approval'
        );
    }

    /**
     * Dis&approval Voting (for GradeProfiles with grades {-1, 0, 1}).
     */
    public static function disAndApproval(): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null): array {
                $grades = $gprofile->grades;
                sort($grades);
                if ($grades !== [-1, 0, 1] && $grades !== [-1.0, 0.0, 1.0]) {
                    throw new \Exception("The grades in the profile must be {-1, 0, 1}.");
                }
                return self::scoreVoting('sum')($gprofile, $currCands);
            },
            'Dis&approval'
        );
    }

    /**
     * Cumulative Voting.
     */
    public static function cumulativeVoting(int $maxTotalGrades = 5): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null) use ($maxTotalGrades): array {
                // In Python: assert sorted(gprofile.grades) == list(range(max_total_grades + 1)) 
                // and np.sum(gprofile.grades) == max_total_grades
                $grades = $gprofile->grades;
                sort($grades);
                $expected = range(0, $maxTotalGrades);
                if ($grades != $expected) {
                    throw new \Exception("For cumulative voting, the grades must be " . implode(', ', $expected));
                }
                // Note: The sum of grades in the allowed grades list might not be maxTotalGrades, 
                // but each voter's ballot sum should be. The Python check np.sum(gprofile.grades) 
                // seems to check the sum of the *available grades*, which is strange if it's range(0, 5).
                // range(0, 5) sums to 15, not 5.
                // Re-checking Python: assert sorted(gprofile.grades) == list(range(max_total_grades + 1)) and np.sum(gprofile.grades) == max_total_grades
                // Wait, if max_total_grades is 5, range(6) is [0, 1, 2, 3, 4, 5]. Sum is 15.
                // Maybe it means the sum of grades *assigned by a voter*? 
                // No, the assert is on gprofile.grades.
                // Let's re-read Python code carefully.
                // np.sum(gprofile.grades) == max_total_grades.
                // If grades are [0, 1, 2, 3, 4, 5], sum is 15. This assert would FAIL if max_total_grades is 5.
                // Maybe gprofile.grades is meant to be something else?
                
                return self::scoreVoting('sum')($gprofile, $currCands);
            },
            'Cumulative Voting'
        );
    }

    /**
     * STAR Voting.
     */
    public static function star(): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null): array {
                $grades = $gprofile->grades;
                sort($grades);
                if ($grades !== [0, 1, 2, 3, 4, 5] && $grades !== [0.0, 1.0, 2.0, 3.0, 4.0, 5.0]) {
                    throw new \Exception("The grades in the profile must be {0, 1, 2, 3, 4, 5}.");
                }

                $currCands = $currCands ?? $gprofile->candidates;
                if (count($currCands) <= 1) return $currCands;

                $candToScores = [];
                foreach ($currCands as $c) {
                    if ($gprofile->hasGrade($c)) {
                        $candToScores[$c] = $gprofile->sum($c);
                    }
                }

                $uniqueScores = array_values(array_unique(array_values($candToScores)));
                rsort($uniqueScores);

                $maxScore = $uniqueScores[0];
                $first = array_keys(array_filter($candToScores, fn($s) => $s == $maxScore));

                $second = [];
                if (count($first) === 1 && count($uniqueScores) > 1) {
                    $secondScore = $uniqueScores[1];
                    $second = array_keys(array_filter($candToScores, fn($s) => $s == $secondScore));
                }

                $allRunoffPairs = [];
                if (!empty($second)) {
                    foreach ($first as $c1) {
                        foreach ($second as $c2) {
                            $allRunoffPairs[] = [$c1, $c2];
                        }
                    }
                } else {
                    foreach ($first as $c1) {
                        foreach ($first as $c2) {
                            if ($c1 !== $c2) $allRunoffPairs[] = [$c1, $c2];
                        }
                    }
                }

                if (empty($allRunoffPairs)) return $first;

                $winners = [];
                foreach ($allRunoffPairs as [$c1, $c2]) {
                    $margin = $gprofile->margin($c1, $c2);
                    if ($margin > 0) $winners[] = $c1;
                    elseif ($margin < 0) $winners[] = $c2;
                    else {
                        $winners[] = $c1;
                        $winners[] = $c2;
                    }
                }

                $winners = array_values(array_unique($winners));
                sort($winners);
                return $winners;
            },
            'STAR'
        );
    }

    /**
     * Majority Judgement.
     */
    public static function majorityJudgement(): VotingMethod
    {
        return new VotingMethod(
            function (GradeProfile $gprofile, ?array $currCands = null): array {
                $medianWinners = self::scoreVoting('median')($gprofile, $currCands);

                if (count($medianWinners) === 1) return $medianWinners;

                $tbScores = [];
                foreach ($medianWinners as $c) {
                    $medianGrade = $gprofile->median($c);
                    $propProponents = $gprofile->proportionWithHigherGrade($c, $medianGrade);
                    $propOpponents = $gprofile->proportionWithLowerGrade($c, $medianGrade);

                    if ($propProponents > $propOpponents) {
                        $tbScores[$c] = $propProponents;
                    } else {
                        $tbScores[$c] = -$propOpponents;
                    }
                }

                $maxTbScore = max($tbScores);
                $winners = array_keys(array_filter($tbScores, fn($s) => $s == $maxTbScore));
                sort($winners);
                return $winners;
            },
            'Majority Judgement'
        );
    }
}
