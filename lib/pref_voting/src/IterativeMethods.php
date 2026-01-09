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
 * Implementations of iterative voting methods.
 */
class IterativeMethods
{
    /**
     * Instant Runoff (Hare, Ranked Choice).
     */
    public static function instantRunoff(string $algorithm = 'basic'): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($algorithm): array {
                if ($profile instanceof ProfileWithTies) {
                    return self::instantRunoffForTruncatedLinearOrders($profile, $currCands);
                }

                $candidates = $currCands ?? $profile->candidates;
                if (empty($candidates)) return [];

                if ($algorithm === 'recursive') {
                    return self::recursiveIRV($profile, array_values($candidates));
                }

                $remaining = array_values($candidates);
                $strictMajSize = $profile->strictMajSize();

                while (count($remaining) > 1) {
                    $scores = $profile->pluralityScores($remaining);
                    foreach ($scores as $c => $s) {
                        if ($s >= $strictMajSize) return [$c];
                    }

                    $minScore = min($scores);
                    $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    if (count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Instant Runoff'
        );
    }

    private static function recursiveIRV(Profile $profile, array $candidates): array
    {
        if (count($candidates) <= 1) return $candidates;

        $scores = $profile->pluralityScores($candidates);
        $minScore = min($scores);
        $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        if (count($toEliminate) === count($candidates)) {
            sort($candidates);
            return $candidates;
        }

        return self::recursiveIRV($profile, array_values(array_diff($candidates, $toEliminate)));
    }

    /**
     * Instant Runoff for ProfileWithTies (Truncated Linear Orders).
     */
    public static function instantRunoffForTruncatedLinearOrders(ProfileWithTies $profile, ?array $currCands = null): array
    {
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        foreach ($rankings as $r) {
            if ($r->hasOvervote()) {
                throw new \Exception("Instant Runoff is only defined when all the ballots are truncated linear orders.");
            }
        }

        $currCands = $currCands ?? $profile->candidates;
        $prof = $profile->removeCandidates(array_diff($profile->candidates, $currCands));
        $prof->removeEmptyRankings();

        $remaining = $prof->candidates;
        while (true) {
            $scores = $prof->pluralityScores($remaining);
            if (empty($scores)) break;

            $maxScore = max($scores);
            $threshold = $prof->strictMajSize();

            if ($maxScore >= $threshold) {
                $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
                sort($winners);
                return $winners;
            }

            $minScore = min($scores);
            $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

            if (count($toEliminate) === count($remaining)) {
                break;
            }

            $remaining = array_values(array_diff($remaining, $toEliminate));
            $prof = $prof->removeCandidates($toEliminate);
            $prof->removeEmptyRankings();
        }

        $scores = $prof->pluralityScores($remaining);
        $maxScore = max($scores);
        $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
        sort($winners);
        return $winners;
    }

    /**
     * Instant Runoff for Truncated Linear Orders with Parallel Universe Tie-breaking (PUT).
     */
    public static function instantRunoffForTruncatedLinearOrdersPut(ProfileWithTies $profile, ?array $currCands = null): array
    {
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        foreach ($rankings as $r) {
            if ($r->hasOvervote()) {
                throw new \Exception("Instant Runoff is only defined when all the ballots are truncated linear orders.");
            }
        }

        $currCands = $currCands ?? $profile->candidates;
        $prof = $profile->removeCandidates(array_diff($profile->candidates, $currCands));
        $prof->removeEmptyRankings();

        $winners = self::recursiveIRVPutTruncated($prof, $prof->candidates);
        sort($winners);
        return $winners;
    }

    private static function recursiveIRVPutTruncated(ProfileWithTies $profile, array $candidates): array
    {
        if (empty($candidates)) return [];
        
        $scores = $profile->pluralityScores($candidates);
        if (empty($scores)) return [];

        $maxScore = max($scores);
        $threshold = $profile->strictMajSize();

        if ($maxScore >= $threshold) {
            $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
            return $winners;
        }

        $positives = array_keys(array_filter($scores, fn($s) => $s > 0));
        if (count($positives) < count($candidates)) {
            $newProf = $profile->removeCandidates(array_values(array_diff($candidates, $positives)));
            $newProf->removeEmptyRankings();
            return self::recursiveIRVPutTruncated($newProf, $positives);
        }

        $minScore = min($scores);
        $toEliminateAll = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        $winners = [];
        foreach ($toEliminateAll as $candToEliminate) {
            $newRemaining = array_values(array_diff($candidates, [$candToEliminate]));
            $newProf = $profile->removeCandidates([$candToEliminate]);
            $newProf->removeEmptyRankings();
            $res = self::recursiveIRVPutTruncated($newProf, $newRemaining);
            $winners = array_merge($winners, $res);
        }

        return array_values(array_unique($winners));
    }

    /**
     * Approval-IRV with Parallel Universe Tie-breaking (PUT) for ProfileWithTies.
     *
     * Each voter gives 1 point to ALL candidates in their top indifference class.
     * The candidate with the lowest score is eliminated (exploring all tie-breaks).
     */
    public static function approvalIrvPut(): VotingMethod
    {
        return new VotingMethod(
            function (ProfileWithTies $profile, ?array $currCands = null): array {
                $currCands = $currCands ?? $profile->candidates;
                $prof = $profile->removeCandidates(array_diff($profile->candidates, $currCands));
                $prof->removeEmptyRankings();

                $winners = self::recursiveApprovalIrvPut($prof, $prof->candidates);
                sort($winners);
                return $winners;
            },
            'Approval-IRV PUT'
        );
    }

    /**
     * Computes approval scores for Approval-IRV.
     * Each voter gives 1 point to ALL candidates in their top indifference class.
     * @return array<int|string, float>
     */
    private static function approvalScores(ProfileWithTies $profile, array $candidates): array
    {
        $scores = array_fill_keys($candidates, 0.0);
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($rankings as $i => $ranking) {
            $topCands = $ranking->first($candidates);
            foreach ($topCands as $c) {
                $scores[$c] += $rcounts[$i];
            }
        }

        return $scores;
    }

    private static function recursiveApprovalIrvPut(ProfileWithTies $profile, array $candidates): array
    {
        if (empty($candidates)) return [];
        if (count($candidates) === 1) return $candidates;

        $scores = self::approvalScores($profile, $candidates);
        if (empty($scores)) return [];

        // Eliminate candidates with zero score first (not ranked first by anyone)
        $positives = array_keys(array_filter($scores, fn($s) => $s > 0));
        if (count($positives) < count($candidates) && count($positives) > 0) {
            $toRemove = array_values(array_diff($candidates, $positives));
            $newProf = $profile->removeCandidates($toRemove);
            $newProf->removeEmptyRankings();
            return self::recursiveApprovalIrvPut($newProf, $positives);
        }

        // Find candidates with lowest score
        $minScore = min($scores);
        $toEliminateAll = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        // If all candidates are tied for lowest, they're all winners
        if (count($toEliminateAll) === count($candidates)) {
            return $candidates;
        }

        // Parallel universe tie-breaking
        $winners = [];
        foreach ($toEliminateAll as $candToEliminate) {
            $newRemaining = array_values(array_diff($candidates, [$candToEliminate]));
            $newProf = $profile->removeCandidates([$candToEliminate]);
            $newProf->removeEmptyRankings();
            $res = self::recursiveApprovalIrvPut($newProf, $newRemaining);
            $winners = array_merge($winners, $res);
        }

        return array_values(array_unique($winners));
    }

    /**
     * Split-IRV with Parallel Universe Tie-breaking (PUT) for ProfileWithTies.
     *
     * Each voter gives a total of 1 point split evenly among candidates in their top indifference class.
     * If k candidates are tied at the top, each gets 1/k points.
     */
    public static function splitIrvPut(): VotingMethod
    {
        return new VotingMethod(
            function (ProfileWithTies $profile, ?array $currCands = null): array {
                $currCands = $currCands ?? $profile->candidates;
                $prof = $profile->removeCandidates(array_diff($profile->candidates, $currCands));
                $prof->removeEmptyRankings();

                $winners = self::recursiveSplitIrvPut($prof, $prof->candidates);
                sort($winners);
                return $winners;
            },
            'Split-IRV PUT'
        );
    }

    /**
     * Computes split scores for Split-IRV.
     * Each voter gives a total of 1 point split evenly among candidates in their top indifference class.
     * @return array<int|string, float>
     */
    private static function splitScores(ProfileWithTies $profile, array $candidates): array
    {
        $scores = array_fill_keys($candidates, 0.0);
        [$rankings, $rcounts] = $profile->getRankingsCounts();

        foreach ($rankings as $i => $ranking) {
            $topCands = $ranking->first($candidates);
            if (!empty($topCands)) {
                $share = 1.0 / count($topCands);
                foreach ($topCands as $c) {
                    $scores[$c] += $rcounts[$i] * $share;
                }
            }
        }

        return $scores;
    }

    private static function recursiveSplitIrvPut(ProfileWithTies $profile, array $candidates): array
    {
        if (empty($candidates)) return [];
        if (count($candidates) === 1) return $candidates;

        $scores = self::splitScores($profile, $candidates);
        if (empty($scores)) return [];

        // Eliminate candidates with zero score first
        $positives = array_keys(array_filter($scores, fn($s) => $s > 0));
        if (count($positives) < count($candidates) && count($positives) > 0) {
            $toRemove = array_values(array_diff($candidates, $positives));
            $newProf = $profile->removeCandidates($toRemove);
            $newProf->removeEmptyRankings();
            return self::recursiveSplitIrvPut($newProf, $positives);
        }

        // Find candidates with lowest score (use epsilon for float comparison)
        $minScore = min($scores);
        $epsilon = 1e-9;
        $toEliminateAll = array_keys(array_filter($scores, fn($s) => abs($s - $minScore) < $epsilon));

        // If all candidates are tied for lowest, they're all winners
        if (count($toEliminateAll) === count($candidates)) {
            return $candidates;
        }

        // Parallel universe tie-breaking
        $winners = [];
        foreach ($toEliminateAll as $candToEliminate) {
            $newRemaining = array_values(array_diff($candidates, [$candToEliminate]));
            $newProf = $profile->removeCandidates([$candToEliminate]);
            $newProf->removeEmptyRankings();
            $res = self::recursiveSplitIrvPut($newProf, $newRemaining);
            $winners = array_merge($winners, $res);
        }

        return array_values(array_unique($winners));
    }

    /**
     * Coombs voting method.
     */
    public static function coombs(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $remaining = $currCands ?? $profile->candidates;
                $strictMajSize = $profile->strictMajSize();

                while (count($remaining) > 1) {
                    $pScores = $profile->pluralityScores($remaining);
                    foreach ($pScores as $c => $s) {
                        if ($s >= $strictMajSize) return [$c];
                    }

                    // Count last-place votes
                    $lastPlaceScores = array_fill_keys($remaining, 0);
                    [$rankings, $rcounts] = $profile->getRankingsCounts();
                    foreach ($rankings as $i => $r) {
                        $filtered = array_values(array_filter($r, fn($c) => in_array($c, $remaining, true)));
                        if (!empty($filtered)) {
                            $lastPlaceScores[end($filtered)] += $rcounts[$i];
                        }
                    }

                    $maxLast = max($lastPlaceScores);
                    $toEliminate = array_keys(array_filter($lastPlaceScores, fn($s) => $s == $maxLast));

                    if (count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Coombs'
        );
    }

    /**
     * Baldwin voting method.
     */
    public static function baldwin(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $remaining = $currCands ?? $profile->candidates;

                while (count($remaining) > 1) {
                    $scores = $profile->bordaScores($remaining);
                    $minScore = min($scores);
                    $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    if (count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Baldwin'
        );
    }

    /**
     * Strict Nanson voting method.
     */
    public static function strictNanson(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $remaining = $currCands ?? $profile->candidates;

                while (count($remaining) > 1) {
                    $scores = $profile->bordaScores($remaining);
                    $avg = array_sum($scores) / count($scores);
                    $toEliminate = array_keys(array_filter($scores, fn($s) => $s < $avg));

                    if (empty($toEliminate) || count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Strict Nanson'
        );
    }

    /**
     * Weak Nanson voting method.
     */
    public static function weakNanson(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $remaining = $currCands ?? $profile->candidates;

                while (count($remaining) > 1) {
                    $scores = $profile->bordaScores($remaining);
                    $avg = array_sum($scores) / count($scores);
                    $toEliminate = array_keys(array_filter($scores, fn($s) => $s <= $avg));

                    if (empty($toEliminate) || count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Weak Nanson'
        );
    }

    /**
     * Benham voting method.
     */
    public static function benham(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $remaining = $currCands ?? $profile->candidates;

                while (count($remaining) > 1) {
                    $cw = $profile->condorcetWinner($remaining);
                    if ($cw !== null) return [$cw];

                    $scores = $profile->pluralityScores($remaining);
                    $minScore = min($scores);
                    $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    if (count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }

                return $remaining;
            },
            'Benham'
        );
    }

    /**
     * Bottom-Two-Runoff Instant Runoff (BTR-IRV).
     */
    public static function bottomTwoRunoffInstantRunoff(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveBtrIrv($profile, $currCands ?? $profile->candidates);
            },
            'Bottom-Two-Runoff IRV'
        );
    }

    private static function recursiveBtrIrv(Profile $profile, array $candidates): array
    {
        if (count($candidates) <= 1) return $candidates;

        $scores = $profile->pluralityScores($candidates);
        $minScore = min($scores);
        $lowest = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        $pairs = [];
        if (count($lowest) >= 2) {
             // Pairs from lowest
             for ($i = 0; $i < count($lowest); $i++) {
                 for ($j = $i + 1; $j < count($lowest); $j++) {
                     $pairs[] = [$lowest[$i], $lowest[$j]];
                 }
             }
        } else {
             // lowest[0] vs second lowest
             $c1 = $lowest[0];
             $otherScores = array_diff_key($scores, [$c1 => 0]); // Remove c1
             if (empty($otherScores)) return [$c1]; // Should not happen if count > 1

             $secondMin = min($otherScores);
             $secondLowest = array_keys(array_filter($otherScores, fn($s) => $s == $secondMin));
             
             foreach ($secondLowest as $c2) {
                 $pairs[] = [$c1, $c2];
             }
        }

        $winners = [];
        foreach ($pairs as [$a, $b]) {
            $margin = $profile->margin($a, $b);
            
            $toEliminate = [];
            if ($margin > 0) $toEliminate[] = $b;
            elseif ($margin < 0) $toEliminate[] = $a;
            else {
                $toEliminate[] = $a;
                $toEliminate[] = $b;
            }

            foreach ($toEliminate as $cand) {
                $nextCands = array_values(array_diff($candidates, [$cand]));
                $res = self::recursiveBtrIrv($profile, $nextCands);
                $winners = array_merge($winners, $res);
            }
        }
        
        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Iterated Removal of Condorcet Loser.
     */
    public static function iteratedRemovalCL(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $remaining = $currCands ?? $edata->candidates;

                while (count($remaining) > 1) {
                    $cl = $edata->condorcetLoser($remaining);
                    if ($cl === null) break;
                    $remaining = array_values(array_diff($remaining, [$cl]));
                }

                sort($remaining);
                return $remaining;
            },
            'Iterated Removal CL'
        );
    }

    /**
     * Instant Runoff with Parallel Universe Tie-breaking (PUT).
     */
    public static function instantRunoffPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveIRVPut($profile, $currCands ?? $profile->candidates);
            },
            'Instant Runoff PUT'
        );
    }

    private static function recursiveIRVPut(Profile $profile, array $candidates): array
    {
        $scores = $profile->pluralityScores($candidates);
        $strictMajSize = $profile->strictMajSize();
        foreach ($scores as $c => $s) {
            if ($s >= $strictMajSize) return [$c];
        }

        $positives = array_keys(array_filter($scores, fn($s) => $s > 0));
        if (count($positives) < count($candidates)) {
            return self::recursiveIRVPut($profile, $positives);
        }

        if (count($candidates) <= 1) return $candidates;

        $minScore = min($scores);
        $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        $winners = [];
        foreach ($toEliminate as $cand) {
            $remaining = array_values(array_diff($candidates, [$cand]));
            $res = self::recursiveIRVPut($profile, $remaining);
            $winners = array_merge($winners, $res);
        }

        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Instant Runoff with fixed tie-breaker.
     */
    public static function instantRunoffTb(?array $tieBreaker = null): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($tieBreaker): array {
                $candidates = $currCands ?? $profile->candidates;
                $tb = $tieBreaker ?? range(0, count($profile->candidates) - 1);
                $remaining = array_values($candidates);
                $strictMajSize = $profile->strictMajSize();

                while (count($remaining) > 1) {
                    $scores = $profile->pluralityScores($remaining);
                    foreach ($scores as $c => $s) {
                        if ($s >= $strictMajSize) return [$c];
                    }

                    $minScore = min($scores);
                    $lowest = array_keys(array_filter($scores, fn($s) => $s == $minScore));
                    
                    if (count($lowest) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    // Tie-break: remove candidate with lowest index in tb
                    $toRemove = $lowest[0];
                    foreach ($lowest as $c) {
                        if (array_search($c, $tb, true) < array_search($toRemove, $tb, true)) {
                            $toRemove = $c;
                        }
                    }
                    $remaining = array_values(array_diff($remaining, [$toRemove]));
                }
                return $remaining;
            },
            'Instant Runoff TB'
        );
    }

    /**
     * Coombs with fixed tie-breaker.
     */
    public static function coombsTb(?array $tieBreaker = null): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($tieBreaker): array {
                $candidates = $currCands ?? $profile->candidates;
                $tb = $tieBreaker ?? range(0, count($profile->candidates) - 1);
                $remaining = array_values($candidates);
                $strictMajSize = $profile->strictMajSize();

                while (count($remaining) > 1) {
                    $pScores = $profile->pluralityScores($remaining);
                    foreach ($pScores as $c => $s) {
                        if ($s >= $strictMajSize) return [$c];
                    }

                    $lastPlaceScores = array_fill_keys($remaining, 0);
                    [$rankings, $rcounts] = $profile->getRankingsCounts();
                    foreach ($rankings as $i => $r) {
                        $filtered = array_values(array_filter($r, fn($c) => in_array($c, $remaining, true)));
                        if (!empty($filtered)) $lastPlaceScores[end($filtered)] += $rcounts[$i];
                    }

                    $maxLast = max($lastPlaceScores);
                    $highest = array_keys(array_filter($lastPlaceScores, fn($s) => $s == $maxLast));

                    if (count($highest) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $toRemove = $highest[0];
                    foreach ($highest as $c) {
                        if (array_search($c, $tb, true) < array_search($toRemove, $tb, true)) {
                            $toRemove = $c;
                        }
                    }
                    $remaining = array_values(array_diff($remaining, [$toRemove]));
                }
                return $remaining;
            },
            'Coombs TB'
        );
    }

    /**
     * Coombs with Parallel Universe Tie-breaking (PUT).
     */
    public static function coombsPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveCoombsPut($profile, $currCands ?? $profile->candidates);
            },
            'Coombs PUT'
        );
    }

    private static function recursiveCoombsPut(Profile $profile, array $candidates): array
    {
        $scores = $profile->pluralityScores($candidates);
        $strictMajSize = $profile->strictMajSize();
        foreach ($scores as $c => $s) {
            if ($s >= $strictMajSize) return [$c];
        }

        if (count($candidates) <= 1) return $candidates;

        $lastPlaceScores = array_fill_keys($candidates, 0);
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        foreach ($rankings as $i => $r) {
            $filtered = array_values(array_filter($r, fn($c) => in_array($c, $candidates, true)));
            if (!empty($filtered)) $lastPlaceScores[end($filtered)] += $rcounts[$i];
        }

        $maxLast = max($lastPlaceScores);
        $toEliminate = array_keys(array_filter($lastPlaceScores, fn($s) => $s == $maxLast));

        $winners = [];
        foreach ($toEliminate as $cand) {
            $remaining = array_values(array_diff($candidates, [$cand]));
            $res = self::recursiveCoombsPut($profile, $remaining);
            $winners = array_merge($winners, $res);
        }

        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Baldwin with fixed tie-breaker.
     */
    public static function baldwinTb(?array $tieBreaker = null): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($tieBreaker): array {
                $candidates = $currCands ?? $profile->candidates;
                $tb = $tieBreaker ?? range(0, count($profile->candidates) - 1);
                $remaining = array_values($candidates);

                while (count($remaining) > 1) {
                    $scores = $profile->bordaScores($remaining);
                    $minScore = min($scores);
                    $lowest = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    if (count($lowest) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $toRemove = $lowest[0];
                    foreach ($lowest as $c) {
                        if (array_search($c, $tb, true) < array_search($toRemove, $tb, true)) {
                            $toRemove = $c;
                        }
                    }
                    $remaining = array_values(array_diff($remaining, [$toRemove]));
                }
                return $remaining;
            },
            'Baldwin TB'
        );
    }

    /**
     * Baldwin with Parallel Universe Tie-breaking (PUT).
     */
    public static function baldwinPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveBaldwinPut($profile, $currCands ?? $profile->candidates);
            },
            'Baldwin PUT'
        );
    }

    private static function recursiveBaldwinPut(Profile $profile, array $candidates): array
    {
        if (count($candidates) <= 1) return $candidates;

        $scores = $profile->bordaScores($candidates);
        $minScore = min($scores);
        $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        if (count($toEliminate) === count($candidates)) {
            sort($candidates);
            return $candidates;
        }

        $winners = [];
        foreach ($toEliminate as $cand) {
            $remaining = array_values(array_diff($candidates, [$cand]));
            $res = self::recursiveBaldwinPut($profile, $remaining);
            $winners = array_merge($winners, $res);
        }

        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Benham with Parallel Universe Tie-breaking (PUT).
     */
    public static function benhamPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveBenhamPut($profile, $currCands ?? $profile->candidates);
            },
            'Benham PUT'
        );
    }

    private static function recursiveBenhamPut(Profile $profile, array $candidates): array
    {
        $cw = $profile->condorcetWinner($candidates);
        if ($cw !== null) return [$cw];

        if (count($candidates) <= 1) return $candidates;

        $scores = $profile->pluralityScores($candidates);
        $minScore = min($scores);
        $toEliminate = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        if (count($toEliminate) === count($candidates)) {
            sort($candidates);
            return $candidates;
        }

        $winners = [];
        foreach ($toEliminate as $cand) {
            $remaining = array_values(array_diff($candidates, [$cand]));
            $res = self::recursiveBenhamPut($profile, $remaining);
            $winners = array_merge($winners, $res);
        }

        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Raynaud voting method.
     */
    public static function raynaud(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $remaining = $currCands ?? $edata->candidates;

                while (true) {
                    if (count($remaining) <= 1) return $remaining;

                    // Worst pairwise margin for each candidate
                    $worstLosses = [];
                    foreach ($remaining as $c) {
                        $maxLoss = -PHP_INT_MAX;
                        $hasLoss = false;
                        foreach ($remaining as $other) {
                            if ($c === $other) continue;
                            $margin = $edata->margin($other, $c);
                            if ($margin > $maxLoss) {
                                $maxLoss = $margin;
                                $hasLoss = true;
                            }
                        }
                        $worstLosses[$c] = $hasLoss ? $maxLoss : -PHP_INT_MAX;
                    }

                    $maxWorstLoss = max($worstLosses);
                    $toEliminate = array_keys(array_filter($worstLosses, fn($s) => $s == $maxWorstLoss));

                    if (count($toEliminate) === count($remaining)) {
                        sort($remaining);
                        return $remaining;
                    }

                    $remaining = array_values(array_diff($remaining, $toEliminate));
                }
            },
            'Raynaud'
        );
    }

    /**
     * Woodall voting method.
     */
    public static function woodall(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $candidates = $currCands ?? $profile->candidates;
                $topCycleMethod = C1Methods::topCycle();
                $sSet = $topCycleMethod($profile, $candidates);

                if (count($sSet) === 1) return $sSet;

                $remaining = array_values($candidates);
                while (true) {
                    $scores = $profile->pluralityScores($remaining);
                    $minScore = min($scores);
                    $lowest = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    $inSSet = array_values(array_intersect($remaining, $sSet));
                    $toEliminate = $lowest;
                    $nextRemaining = array_values(array_diff($remaining, $toEliminate));
                    $nextInSSet = array_values(array_intersect($nextRemaining, $sSet));

                    if (empty($nextInSSet)) {
                        sort($inSSet);
                        return $inSSet;
                    }
                    if (count($nextInSSet) === 1) {
                        return $nextInSSet;
                    }

                    $remaining = $nextRemaining;
                }
            },
            'Woodall'
        );
    }

    /**
     * Knockout Voting.
     */
    public static function knockout(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                return self::recursiveKnockout($profile, $currCands ?? $profile->candidates);
            },
            'Knockout Voting'
        );
    }

    private static function recursiveKnockout(Profile $profile, array $candidates): array
    {
        if (count($candidates) === 1) return $candidates;

        // Uses global Borda scores
        $bordaScores = $profile->bordaScores();
        $currScores = array_filter($bordaScores, fn($c) => in_array($c, $candidates, true), ARRAY_FILTER_USE_KEY);
        
        $minScore = min($currScores);
        $lowest = array_keys(array_filter($currScores, fn($s) => $s == $minScore));

        $winners = [];
        if (count($lowest) > 1) {
            foreach ($lowest as $c1) {
                foreach ($lowest as $c2) {
                    if ($c1 === $c2) continue;
                    if ($profile->margin($c1, $c2) >= 0) {
                        $remaining = array_values(array_diff($candidates, [$c2]));
                        $winners = array_merge($winners, self::recursiveKnockout($profile, $remaining));
                    }
                }
            }
        } else {
            $c1 = $lowest[0];
            $others = array_diff($candidates, [$c1]);
            $otherScores = array_filter($currScores, fn($c) => in_array($c, $others, true), ARRAY_FILTER_USE_KEY);
            $secondMin = min($otherScores);
            $secondLowest = array_keys(array_filter($otherScores, fn($s) => $s == $secondMin));

            foreach ($secondLowest as $c2) {
                if ($profile->margin($c2, $c1) >= 0) {
                    $remaining = array_values(array_diff($candidates, [$c1]));
                    $winners = array_merge($winners, self::recursiveKnockout($profile, $remaining));
                }
                if ($profile->margin($c1, $c2) > 0) {
                    $remaining = array_values(array_diff($candidates, [$c2]));
                    $winners = array_merge($winners, self::recursiveKnockout($profile, $remaining));
                }
            }
        }

        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Plurality Veto.
     */
    public static function pluralityVeto(?array $voterOrder = null): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($voterOrder): array {
                $candidates = $currCands ?? $profile->candidates;
                $scores = $profile->pluralityScores($candidates);
                $order = $voterOrder ?? range(0, $profile->numVoters - 1);

                $active = array_values($candidates);
                $lastRemaining = null;

                foreach ($order as $voterIdx) {
                    $positive = array_keys(array_filter($scores, fn($s) => $s > 0 && in_array(array_search($s, $scores), $active, true)));
                    // The above array_filter logic is a bit complex due to PHP array behavior.
                    // Let's redo:
                    $remainingWithPositive = [];
                    foreach ($active as $c) {
                        if ($scores[$c] > 0) $remainingWithPositive[] = $c;
                    }

                    if (empty($remainingWithPositive)) {
                        return $lastRemaining !== null ? [$lastRemaining] : $active;
                    }
                    if (count($remainingWithPositive) === 1) return $remainingWithPositive;

                    $ranking = $profile->getRankings()[$voterIdx];
                    // Find bottom-ranked among $remainingWithPositive
                    $bottom = null;
                    foreach (array_reverse($ranking) as $c) {
                        if (in_array($c, $remainingWithPositive, true)) {
                            $bottom = $c;
                            break;
                        }
                    }

                    $scores[$bottom]--;
                    if ($scores[$bottom] === 0) {
                        $active = array_values(array_diff($active, [$bottom]));
                        $lastRemaining = $bottom;
                    }
                }

                if ($lastRemaining !== null) return [$lastRemaining];
                $maxS = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $maxS));
                sort($winners);
                return $winners;
            },
            'Plurality Veto'
        );
    }

    /**
     * Consensus Builder.
     */
    public static function consensusBuilder(?array $consensusRanking = null, float $beta = 0.5): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($consensusRanking, $beta): array {
                $candidates = $currCands ?? $profile->candidates;
                $cr = $consensusRanking ?? $candidates;
                sort($cr); // Default is sorted list

                $eliminated = [];
                $lastProcessed = null;

                foreach (array_reverse($cr) as $i) {
                    if (!in_array($i, $candidates, true) || in_array($i, $eliminated, true)) continue;

                    foreach ($cr as $j) {
                        if ($i === $j || !in_array($j, $candidates, true) || in_array($j, $eliminated, true)) continue;

                        // Check if j is above i in cr
                        if (array_search($j, $cr, true) < array_search($i, $cr, true)) {
                            if ($profile->support($i, $j) / $profile->numVoters >= $beta) {
                                $eliminated[] = $j;
                            }
                        }
                    }
                    $lastProcessed = $i;
                }
                return [$lastProcessed];
            },
            'Consensus Builder'
        );
    }

    /**
     * Instant Runoff Ranking SWF.
     */
    public static function instantRunoffRanking(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile $profile, ?array $currCands = null): array {
                return [self::recursiveIRVRanking($profile, $currCands ?? $profile->candidates)];
            },
            'Instant Runoff ranking'
        );
    }

    private static function recursiveIRVRanking(Profile $profile, array $candidates): Ranking
    {
        $scores = $profile->pluralityScores($candidates);
        $minScore = min($scores);
        $lowest = array_keys(array_filter($scores, fn($s) => $s == $minScore));

        if (count($lowest) === count($candidates)) {
            $rmap = array_fill_keys($candidates, 0);
            return new Ranking($rmap, $profile->cmap);
        }

        $remaining = array_values(array_diff($candidates, $lowest));
        $recRanking = self::recursiveIRVRanking($profile, $remaining);
        $maxRank = max($recRanking->getRanks());
        
        $rmap = $recRanking->rmap;
        foreach ($lowest as $c) {
            $rmap[$c] = $maxRank + 1;
        }
        return new Ranking($rmap, $profile->cmap);
    }

    /**
     * Higher-order method: Iteratively apply vm until fixpoint.
     */
    public static function iterated(VotingMethod $vm): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null) use ($vm): array {
                $current = $currCands ?? $edata->candidates;
                while (true) {
                    $next = $vm($edata, $current);
                    sort($current);
                    sort($next);
                    if ($current === $next) return $current;
                    $current = $next;
                }
            },
            "Iterated {$vm->name}"
        );
    }

    /**
     * Higher-order method: Tideman Alternative.
     */
    public static function tidemanAlternative(VotingMethod $vm): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null) use ($vm): array {
                $candidates = $currCands ?? $profile->candidates;
                $winners = $vm($profile, $candidates);

                while (true) {
                    $scores = $profile->pluralityScores($winners);
                    $minScore = min($scores);
                    $toRemove = array_keys(array_filter($scores, fn($s) => $s == $minScore));

                    if (count($toRemove) === count($winners)) return $winners;

                    $candidates = array_values(array_diff($candidates, $toRemove));
                    $winners = $vm($profile, $candidates);
                }
            },
            "Tideman Alternative {$vm->name}"
        );
    }
}

// Pre-instantiated methods
$instantRunoff = IterativeMethods::instantRunoff();
$instantRunoffPut = IterativeMethods::instantRunoffPut();
$coombs = IterativeMethods::coombs();
$coombsPut = IterativeMethods::coombsPut();
$baldwin = IterativeMethods::baldwin();
$baldwinPut = IterativeMethods::baldwinPut();
$strictNanson = IterativeMethods::strictNanson();
$weakNanson = IterativeMethods::weakNanson();
$benham = IterativeMethods::benham();
$benhamPut = IterativeMethods::benhamPut();
$btrIrv = IterativeMethods::bottomTwoRunoffInstantRunoff();
$iteratedRemovalCL = IterativeMethods::iteratedRemovalCL();
$raynaud = IterativeMethods::raynaud();
$woodall = IterativeMethods::woodall();
$knockout = IterativeMethods::knockout();
$pluralityVeto = IterativeMethods::pluralityVeto();
$consensusBuilder = IterativeMethods::consensusBuilder();
$instantRunoffRanking = IterativeMethods::instantRunoffRanking();
$approvalIrvPut = IterativeMethods::approvalIrvPut();
$splitIrvPut = IterativeMethods::splitIrvPut();
