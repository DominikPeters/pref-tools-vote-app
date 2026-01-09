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
 * An anonymous profile of (truncated) strict weak orders of n candidates.
 *
 * Unlike Profile, this supports rankings with ties and incomplete rankings.
 *
 * Example:
 *   $prof = new ProfileWithTies([
 *       [0 => 1, 1 => 2, 2 => 3],    // 0 first, 1 second, 2 third
 *       [1 => 1, 2 => 1, 0 => 2],    // 1 and 2 tied first, 0 second
 *       [2 => 1, 0 => 2],            // 2 first, 0 second (1 unranked)
 *   ], [2, 3, 1]);
 */
class ProfileWithTies
{
    /** @var array<int|string> List of candidates */
    public array $candidates;

    /** @var int Number of candidates */
    public int $numCands;

    /** @var array<int|string, string> Candidate name map */
    public array $cmap;

    /** @var Ranking[] The rankings */
    private array $rankings;

    /** @var int[] Count of voters for each ranking */
    public array $rcounts;

    /** @var int Total number of voters */
    public int $numVoters;

    /** @var bool Whether using extended strict preference */
    public bool $usingExtendedStrictPreference = false;

    /** @var array<array<int>> Cached support matrix */
    private array $supports;

    /**
     * @param array<array<int|string, int>|Ranking> $rankings
     * @param int[]|null $rcounts
     * @param array<int|string>|null $candidates
     * @param array<int|string, string>|null $cmap
     */
    public function __construct(
        array $rankings,
        ?array $rcounts = null,
        ?array $candidates = null,
        ?array $cmap = null
    ) {
        // Extract all candidates from rankings if not provided
        if ($candidates === null) {
            $allCands = [];
            foreach ($rankings as $r) {
                $cands = $r instanceof Ranking ? $r->getCands() : array_keys($r);
                $allCands = array_merge($allCands, $cands);
            }
            $this->candidates = array_values(array_unique($allCands));
            sort($this->candidates);
        } else {
            $this->candidates = $candidates;
            sort($this->candidates);
        }

        $this->numCands = count($this->candidates);

        $this->cmap = $cmap ?? array_combine(
            $this->candidates,
            array_map('strval', $this->candidates)
        );

        // Convert to Ranking objects
        $this->rankings = [];
        foreach ($rankings as $r) {
            if ($r instanceof Ranking) {
                $this->rankings[] = new Ranking($r->rmap, $this->cmap);
            } else {
                $this->rankings[] = new Ranking($r, $this->cmap);
            }
        }

        $this->rcounts = $rcounts ?? array_fill(0, count($rankings), 1);
        $this->numVoters = array_sum($this->rcounts);

        // Compute supports
        $this->computeSupports();
    }

    /**
     * Compute support matrix using current preference mode.
     */
    private function computeSupports(): void
    {
        $this->supports = [];
        foreach ($this->candidates as $c1) {
            $this->supports[$c1] = [];
            foreach ($this->candidates as $c2) {
                $support = 0;
                foreach ($this->rankings as $i => $ranking) {
                    $pref = $this->usingExtendedStrictPreference
                        ? $ranking->extendedStrictPref($c1, $c2)
                        : $ranking->strictPref($c1, $c2);
                    if ($pref) {
                        $support += $this->rcounts[$i];
                    }
                }
                $this->supports[$c1][$c2] = $support;
            }
        }
    }

    /**
     * Switch to using extended strict preferences.
     */
    public function useExtendedStrictPreference(): void
    {
        $this->usingExtendedStrictPreference = true;
        $this->computeSupports();
    }

    /**
     * Switch to using strict preferences.
     */
    public function useStrictPreference(): void
    {
        $this->usingExtendedStrictPreference = false;
        $this->computeSupports();
    }

    /**
     * Returns rankings and counts.
     * @return array{0: Ranking[], 1: int[]}
     */
    public function getRankingsCounts(): array
    {
        return [$this->rankings, $this->rcounts];
    }

    /**
     * Returns all individual rankings expanded by counts.
     * @return Ranking[]
     */
    public function getRankings(): array
    {
        $result = [];
        foreach ($this->rankings as $i => $r) {
            for ($n = 0; $n < $this->rcounts[$i]; $n++) {
                $result[] = $r;
            }
        }
        return $result;
    }

    /**
     * Number of voters ranking c1 strictly above c2.
     */
    public function support(int|string $c1, int|string $c2): int
    {
        return $this->supports[$c1][$c2];
    }

    /**
     * Ratio of support(c1,c2) to support(c2,c1).
     */
    public function ratio(int|string $c1, int|string $c2): float
    {
        $s12 = $this->support($c1, $c2);
        $s21 = $this->support($c2, $c1);
        if ($s12 > 0 && $s21 > 0) {
            return $s12 / $s21;
        } elseif ($s12 > 0 && $s21 === 0) {
            return floatval($this->numVoters + $s12);
        } elseif ($s12 === 0 && $s21 > 0) {
            return 1.0 / ($this->numVoters + $s21);
        } else {
            return 1.0;
        }
    }

