<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace AbcVoting;

class ThieleRules
{
    /**
     * Compute winning committees with Thiele methods using brute-force.
     */
    public static function computeThieleBruteForce(
        string $scorefctId,
        Profile $profile,
        int $committeesize,
        bool $resolute = false,
        ?int $maxNumOfCommittees = null
    ): array {
        $optCommittees = [];
        $optScore = -1.0;

        $candidates = range(0, $profile->numCand - 1);
        foreach (Utils::combinations($candidates, $committeesize) as $committee) {
            $score = Scores::thieleScore($scorefctId, $profile, $committee);
            
            if ($score > $optScore + 1e-12) {
                $optScore = $score;
                $optCommittees = [$committee];
            } elseif (abs($score - $optScore) < 1e-12) {
                $optCommittees[] = $committee;
            }
        }

        return self::finalizeCommittees($optCommittees, $resolute, $maxNumOfCommittees);
    }

    public static function computePav(Profile $profile, int $committeesize, bool $resolute = false): array
    {
        return self::computeThieleBruteForce('pav', $profile, $committeesize, $resolute);
    }

    public static function computeCc(Profile $profile, int $committeesize, bool $resolute = false): array
    {
        return self::computeThieleBruteForce('cc', $profile, $committeesize, $resolute);
    }

    public static function computeSlav(Profile $profile, int $committeesize, bool $resolute = false): array
    {
        return self::computeThieleBruteForce('slav', $profile, $committeesize, $resolute);
    }

    public static function computeAdams(Profile $profile, int $committeesize, bool $resolute = false, ?int $maxNumOfCommittees = null): array
    {
        // Adams rule is defined as: first maximize CC score, then Adams score
        $bestCommittees = [];
        $bestCoverage = -1.0;

        $candidates = range(0, $profile->numCand - 1);
        foreach (Utils::combinations($candidates, $committeesize) as $committee) {
            $coverage = Scores::thieleScore('cc', $profile, $committee);
            if ($coverage > $bestCoverage + 1e-12) {
                $bestCoverage = $coverage;
                $bestCommittees = [$committee];
            } elseif (abs($coverage - $bestCoverage) < 1e-12) {
                $bestCommittees[] = $committee;
            }
        }

        $finalCommittees = [];
        $bestScore = -1.0;
        foreach ($bestCommittees as $committee) {
            $score = Scores::thieleScore('adams', $profile, $committee);
            if ($score > $bestScore + 1e-12) {
                $bestScore = $score;
                $finalCommittees = [$committee];
            } elseif (abs($score - $bestScore) < 1e-12) {
                $finalCommittees[] = $committee;
            }
        }

        return self::finalizeCommittees($finalCommittees, $resolute, $maxNumOfCommittees);
    }

    /**
     * Lexicographic Chamberlin-Courant (Lex-CC).
     */
    public static function computeLexCc(Profile $profile, int $committeesize, bool $resolute = false, ?int $maxNumOfCommittees = null): array
    {
        $optCommittees = [];
        $optScoreVector = array_fill(0, $committeesize, -1.0);

        $candidates = range(0, $profile->numCand - 1);
        foreach (Utils::combinations($candidates, $committeesize) as $committee) {
            $scoreVector = [];
            for ($ell = 1; $ell <= $committeesize; $ell++) {
                // atleast_ell score function implementation in Scores
                $scoreVector[] = self::atLeastEllScore($profile, $committee, $ell);
            }
            
            $isBetter = false;
            $isEqual = true;
            for ($i = 0; $i < $committeesize; $i++) {
                if ($scoreVector[$i] > $optScoreVector[$i] + 1e-12) {
                    $isBetter = true;
                    $isEqual = false;
                    break;
                } elseif ($scoreVector[$i] < $optScoreVector[$i] - 1e-12) {
                    $isBetter = false;
                    $isEqual = false;
                    break;
                }
            }

            if ($isBetter) {
                $optScoreVector = $scoreVector;
                $optCommittees = [$committee];
            } elseif ($isEqual) {
                $optCommittees[] = $committee;
            }
        }

        return self::finalizeCommittees($optCommittees, $resolute, $maxNumOfCommittees);
    }

    private static function atLeastEllScore(Profile $profile, array $committee, int $ell): float
    {
        $score = 0.0;
        foreach ($profile->getVoters() as $voter) {
            $count = count(array_intersect($committee, $voter->approved));
            if ($count >= $ell) {
                $score += $voter->weight;
            }
        }
        return $score;
    }

    private static function finalizeCommittees(array $committees, bool $resolute, ?int $maxNumOfCommittees = null): array
    {
        usort($committees, function ($a, $b) {
            sort($a);
            sort($b);
            return strcmp(implode(',', $a), implode(',', $b));
        });
        if ($maxNumOfCommittees !== null) {
            $committees = array_slice($committees, 0, $maxNumOfCommittees);
        }
        if ($resolute && !empty($committees)) {
            return [$committees[0]];
        }
        return $committees;
    }
}