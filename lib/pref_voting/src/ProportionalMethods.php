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
 * Implementations of voting methods for proportional representation.
 */
class ProportionalMethods
{
    public const EPS = 1e-12;

    private static function trace(string $msg): void
    {
        if (getenv('STV_TRACE') === '1') {
            echo $msg . "\n";
        }
    }

    /**
     * Create initial ranking pieces from ProfileWithTies.
     *
     * @param Profile|ProfileWithTies $profile
     * @param array<int|string> $recipients
     * @param ParcelIndex $parcels
     * @return RankingPiece[]
     */
    private static function initialPiecesFromProfile(Profile|ProfileWithTies $profile, array $recipients, ParcelIndex $parcels): array
    {
        if ($profile instanceof Profile) {
            $profile = $profile->toProfileWithTies();
        }

        $pieces = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        $recipientSet = array_flip($recipients);

        foreach ($rankings as $idx => $ranking) {
            $count = $rcounts[$idx];
            if ($count <= 0) {
                continue;
            }

            $rmap = $ranking->rmap;
            $firstRank = null;
            $firstCands = [];

            foreach ($rmap as $c => $r) {
                if ($r !== null && isset($recipientSet[$c])) {
                    if ($firstRank === null || $r < $firstRank) {
                        $firstRank = $r;
                        $firstCands = [$c];
                    } elseif ($r === $firstRank) {
                        $firstCands[] = $c;
                    }
                }
            }

            if (!empty($firstCands)) {
                $share = (float)$count / count($firstCands);
                foreach ($firstCands as $c) {
                    $p = new RankingPiece($ranking, $share, $firstRank, $c, $share);
                    $parcels->noteArrival($c, count($pieces));
                    $pieces[] = $p;
                }
            }
        }
        return $pieces;
    }

    /**
     * Tally the total weight allocated to each candidate from a collection of pieces.
     *
     * @param RankingPiece[] $pieces
     * @param array<int|string>|null $restrictTo
     * @return array<int|string, float>
     */
    private static function tallyFromPieces(array $pieces, ?array $restrictTo = null): array
    {
        $tally = [];
        if ($restrictTo !== null) {
            foreach ($restrictTo as $c) {
                $tally[$c] = 0.0;
            }
        }
        $restrictSet = $restrictTo !== null ? array_flip($restrictTo) : null;

        foreach ($pieces as $p) {
            if ($p->weight <= self::EPS) {
                continue;
            }
            if ($restrictSet === null || isset($restrictSet[$p->cand])) {
                $tally[$p->cand] = ($tally[$p->cand] ?? 0.0) + $p->weight;
            }
        }
        return $tally;
    }

    /**
     * Find next preferences in a ranking after currentRank that are in recipients.
     *
     * @param Ranking $ranking
     * @param array<int|string> $recipients
     * @param int $currentRank
     * @return array{0: array<int|string>, 1: int}
     */
    private static function nextPrefsFromRanking(Ranking $ranking, array $recipients, int $currentRank): array
    {
        $rmap = $ranking->rmap;
        $recipientSet = array_flip($recipients);
        $nextRanks = [];
        foreach ($rmap as $c => $r) {
            if ($r !== null && $r > $currentRank && isset($recipientSet[$c])) {
                $nextRanks[] = $r;
            }
        }

        if (empty($nextRanks)) {
            return [[], -1];
        }

        $nextRank = min($nextRanks);
        $nextCands = [];
        foreach ($rmap as $c => $r) {
            if ($r === $nextRank && isset($recipientSet[$c])) {
                $nextCands[] = $c;
            }
        }
        return [$nextCands, $nextRank];
    }

    /**
     * Move a ranking piece forward to next preferences.
     *
     * @param RankingPiece $piece
     * @param array<int|string> $recipients
     * @return array<array{0: int|string, 1: float, 2: int}>
     */
    private static function movePieceForward(RankingPiece $piece, array $recipients): array
    {
        [$nxt, $newRank] = self::nextPrefsFromRanking($piece->ranking, $recipients, $piece->currentRank);
        if (empty($nxt)) {
            return [];
        }
        $share = 1.0 / (float)count($nxt);
        $result = [];
        foreach ($nxt as $c) {
            $result[] = [$c, $share, $newRank];
        }
        return $result;
    }

    /**
     * Inclusive Gregory surplus transfer.
     */
    private static function transferSurplusInclusive(
        array &$pieces,
        $elect,
        float $quota,
        array $recipients,
        ParcelIndex $parcels,
        bool $drainAll = true,
        bool $lastParcelOnly = false,
        bool $ersRounding = false
    ): bool {
        $tall = self::tallyFromPieces($pieces, array_merge($recipients, [$elect]));
        $surplus = ($tall[$elect] ?? 0.0) - $quota;
        if ($surplus <= self::EPS) {
            return false;
        }

        if ($lastParcelOnly) {
            $donorIdxs = $parcels->lastParcel($elect);
        } else {
            $donorIdxs = [];
            foreach ($pieces as $i => $p) {
                if ($p->cand === $elect && $p->weight > self::EPS) {
                    $donorIdxs[] = $i;
                }
            }
        }

        if (empty($donorIdxs)) {
            return false;
        }

        if ($drainAll) {
            $totalWeight = 0.0;
            foreach ($donorIdxs as $i) {
                $totalWeight += $pieces[$i]->weight;
            }
            if ($totalWeight <= self::EPS) {
                return false;
            }
            $frac = min(1.0, $surplus / $totalWeight);
            $anyMoved = false;
            $opened = [];

            foreach ($donorIdxs as $i) {
                $p = $pieces[$i];
                $drain = $p->weight * $frac;
                if ($ersRounding) {
                    $drain = floor($drain * 100.0) / 100.0;
                }
                if ($drain <= self::EPS) {
                    continue;
                }
                $forwards = self::movePieceForward($p, $recipients);
                if (!empty($forwards)) {
                    $share = $drain / (float)count($forwards);
                    foreach ($forwards as [$nxtC, $unused, $newRank]) {
                        if (!isset($opened[$nxtC])) {
                            $parcels->startNewParcel($nxtC);
                            $opened[$nxtC] = true;
                        }
                        $pieces[] = $p->cloneTo($nxtC, $newRank, $share);
                        $parcels->noteArrival($nxtC, count($pieces) - 1);
                    }
                }
                $p->weight -= $drain;
                $anyMoved = true;
            }
            if ($lastParcelOnly) {
                $parcels->clearParcel($elect);
            }
            return $anyMoved;
        }

        // Compensation (ERS/NB)
        $donors = [];
        $nxtCache = [];
        foreach ($donorIdxs as $i) {
            $forwards = self::movePieceForward($pieces[$i], $recipients);
            if (!empty($forwards)) {
                $donors[] = $i;
                $nxtCache[$i] = $forwards;
            }
        }

        $totalTransferable = 0.0;
        foreach ($donors as $i) {
            $totalTransferable += $pieces[$i]->weight;
        }
        if ($totalTransferable <= self::EPS) {
            return false;
        }

        $frac = min(1.0, $surplus / $totalTransferable);
        $anyMoved = false;
        $opened = [];

        foreach ($donors as $i) {
            $p = $pieces[$i];
            $drain = $p->weight * $frac;
            if ($ersRounding) {
                $drain = floor($drain * 100.0) / 100.0;
            }
            if ($drain <= self::EPS) {
                continue;
            }
            $forwards = $nxtCache[$i];
            $share = $drain / (float)count($forwards);
            foreach ($forwards as [$nxtC, $unused, $newRank]) {
                if (!isset($opened[$nxtC])) {
                    $parcels->startNewParcel($nxtC);
                    $opened[$nxtC] = true;
                }
                $pieces[] = $p->cloneTo($nxtC, $newRank, $share);
                $parcels->noteArrival($nxtC, count($pieces) - 1);
            }
            $p->weight -= $drain;
            $anyMoved = true;
        }
        if ($lastParcelOnly) {
            $parcels->clearParcel($elect);
        }
        return $anyMoved;
    }