    /**
     * Returns rankings as indifference lists.
     * @return array<array<array<int|string>>>
     */
    public function getRankingsAsIndiffList(): array
    {
        $result = [];
        foreach ($this->getRankings() as $ranking) {
            $result[] = $ranking->toIndiffList();
        }
        return $result;
    }

    /**
     * Margin of c1 over c2.
     */
    public function margin(int|string $c1, int|string $c2): int
    {
        return $this->supports[$c1][$c2] - $this->supports[$c2][$c1];
    }

    /**
     * Returns true if c1 is majority preferred to c2.
     */
    public function majorityPrefers(int|string $c1, int|string $c2): bool
    {
        return $this->margin($c1, $c2) > 0;
    }

    /**
     * Returns true if c1 and c2 are tied.
     */
    public function isTied(int|string $c1, int|string $c2): bool
    {
        return $this->margin($c1, $c2) === 0;
    }

    /**
     * Returns the margin matrix.
     * @return array<array<int>>
     */
    public function getMarginMatrix(): array
    {
        $matrix = [];
        foreach ($this->candidates as $c1) {
            $row = [];
            foreach ($this->candidates as $c2) {
                $row[] = $this->margin($c1, $c2);
            }
            $matrix[] = $row;
        }
        return $matrix;
    }

    /**
     * Returns candidates that are majority preferred to cand.
     * @param array<int|string>|null $currCands
     * @return array<int|string>
     */
    public function dominators(int|string $cand, ?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $currCands,
            fn($c) => $this->majorityPrefers($c, $cand)
        ));
    }

    /**
     * Returns candidates that cand is majority preferred to.
     * @param array<int|string>|null $currCands
     * @return array<int|string>
     */
    public function dominates(int|string $cand, ?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $currCands,
            fn($c) => $this->majorityPrefers($cand, $c)
        ));
    }

    /**
     * Returns the Condorcet winner if one exists.
     * @param array<int|string>|null $currCands
     * @return int|string|null
     */
    public function condorcetWinner(?array $currCands = null): int|string|null
    {
        $currCands = $currCands ?? $this->candidates;

        foreach ($currCands as $c1) {
            $beatsAll = true;
            foreach ($currCands as $c2) {
                if ($c1 !== $c2 && !$this->majorityPrefers($c1, $c2)) {
                    $beatsAll = false;
                    break;
                }
            }
            if ($beatsAll) {
                return $c1;
            }
        }
        return null;
    }

    /**
     * Returns weak Condorcet winners.
     * @param array<int|string>|null $currCands
     * @return array<int|string>|null
     */
    public function weakCondorcetWinner(?array $currCands = null): ?array
    {
        $currCands = $currCands ?? $this->candidates;
        $weakCw = [];

        foreach ($currCands as $c1) {
            $notBeaten = true;
            foreach ($currCands as $c2) {
                if ($c1 !== $c2 && $this->majorityPrefers($c2, $c1)) {
                    $notBeaten = false;
                    break;
                }
            }
            if ($notBeaten) {
                $weakCw[] = $c1;
            }
        }

        if (empty($weakCw)) {
            return null;
        }
        sort($weakCw);
        return $weakCw;
    }

    /**
     * Returns the Condorcet loser if one exists.
     * @param array<int|string>|null $currCands
     * @return int|string|null
     */
    public function condorcetLoser(?array $currCands = null): int|string|null
    {
        $currCands = $currCands ?? $this->candidates;

        foreach ($currCands as $c1) {
            $losesToAll = true;
            foreach ($currCands as $c2) {
                if ($c1 !== $c2 && !$this->majorityPrefers($c2, $c1)) {
                    $losesToAll = false;
                    break;
                }
            }
            if ($losesToAll) {
                return $c1;
            }
        }
        return null;
    }

    /**
     * Returns Copeland scores.
     * @param array<int|string>|null $currCands
     * @param array{0: float, 1: float, 2: float} $scores
     * @return array<int|string, float>
     */
    public function copelandScores(?array $currCands = null, array $scores = [1, 0, -1]): array
    {
        $currCands = $currCands ?? $this->candidates;
        [$wscore, $tscore, $lscore] = $scores;

        $cScores = [];
        foreach ($currCands as $c) {
            $cScores[$c] = 0.0;
        }

        foreach ($currCands as $c1) {
            foreach ($currCands as $c2) {
                if ($c1 === $c2) continue;
                if ($this->majorityPrefers($c1, $c2)) {
                    $cScores[$c1] += $wscore;
                } elseif ($this->majorityPrefers($c2, $c1)) {
                    $cScores[$c1] += $lscore;
                } else {
                    $cScores[$c1] += $tscore;
                }
            }
        }

        return $cScores;
    }

    /**
     * Returns plurality scores (requires no ties for first place).
     * @param array<int|string>|null $currCands
     * @return array<int|string, int>
     * @throws \Exception if any voter has ties for first place
     */
    public function pluralityScores(?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;

        $scores = [];
        foreach ($currCands as $c) {
            $scores[$c] = 0;
        }

        foreach ($this->rankings as $i => $ranking) {
            $first = $ranking->first($currCands);
            if (count($first) > 1) {
                throw new \Exception("Cannot find plurality scores unless all voters rank a unique candidate in first place.");
            }
            if (count($first) === 1) {
                $scores[$first[0]] += $this->rcounts[$i];
            }
        }

        return $scores;
    }

    /**
     * Returns plurality scores ignoring overvotes (ties for first).
     * @param array<int|string>|null $currCands
     * @return array<int|string, int>
     */
    public function pluralityScoresIgnoringOvervotes(?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;

        $scores = [];
        foreach ($currCands as $c) {
            $scores[$c] = 0;
        }

        foreach ($this->rankings as $i => $ranking) {
            if ($ranking->isEmpty()) continue;
            $first = $ranking->first($currCands);
            if (count($first) === 1) {
                $scores[$first[0]] += $this->rcounts[$i];
            }
        }

        return $scores;
    }

    /**
     * Returns the strict majority size.
     */
    public function strictMajSize(): int
    {
        return intval($this->numVoters / 2) + 1;
    }

    /**
     * Returns true if the profile only contains (truncated) linear orders.
     */
    public function isTruncatedLinear(): bool
    {
        foreach ($this->rankings as $ranking) {
            if (!$ranking->isTruncatedLinear($this->numCands) && !$ranking->isLinear($this->numCands)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes candidates from the profile.
     * @param array<int|string> $candsToIgnore
     */
    public function removeCandidates(array $candsToIgnore): ProfileWithTies
    {
        $newRankings = [];
        foreach ($this->rankings as $ranking) {
            $newRmap = array_filter(
                $ranking->rmap,
                fn($c) => !in_array($c, $candsToIgnore, true),
                ARRAY_FILTER_USE_KEY
            );
            $newRankings[] = $newRmap;
        }

        $newCandidates = array_values(array_filter(
            $this->candidates,
            fn($c) => !in_array($c, $candsToIgnore, true)
        ));

        $newProf = new ProfileWithTies(
            $newRankings,
            $this->rcounts,
            $newCandidates,
            $this->cmap
        );

        if ($this->usingExtendedStrictPreference) {
            $newProf->useExtendedStrictPreference();
        }

        return $newProf;
    }

    /**
     * Removes empty rankings from the profile.
     */
    public function removeEmptyRankings(): void
    {
        $newRankings = [];
        $newRcounts = [];

        foreach ($this->rankings as $i => $r) {
            if (!$r->isEmpty()) {
                $newRankings[] = $r;
                $newRcounts[] = $this->rcounts[$i];
            }
        }

        $this->rankings = $newRankings;
        $this->rcounts = $newRcounts;
        $this->numVoters = (int) array_sum($this->rcounts);

        // Recompute supports since numVoters changed
        $this->computeSupports();
    }

    /**
     * Converts to linear Profile if possible.
     */
    public function toLinearProfile(): ?Profile
    {
        $newRankings = [];
        foreach ($this->rankings as $ranking) {
            $linear = $ranking->toLinear();
            if ($linear === null || count($linear) !== $this->numCands) {
                return null;
            }
            // Remap to integer indices
            $candToIdx = array_flip($this->candidates);
            $newRankings[] = array_map(fn($c) => $candToIdx[$c], $linear);
        }

        $newCmap = [];
        foreach ($this->candidates as $i => $c) {
            $newCmap[$i] = $this->cmap[$c];
        }

        return new Profile($newRankings, $this->rcounts, $newCmap);
    }

    /**
     * Returns the majority graph of the profile.
     */
    public function majorityGraph(): MajorityGraph
    {
        return MajorityGraph::fromProfile($this);
    }

    /**
     * Returns the margin graph of the profile.
     */
    public function marginGraph(): MarginGraph
    {
        return MarginGraph::fromProfile($this);
    }

    /**
     * Returns the support graph of the profile.
     */
    public function supportGraph(): SupportGraph
    {
        return SupportGraph::fromProfile($this);
    }

    /**
     * Display the profile.
     */
    public function display(?array $cmap = null): void
    {
        $cmap = $cmap ?? $this->cmap;

        echo implode("\t", $this->rcounts) . "\n";
        echo str_repeat("-", count($this->rcounts) * 8) . "\n";

        // Find max rank across all rankings
        $maxRank = 0;
        foreach ($this->rankings as $ranking) {
            $ranks = $ranking->getRanks();
            if (!empty($ranks)) {
                $maxRank = max($maxRank, max($ranks));
            }
        }

        for ($rank = 1; $rank <= $maxRank; $rank++) {
            $row = [];
            foreach ($this->rankings as $ranking) {
                $cands = $ranking->candsAtRank($rank);
                $names = array_map(fn($c) => $cmap[$c], $cands);
                $row[] = implode(' ', $names);
            }
            echo implode("\t", $row) . "\n";
        }
    }

    public function __toString(): string
    {
        return sprintf(
            "ProfileWithTies(%d candidates, %d voters, %d ranking types)",
            $this->numCands,
            $this->numVoters,
            count($this->rankings)
        );
    }
}
