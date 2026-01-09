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

class Scores
{
    public static function pavScoreFct(int $i): float
    {
        return $i === 0 ? 0.0 : 1.0 / $i;
    }

    public static function slavScoreFct(int $i): float
    {
        return $i === 0 ? 0.0 : 1.0 / (2 * $i - 1);
    }

    public static function ccScoreFct(int $i): float
    {
        return $i === 1 ? 1.0 : 0.0;
    }

    public static function avScoreFct(int $i): float
    {
        return $i >= 1 ? 1.0 : 0.0;
    }

    public static function adamsScoreFct(int $i): float
    {
        if ($i === 0) return 0.0;
        if ($i === 1) return 1.0;
        return 1.0 / ($i - 1);
    }

    public static function getMarginalScoreFct(string $scorefctId): callable
    {
        switch ($scorefctId) {
            case 'pav':
                return [self::class, 'pavScoreFct'];
            case 'slav':
                return [self::class, 'slavScoreFct'];
            case 'cc':
                return [self::class, 'ccScoreFct'];
            case 'av':
                return [self::class, 'avScoreFct'];
            case 'adams':
                return [self::class, 'adamsScoreFct'];
            default:
                if (str_starts_with($scorefctId, 'geom')) {
                    $base = (float)substr($scorefctId, 4);
                    return function (int $i) use ($base) {
                        return $i === 0 ? 0.0 : 1.0 / pow($base, $i - 1);
                    };
                }
                throw new \InvalidArgumentException("Unknown score function: $scorefctId");
        }
    }

    public static function thieleScore(string $scorefctId, Profile $profile, array $committee): float
    {
        $marginalScoreFct = self::getMarginalScoreFct($scorefctId);
        $score = 0.0;
        foreach ($profile->getVoters() as $voter) {
            $candInCom = count(array_intersect($committee, $voter->approved));
            for ($i = 1; $i <= $candInCom; $i++) {
                $score += $voter->weight * $marginalScoreFct($i);
            }
        }
        return $score;
    }

    /**
     * Return marginal score increases from adding one candidate to the committee.
     */
    public static function marginalThieleScoresAdd(callable $marginalScoreFct, Profile $profile, array $committee): array
    {
        $marginal = array_fill(0, $profile->numCand, 0.0);
        foreach ($profile->getVoters() as $voter) {
            $intersectionSize = count(array_intersect($voter->approved, $committee));
            foreach ($voter->approved as $cand) {
                $marginal[$cand] += $voter->weight * $marginalScoreFct($intersectionSize + 1);
            }
        }
        foreach ($committee as $cand) {
            $marginal[$cand] = -1.0;
        }
        return $marginal;
    }
}