    /**
     * Scottish STV surplus transfer (SSI 2007/42).
     */
    private static function transferSurplusScottish(
        array &$pieces,
        $elect,
        float $quota,
        array $recipients,
        ParcelIndex $parcels,
        int $decimals = 5
    ): bool {
        $tall = self::tallyFromPieces($pieces, array_merge($recipients, [$elect]));
        $total = $tall[$elect] ?? 0.0;
        $surplus = $total - $quota;
        if ($surplus <= self::EPS || $total <= self::EPS) {
            return false;
        }

        $scale = pow(10, $decimals);
        $anyMoved = false;
        $opened = [];

        $donorIdxs = [];
        foreach ($pieces as $i => $p) {
            if ($p->cand === $elect && $p->weight > self::EPS) {
                $donorIdxs[] = $i;
            }
        }
        if (empty($donorIdxs)) {
            return false;
        }

        foreach ($donorIdxs as $i) {
            $p = $pieces[$i];
            $forwards = self::movePieceForward($p, $recipients);
            if (empty($forwards)) {
                continue;
            }

            $base = $p->arrivedValue;
            $drain = floor((($surplus * $base / $total) * $scale) + self::EPS) / (float)$scale;
            if ($drain <= self::EPS) {
                continue;
            }

            $drain = min($drain, $p->weight);
            if ($drain <= self::EPS) {
                continue;
            }

            $p->weight -= $drain;
            $share = $drain / (float)count($forwards);
            foreach ($forwards as [$nxtC, $unused, $newRank]) {
                if (!isset($opened[$nxtC])) {
                    $parcels->startNewParcel($nxtC);
                    $opened[$nxtC] = true;
                }
                $pieces[] = $p->cloneTo($nxtC, $newRank, $share);
                $parcels->noteArrival($nxtC, count($pieces) - 1);
            }
            $anyMoved = true;
        }
        return $anyMoved;
    }

    /**
     * Eliminate the lowest candidate.
     */
    private static function eliminateLowest(
        array &$pieces,
        array &$continuing,
        ParcelIndex $parcels,
        ?callable $tieBreakKey = null
    ): ?array {
        if (empty($continuing)) {
            return null;
        }
        $tallies = self::tallyFromPieces($pieces, $continuing);
        $minT = INF;
        $lowest = [];
        foreach ($continuing as $c) {
            $t = $tallies[$c] ?? 0.0;
            if ($t < $minT - self::EPS) {
                $minT = $t;
                $lowest = [$c];
            } elseif (abs($t - $minT) <= self::EPS) {
                $lowest[] = $c;
            }
        }

        if (empty($lowest)) {
            return null;
        }

        if (count($lowest) > 1) {
            if ($tieBreakKey) {
                usort($lowest, fn($a, $b) => $tieBreakKey($a) <=> $tieBreakKey($b));
            } else {
                usort($lowest, fn($a, $b) => (string)$a <=> (string)$b);
            }
        }
        $elim = $lowest[0];
        $continuing = array_values(array_filter($continuing, fn($c) => $c !== $elim));

        $newPieces = [];
        $oldToNew = [];
        $pendingNotes = [];

        foreach ($pieces as $oldIdx => $p) {
            if ($p->cand !== $elim) {
                $newIdx = count($newPieces);
                $newPieces[] = $p;
                $oldToNew[$oldIdx] = $newIdx;
                continue;
            }
            $forwards = self::movePieceForward($p, $continuing);
            if (empty($forwards)) {
                $p->weight = 0.0;
                continue;
            }
            $opened = [];
            $share = $p->weight / (float)count($forwards);
            foreach ($forwards as [$nxtC, $unused, $newRank]) {
                if (!isset($opened[$nxtC])) {
                    $parcels->startNewParcel($nxtC);
                    $opened[$nxtC] = true;
                }
                $createdIdx = count($newPieces);
                $newPieces[] = $p->cloneTo($nxtC, $newRank, $share);
                $pendingNotes[] = [$nxtC, $createdIdx];
            }
            $p->weight = 0.0;
        }
        $parcels->remapIndices($oldToNew);
        foreach ($pendingNotes as [$cand, $idx]) {
            $parcels->noteArrival($cand, $idx);
        }
        $pieces = $newPieces;
        return [$elim, $pieces];
    }

    private static function nbQuota(float $totalWeight, int $numSeats): float
    {
        return $totalWeight / (float)($numSeats + 1);
    }

    private static function droopIntQuota(float $totalWeight, int $numSeats): float
    {
        if ($numSeats <= 0) {
            return INF;
        }
        return floor($totalWeight / (float)($numSeats + 1)) + 1;
    }

    // ---------- Public STV variants ----------

