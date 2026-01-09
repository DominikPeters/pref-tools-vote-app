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
 * A ranking of a set of candidates.
 *
 * A ranking is a map from candidates to ranks (integers). There is no assumption
 * that all candidates in an election are ranked.
 *
 * Example:
 *   $rank1 = new Ranking([0 => 1, 1 => 3, 2 => 2]); // 0 first, 2 second, 1 third
 *   $rank2 = new Ranking([0 => 1, 1 => 1, 2 => 2]); // 0 and 1 tied for first, 2 last
 *   $rank3 = new Ranking([0 => 1, 2 => 3]);         // 0 first, 2 third (1 unranked)
 *
 * Note: The numerical value of ranks are only used for ordinal comparison.
 */
class Ranking
{
    /** @var array<int|string, int> Map from candidate to rank */
    public array $rmap;

    /** @var array<int|string, string> Map from candidate to display name */
    public array $cmap;

    public function __construct(array $rmap, ?array $cmap = null)
    {
        $this->rmap = $rmap;
        $this->cmap = $cmap ?? array_combine(
            array_keys($rmap),
            array_map('strval', array_keys($rmap))
        );
    }

    /**
     * Returns a sorted list of unique ranks.
     * @return int[]
     */
    public function getRanks(): array
    {
        $ranks = array_unique(array_values($this->rmap));
        sort($ranks);
        return $ranks;
    }

    /**
     * Returns a sorted list of candidates that are ranked.
     * @return array<int|string>
     */
    public function getCands(): array
    {
        $cands = array_keys($this->rmap);
        sort($cands);
        return $cands;
    }

    /**
     * Returns the number of ranked candidates.
     */
    public function numRankedCandidates(): int
    {
        return count($this->rmap);
    }

    /**
     * Returns candidates at a specific rank.
     * @return array<int|string>
     */
    public function candsAtRank(int $r): array
    {
        return array_keys(array_filter($this->rmap, fn($rank) => $rank === $r));
    }

    /**
     * Returns true if candidate c is ranked.
     */
    public function isRanked(int|string $c): bool
    {
        return array_key_exists($c, $this->rmap);
    }

    /**
     * Returns true if c1 is strictly preferred to c2.
     * Both must be ranked and c1's rank must be smaller than c2's.
     */
    public function strictPref(int|string $c1, int|string $c2): bool
    {
        return $this->isRanked($c1)
            && $this->isRanked($c2)
            && $this->rmap[$c1] < $this->rmap[$c2];
    }

    /**
     * Returns true if c1 is extended strictly preferred to c2.
     * True when c1 is ranked and c2 is not, or c1's rank < c2's rank.
     */
    public function extendedStrictPref(int|string $c1, int|string $c2): bool
    {
        if ($this->isRanked($c1) && !$this->isRanked($c2)) {
            return true;
        }
        return $this->isRanked($c1)
            && $this->isRanked($c2)
            && $this->rmap[$c1] < $this->rmap[$c2];
    }

    /**
     * Returns true if c1 and c2 are tied (same rank, both ranked).
     */
    public function indiff(int|string $c1, int|string $c2): bool
    {
        return $this->isRanked($c1)
            && $this->isRanked($c2)
            && $this->rmap[$c1] === $this->rmap[$c2];
    }

    /**
     * Returns true if c1 and c2 are in extended indifference.
     * True when both unranked or both ranked at the same rank.
     */
    public function extendedIndiff(int|string $c1, int|string $c2): bool
    {
        if (!$this->isRanked($c1) && !$this->isRanked($c2)) {
            return true;
        }
        return $this->isRanked($c1)
            && $this->isRanked($c2)
            && $this->rmap[$c1] === $this->rmap[$c2];
    }

    /**
     * Returns true if c1 is weakly preferred to c2 (tied or strictly preferred).
     */
    public function weakPref(int|string $c1, int|string $c2): bool
    {
        return $this->strictPref($c1, $c2) || $this->indiff($c1, $c2);
    }

    /**
     * Returns true if c1 is extended weakly preferred to c2.
     */
    public function extendedWeakPref(int|string $c1, int|string $c2): bool
    {
        return $this->extendedStrictPref($c1, $c2) || $this->extendedIndiff($c1, $c2);
    }

    /**
     * Returns a new Ranking with candidate a removed.
     */
    public function removeCand(int|string $a): Ranking
    {
        $newRmap = array_filter($this->rmap, fn($c) => $c !== $a, ARRAY_FILTER_USE_KEY);
        $newCmap = array_filter($this->cmap, fn($c) => $c !== $a, ARRAY_FILTER_USE_KEY);
        return new Ranking($newRmap, $newCmap);
    }

