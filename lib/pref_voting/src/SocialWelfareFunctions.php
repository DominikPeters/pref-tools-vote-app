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
 * Implementations of Social Welfare Functions.
 */
class SocialWelfareFunctions
{
    /**
     * Kemeny-Young Social Welfare Function.
     * 
     * Returns an array of Ranking objects that minimize Kendall tau distance.
     */
    public static function kemenyYoung(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $bestRankingScore = -PHP_INT_MAX;
                $kyRankings = [];

                foreach (self::permutations(array_values($candidates)) as $r) {
                    $scoreOfR = 0;
                    $count = count($r);
                    for ($i = 0; $i < $count; $i++) {
                        for ($j = $i + 1; $j < $count; $j++) {
                            $scoreOfR += $edata->margin($r[$i], $r[$j]);
                        }
                    }

                    if ($scoreOfR > $bestRankingScore) {
                        $bestRankingScore = $scoreOfR;
                        $kyRankings = [$r];
                    } elseif ($scoreOfR === $bestRankingScore) {
                        $kyRankings[] = $r;
                    }
                }

                $rankings = [];
                foreach ($kyRankings as $r) {
                    $rankings[] = Ranking::fromLinearOrder($r, $edata->cmap);
                }

                return $rankings;
            },
            'Kemeny-Young'
        );
    }

    /**
     * Squared Kemeny Rule Social Welfare Function.
     * 
     * The cost of an output ranking is the sum over voters of the square of the 
     * Kendall-tau distance between the voter's ranking and the output ranking.
     * Only applies to Profiles.
     */
    public static function squaredKemeny(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile $profile, ?array $currCands = null): array {
                $candidates = $currCands ?? $profile->candidates;
                if (empty($candidates)) return [];

                [$vRankings, $vCounts] = $profile->getRankingsCounts();
                
                // If currCands is set, we need to restrict voter rankings
                if ($currCands !== null) {
                    $restrictedVRankings = [];
                    foreach ($vRankings as $vr) {
                        $restrictedVRankings[] = array_values(array_filter($vr, fn($c) => in_array($c, $candidates, true)));
                    }
                    $vRankings = $restrictedVRankings;
                }

                $minCost = INF;
                $bestRankings = [];

                foreach (self::permutations(array_values($candidates)) as $r) {
                    $cost = 0;
                    foreach ($vRankings as $idx => $vr) {
                        $dist = self::kendallTauDistance($vr, $r);
                        $cost += $vCounts[$idx] * ($dist * $dist);
                    }

                    if ($cost < $minCost - 1e-12) {
                        $minCost = $cost;
                        $bestRankings = [$r];
                    } elseif (abs($cost - $minCost) < 1e-12) {
                        $bestRankings[] = $r;
                    }
                }

                $rankings = [];
                foreach ($bestRankings as $r) {
                    $rankings[] = Ranking::fromLinearOrder($r, $profile->cmap);
                }

                return $rankings;
            },
            'Squared Kemeny'
        );
    }

    /**
     * Computes the Kendall tau distance between two linear rankings.
     */
    public static function kendallTauDistance(array $rankA, array $rankB): int
    {
        $indexB = array_flip($rankB);
        $tau = 0;
        $count = count($rankA);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($indexB[$rankA[$i]] > $indexB[$rankA[$j]]) {
                    $tau++;
                }
            }
        }
        return $tau;
    }

    /**
     * @param array $items
     * @return \Generator
     */
    private static function permutations(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;
            return;
        }

        foreach ($items as $key => $item) {
            $remaining = $items;
            unset($remaining[$key]);
            foreach (self::permutations($remaining) as $perm) {
                array_unshift($perm, $item);
                yield $perm;
            }
        }
    }
}

// Pre-instantiated methods
$kemenyYoungSWF = SocialWelfareFunctions::kemenyYoung();