    public static function stvScottish(int $numSeats = 2, ?array $currCands = null, int $decimals = 5, $rng = null): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $decimals, $rng): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }

                $continuing = $currCands ?? $profile->candidates;
                $winners = [];
                $parcels = new ParcelIndex();
                $pieces = self::initialPiecesFromProfile($profile, $continuing, $parcels);

                [, $rcounts] = $profile->getRankingsCounts();
                $totalVotes = array_sum($rcounts);
                $quota = self::droopIntQuota((float)$totalVotes, $numSeats);

                $history = [];
                $snapshot = function () use (&$history, &$pieces) {
                    $history[] = self::tallyFromPieces($pieces);
                };
                $snapshot();

                $historyPrefer = function (array $cands, string $prefer = "highest") use (&$history) {
                    $tied = $cands;
                    foreach (array_reverse($history) as $snap) {
                        $vals = [];
                        foreach ($tied as $c) {
                            $vals[] = [$c, $snap[$c] ?? 0.0];
                        }
                        if (empty($vals)) break;

                        if ($prefer === "highest") {
                            $extreme = max(array_column($vals, 1));
                            $narrowed = array_column(array_filter($vals, fn($v) => abs($v[1] - $extreme) <= self::EPS), 0);
                        } else {
                            $extreme = min(array_column($vals, 1));
                            $narrowed = array_column(array_filter($vals, fn($v) => abs($v[1] - $extreme) <= self::EPS), 0);
                        }

                        if (count($narrowed) > 0 && count($narrowed) < count($tied)) {
                            $tied = $narrowed;
                            if (count($tied) === 1) return $tied[0];
                        }
                    }
                    return null;
                };

                $safety = 0;
                while (count($winners) < $numSeats) {
                    if (++$safety > 50000) throw new \RuntimeException("stv_scottish: loop safety tripped");

                    $talliesC = self::tallyFromPieces($pieces, $continuing);
                    $electedNow = array_filter($continuing, fn($c) => ($talliesC[$c] ?? 0.0) >= $quota - self::EPS);

                    if (!empty($electedNow)) {
                        sort($electedNow);
                        foreach ($electedNow as $c) {
                            $continuing = array_values(array_filter($continuing, fn($cc) => $cc !== $c));
                            $winners[] = $c;
                        }

                        $stuck = [];
                        while (true) {
                            $tallNow = self::tallyFromPieces($pieces);
                            $elig = array_filter($winners, fn($c) => (($tallNow[$c] ?? 0.0) - (float)$quota) > self::EPS && !in_array($c, $stuck, true));
                            if (empty($elig)) break;

                            $surpluses = [];
                            foreach ($elig as $c) {
                                $surpluses[$c] = $tallNow[$c] - (float)$quota;
                            }
                            $maxS = max($surpluses);
                            $tied = array_keys(array_filter($surpluses, fn($s) => abs($s - $maxS) <= self::EPS));

                            if (count($tied) > 1) {
                                $chosen = $historyPrefer($tied, "highest") ?? ($rng ? $rng->choice($tied) : $tied[array_rand($tied)]);
                            } else {
                                $chosen = $tied[0];
                            }

                            $moved = self::transferSurplusScottish($pieces, $chosen, (float)$quota, $continuing, $parcels, $decimals);
                            if ($moved) {
                                $snapshot();
                            } else {
                                $stuck[] = $chosen;
                            }
                        }

                        if (count($continuing) <= $numSeats - count($winners)) {
                            sort($continuing);
                            $winners = array_merge($winners, $continuing);
                            break;
                        }
                        continue;
                    }

                    $tallNow = self::tallyFromPieces($pieces);
                    $surplusElect = array_filter($winners, fn($c) => (($tallNow[$c] ?? 0.0) - (float)$quota) > self::EPS);
                    if (!empty($surplusElect)) {
                        $maxS = -INF;
                        foreach ($surplusElect as $c) {
                            $maxS = max($maxS, $tallNow[$c] - (float)$quota);
                        }
                        $tied = array_filter($surplusElect, fn($c) => abs(($tallNow[$c] - (float)$quota) - $maxS) <= self::EPS);
                        if (count($tied) > 1) {
                            $chosen = $historyPrefer($tied, "highest") ?? ($rng ? $rng->choice($tied) : $tied[array_rand($tied)]);
                        } else {
                            $chosen = reset($tied);
                        }

                        $moved = self::transferSurplusScottish($pieces, $chosen, (float)$quota, $continuing, $parcels, $decimals);
                        if ($moved) {
                            $snapshot();
                            continue;
                        }
                    }

                    if (count($continuing) <= $numSeats - count($winners)) {
                        sort($continuing);
                        $winners = array_merge($winners, $continuing);
                        break;
                    }

                    $talliesC = self::tallyFromPieces($pieces, $continuing);
                    $minT = min($talliesC);
                    $lowest = array_keys(array_filter($talliesC, fn($t) => abs($t - $minT) <= self::EPS));

                    if (count($lowest) > 1) {
                        $elim = $historyPrefer($lowest, "lowest") ?? ($rng ? $rng->choice($lowest) : $lowest[array_rand($lowest)]);
                    } else {
                        $elim = $lowest[0];
                    }

                    $continuing = array_values(array_filter($continuing, fn($c) => $c !== $elim));
                    self::eliminateLowestScottish($pieces, $elim, $continuing, $parcels);
                    $snapshot();
                }

                sort($winners);
                return $winners;
            },
            'STV-Scottish'
        );
    }

    private static function eliminateLowestScottish(array &$pieces, $elim, array $continuing, ParcelIndex $parcels): void
    {
        $newPieces = [];
        $oldToNew = [];
        $pendingNotes = [];
        foreach ($pieces as $oldIdx => $p) {
            if ($p->cand !== $elim) {
                $newIdx = count($newPieces);
                $newPieces[] = $p;
                $oldToNew[$oldIdx] = $newIdx;
                continue;
            }
            $forwards = self::movePieceForward($p, $continuing);
            if (empty($forwards)) {
                $p->weight = 0.0;
                continue;
            }
            $opened = [];
            $share = $p->weight / (float)count($forwards);
            foreach ($forwards as [$nxtC, $unused, $newRank]) {
                if (!isset($opened[$nxtC])) {
                    $parcels->startNewParcel($nxtC);
                    $opened[$nxtC] = true;
                }
                $createdIdx = count($newPieces);
                $newPieces[] = $p->cloneTo($nxtC, $newRank, $share);
                $pendingNotes[] = [$nxtC, $createdIdx];
            }
            $p->weight = 0.0;
        }
        $parcels->remapIndices($oldToNew);
        foreach ($pendingNotes as [$cand, $idx]) {
            $parcels->noteArrival($cand, $idx);
        }
        $pieces = $newPieces;
    }

    public static function stvNb(
        int $numSeats = 2,
        ?array $currCands = null,
        string $quotaRule = "nb",
        bool $mannStrict = false,
        bool $drainAll = false,
        ?callable $tieBreakKey = null,
        bool $ersRounding = false
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $quotaRule, $mannStrict, $drainAll, $tieBreakKey, $ersRounding): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }

                $continuing = $currCands ?? $profile->candidates;
                $winners = [];
                $parcels = new ParcelIndex();
                $pieces = self::initialPiecesFromProfile($profile, $continuing, $parcels);

                [, $rcounts] = $profile->getRankingsCounts();
                $totalWeight = (float)array_sum($rcounts);

                if ($totalWeight <= self::EPS || empty($continuing) || $numSeats <= 0) {
                    return [];
                }

                $rule = strtolower($quotaRule);
                if ($rule === "nb") {
                    $rawQuota = self::nbQuota($totalWeight, $numSeats);
                    if ($ersRounding) {
                        $quota = ($rawQuota > 100.0) ? ceil($rawQuota) : ceil($rawQuota * 100.0) / 100.0;
                    } else {
                        $quota = $rawQuota;
                    }
                } elseif ($rule === "droop") {
                    $quota = self::droopIntQuota($totalWeight, $numSeats);
                } else {
                    throw new \InvalidArgumentException("Unknown quota_rule \"$quotaRule\". Use \"nb\" or \"droop\".");
                }

                $safety = 0;
                while (count($winners) < $numSeats) {
                    if (++$safety > 20000) throw new \RuntimeException("stv_nb: loop safety tripped");

                    $talliesC = self::tallyFromPieces($pieces, $continuing);
                    $electedNow = array_filter($continuing, function ($c) use ($talliesC, $quota, $mannStrict) {
                        $t = $talliesC[$c] ?? 0.0;
                        return $mannStrict ? ($t > $quota + self::EPS) : ($t >= $quota - self::EPS);
                    });

                    if (!empty($electedNow)) {
                        sort($electedNow);
                        foreach ($electedNow as $c) {
                            $continuing = array_values(array_filter($continuing, fn($cc) => $cc !== $c));
                            $winners[] = $c;
                        }

                        $stuck = [];
                        while (true) {
                            $tallAll = self::tallyFromPieces($pieces, array_merge($continuing, $winners));
                            $surplusers = array_filter($winners, fn($c) => (($tallAll[$c] ?? 0.0) - $quota) > self::EPS && !in_array($c, $stuck, true));
                            if (empty($surplusers)) break;

                            $elect = null;
                            $maxVal = -INF;
                            foreach ($surplusers as $c) {
                                $val = $tallAll[$c] - $quota;
                                if ($val > $maxVal + self::EPS) {
                                    $maxVal = $val;
                                    $elect = $c;
                                } elseif (abs($val - $maxVal) <= self::EPS) {
                                    if ((string)$c < (string)$elect) $elect = $c;
                                }
                            }

                            $moved = self::transferSurplusInclusive($pieces, $elect, $quota, $continuing, $parcels, $drainAll, false, $ersRounding);
                            if (!$moved) $stuck[] = $elect;
                        }

                        if (count($continuing) <= $numSeats - count($winners)) {
                            sort($continuing);
                            $winners = array_merge($winners, $continuing);
                            break;
                        }
                        continue;
                    }

                    if (count($continuing) <= $numSeats - count($winners)) {
                        sort($continuing);
                        $winners = array_merge($winners, $continuing);
                        break;
                    }

                    $res = self::eliminateLowest($pieces, $continuing, $parcels, $tieBreakKey);
                    if ($res === null) break;
                    [$elim, $pieces] = $res;
                }

                sort($winners);
                return $winners;
            },
            'STV-NB'
        );
    }

    public static function stvWig(
        int $numSeats = 2,
        ?array $currCands = null,
        string $quotaRule = "nb",
        bool $drainAll = true,
        ?callable $tieBreakKey = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $quotaRule, $drainAll, $tieBreakKey): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }

                $continuing = $currCands ?? $profile->candidates;
                $winners = [];
                $parcels = new ParcelIndex();
                $pieces = self::initialPiecesFromProfile($profile, $continuing, $parcels);

                if ($numSeats <= 0 || empty($continuing)) return [];

                [, $rcounts] = $profile->getRankingsCounts();
                $totalWeight = (float)array_sum($rcounts);

                $rule = strtolower($quotaRule);
                if ($rule === "nb") {
                    $quota = self::nbQuota($totalWeight, $numSeats);
                } elseif ($rule === "droop") {
                    $quota = self::droopIntQuota($totalWeight, $numSeats);
                } else {
                    throw new \InvalidArgumentException("Unknown quota_rule \"$quotaRule\". Use \"nb\" or \"droop\".");
                }

                $safety = 0;
                while (count($winners) < $numSeats) {
                    if (++$safety > 20000) throw new \RuntimeException("stv_wig: loop safety tripped");

                    $talliesC = self::tallyFromPieces($pieces, $continuing);
                    $electedNow = array_filter($continuing, fn($c) => ($talliesC[$c] ?? 0.0) >= $quota - self::EPS);

                    if (!empty($electedNow)) {
                        sort($electedNow);
                        foreach ($electedNow as $c) {
                            $continuing = array_values(array_filter($continuing, fn($cc) => $cc !== $c));
                            $winners[] = $c;
                        }

                        $stuck = [];
                        while (true) {
                            $tallAll = self::tallyFromPieces($pieces, array_merge($continuing, $winners));
                            $surplusers = array_filter($winners, fn($c) => (($tallAll[$c] ?? 0.0) - $quota) > self::EPS && !in_array($c, $stuck, true));
                            if (empty($surplusers)) break;

                            $elect = null;
                            $maxVal = -INF;
                            foreach ($surplusers as $c) {
                                $val = $tallAll[$c] - $quota;
                                if ($val > $maxVal + self::EPS) {
                                    $maxVal = $val;
                                    $elect = $c;
                                } elseif (abs($val - $maxVal) <= self::EPS) {
                                    if ((string)$c < (string)$elect) $elect = $c;
                                }
                            }

                            $moved = self::transferSurplusInclusive($pieces, $elect, $quota, $continuing, $parcels, true, false);
                            if (!$moved) $stuck[] = $elect;
                        }

                        if (count($continuing) <= $numSeats - count($winners)) {
                            sort($continuing);
                            $winners = array_merge($winners, $continuing);
                            break;
                        }
                        continue;
                    }

                    if (count($continuing) <= $numSeats - count($winners)) {
                        sort($continuing);
                        $winners = array_merge($winners, $continuing);
                        break;
                    }

                    $res = self::eliminateLowest($pieces, $continuing, $parcels, $tieBreakKey);
                    if ($res === null) break;
                    [$elim, $pieces] = $res;
                }

                sort($winners);
                return $winners;
            },
            'STV-WIG'
        );
    }

    public static function stvLastParcel(
        int $numSeats = 2,
        ?array $currCands = null,
        string $quotaRule = "nb",
        ?callable $tieBreakKey = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $quotaRule, $tieBreakKey): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }

                $continuing = $currCands ?? $profile->candidates;
                $winners = [];
                $parcels = new ParcelIndex();
                $pieces = self::initialPiecesFromProfile($profile, $continuing, $parcels);

                [, $rcounts] = $profile->getRankingsCounts();
                $totalWeight = (float)array_sum($rcounts);

                if ($totalWeight <= self::EPS || empty($continuing) || $numSeats <= 0) return [];
                if (strtolower($quotaRule) !== "nb") throw new \InvalidArgumentException("Only NB quota is implemented.");

                $quota = self::nbQuota($totalWeight, $numSeats);

                $safety = 0;
                while (count($winners) < $numSeats) {
                    if (++$safety > 20000) throw new \RuntimeException("stv_last_parcel: loop safety tripped");

                    $talliesC = self::tallyFromPieces($pieces, $continuing);
                    $electedNow = array_filter($continuing, fn($c) => ($talliesC[$c] ?? 0.0) >= $quota - self::EPS);

                    if (!empty($electedNow)) {
                        sort($electedNow);
                        foreach ($electedNow as $c) {
                            $continuing = array_values(array_filter($continuing, fn($cc) => $cc !== $c));
                            $winners[] = $c;
                        }

                        foreach ($electedNow as $c) {
                            self::transferSurplusInclusive($pieces, $c, $quota, $continuing, $parcels, true, true);
                        }
                        continue;
                    }

                    if (count($continuing) <= $numSeats - count($winners)) {
                        sort($continuing);
                        $winners = array_merge($winners, $continuing);
                        break;
                    }

                    $res = self::eliminateLowest($pieces, $continuing, $parcels, $tieBreakKey);
                    if ($res === null) break;
                    [$elim, $pieces] = $res;
                }

                sort($winners);
                return $winners;
            },
            'STV-Last-Parcel'
        );
    }

    // ---------- Meek STV ----------

    private static function meekFlowOneBallot(array $tiers, array $keep, array $continuing): array
    {
        $remaining = 1.0;
        $out = [];
        $continuingSet = array_flip($continuing);
        foreach ($tiers as $tier) {
            $avail = array_filter($tier, fn($c) => isset($continuingSet[$c]));
            if (empty($avail)) continue;

            $share = $remaining / (float)count($avail);
            $spilled = 0.0;
            foreach ($avail as $c) {
                $k = $keep[$c] ?? 1.0;
                $kept = $k * $share;
                $out[] = [$c, $kept];
                $spilled += (1.0 - $k) * $share;
            }
            $remaining = $spilled;
            if ($remaining <= self::EPS) break;
        }
        return $out;
    }

    private static function meekTallyFromProfile(ProfileWithTies $profile, array $keep, array $continuing): array
    {
        $tally = [];
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        foreach ($rankings as $idx => $ranking) {
            $count = (float)$rcounts[$idx];
            if ($count <= 0) continue;

            $rmap = $ranking->rmap;
            $byRank = [];
            foreach ($rmap as $c => $r) {
                if ($r !== null) {
                    $byRank[(int)$r][] = $c;
                }
            }
            ksort($byRank);
            $tiers = [];
            foreach ($byRank as $cands) {
                sort($cands);
                $tiers[] = $cands;
            }

            if (!empty($tiers)) {
                foreach (self::meekFlowOneBallot($tiers, $keep, $continuing) as [$c, $a]) {
                    $tally[$c] = ($tally[$c] ?? 0.0) + $a * $count;
                }
            }
        }
        return $tally;
    }

    public static function stvMeek(
        int $numSeats = 2,
        ?array $currCands = null,
        float $tol = 1e-10,
        int $maxIter = 2000,
        ?callable $tieBreakKey = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $tol, $maxIter, $tieBreakKey): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }

                $candidatesList = $currCands ?? $profile->candidates;
                $continuing = $candidatesList;
                $keep = [];
                foreach ($continuing as $c) $keep[$c] = 1.0;

                [, $rcounts] = $profile->getRankingsCounts();
                $totalWeight = (float)array_sum($rcounts);
                if ($totalWeight <= self::EPS || empty($continuing) || $numSeats <= 0) return [];

                while (count($continuing) > $numSeats) {
                    for ($i = 0; $i < $maxIter; $i++) {
                        $tallies = self::meekTallyFromProfile($profile, $keep, $continuing);
                        $activeTotal = 0.0;
                        foreach ($continuing as $c) $activeTotal += $tallies[$c] ?? 0.0;
                        $quota = ($activeTotal > self::EPS) ? $activeTotal / (float)($numSeats + 1) : 0.0;

                        $changed = false;
                        foreach ($continuing as $c) {
                            $t = $tallies[$c] ?? 0.0;
                            if ($t > $quota + $tol && ($keep[$c] ?? 1.0) > 0.0) {
                                $newKeep = min($keep[$c], $quota / $t);
                                if ($keep[$c] - $newKeep > $tol) {
                                    $keep[$c] = $newKeep;
                                    $changed = true;
                                }
                            }
                        }
                        if (!$changed) break;
                    }

                    $tallies = self::meekTallyFromProfile($profile, $keep, $continuing);
                    $minT = INF;
                    $lowest = [];
                    foreach ($continuing as $c) {
                        $t = $tallies[$c] ?? 0.0;
                        if ($t < $minT - self::EPS) {
                            $minT = $t;
                            $lowest = [$c];
                        } elseif (abs($t - $minT) <= self::EPS) {
                            $lowest[] = $c;
                        }
                    }
                    if (count($lowest) > 1) {
                        if ($tieBreakKey) {
                            usort($lowest, fn($a, $b) => $tieBreakKey($a) <=> $tieBreakKey($b));
                        } else {
                            usort($lowest, fn($a, $b) => (string)$a <=> (string)$b);
                        }
                    }
                    $elim = $lowest[0];
                    $continuing = array_values(array_filter($continuing, fn($c) => $c !== $elim));
                    $keep[$elim] = 0.0;
                }

                sort($continuing);
                return array_slice($continuing, 0, $numSeats);
            },
            'STV-Meek'
        );
    }

    public static function stvWarren(
        int $numSeats = 2,
        ?array $currCands = null,
        float $tol = 1e-10,
        ?callable $tieBreakKey = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $tol, $tieBreakKey): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }
                $continuing = $currCands ?? $profile->candidates;
                [$rankings, $rcounts] = $profile->getRankingsCounts();

                $topset = function (Ranking $ranking, array $accept) {
                    $acceptSet = array_flip($accept);
                    $ranks = [];
                    foreach ($ranking->rmap as $c => $r) {
                        if ($r !== null && isset($acceptSet[$c])) $ranks[] = $r;
                    }
                    if (empty($ranks)) return [];
                    $rmin = min($ranks);
                    $res = [];
                    foreach ($ranking->rmap as $c => $r) {
                        if ($r === $rmin && isset($acceptSet[$c])) $res[] = $c;
                    }
                    return $res;
                };

                $nextset = function (Ranking $ranking, array $accept, $exclude) {
                    $acceptSet = array_flip($accept);
                    $pool = [];
                    foreach ($ranking->rmap as $c => $r) {
                        if ($c !== $exclude && $r !== null && isset($acceptSet[$c])) $pool[] = [$c, $r];
                    }
                    if (empty($pool)) return [];
                    $rmin = min(array_column($pool, 1));
                    $res = [];
                    foreach ($pool as [$c, $r]) {
                        if ($r === $rmin) $res[] = $c;
                    }
                    return $res;
                };

                while (count($continuing) > $numSeats) {
                    $alloc = [];
                    $S = $continuing;
                    foreach ($rankings as $i => $ranking) {
                        $alloc[$i] = [];
                        $tops = $topset($ranking, $S);
                        if (!empty($tops)) {
                            $share = 1.0 / (float)count($tops);
                            foreach ($tops as $t) $alloc[$i][$t] = $share;
                        }
                    }

                    $maxEqualizeIters = 2000;
                    $iters = 0;
                    while (true) {
                        $totals = [];
                        foreach ($alloc as $i => $A) {
                            $m = (float)$rcounts[$i];
                            foreach ($A as $c => $per) {
                                $totals[$c] = ($totals[$c] ?? 0.0) + $per * $m;
                            }
                        }
                        $activeTotal = 0.0;
                        foreach ($continuing as $c) $activeTotal += $totals[$c] ?? 0.0;
                        $quota = ($numSeats > 0) ? $activeTotal / (float)($numSeats + 1) : INF;

                        $over = array_filter($continuing, fn($c) => ($totals[$c] ?? 0.0) > $quota + $tol);
                        if (empty($over)) break;
                        if (++$iters > $maxEqualizeIters) break;

                        $changed = false;
                        sort($over);
                        foreach ($over as $c) {
                            $perList = [];
                            foreach ($alloc as $i => $A) {
                                $w = $A[$c] ?? 0.0;
                                if ($w > self::EPS) $perList[] = [$w, (float)$rcounts[$i]];
                            }
                            if (empty($perList)) continue;

                            $lo = 0.0; $hi = max(array_column($perList, 0));
                            for ($j = 0; $j < 64; $j++) {
                                $mid = 0.5 * ($lo + $hi);
                                $s = 0.0;
                                foreach ($perList as [$w, $m]) $s += $m * min($w, $mid);
                                if ($s > $quota) $hi = $mid; else $lo = $mid;
                            }
                            $p_c = $lo;

                            foreach ($rankings as $i => $ranking) {
                                $per = $alloc[$i][$c] ?? 0.0;
                                if ($per <= self::EPS) continue;
                                $newPer = min($per, $p_c);
                                $deltaPer = $per - $newPer;
                                if ($deltaPer > $tol) {
                                    $alloc[$i][$c] = $newPer;
                                    $nxt = $nextset($ranking, $continuing, $c);
                                    if (!empty($nxt)) {
                                        $share = $deltaPer / (float)count($nxt);
                                        foreach ($nxt as $d) $alloc[$i][$d] = ($alloc[$i][$d] ?? 0.0) + $share;
                                    }
                                    $changed = true;
                                }
                            }
                        }
                        if (!$changed) break;
                    }

                    $totals = [];
                    foreach ($alloc as $i => $A) {
                        $m = (float)$rcounts[$i];
                        foreach ($A as $c => $per) {
                            $totals[$c] = ($totals[$c] ?? 0.0) + $per * $m;
                        }
                    }
                    if (count($continuing) === $numSeats) break;

                    $minT = INF; $lowest = [];
                    foreach ($continuing as $c) {
                        $t = $totals[$c] ?? 0.0;
                        if ($t < $minT - self::EPS) { $minT = $t; $lowest = [$c]; }
                        elseif (abs($t - $minT) <= self::EPS) { $lowest[] = $c; }
                    }
                    if (count($lowest) > 1) {
                        if ($tieBreakKey) usort($lowest, fn($a, $b) => $tieBreakKey($a) <=> $tieBreakKey($b));
                        else usort($lowest, fn($a, $b) => (string)$a <=> (string)$b);
                    }
                    $elim = $lowest[0];
                    $continuing = array_values(array_filter($continuing, fn($c) => $c !== $elim));
                }
                sort($continuing);
                return array_slice($continuing, 0, $numSeats);
            },
            'STV-Warren'
        );
    }

    public static function approvalStv(
        int $numSeats = 2,
        ?array $currCands = null,
        string $quotaRule = "droop",
        ?callable $selectTiebreak = null,
        ?callable $elimTiebreak = null,
        $rng = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $quotaRule, $selectTiebreak, $elimTiebreak, $rng): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }
                [$rankings, $rcounts] = $profile->getRankingsCounts();
                $continuing = $currCands ?? $profile->candidates;
                $winners = [];
                $n = (float)array_sum($rcounts);

                if ($n <= self::EPS || $numSeats <= 0 || empty($continuing)) return [];

                if ($quotaRule === "droop") { $quota = $n / (float)($numSeats + 1); $strict = true; }
                elseif ($quotaRule === "droop_int") { $quota = floor($n / (float)($numSeats + 1)) + 1; $strict = true; }
                elseif ($quotaRule === "hare") { $quota = $n / (float)$numSeats; $strict = false; }
                else throw new \InvalidArgumentException("quota_rule must be one of {'droop','droop_int','hare'}");

                $budgets = array_map('floatval', $rcounts);

                $topset = function (Ranking $ranking, array $accept) {
                    $acceptSet = array_flip($accept);
                    $ranks = [];
                    foreach ($ranking->rmap as $c => $r) {
                        if ($r !== null && isset($acceptSet[$c])) $ranks[] = $r;
                    }
                    if (empty($ranks)) return [];
                    $rmin = min($ranks);
                    $res = [];
                    foreach ($ranking->rmap as $c => $r) {
                        if ($r === $rmin && isset($acceptSet[$c])) $res[] = $c;
                    }
                    return $res;
                };

                while (count($winners) < $numSeats && !empty($continuing)) {
                    if (count($continuing) <= $numSeats - count($winners)) {
                        sort($continuing);
                        $winners = array_merge($winners, $continuing);
                        break;
                    }

                    $S = [];
                    $A = array_flip($continuing);
                    foreach ($rankings as $i => $ranking) {
                        if ($budgets[$i] <= self::EPS) continue;
                        foreach ($topset($ranking, $continuing) as $c) {
                            $S[$c] = ($S[$c] ?? 0.0) + $budgets[$i];
                        }
                    }
                    foreach ($continuing as $c) if (!isset($S[$c])) $S[$c] = 0.0;

                    $elig = array_filter(array_keys($S), fn($c) => $strict ? ($S[$c] > $quota + self::EPS) : ($S[$c] + self::EPS >= $quota));

                    if (!empty($elig)) {
                        if ($selectTiebreak === null) {
                            $maxv = -INF;
                            foreach ($elig as $c) $maxv = max($maxv, $S[$c]);
                            $tied = array_filter($elig, fn($c) => abs($S[$c] - $maxv) <= self::EPS);
                            sort($tied);
                            $chosen = $tied[0];
                        } else {
                            $best = -INF;
                            foreach ($elig as $c) $best = max($best, $selectTiebreak($c));
                            $tied = array_filter($elig, fn($c) => abs($selectTiebreak($c) - $best) <= self::EPS);
                            sort($tied);
                            $chosen = $rng ? $rng->choice($tied) : $tied[array_rand($tied)];
                        }
                        $winners[] = $chosen;
                        $totalS = $S[$chosen];
                        $factor = max(0.0, ($totalS - $quota) / $totalS);
                        
                        $oldContinuing = $continuing;
                        $continuing = array_values(array_filter($continuing, fn($c) => $c !== $chosen));

                        foreach ($rankings as $i => $ranking) {
                            if ($budgets[$i] <= self::EPS) continue;
                            if (in_array($chosen, $topset($ranking, $oldContinuing), true)) $budgets[$i] *= $factor;
                        }
                        continue;
                    }

                    $minv = min($S);
                    $lowest = array_keys(array_filter($S, fn($v) => abs($v - $minv) <= self::EPS));
                    if (count($lowest) > 1) {
                        if ($elimTiebreak === null) {
                            sort($lowest);
                            $elim = $lowest[0];
                        } else {
                            $mink = INF;
                            foreach ($lowest as $c) $mink = min($mink, $elimTiebreak($c));
                            $tied = array_filter($lowest, fn($c) => abs($elimTiebreak($c) - $mink) <= self::EPS);
                            sort($tied);
                            $elim = $rng ? $rng->choice($tied) : $tied[array_rand($tied)];
                        }
                    } else {
                        $elim = $lowest[0];
                    }
                    $continuing = array_values(array_filter($continuing, fn($c) => $c !== $elim));
                }

                sort($winners);
                return $winners;
            },
            'Approval-STV'
        );
    }

    // ---------- CPO-STV ----------

    private static function committeeMarginPwt(array $A, array $B, ProfileWithTies $profile, string $inpairSurplus = "meek"): float
    {
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        $S = array_unique(array_merge($A, $B));
        $I = array_intersect($A, $B);
        $k = count($A);

        $balAlloc = [];
        foreach ($rankings as $i => $ranking) {
            $balAlloc[$i] = [];
            $tops = self::topsetInRanking($ranking, $S);
            if (empty($tops)) continue;
            $share = (float)$rcounts[$i] / (float)count($tops);
            foreach ($tops as $t) $balAlloc[$i][$t] = $share;
        }

        $tol = 1e-12;
        $maxIters = 10000;
        $rule = strtolower($inpairSurplus);

        for ($iter = 0; $iter < $maxIters; $iter++) {
            $totals = [];
            foreach ($balAlloc as $alloc) {
                foreach ($alloc as $c => $w) $totals[$c] = ($totals[$c] ?? 0.0) + $w;
            }
            $usable = 0.0;
            foreach ($S as $c) $usable += $totals[$c] ?? 0.0;
            if ($k === 0 || $usable <= $tol) break;
            $quota = $usable / (float)($k + 1);

            $changed = false;
            sort($I);
            foreach ($I as $c) {
                $tc = $totals[$c] ?? 0.0;
                $excess = $tc - $quota;
                if ($excess <= $tol || $tc <= $tol) continue;

                if ($rule === "warren") {
                    $wList = [];
                    foreach ($balAlloc as $i => $alloc) {
                        $w_c = $alloc[$c] ?? 0.0;
                        if ($w_c <= 0.0) continue;
                        $m = (float)$rcounts[$i];
                        $wList[] = [$w_c / $m, $m];
                    }
                    if (empty($wList)) continue;
                    $lo = 0.0; $hi = max(array_column($wList, 0));
                    for ($j = 0; $j < 64; $j++) {
                        $mid = 0.5 * ($lo + $hi);
                        $s = 0.0;
                        foreach ($wList as [$w, $m]) $s += $m * min($w, $mid);
                        if ($s > $quota) $hi = $mid; else $lo = $mid;
                    }
                    $p_c = $lo;

                    foreach ($rankings as $i => $ranking) {
                        $w_c = $balAlloc[$i][$c] ?? 0.0;
                        if ($w_c <= 0.0) continue;
                        $m = (float)$rcounts[$i];
                        $per = $w_c / $m;
                        $newPer = min($per, $p_c);
                        $deltaTotal = ($per - $newPer) * $m;
                        if ($deltaTotal <= $tol) continue;
                        $balAlloc[$i][$c] = $newPer * $m;
                        $nxt = self::nextsetInRanking($ranking, $S, $c);
                        if (!empty($nxt)) {
                            $share = $deltaTotal / (float)count($nxt);
                            foreach ($nxt as $nx) $balAlloc[$i][$nx] = ($balAlloc[$i][$nx] ?? 0.0) + $share;
                        }
                        $changed = true;
                    }
                } else {
                    $ratio = $excess / $tc;
                    foreach ($rankings as $i => $ranking) {
                        $w_c = $balAlloc[$i][$c] ?? 0.0;
                        if ($w_c <= 0.0) continue;
                        $delta = $w_c * $ratio;
                        if ($delta <= $tol) continue;
                        $balAlloc[$i][$c] = $w_c - $delta;
                        $nxt = self::nextsetInRanking($ranking, $S, $c);
                        if (!empty($nxt)) {
                            $share = $delta / (float)count($nxt);
                            foreach ($nxt as $nx) $balAlloc[$i][$nx] = ($balAlloc[$i][$nx] ?? 0.0) + $share;
                        }
                        $changed = true;
                    }
                }
            }
            if (!$changed) break;
        }

        $totals = [];
        foreach ($balAlloc as $alloc) {
            foreach ($alloc as $c => $w) $totals[$c] = ($totals[$c] ?? 0.0) + $w;
        }
        $scoreA = 0.0; foreach ($A as $c) $scoreA += $totals[$c] ?? 0.0;
        $scoreB = 0.0; foreach ($B as $c) $scoreB += $totals[$c] ?? 0.0;
        return $scoreA - $scoreB;
    }

    private static function topsetInRanking(Ranking $ranking, array $acceptSet): array
    {
        $accept = array_flip($acceptSet);
        $ranks = [];
        foreach ($ranking->rmap as $c => $r) {
            if ($r !== null && isset($accept[$c])) $ranks[] = $r;
        }
        if (empty($ranks)) return [];
        $rmin = min($ranks);
        $res = [];
        foreach ($ranking->rmap as $c => $r) {
            if ($r === $rmin && isset($accept[$c])) $res[] = $c;
        }
        return $res;
    }

    private static function nextsetInRanking(Ranking $ranking, array $acceptSet, $exclude): array
    {
        $accept = array_flip($acceptSet);
        $pool = [];
        foreach ($ranking->rmap as $c => $r) {
            if ($c !== $exclude && $r !== null && isset($accept[$c])) $pool[] = $r;
        }
        if (empty($pool)) return [];
        $rmin = min($pool);
        $res = [];
        foreach ($ranking->rmap as $c => $r) {
            if ($c !== $exclude && $r === $rmin && isset($accept[$c])) $res[] = $c;
        }
        return $res;
    }

    public static function cpoStv(
        int $numSeats = 2,
        ?array $currCands = null,
        string $inpairSurplus = "meek",
        ?VotingMethod $fallbackVm = null
    ): VotingMethod {
        return new VotingMethod(
            function (Profile|ProfileWithTies $profile, ?array $currCands = null) use ($numSeats, $inpairSurplus, $fallbackVm): array {
                if ($profile instanceof Profile) {
                    $profile = $profile->toProfileWithTies();
                }
                $candidates = $currCands ?? $profile->candidates;

                // combinations
                $committees = self::combinations($candidates, $numSeats);
                if (count($committees) <= 1) return !empty($committees) ? $committees[0] : [];

                $condorcetCommitteeExists = true;
                $C = $committees[0];
                foreach ($committees as $A) {
                    if (self::committeeMarginPwt($A, $C, $profile, $inpairSurplus) > 0) $C = $A;
                }
                foreach ($committees as $B) {
                    if ($C !== $B && !(self::committeeMarginPwt($C, $B, $profile, $inpairSurplus) > 0)) {
                        $condorcetCommitteeExists = false;
                        break;
                    }
                }
                if ($condorcetCommitteeExists) return $C;

                $weightedEdges = [];
                for ($i = 0; $i < count($committees); $i++) {
                    for ($j = $i + 1; $j < count($committees); $j++) {
                        $A = $committees[$i]; $B = $committees[$j];
                        $m = self::committeeMarginPwt($A, $B, $profile, $inpairSurplus);
                        if ($m > 0) $weightedEdges[] = [$i, $j, $m];
                        elseif ($m < 0) $weightedEdges[] = [$j, $i, abs($m)];
                    }
                }

                $committeeIndices = array_keys($committees);
                $mg = new MarginGraph($committeeIndices, $weightedEdges);
                $fallback = $fallbackVm ?? MarginBasedMethods::minimax();
                $winnerIndices = $fallback($mg);
                $finalIdx = $winnerIndices[array_rand($winnerIndices)];
                $final = $committees[$finalIdx];
                sort($final);
                return $final;
            },
            'CPO-STV'
        );
    }

    private static function combinations(array $arr, int $k): array
    {
        $res = [];
        $n = count($arr);
        if ($k > $n) return [];
        if ($k === 0) return [[]];
        if ($k === $n) return [$arr];

        $indices = range(0, $k - 1);
        $res[] = array_map(fn($i) => $arr[$i], $indices);

        while (true) {
            $i = $k - 1;
            while ($i >= 0 && $indices[$i] === $i + $n - $k) $i--;
            if ($i < 0) break;
            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) $indices[$j] = $indices[$j - 1] + 1;
            $res[] = array_map(fn($idx) => $arr[$idx], $indices);
        }
        return $res;
    }
}

// Pre-instantiated voting methods
$stvScottish = ProportionalMethods::stvScottish();
$stvNb = ProportionalMethods::stvNb();
$stvWig = ProportionalMethods::stvWig();
$stvLastParcel = ProportionalMethods::stvLastParcel();
$stvMeek = ProportionalMethods::stvMeek();
$stvWarren = ProportionalMethods::stvWarren();
$approvalStv = ProportionalMethods::approvalStv();
$cpoStv = ProportionalMethods::cpoStv();