    /**
     * Returns candidates with the highest (smallest) rank from cs.
     * If cs is null, uses all ranked candidates.
     * @param array<int|string>|null $cs
     * @return array<int|string>
     */
    public function first(?array $cs = null): array
    {
        $cands = $cs ?? array_keys($this->rmap);
        $ranks = [];
        foreach ($cands as $c) {
            if ($this->isRanked($c)) {
                $ranks[$c] = $this->rmap[$c];
            }
        }
        if (empty($ranks)) {
            return [];
        }
        $minRank = min($ranks);
        $result = array_keys(array_filter($ranks, fn($r) => $r === $minRank));
        sort($result);
        return $result;
    }

    /**
     * Returns candidates with the lowest (largest) rank from cs.
     * @param array<int|string>|null $cs
     * @return array<int|string>
     */
    public function last(?array $cs = null): array
    {
        $cands = $cs ?? array_keys($this->rmap);
        $ranks = [];
        foreach ($cands as $c) {
            if ($this->isRanked($c)) {
                $ranks[$c] = $this->rmap[$c];
            }
        }
        if (empty($ranks)) {
            return [];
        }
        $maxRank = max($ranks);
        $result = array_keys(array_filter($ranks, fn($r) => $r === $maxRank));
        sort($result);
        return $result;
    }

    /**
     * Returns true if the ranking is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->rmap);
    }

    /**
     * Returns true if the ranking has a tie.
     */
    public function hasTie(): bool
    {
        $ranks = array_values($this->rmap);
        return count($ranks) !== count(array_unique($ranks));
    }

    /**
     * Returns true if the ranking is a complete linear order of numCands candidates.
     */
    public function isLinear(int $numCands): bool
    {
        return !$this->hasTie() && count($this->rmap) === $numCands;
    }

    /**
     * Returns true if the ranking is a truncated linear order (no ties, fewer than numCands).
     */
    public function isTruncatedLinear(int $numCands): bool
    {
        return !$this->hasTie() && count($this->rmap) < $numCands;
    }

    /**
     * Returns true if a rank is skipped (when ranks are normalized).
     */
    public function hasSkippedRank(): bool
    {
        $ranks = $this->getRanks();
        if (empty($ranks)) {
            return false;
        }
        return $ranks !== range(1, count($ranks));
    }

    /**
     * Returns true if the voter submitted an overvote (a ranking with a tie).
     */
    public function hasOvervote(): bool
    {
        return $this->hasTie();
    }

    /**
     * Truncate the ranking at an overvote.
     */
    public function truncateOvervote(): void
    {
        $newRmap = [];
        foreach ($this->getRanks() as $r) {
            $candsAtRank = $this->candsAtRank($r);
            if (count($candsAtRank) === 1) {
                $newRmap[$candsAtRank[0]] = $r;
            } else {
                break;
            }
        }
        $this->rmap = $newRmap;
    }

