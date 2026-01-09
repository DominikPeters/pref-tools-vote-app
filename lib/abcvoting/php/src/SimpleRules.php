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

class SimpleRules
{
    /**
     * Algorithm for separable rules (such as AV and SAV).
     */
    public static function separableRuleAlgorithm(
        string $ruleId,
        Profile $profile,
        int $committeesize,
        bool $resolute = false,
        ?int $maxNumOfCommittees = null
    ): array {
        $score = array_fill(0, $profile->numCand, 0.0);
        foreach ($profile->getVoters() as $voter) {
            $numApproved = count($voter->approved);
            foreach ($voter->approved as $cand) {
                if ($ruleId === "sav") {
                    if ($numApproved > 0) {
                        $score[$cand] += $voter->weight / $numApproved;
                    }
                } elseif ($ruleId === "av") {
                    $score[$cand] += $voter->weight;
                } else {
                    throw new \InvalidArgumentException("Unknown rule ID: $ruleId");
                }
            }
        }

        $sortedScores = $score;
        sort($sortedScores);
        $cutoff = $sortedScores[$profile->numCand - $committeesize];

        $certainCands = [];
        $possibleCands = [];
        foreach (range(0, $profile->numCand - 1) as $cand) {
            if ($score[$cand] > $cutoff + 1e-12) {
                $certainCands[] = $cand;
            } elseif (abs($score[$cand] - $cutoff) < 1e-12) {
                $possibleCands[] = $cand;
            }
        }

        $missing = $committeesize - count($certainCands);
        if (count($possibleCands) === $missing) {
            $certainCands = array_merge($certainCands, $possibleCands);
            sort($certainCands);
            $possibleCands = [];
            $missing = 0;
        }

        $committees = [];
        if ($resolute) {
            $committees[] = array_merge($certainCands, array_slice($possibleCands, 0, $missing));
        } else {
            foreach (Utils::combinations($possibleCands, $missing) as $selection) {
                $committees[] = array_merge($certainCands, $selection);
                if ($maxNumOfCommittees !== null && count($committees) >= $maxNumOfCommittees) {
                    break;
                }
            }
        }

        foreach ($committees as &$committee) {
            sort($committee);
        }
        
        // Sort committees for consistency
        usort($committees, function ($a, $b) {
            return strcmp(implode(',', $a), implode(',', $b));
        });

        return $committees;
    }

    public static function computeAv(Profile $profile, int $committeesize, bool $resolute = false): array
    {
        return self::separableRuleAlgorithm("av", $profile, $committeesize, $resolute);
    }

    public static function computeSav(Profile $profile, int $committeesize, bool $resolute = false): array
    {
        return self::separableRuleAlgorithm("sav", $profile, $committeesize, $resolute);
    }
}
