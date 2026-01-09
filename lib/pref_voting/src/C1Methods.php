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
 * Implementations of voting methods that work on both profiles and majority graphs.
 */
class C1Methods
{
    /**
     * Return the Condorcet winner if one exists, otherwise return all candidates.
     */
    public static function condorcet(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $cw = $edata->condorcetWinner($currCands);
                if ($cw !== null) {
                    return [$cw];
                }
                sort($candidates);
                return $candidates;
            },
            'Condorcet'
        );
    }

    /**
     * Return all weak Condorcet winners if any exist, otherwise return all candidates.
     */
    public static function weakCondorcet(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $wcw = $edata->weakCondorcetWinner($currCands);
                if ($wcw !== null) {
                    return $wcw;
                }
                sort($candidates);
                return $candidates;
            },
            'Weak Condorcet'
        );
    }

    /**
     * Copeland winners are candidates with the maximum Copeland score.
     */
    public static function copeland(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $scores = $edata->copelandScores($currCands);
                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
                sort($winners);
                return $winners;
            },
            'Copeland'
        );
    }

    /**
     * SWF version of Copeland.
     */
    public static function copelandRanking(bool $local = true, ?string $tieBreaking = null): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null) use ($local, $tieBreaking): array {
                $cands = $currCands ?? $edata->candidates;
                if ($local) {
                    $scores = $edata->copelandScores($cands);
                } else {
                    $allScores = $edata->copelandScores();
                    $scores = array_filter($allScores, fn($c) => in_array($c, $cands, true), ARRAY_FILTER_USE_KEY);
                }

                $rmap = [];
                foreach ($scores as $c => $s) {
                    $rmap[$c] = -$s;
                }

                $ranking = new Ranking($rmap, $edata->cmap);
                $ranking->normalizeRanks();

                if ($tieBreaking === 'alphabetic') {
                    $ranking = Ranking::breakTiesAlphabetically($ranking);
                }

                return [$ranking];
            },
            'Copeland ranking'
        );
    }

    /**
     * Llull winners are candidates with the maximum Llull score.
     * Llull: 1 for win, 1 for tie, 0 for loss.
     */
    public static function llull(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $scores = $edata->copelandScores($currCands, [1, 1, 0]);
                $maxScore = max($scores);
                $winners = array_keys(array_filter($scores, fn($s) => $s == $maxScore));
                sort($winners);
                return $winners;
            },
            'Llull'
        );
    }

    /**
     * Uncovered Set (Gillies version).
     */
    public static function ucGill(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $dom = [];
                foreach ($candidates as $c) {
                    $dom[$c] = $edata->dominators($c, $candidates);
                }

                $winners = [];
                foreach ($candidates as $c1) {
                    $isUndefeated = true;
                    foreach ($dom[$c1] as $c2) {
                        // Check if c2 left covers c1: dom(c2) subset of dom(c1)
                        if (self::isSubset($dom[$c2], $dom[$c1])) {
                            $isUndefeated = false;
                            break;
                        }
                    }
                    if ($isUndefeated) {
                        $winners[] = $c1;
                    }
                }
                sort($winners);
                return $winners;
            },
            'Uncovered Set'
        );
    }

    /**
     * Uncovered Set (Fishburn version).
     */
    public static function ucFish(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $dom = [];
                foreach ($candidates as $c) {
                    $dom[$c] = $edata->dominators($c, $candidates);
                }

                $winners = [];
                foreach ($candidates as $c1) {
                    $isUndefeated = true;
                    foreach ($candidates as $c2) {
                        if ($c1 === $c2) continue;
                        $c2CoversC1 = self::isSubset($dom[$c2], $dom[$c1]);
                        $c1CoversC2 = self::isSubset($dom[$c1], $dom[$c2]);
                        if ($c2CoversC1 && !$c1CoversC2) {
                            $isUndefeated = false;
                            break;
                        }
                    }
                    if ($isUndefeated) {
                        $winners[] = $c1;
                    }
                }
                sort($winners);
                return $winners;
            },
            'Uncovered Set - Fishburn'
        );
    }

    /**
     * Uncovered Set (Bordes version).
     */
    public static function ucBordes(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $weakDom = [];
                foreach ($candidates as $c1) {
                    $weakDom[$c1] = [];
                    foreach ($candidates as $c2) {
                        if ($edata->majorityPrefers($c2, $c1) || $edata->isTied($c1, $c2)) {
                            $weakDom[$c1][] = $c2;
                        }
                    }
                }

                $winners = [];
                foreach ($candidates as $c1) {
                    $isUndefeated = true;
                    foreach ($edata->dominators($c1, $candidates) as $c2) {
                        if (self::isSubset($weakDom[$c2], $weakDom[$c1])) {
                            $isUndefeated = false;
                            break;
                        }
                    }
                    if ($isUndefeated) {
                        $winners[] = $c1;
                    }
                }
                sort($winners);
                return $winners;
            },
            'Uncovered Set - Bordes'
        );
    }

    /**
     * Uncovered Set (McKelvey version).
     */
    public static function ucMcKelvey(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $dom = [];
                $weakDom = [];
                foreach ($candidates as $c1) {
                    $dom[$c1] = $edata->dominators($c1, $candidates);
                    $weakDom[$c1] = [];
                    foreach ($candidates as $c2) {
                        if ($edata->majorityPrefers($c2, $c1) || $edata->isTied($c1, $c2)) {
                            $weakDom[$c1][] = $c2;
                        }
                    }
                }

                $winners = [];
                foreach ($candidates as $c1) {
                    $isUndefeated = true;
                    foreach ($dom[$c1] as $c2) {
                        if (self::isSubset($dom[$c2], $dom[$c1]) && self::isSubset($weakDom[$c2], $weakDom[$c1])) {
                            $isUndefeated = false;
                            break;
                        }
                    }
                    if ($isUndefeated) {
                        $winners[] = $c1;
                    }
                }
                sort($winners);
                return $winners;
            },
            'Uncovered Set - McKelvey'
        );
    }

    /**
     * Top Cycle (Smith Set).
     */
    public static function topCycle(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                // Build weak majority graph: edge if c1 >= c2
                $adj = [];
                foreach ($candidates as $c1) {
                    $adj[$c1] = [];
                    foreach ($candidates as $c2) {
                        if ($c1 !== $c2 && ($edata->majorityPrefers($c1, $c2) || $edata->isTied($c1, $c2))) {
                            $adj[$c1][] = $c2;
                        }
                    }
                }

                $sccs = self::getSCCs($candidates, $adj);
                
                // Find SCC with no incoming edges from other SCCs
                // In Top Cycle, this is the SCC whose candidates beat all others
                foreach ($sccs as $scc) {
                    $isTop = true;
                    foreach ($candidates as $other) {
                        if (in_array($other, $scc, true)) continue;
                        foreach ($scc as $member) {
                            if ($edata->majorityPrefers($other, $member)) {
                                $isTop = false;
                                break 2;
                            }
                        }
                    }
                    if ($isTop) {
                        sort($scc);
                        return $scc;
                    }
                }

                return [];
            },
            'Top Cycle'
        );
    }

    /**
     * GOCHA (Schwartz Set).
     */
    public static function gocha(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];

                // Build majority graph
                $adj = [];
                foreach ($candidates as $c1) {
                    $adj[$c1] = $edata->dominates($c1, $candidates);
                }

                $sccs = self::getSCCs($candidates, $adj);
                
                $winners = [];
                foreach ($sccs as $scc) {
                    $isUndefeated = true;
                    foreach ($candidates as $other) {
                        if (in_array($other, $scc, true)) continue;
                        foreach ($scc as $member) {
                            if ($edata->majorityPrefers($other, $member)) {
                                // Check if member can reach other
                                if (!self::canReach($member, $other, $adj)) {
                                    $isUndefeated = false;
                                    break 2;
                                }
                            }
                        }
                    }
                    if ($isUndefeated) {
                        $winners = array_merge($winners, $scc);
                    }
                }

                sort($winners);
                return array_values(array_unique($winners));
            },
            'GOCHA'
        );
    }

    /**
     * Banks winners.
     */
    public static function banks(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                $adj = [];
                foreach ($candidates as $c1) {
                    $adj[$c1] = $edata->dominates($c1, $candidates);
                }

                $maximalChains = self::getMaximalChains($candidates, $adj);
                $winners = [];
                foreach ($maximalChains as $chain) {
                    $winners[] = $chain[0];
                }
                $winners = array_unique($winners);
                sort($winners);
                return array_values($winners);
            },
            'Banks'
        );
    }

    /**
     * Slater winners.
     */
    public static function slater(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $candidates = $currCands ?? $edata->candidates;
                if (empty($candidates)) return [];
                
                $minDist = PHP_INT_MAX;
                $winners = [];

                foreach (self::permutations($candidates) as $order) {
                    $dist = 0;
                    $n = count($order);
                    for ($i = 0; $i < $n; $i++) {
                        for ($j = $i + 1; $j < $n; $j++) {
                            if ($edata->majorityPrefers($order[$j], $order[$i])) {
                                $dist++;
                            }
                        }
                    }

                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $winners = [$order[0]];
                    } elseif ($dist === $minDist) {
                        $winners[] = $order[0];
                    }
                }

                $winners = array_unique($winners);
                sort($winners);
                return array_values($winners);
            },
            'Slater'
        );
    }

    // Helper functions

    private static function isSubset(array $sub, array $parent): bool
    {
        foreach ($sub as $val) {
            if (!in_array($val, $parent, true)) {
                return false;
            }
        }
        return true;
    }

    private static function getSCCs(array $nodes, array $adj): array
    {
        $index = 0;
        $stack = [];
        $indices = [];
        $lowlink = [];
        $onStack = [];
        $sccs = [];

        $strongconnect = function ($v) use (&$strongconnect, &$index, &$stack, &$indices, &$lowlink, &$onStack, &$sccs, $adj) {
            $indices[$v] = $index;
            $lowlink[$v] = $index;
            $index++;
            $stack[] = $v;
            $onStack[$v] = true;

            foreach ($adj[$v] as $w) {
                if (!isset($indices[$w])) {
                    $strongconnect($w);
                    $lowlink[$v] = min($lowlink[$v], $lowlink[$w]);
                } elseif ($onStack[$w]) {
                    $lowlink[$v] = min($lowlink[$v], $indices[$w]);
                }
            }

            if ($lowlink[$v] === $indices[$v]) {
                $scc = [];
                do {
                    $w = array_pop($stack);
                    $onStack[$w] = false;
                    $scc[] = $w;
                } while ($w !== $v);
                $sccs[] = $scc;
            }
        };

        foreach ($nodes as $node) {
            if (!isset($indices[$node])) {
                $strongconnect($node);
            }
        }

        return $sccs;
    }

    private static function canReach($start, $target, array $adj): bool
    {
        $visited = [];
        $queue = [$start];
        while (!empty($queue)) {
            $v = array_shift($queue);
            if ($v === $target) return true;
            if (!isset($visited[$v])) {
                $visited[$v] = true;
                foreach ($adj[$v] as $w) {
                    $queue[] = $w;
                }
            }
        }
        return false;
    }

    private static function getMaximalChains(array $nodes, array $adj): array
    {
        $chains = [];
        foreach ($nodes as $n) {
            self::findChains([$n], $nodes, $adj, $chains);
        }

        $maximal = [];
        foreach ($chains as $c1) {
            $isMax = true;
            foreach ($chains as $c2) {
                if ($c1 === $c2) continue;
                if (self::isSubsequence($c1, $c2)) {
                    $isMax = false;
                    break;
                }
            }
            if ($isMax) {
                $maximal[] = $c1;
            }
        }
        return $maximal;
    }

    private static function findChains(array $current, array $all, array $adj, array &$chains): void
    {
        $last = end($current);
        $found = false;
        foreach ($all as $next) {
            if (in_array($next, $current, true)) continue;
            // Check if adding $next maintains transitivity/linearity
            $beatsAll = true;
            foreach ($current as $member) {
                if (!in_array($next, $adj[$member], true)) {
                    $beatsAll = false;
                    break;
                }
            }
            if ($beatsAll) {
                $found = true;
                $newChain = $current;
                $newChain[] = $next;
                self::findChains($newChain, $all, $adj, $chains);
            }
        }
        if (!$found) {
            $chains[] = $current;
        }
    }

    private static function isSubsequence(array $sub, array $main): bool
    {
        $subIdx = 0;
        foreach ($main as $val) {
            if ($subIdx < count($sub) && $sub[$subIdx] === $val) {
                $subIdx++;
            }
        }
        return $subIdx === count($sub);
    }

    private static function permutations(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;
        } else {
            foreach (self::permutations(array_slice($items, 1)) as $p) {
                for ($i = 0; $i <= count($p); $i++) {
                    yield array_merge(array_slice($p, 0, $i), [ $items[0] ], array_slice($p, $i));
                }
            }
        }
    }
}

// Pre-instantiated methods
$condorcet = C1Methods::condorcet();
$weakCondorcet = C1Methods::weakCondorcet();
$copeland = C1Methods::copeland();
$llull = C1Methods::llull();
$ucGill = C1Methods::ucGill();
$ucFish = C1Methods::ucFish();
$ucBordes = C1Methods::ucBordes();
$ucMcKelvey = C1Methods::ucMcKelvey();
$topCycle = C1Methods::topCycle();
$gocha = C1Methods::gocha();
$banks = C1Methods::banks();
$slater = C1Methods::slater();