    /**
     * Returns true if every candidate in c1s is weakly preferred to every candidate in c2s.
     * @param array<int|string> $c1s
     * @param array<int|string> $c2s
     */
    public function AAdom(array $c1s, array $c2s, bool $useExtendedPreferences = false): bool
    {
        foreach ($c1s as $c1) {
            foreach ($c2s as $c2) {
                $isWeakPref = $useExtendedPreferences
                    ? $this->extendedWeakPref($c1, $c2)
                    : $this->weakPref($c1, $c2);
                if (!$isWeakPref) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns true if AAdom(c1s, c2s) and there is some candidate in c1s that is strictly preferred to every candidate in c2s.
     * @param array<int|string> $c1s
     * @param array<int|string> $c2s
     */
    public function strongDom(array $c1s, array $c2s, bool $useExtendedPreferences = false): bool
    {
        if (!$this->AAdom($c1s, $c2s, $useExtendedPreferences)) {
            return false;
        }

        foreach ($c1s as $c1) {
            $strictlyPrefersAll = true;
            foreach ($c2s as $c2) {
                $isStrictPref = $useExtendedPreferences
                    ? $this->extendedStrictPref($c1, $c2)
                    : $this->strictPref($c1, $c2);
                if (!$isStrictPref) {
                    $strictlyPrefersAll = false;
                    break;
                }
            }
            if ($strictlyPrefersAll) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if AAdom(c1s, c2s) and there is some candidate in c1s that is strictly preferred to some candidate in c2s.
     * @param array<int|string> $c1s
     * @param array<int|string> $c2s
     */
    public function weakDom(array $c1s, array $c2s, bool $useExtendedPreferences = false): bool
    {
        if (!$this->AAdom($c1s, $c2s, $useExtendedPreferences)) {
            return false;
        }

        foreach ($c1s as $c1) {
            foreach ($c2s as $c2) {
                $isStrictPref = $useExtendedPreferences
                    ? $this->extendedStrictPref($c1, $c2)
                    : $this->strictPref($c1, $c2);
                if ($isStrictPref) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * If the ranking has no ties, returns it as a tuple (array); otherwise null.
     * @return array<int|string>|null
     */
    public function toLinear(): ?array
    {
        if ($this->hasTie()) {
            return null;
        }
        $items = $this->rmap;
        asort($items);
        return array_keys($items);
    }

    /**
     * Returns the ranking as a tuple of indifference classes.
     * @return array<array<int|string>>
     */
    public function toIndiffList(): array
    {
        $result = [];
        foreach ($this->getRanks() as $r) {
            $result[] = $this->candsAtRank($r);
        }
        return $result;
    }

    /**
     * Normalize ranks so they start at 1 and increment by 1.
     */
    public function normalizeRanks(): void
    {
        $ranks = $this->getRanks();
        $rankMap = array_flip($ranks);
        foreach ($this->rmap as $c => $r) {
            $this->rmap[$c] = $rankMap[$r] + 1;
        }
    }

    /**
     * Creates a Ranking from a list of indifference classes.
     * @param array<array<int|string>> $indiffList
     */
    public static function fromIndiffList(array $indiffList, ?array $cmap = null): Ranking
    {
        $rmap = [];
        foreach ($indiffList as $r => $cands) {
            foreach ($cands as $c) {
                $rmap[$c] = $r + 1;
            }
        }
        return new Ranking($rmap, $cmap);
    }

    /**
     * Creates a Ranking from a linear order (array of candidates).
     * @param array<int|string> $linearOrder
     */
    public static function fromLinearOrder(array $linearOrder, ?array $cmap = null): Ranking
    {
        $rmap = [];
        foreach ($linearOrder as $r => $c) {
            $rmap[$c] = $r + 1;
        }
        return new Ranking($rmap, $cmap);
    }

    /**
     * Returns the reverse of the ranking.
     */
    public function reverse(): Ranking
    {
        $newRmap = array_map(fn($r) => -$r, $this->rmap);
        $ranking = new Ranking($newRmap, $this->cmap);
        $ranking->normalizeRanks();
        return $ranking;
    }

    /**
     * Break ties in a ranking alphabetically by candidate ID.
     */
    public static function breakTiesAlphabetically(Ranking $ranking): Ranking
    {
        $cands = $ranking->getCands();
        $newRmap = [];
        $n = 0;

        foreach ($ranking->getRanks() as $r) {
            $candsAtRank = $ranking->candsAtRank($r);
            sort($candsAtRank);
            foreach ($candsAtRank as $c) {
                $newRmap[$c] = $n;
                $n++;
            }
        }

        return new Ranking($newRmap, $ranking->cmap);
    }

    /**
     * Returns true if this ranking equals another.
     */
    public function equals(Ranking $other): bool
    {
        $selfRanks = $this->getRanks();
        $otherRanks = $other->getRanks();

        if (count($selfRanks) !== count($otherRanks)) {
            return false;
        }

        foreach (array_map(null, $selfRanks, $otherRanks) as [$sr, $or]) {
            $selfCands = $this->candsAtRank($sr);
            $otherCands = $other->candsAtRank($or);
            sort($selfCands);
            sort($otherCands);
            if ($selfCands !== $otherCands) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns string representation of the ranking.
     */
    public function __toString(): string
    {
        $str = '';
        foreach ($this->getRanks() as $r) {
            $candsAtRank = $this->candsAtRank($r);
            if (count($candsAtRank) === 1) {
                $str .= $this->cmap[$candsAtRank[0]] . ' ';
            } else {
                $str .= '( ' . implode('  ', array_map(fn($c) => $this->cmap[$c], $candsAtRank)) . ' ) ';
            }
        }
        return trim($str);
    }

    /**
     * Returns the candidate(s) at the r-th rank (0-indexed).
     * @return int|string|array<int|string>
     */
    public function getItem(int $r)
    {
        $ranks = $this->getRanks();
        if ($r >= count($ranks)) {
            throw new \OutOfBoundsException("There is no item at rank " . ($r + 1));
        }
        $candsAtRank = $this->candsAtRank($ranks[$r]);
        return count($candsAtRank) === 1 ? $candsAtRank[0] : $candsAtRank;
    }
}
