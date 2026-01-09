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
 * Utility-based voting methods and social welfare functions.
 */
class UtilityMethods
{
    /**
     * Sum Utilitarian SWF.
     */
    public static function sumUtilitarianRanking(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                $cands = $currCands ?? $uprof->domain;
                $sums = [];
                foreach ($cands as $c) {
                    $sums[$c] = $uprof->utilSum($c);
                }
                
                usort($cands, function($a, $b) use ($sums) {
                    if (abs($sums[$a] - $sums[$b]) < 1e-12) return 0;
                    return $sums[$b] <=> $sums[$a];
                });

                $rmap = [];
                $rank = 1;
                foreach ($cands as $idx => $c) {
                    if ($idx > 0 && abs($sums[$c] - $sums[$cands[$idx-1]]) > 1e-12) {
                        $rank = $idx + 1;
                    }
                    $rmap[$c] = $rank;
                }

                return [new Ranking($rmap, $uprof->cmap)];
            },
            'Sum Utilitarian'
        );
    }

    /**
     * Sum Utilitarian Voting Method.
     */
    public static function sumUtilitarian(): VotingMethod
    {
        return new VotingMethod(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                return self::sumUtilitarianRanking()->winners($uprof, $currCands);
            },
            'Sum Utilitarian'
        );
    }

    /**
     * Relative Utilitarian SWF.
     */
    public static function relativeUtilitarianRanking(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                $cands = $currCands ?? $uprof->domain;
                $relUtils = [];
                foreach ($uprof->getUtilitiesCounts()[0] as $u) {
                    $relUtils[] = $u->normalizeByRange();
                }

                $sums = [];
                foreach ($cands as $c) {
                    $sum = 0.0;
                    foreach ($relUtils as $u) {
                        $val = $u($c);
                        if ($val !== null) $sum += $val;
                    }
                    $sums[$c] = $sum;
                }

                usort($cands, function($a, $b) use ($sums) {
                    if (abs($sums[$a] - $sums[$b]) < 1e-12) return 0;
                    return $sums[$b] <=> $sums[$a];
                });

                $rmap = [];
                $rank = 1;
                foreach ($cands as $idx => $c) {
                    if ($idx > 0 && abs($sums[$c] - $sums[$cands[$idx-1]]) > 1e-12) {
                        $rank = $idx + 1;
                    }
                    $rmap[$c] = $rank;
                }

                return [new Ranking($rmap, $uprof->cmap)];
            },
            'Relative Utilitarian'
        );
    }

    /**
     * Relative Utilitarian Voting Method.
     */
    public static function relativeUtilitarian(): VotingMethod
    {
        return new VotingMethod(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                return self::relativeUtilitarianRanking()->winners($uprof, $currCands);
            },
            'Relative Utilitarian'
        );
    }

    /**
     * Maximin SWF.
     */
    public static function maximinRanking(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                $cands = $currCands ?? $uprof->domain;
                $mins = [];
                foreach ($cands as $c) {
                    $mins[$c] = $uprof->utilMin($c);
                }

                usort($cands, function($a, $b) use ($mins) {
                    if (abs($mins[$a] - $mins[$b]) < 1e-12) return 0;
                    return $mins[$b] <=> $mins[$a];
                });

                $rmap = [];
                $rank = 1;
                foreach ($cands as $idx => $c) {
                    if ($idx > 0 && abs($mins[$c] - $mins[$cands[$idx-1]]) > 1e-12) {
                        $rank = $idx + 1;
                    }
                    $rmap[$c] = $rank;
                }

                return [new Ranking($rmap, $uprof->cmap)];
            },
            'Maximin'
        );
    }

    /**
     * Maximin Voting Method.
     */
    public static function maximin(): VotingMethod
    {
        return new VotingMethod(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                return self::maximinRanking()->winners($uprof, $currCands);
            },
            'Maximin'
        );
    }

    /**
     * Lexicographic Maximin SWF.
     */
    public static function lexicographicMaximinRanking(): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                $cands = $currCands ?? $uprof->domain;
                $utils = [];
                $voters = $uprof->getUtilities();
                foreach ($cands as $c) {
                    $cUtils = [];
                    foreach ($voters as $u) {
                        $val = $u($c);
                        if ($val !== null) $cUtils[] = $val;
                    }
                    sort($cUtils);
                    $utils[$c] = $cUtils;
                }

                usort($cands, function($a, $b) use ($utils) {
                    $ua = $utils[$a];
                    $ub = $utils[$b];
                    $count = min(count($ua), count($ub));
                    for ($i = 0; $i < $count; $i++) {
                        if ($ua[$i] > $ub[$i] + 1e-12) return -1;
                        if ($ua[$i] < $ub[$i] - 1e-12) return 1;
                    }
                    return count($ub) <=> count($ua);
                });

                $rmap = [];
                $rank = 1;
                foreach ($cands as $idx => $c) {
                    if ($idx > 0) {
                        $ua = $utils[$c];
                        $ub = $utils[$cands[$idx-1]];
                        $match = count($ua) === count($ub);
                        if ($match) {
                            foreach ($ua as $i => $val) {
                                if (abs($val - $ub[$i]) > 1e-12) {
                                    $match = false;
                                    break;
                                }
                            }
                        }
                        if (!$match) {
                            $rank = $idx + 1;
                        }
                    }
                    $rmap[$c] = $rank;
                }

                return [new Ranking($rmap, $uprof->cmap)];
            },
            'Lexicographic Maximin'
        );
    }

    /**
     * Lexicographic Maximin Voting Method.
     */
    public static function lexicographicMaximin(): VotingMethod
    {
        return new VotingMethod(
            function (UtilityProfile $uprof, ?array $currCands = null): array {
                return self::lexicographicMaximinRanking()->winners($uprof, $currCands);
            },
            'Lexicographic Maximin'
        );
    }

    /**
     * Nash SWF.
     */
    public static function nashRanking(int|string|null $sq = null): SocialWelfareFunction
    {
        return new SocialWelfareFunction(
            function (UtilityProfile $uprof, ?array $currCands = null) use ($sq): array {
                $domain = $uprof->domain;
                if ($sq !== null && !in_array($sq, $domain, true)) {
                    throw new \InvalidArgumentException("The status quo must be in the domain of the profile.");
                }

                $candsToConsider = $currCands ?? $domain;
                $sq = $sq ?? $candsToConsider[0];

                $voters = $uprof->getUtilities();
                $itemsToRank = [];
                foreach ($candsToConsider as $x) {
                    $betterThanSq = true;
                    foreach ($voters as $u) {
                        if ($u($x) <= $u($sq) + 1e-12) {
                            $betterThanSq = false;
                            break;
                        }
                    }
                    if ($betterThanSq) $itemsToRank[] = $x;
                }
                if (!in_array($sq, $itemsToRank, true)) $itemsToRank[] = $sq;

                $nashUtils = [];
                foreach ($itemsToRank as $x) {
                    $prod = 1.0;
                    foreach ($voters as $u) {
                        $prod *= ($u($x) - $u($sq));
                    }
                    $nashUtils[$x] = $prod;
                }

                usort($itemsToRank, function($a, $b) use ($nashUtils) {
                    if (abs($nashUtils[$a] - $nashUtils[$b]) < 1e-12) return 0;
                    return $nashUtils[$b] <=> $nashUtils[$a];
                });

                $rmap = [];
                $rank = 1;
                foreach ($itemsToRank as $idx => $c) {
                    if ($idx > 0 && abs($nashUtils[$c] - $nashUtils[$itemsToRank[$idx-1]]) > 1e-12) {
                        $rank = $idx + 1;
                    }
                    $rmap[$c] = $rank;
                }

                return [new Ranking($rmap, $uprof->cmap)];
            },
            'Nash'
        );
    }

    /**
     * Nash Voting Method.
     */
    public static function nash(int|string|null $sq = null): VotingMethod
    {
        return new VotingMethod(
            function (UtilityProfile $uprof, ?array $currCands = null) use ($sq): array {
                return self::nashRanking($sq)->winners($uprof, $currCands);
            },
            'Nash'
        );
    }
}