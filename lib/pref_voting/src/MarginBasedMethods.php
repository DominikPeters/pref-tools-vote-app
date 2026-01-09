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
 * Implementations of voting methods that work on both profiles and margin graphs.
 */
class MarginBasedMethods
{
    /**
     * Minimax winners are candidates with the smallest maximum pairwise loss.
     */
    public static function minimax(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $scores = [];
                foreach ($candidates as $c) {
                    $maxLoss = -PHP_INT_MAX;
                    $hasLoss = false;
                    foreach ($candidates as $other) {
                        if ($c === $other) continue;
                        if ($edata->majorityPrefers($other, $c)) {
                            $margin = $edata->margin($other, $c);
                            if ($margin > $maxLoss) {
                                $maxLoss = $margin;
                            }
                            $hasLoss = true;
                        }
                    }
                    $scores[$c] = $hasLoss ? $maxLoss : 0;
                }

                $minScore = min($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $minScore));
                sort($winners);
                return $winners;
            },
            'Minimax'
        );
    }

    /**
     * Minimax using support instead of margin.
     */
    public static function minimaxSupport(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $scores = [];
                foreach ($candidates as $c) {
                    $maxSupport = 0;
                    foreach ($candidates as $other) {
                        if ($c === $other) continue;
                        $support = $edata->support($other, $c);
                        if ($support > $maxSupport) {
                            $maxSupport = $support;
                        }
                    }
                    $scores[$c] = $maxSupport;
                }

                $minScore = min($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $minScore));
                sort($winners);
                return $winners;
            },
            'Minimax (Support)'
        );
    }

    /**
     * Leximax voting method.
     */
    public static function leximax(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $sequences = [];
                foreach ($candidates as $c) {
                    $margins = [];
                    foreach ($candidates as $other) {
                        if ($c === $other) continue;
                        $margins[] = $edata->margin($other, $c);
                    }
                    rsort($margins);
                    $sequences[$c] = $margins;
                }

                // Find minimal sequence lexicographically
                $minSequence = null;
                foreach ($sequences as $seq) {
                    if ($minSequence === null || $seq < $minSequence) {
                        $minSequence = $seq;
                    }
                }

                $winners = array_keys(array_filter($sequences, fn($s) => $s === $minSequence));
                sort($winners);
                return $winners;
            },
            'Leximax'
        );
    }

    /**
     * Most Wins, Smallest Loss (MWSL) voting method.
     */
    public static function mwsl(bool $halfPointForTies = true, bool $lexicographicTieBreaker = true): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null) use ($halfPointForTies, $lexicographicTieBreaker): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $scores = $halfPointForTies 
                    ? $edata->copelandScores($candidates, [1, 0.5, 0])
                    : $edata->copelandScores($candidates, [1, 0, 0]);

                $maxWinScore = max($scores);
                $mostWins = array_keys(array_filter($scores, fn($s) => $s == $maxWinScore));

                if (count($mostWins) === 1) {
                    return $mostWins;
                }

                if (!$lexicographicTieBreaker) {
                    $smallestLosses = [];
                    foreach ($mostWins as $c) {
                        $defeaters = $edata->dominators($c, $candidates);
                        if (!empty($defeaters)) {
                            $margins = array_map(fn($d) => $edata->margin($d, $c), $defeaters);
                            $smallestLosses[$c] = min($margins);
                        } else {
                            $smallestLosses[$c] = 0;
                        }
                    }
                    $minLoss = min($smallestLosses);
                    $winners = array_keys(array_filter($smallestLosses, fn($s) => $s == $minLoss));
                } else {
                    $lossSequences = [];
                    foreach ($mostWins as $c) {
                        $defeaters = $edata->dominators($c, $candidates);
                        if (!empty($defeaters)) {
                            $margins = array_map(fn($d) => $edata->margin($d, $c), $defeaters);
                            rsort($margins);
                            $lossSequences[$c] = $margins;
                        } else {
                            $lossSequences[$c] = [0];
                        }
                    }
                    $minSeq = null;
                    foreach ($lossSequences as $seq) {
                        if ($minSeq === null || $seq < $minSeq) {
                            $minSeq = $seq;
                        }
                    }
                    $winners = array_keys(array_filter($lossSequences, fn($s) => $s === $minSeq));
                }

                sort($winners);
                return $winners;
            },
            'Most Wins, Smallest Loss'
        );
    }

    /**
     * Beat Path (Schulze Method).
     */
    public static function beatPath(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];
                $n = count($candidates);
                $cands = array_values($candidates);

                // Initialize strengths
                $p = [];
                for ($i = 0; $i < $n; $i++) {
                    $p[$i] = array_fill(0, $n, -PHP_INT_MAX);
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j) {
                            $margin = $edata->margin($cands[$i], $cands[$j]);
                            if ($margin > 0) {
                                $p[$i][$j] = $margin;
                            }
                        }
                    }
                }

                // Floyd-Warshall
                for ($i = 0; $i < $n; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j) {
                            for ($k = 0; $k < $n; $k++) {
                                if ($i !== $k && $j !== $k) {
                                    $p[$j][$k] = max($p[$j][$k], min($p[$j][$i], $p[$i][$k]));
                                }
                            }
                        }
                    }
                }

                $winners = [];
                for ($i = 0; $i < $n; $i++) {
                    $isWinner = true;
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j && $p[$j][$i] > $p[$i][$j]) {
                            $isWinner = false;
                            break;
                        }
                    }
                    if ($isWinner) {
                        $winners[] = $cands[$i];
                    }
                }

                sort($winners);
                return $winners;
            },
            'Beat Path'
        );
    }

    /**
     * Split Cycle.
     */
    public static function splitCycle(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];
                $n = count($candidates);
                $cands = array_values($candidates);

                // Initialize strengths (Floyd-Warshall variant)
                $p = [];
                for ($i = 0; $i < $n; $i++) {
                    $p[$i] = array_fill(0, $n, -PHP_INT_MAX);
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j) {
                            $margin = $edata->margin($cands[$i], $cands[$j]);
                            if ($margin > 0) {
                                $p[$i][$j] = $margin;
                            }
                        }
                    }
                }

                for ($i = 0; $i < $n; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j) {
                            for ($k = 0; $k < $n; $k++) {
                                if ($i !== $k && $j !== $k) {
                                    $p[$j][$k] = max($p[$j][$k], min($p[$j][$i], $p[$i][$k]));
                                }
                            }
                        }
                    }
                }

                $winners = [];
                for ($i = 0; $i < $n; $i++) {
                    $isWinner = true;
                    for ($j = 0; $j < $n; $j++) {
                        if ($i !== $j) {
                            $marginJI = $edata->margin($cands[$j], $cands[$i]);
                            if ($marginJI > $p[$i][$j]) {
                                $isWinner = false;
                                break;
                            }
                        }
                    }
                    if ($isWinner) {
                        $winners[] = $cands[$i];
                    }
                }

                sort($winners);
                return $winners;
            },
            'Split Cycle'
        );
    }

    /**
     * Ranked Pairs (Tideman's Rule).
     * Iterates over all permutations of tied edges to find all possible winners.
     */
    public static function rankedPairs(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                // Condorcet winner optimization
                $cw = $edata->condorcetWinner($currCands);
                if ($cw !== null) return [$cw];

                // Collect all winning edges (margin >= 0)
                $edges = [];
                foreach ($candidates as $c1) {
                    foreach ($candidates as $c2) {
                        if ($c1 === $c2) continue;
                        $margin = $edata->margin($c1, $c2);
                        if ($margin >= 0) {
                            $edges[] = ['source' => $c1, 'target' => $c2, 'margin' => $margin];
                        }
                    }
                }

                if (empty($edges)) {
                    return array_values($candidates);
                }

                // Group edges by margin
                $groupedEdges = [];
                foreach ($edges as $edge) {
                    $groupedEdges[(string)$edge['margin']][] = $edge;
                }
                krsort($groupedEdges, SORT_NUMERIC);
                $edgeGroups = array_values($groupedEdges);

                $candList = array_values($candidates);
                $candToIdx = array_flip($candList);
                $winners = [];
                $spo = new SPO(count($candList));

                self::processRankedPairsGroups($spo, $edgeGroups, 0, $candToIdx, $candList, $winners);

                $winners = array_unique($winners);
                sort($winners);
                return $winners;
            },
            'Ranked Pairs'
        );
    }

    private static function processRankedPairsGroups(SPO $spo, array $groups, int $idx, array $candToIdx, array $candList, array &$winners): void
    {
        if ($idx >= count($groups)) {
            $initial = $spo->initialElements();
            if (count($initial) > 0) {
                $winners[] = $candList[$initial[0]];
            }
            return;
        }

        $group = $groups[$idx];
        foreach (self::permutations($group) as $permEdges) {
            $nextSpo = clone $spo;
            foreach ($permEdges as $edge) {
                $u = $candToIdx[$edge['source']];
                $v = $candToIdx[$edge['target']];
                // Add edge if it doesn't create a cycle
                if (!$nextSpo->P[$v][$u]) {
                    $nextSpo->add($u, $v);
                }
            }
            self::processRankedPairsGroups($nextSpo, $groups, $idx + 1, $candToIdx, $candList, $winners);
        }
    }

    /**
     * River voting method.
     * Iterates over all permutations of tied edges to find all possible winners.
     */
    public static function river(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                $cw = $edata->condorcetWinner($currCands);
                if ($cw !== null) return [$cw];

                $edges = [];
                foreach ($candidates as $c1) {
                    foreach ($candidates as $c2) {
                        if ($c1 === $c2) continue;
                        $margin = $edata->margin($c1, $c2);
                        if ($margin >= 0) {
                            $edges[] = ['source' => $c1, 'target' => $c2, 'margin' => $margin];
                        }
                    }
                }

                if (empty($edges)) {
                    return array_values($candidates);
                }

                // Group edges by margin
                $groupedEdges = [];
                foreach ($edges as $edge) {
                    $groupedEdges[(string)$edge['margin']][] = $edge;
                }
                krsort($groupedEdges, SORT_NUMERIC);
                $edgeGroups = array_values($groupedEdges);

                $candList = array_values($candidates);
                $candToIdx = array_flip($candList);
                $winners = [];
                $spo = new SPO(count($candList));

                self::processRiverGroups($spo, $edgeGroups, 0, $candToIdx, $candList, $winners);

                $winners = array_unique($winners);
                sort($winners);
                return $winners;
            },
            'River'
        );
    }

    private static function processRiverGroups(SPO $spo, array $groups, int $idx, array $candToIdx, array $candList, array &$winners): void
    {
        if ($idx >= count($groups)) {
            $initial = $spo->initialElements();
            if (count($initial) > 0) {
                $winners[] = $candList[$initial[0]];
            }
            return;
        }

        $group = $groups[$idx];
        foreach (self::permutations($group) as $permEdges) {
            $nextSpo = clone $spo;
            foreach ($permEdges as $edge) {
                $u = $candToIdx[$edge['source']];
                $v = $candToIdx[$edge['target']];
                // River: Skip if target already has an incoming edge
                if (!empty($nextSpo->preds[$v])) continue;

                if (!$nextSpo->P[$v][$u]) {
                    $nextSpo->add($u, $v);
                }
            }
            self::processRiverGroups($nextSpo, $groups, $idx + 1, $candToIdx, $candList, $winners);
        }
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

    /**
     * Simple Stable Voting.
     */
    public static function simpleStableVoting(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $memo = [];
                $winners = self::recursiveSimpleStable($edata, array_values($candidates), $memo);
                sort($winners);
                return $winners;
            },
            'Simple Stable Voting'
        );
    }

    private static function recursiveSimpleStable(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, array $candidates, array &$memo): array
    {
        if (count($candidates) === 1) return $candidates;
        
        sort($candidates);
        $key = implode(',', $candidates);
        if (isset($memo[$key])) return $memo[$key];

        // Order pairs (a,b) by margin
        $matches = [];
        foreach ($candidates as $a) {
            foreach ($candidates as $b) {
                if ($a === $b) continue;
                $matches[] = ['a' => $a, 'b' => $b, 'margin' => $edata->margin($a, $b)];
            }
        }
        usort($matches, fn($m1, $m2) => $m2['margin'] <=> $m1['margin']);

        $svWinners = [];
        $maxMargin = -PHP_INT_MAX;

        foreach ($matches as $match) {
            if ($match['margin'] < $maxMargin) break;
            if (in_array($match['a'], $svWinners, true)) continue;

            $remaining = array_values(array_diff($candidates, [$match['b']]));
            $subWinners = self::recursiveSimpleStable($edata, $remaining, $memo);
            
            if (in_array($match['a'], $subWinners, true)) {
                $svWinners[] = $match['a'];
                $maxMargin = $match['margin'];
            }
        }

        $memo[$key] = $svWinners;
        return $svWinners;
    }

    /**
     * Stable Voting.
     */
    public static function stableVoting(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $memo = [];
                $winners = self::recursiveStable($edata, array_values($candidates), $memo);
                sort($winners);
                return $winners;
            },
            'Stable Voting'
        );
    }

    private static function recursiveStable(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, array $candidates, array &$memo): array
    {
        if (count($candidates) === 1) return $candidates;

        sort($candidates);
        $key = implode(',', $candidates);
        if (isset($memo[$key])) return $memo[$key];

        // Find undefeated candidates via Split Cycle
        $splitCycleWinners = (self::splitCycle())($edata, $candidates);

        // Order pairs (a,b) by margin
        $matches = [];
        foreach ($candidates as $a) {
            foreach ($candidates as $b) {
                if ($a === $b) continue;
                $matches[] = ['a' => $a, 'b' => $b, 'margin' => $edata->margin($a, $b)];
            }
        }
        usort($matches, fn($m1, $m2) => $m2['margin'] <=> $m1['margin']);

        $svWinners = [];
        $maxMargin = -PHP_INT_MAX;

        foreach ($matches as $match) {
            if ($match['margin'] < $maxMargin) break;
            if (!in_array($match['a'], $splitCycleWinners, true)) continue;
            if (in_array($match['a'], $svWinners, true)) continue;

            $remaining = array_values(array_diff($candidates, [$match['b']]));
            // Note: simpleStable is used in the sub-election according to definition
            $subWinners = self::recursiveSimpleStable($edata, $remaining, $memo);
            
            if (in_array($match['a'], $subWinners, true)) {
                $svWinners[] = $match['a'];
                $maxMargin = $match['margin'];
            }
        }

        $memo[$key] = $svWinners;
        return $svWinners;
    }
}

// Pre-instantiated methods
$minimax = MarginBasedMethods::minimax();
$minimaxSupport = MarginBasedMethods::minimaxSupport();
$leximax = MarginBasedMethods::leximax();
$mwsl = MarginBasedMethods::mwsl();
$beatPath = MarginBasedMethods::beatPath();
$splitCycle = MarginBasedMethods::splitCycle();
$rankedPairs = MarginBasedMethods::rankedPairs();
$river = MarginBasedMethods::river();
$simpleStableVoting = MarginBasedMethods::simpleStableVoting();
$stableVoting = MarginBasedMethods::stableVoting();
