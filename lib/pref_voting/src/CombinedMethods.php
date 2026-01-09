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
 * Implementations of voting methods that combine multiple methods.
 */
class CombinedMethods
{
    /**
     * Daunou's voting method.
     * Condorcet winner OR (iterated removal of Condorcet losers + plurality on survivors).
     */
    public static function daunou(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $candidates = $currCands ?? $profile->candidates;
                $cw = $profile->condorcetWinner($candidates);
                if ($cw !== null) {
                    return [$cw];
                }

                $iteratedRemovalCL = IterativeMethods::iteratedRemovalCL();
                $survivors = $iteratedRemovalCL($profile, $candidates);
                
                $plurality = ScoringMethods::plurality();
                $winners = $plurality($profile, $survivors);

                sort($winners);
                return $winners;
            },
            'Daunou'
        );
    }

    /**
     * Black's voting method.
     * Condorcet winner OR Borda.
     */
    public static function blacks(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $cw = $profile->condorcetWinner($currCands);
                if ($cw !== null) {
                    return [$cw];
                }

                $borda = ScoringMethods::borda();
                return $borda($profile, $currCands);
            },
            'Blacks'
        );
    }

    /**
     * Smith Set then IRV.
     */
    public static function smithIrv(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $topCycle = C1Methods::topCycle();
                $smith = $topCycle($profile, $currCands);

                $irv = IterativeMethods::instantRunoff();
                return $irv($profile, $smith);
            },
            'Smith IRV'
        );
    }

    /**
     * Smith Set then IRV PUT.
     */
    public static function smithIrvPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $topCycle = C1Methods::topCycle();
                $smith = $topCycle($profile, $currCands);

                $irvPut = IterativeMethods::instantRunoffPut();
                return $irvPut($profile, $smith);
            },
            'Smith IRV PUT'
        );
    }

    /**
     * Condorcet winner OR IRV.
     */
    public static function condorcetIrv(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $cw = $profile->condorcetWinner($currCands);
                if ($cw !== null) {
                    return [$cw];
                }

                $irv = IterativeMethods::instantRunoff();
                return $irv($profile, $currCands);
            },
            'Condorcet IRV'
        );
    }

    /**
     * Condorcet winner OR IRV PUT.
     */
    public static function condorcetIrvPut(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $cw = $profile->condorcetWinner($currCands);
                if ($cw !== null) {
                    return [$cw];
                }

                $irvPut = IterativeMethods::instantRunoffPut();
                return $irvPut($profile, $currCands);
            },
            'Condorcet IRV PUT'
        );
    }

    /**
     * Higher-order method: Compose vm1 and vm2.
     * Run vm1, then run vm2 on the winners of vm1.
     */
    public static function compose(VotingMethod $vm1, VotingMethod $vm2): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null) use ($vm1, $vm2): array {
                $vm1Winners = $vm1($edata, $currCands);
                return $vm2($edata, $vm1Winners);
            },
            "{$vm1->name}-{$vm2->name}"
        );
    }

    /**
     * Condorcet winner OR plurality.
     */
    public static function condorcetPlurality(): VotingMethod
    {
        return self::compose(C1Methods::condorcet(), ScoringMethods::plurality());
    }

    /**
     * Smith Set then Minimax.
     */
    public static function smithMinimax(): VotingMethod
    {
        return self::compose(C1Methods::topCycle(), MarginBasedMethods::minimax());
    }

    /**
     * Copeland winners then Borda.
     */
    public static function copelandLocalBorda(): VotingMethod
    {
        return self::compose(C1Methods::copeland(), ScoringMethods::borda());
    }

    /**
     * Copeland winners then Minimax.
     */
    public static function copelandLocalMinimax(): VotingMethod
    {
        return self::compose(C1Methods::copeland(), MarginBasedMethods::minimax());
    }

    /**
     * Copeland winners, then break ties with global Borda scores.
     */
    public static function copelandGlobalBorda(): VotingMethod
    {
        return new VotingMethod(
            function (Profile $profile, ?array $currCands = null): array {
                $copeland = C1Methods::copeland();
                $ws = $copeland($profile, $currCands);

                if (count($ws) <= 1) return $ws;

                $globalBordaScores = $profile->bordaScores();
                $wsScores = array_filter($globalBordaScores, fn($c) => in_array($c, $ws, true), ARRAY_FILTER_USE_KEY);
                
                $maxScore = max($wsScores);
                $winners = array_keys(array_filter($wsScores, fn($s) => $s == $maxScore));
                sort($winners);
                return $winners;
            },
            'Copeland-Global-Borda'
        );
    }

    /**
     * Copeland winners, then break ties with global Minimax scores.
     */
    public static function copelandGlobalMinimax(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                $copeland = C1Methods::copeland();
                $ws = $copeland($edata, $currCands);

                if (count($ws) <= 1) return $ws;

                // We need minimax scores relative to the full candidate set
                $candidates = $currCands ?? $edata->candidates;
                $allMinimaxWinners = MarginBasedMethods::minimax()($edata, $candidates);
                
                // The Python version calculates scores directly. Let's replicate that.
                $scores = [];
                foreach ($ws as $c) {
                    $maxLoss = -PHP_INT_MAX;
                    $hasLoss = false;
                    foreach ($candidates as $other) {
                        if ($c === $other) continue;
                        if ($edata->majorityPrefers($other, $c)) {
                            $margin = $edata->margin($other, $c);
                            if ($margin > $maxLoss) $maxLoss = $margin;
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
            'Copeland-Global-Minimax'
        );
    }

    /**
     * Higher-order method: Faceoff between vm1 and vm2.
     */
    public static function faceoff(VotingMethod $vm1, VotingMethod $vm2): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null) use ($vm1, $vm2): array {
                $vm1Winners = $vm1($edata, $currCands);
                $vm2Winners = $vm2($edata, $currCands);

                sort($vm1Winners);
                sort($vm2Winners);
                if ($vm1Winners === $vm2Winners) {
                    return $vm1Winners;
                }

                $winners = [];
                foreach ($vm1Winners as $a) {
                    foreach ($vm2Winners as $b) {
                        $margin = $edata->margin($a, $b);
                        if ($margin > 0) {
                            $winners[] = $a;
                        } elseif ($margin < 0) {
                            $winners[] = $b;
                        } else {
                            $winners[] = $a;
                            $winners[] = $b;
                        }
                    }
                }

                $winners = array_unique($winners);
                sort($winners);
                return array_values($winners);
            },
            "{$vm1->name}-{$vm2->name} Faceoff"
        );
    }

    /**
     * Faceoff between Borda and Minimax.
     */
    public static function bordaMinimaxFaceoff(): VotingMethod
    {
        return self::faceoff(ScoringMethods::borda(), MarginBasedMethods::minimax());
    }
}

// Pre-instantiated methods
$daunou = CombinedMethods::daunou();
$blacks = CombinedMethods::blacks();
$smithIrv = CombinedMethods::smithIrv();
$smithIrvPut = CombinedMethods::smithIrvPut();
$condorcetIrv = CombinedMethods::condorcetIrv();
$condorcetIrvPut = CombinedMethods::condorcetIrvPut();
$condorcetPlurality = CombinedMethods::condorcetPlurality();
$smithMinimax = CombinedMethods::smithMinimax();
$copelandLocalBorda = CombinedMethods::copelandLocalBorda();
$copelandLocalMinimax = CombinedMethods::copelandLocalMinimax();
$copelandGlobalBorda = CombinedMethods::copelandGlobalBorda();
$copelandGlobalMinimax = CombinedMethods::copelandGlobalMinimax();
$bordaMinimaxFaceoff = CombinedMethods::bordaMinimaxFaceoff();
