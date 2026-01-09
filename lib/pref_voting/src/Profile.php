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
 * An anonymous profile of linear rankings of n candidates.
 *
 * Candidates are named 0, 1, ..., n-1 and a ranking is a list of candidate names.
 * For instance, [0, 2, 1] represents: 0 ranked above 2, 2 ranked above 1.
 *
 * Example:
 *   $prof = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]], [2, 3, 1]);
 *   // 2 voters: 0>1>2, 3 voters: 1>2>0, 1 voter: 2>0>1
 */
class Profile
{
    /** @var int Number of candidates */
    public int $numCands;

    /** @var int[] List of candidate IDs */
    public array $candidates;

    /** @var array<array<int>> Rankings as 2D array */
    private array $rankings;

    /** @var int[] Count of voters for each ranking type */
    private array $rcounts;

    /** @var array<array<int>> Ranks matrix: for each ranking, rank of each candidate */
    private array $ranks;

    /** @var array<array<int>> Support tally matrix */
    private array $tally;

    /** @var array<int, string> Candidate name map */
    public array $cmap;

    /** @var int Total number of voters */
    public int $numVoters;

    /**
     * @param array<array<int>> $rankings List of rankings
     * @param int[]|null $rcounts Vote counts (defaults to 1 each)
     * @param array<int, string>|null $cmap Candidate display names
     */
    public function __construct(array $rankings, ?array $rcounts = null, ?array $cmap = null)
    {
        $this->numCands = !empty($rankings) ? count($rankings[0]) : 0;
        $this->candidates = range(0, $this->numCands - 1);

        $this->rankings = $rankings;
        $this->rcounts = $rcounts ?? array_fill(0, count($rankings), 1);

        // Build ranks matrix: for each ranking, compute rank of each candidate
        $this->ranks = [];
        foreach ($this->rankings as $ranking) {
            $rankRow = [];
            foreach ($this->candidates as $c) {
                $pos = array_search($c, $ranking, true);
                $rankRow[$c] = $pos !== false ? $pos + 1 : $this->numCands + 1;
            }
            $this->ranks[] = $rankRow;
        }

        // Build support tally matrix
        $this->tally = [];
        foreach ($this->candidates as $c1) {
            $this->tally[$c1] = [];
            foreach ($this->candidates as $c2) {
                $this->tally[$c1][$c2] = $this->computeSupport($c1, $c2);
            }
        }

        $this->cmap = $cmap ?? array_combine(
            $this->candidates,
            array_map('strval', $this->candidates)
        );

        $this->numVoters = array_sum($this->rcounts);
    }

    /**
     * Computes the raw support of c1 over c2.
     */
    private function computeSupport(int $c1, int $c2): int
    {
        $support = 0;
        foreach ($this->ranks as $i => $rankRow) {
            if ($rankRow[$c1] < $rankRow[$c2]) {
                $support += $this->rcounts[$i];
            }
        }
        return $support;
    }

    /**
     * Returns rankings and their counts.
     * @return array{0: array<array<int>>, 1: int[]}
     */
    public function getRankingsCounts(): array
    {
        return [$this->rankings, $this->rcounts];
    }

    /**
     * Returns unique ranking types as tuples.
     * @return array<array<int>>
     */
    public function getRankingTypes(): array
    {
        $unique = [];
        foreach ($this->rankings as $r) {
            $key = implode(',', $r);
            if (!isset($unique[$key])) {
                $unique[$key] = $r;
            }
        }
        return array_values($unique);
    }

    /**
     * Returns all individual rankings (expanded by counts).
     * @return array<array<int>>
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
     * Number of voters ranking c1 above c2.
     */
    public function support(int $c1, int $c2): int
    {
        return $this->tally[$c1][$c2];
    }

    /**
     * Margin of c1 over c2: support(c1,c2) - support(c2,c1).
     */
    public function margin(int $c1, int $c2): int
    {
        return $this->tally[$c1][$c2] - $this->tally[$c2][$c1];
    }

    /**
     * Returns true if more voters rank c1 over c2 than c2 over c1.
     */
    public function majorityPrefers(int $c1, int $c2): bool
    {
        return $this->margin($c1, $c2) > 0;
    }

    /**
     * Returns true if c1 and c2 are tied.
     */
    public function isTied(int $c1, int $c2): bool
    {
        return $this->margin($c1, $c2) === 0;
    }

    /**
     * Returns the number of voters ranking candidate c at position level.
     */
    public function numRank(int $c, int $level): int
    {
        $count = 0;
        foreach ($this->rankings as $i => $ranking) {
            if (isset($ranking[$level - 1]) && $ranking[$level - 1] === $c) {
                $count += $this->rcounts[$i];
            }
        }
        return $count;
    }

    /**
     * Returns plurality scores for candidates in currCands.
     * @param int[]|null $currCands
     * @return array<int, int>
     */
    public function pluralityScores(?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        $candsToIgnore = array_diff($this->candidates, $currCands);

        $scores = array_fill_keys($currCands, 0);

        foreach ($this->rankings as $i => $ranking) {
            // Find first candidate not in candsToIgnore
            foreach ($ranking as $c) {
                if (!in_array($c, $candsToIgnore, true)) {
                    $scores[$c] += $this->rcounts[$i];
                    break;
                }
            }
        }

        return $scores;
    }

    /**
     * Returns Borda scores for candidates in currCands.
     * @param int[]|null $currCands
     * @return array<int, int>
     */
    public function bordaScores(?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        $numCurrCands = count($currCands);

        $scores = array_fill_keys($currCands, 0);

        foreach ($this->rankings as $i => $ranking) {
            // Filter ranking to only currCands
            $filteredRanking = array_values(array_filter(
                $ranking,
                fn($c) => in_array($c, $currCands, true)
            ));

            // Assign Borda points: n-1 for first, n-2 for second, etc.
            foreach ($filteredRanking as $pos => $c) {
                $scores[$c] += ($numCurrCands - 1 - $pos) * $this->rcounts[$i];
            }
        }

        return $scores;
    }

    /**
     * Returns Copeland scores for candidates in currCands.
     * @param int[]|null $currCands
     * @param array{0: float, 1: float, 2: float} $scores Win/tie/loss scores
     * @return array<int, float>
     */
    public function copelandScores(?array $currCands = null, array $scores = [1, 0, -1]): array
    {
        $currCands = $currCands ?? $this->candidates;
        [$wscore, $tscore, $lscore] = $scores;

        $cScores = array_fill_keys($currCands, 0.0);

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
     * Returns candidates that are majority preferred to cand.
     * @param int[]|null $currCands
     * @return int[]
     */
    public function dominators(int $cand, ?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $currCands,
            fn($c) => $this->majorityPrefers($c, $cand)
        ));
    }

    /**
     * Returns candidates that cand is majority preferred to.
     * @param int[]|null $currCands
     * @return int[]
     */
    public function dominates(int $cand, ?array $currCands = null): array
    {
        $currCands = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $currCands,
            fn($c) => $this->majorityPrefers($cand, $c)
        ));
    }

    /**
     * Returns the Condorcet winner if one exists, null otherwise.
     * @param int[]|null $currCands
     */
    public function condorcetWinner(?array $currCands = null): ?int
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
     * Returns weak Condorcet winners (candidates not beaten by anyone).
     * @param int[]|null $currCands
     * @return int[]|null
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
     * Returns the Condorcet loser if one exists, null otherwise.
     * @param int[]|null $currCands
     */
    public function condorcetLoser(?array $currCands = null): ?int
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
     * Returns the strict majority size.
     */
    public function strictMajSize(): int
    {
        return intval($this->numVoters / 2) + 1;
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
     * Returns true if the profile is uniquely weighted.
     * A profile is uniquely weighted when there are no 0 margins and all margins are unique.
     */
    public function isUniquelyWeighted(): bool
    {
        $margins = [];
        foreach ($this->candidates as $c1) {
            foreach ($this->candidates as $c2) {
                if ($c1 < $c2) {
                    $m = abs($this->margin($c1, $c2));
                    if ($m === 0 || in_array($m, $margins, true)) {
                        return false;
                    }
                    $margins[] = $m;
                }
            }
        }
        return true;
    }

    /**
     * Removes candidates from the profile.
     * Returns new Profile and mapping from new to original candidate names.
     * @param int[] $candsToIgnore
     * @return array{0: Profile, 1: array<int, int>}
     */
    public function removeCandidates(array $candsToIgnore): array
    {
        $newRankings = [];
        foreach ($this->rankings as $ranking) {
            $newRanking = array_values(array_filter(
                $ranking,
                fn($c) => !in_array($c, $candsToIgnore, true)
            ));
            $newRankings[] = $newRanking;
        }

        // Create mapping from new indices to original candidates
        $remainingCands = array_values(array_diff($this->candidates, $candsToIgnore));
        sort($remainingCands);
        $newNames = [];
        $origNames = [];
        foreach ($remainingCands as $newIdx => $origCand) {
            $newNames[$origCand] = $newIdx;
            $origNames[$newIdx] = $origCand;
        }

        // Remap rankings to new candidate indices
        $remappedRankings = [];
        foreach ($newRankings as $ranking) {
            $remappedRankings[] = array_map(fn($c) => $newNames[$c], $ranking);
        }

        return [
            new Profile($remappedRankings, $this->rcounts, $this->cmap),
            $origNames
        ];
    }

    /**
     * Returns a new Profile with candidates permuted according to the mapping.
     * @param array<int, int> $perm A bijection on the set of all candidates
     */
    public function applyCandPermutation(array $perm): Profile
    {
        // Assert permutation is a bijection on all candidates
        $keys = array_keys($perm);
        $values = array_values($perm);
        sort($keys);
        sort($values);

        assert($keys === $this->candidates, "All keys must be valid candidates");
        assert($values === $this->candidates, "All values must be valid candidates");

        $newRankings = [];
        foreach ($this->rankings as $ranking) {
            $newRanking = array_map(fn($c) => $perm[$c], $ranking);
            $newRankings[] = $newRanking;
        }

        return new Profile($newRankings, $this->rcounts, $this->cmap);
    }

    /**
     * Display the profile as a string table.
     */
    public function display(?array $cmap = null): void
    {
        $cmap = $cmap ?? $this->cmap;

        // Header: vote counts
        echo implode("\t", $this->rcounts) . "\n";
        echo str_repeat("-", count($this->rcounts) * 8) . "\n";

        // Each row is a rank level
        for ($level = 0; $level < $this->numCands; $level++) {
            $row = [];
            foreach ($this->rankings as $ranking) {
                $row[] = $cmap[$ranking[$level]];
            }
            echo implode("\t", $row) . "\n";
        }
    }

    /**
     * Returns a ProfileWithTies object representing the same profile.
     */
    public function toProfileWithTies(): ProfileWithTies
    {
        $rankingsWithTies = [];
        foreach ($this->rankings as $ranking) {
            $rmap = [];
            foreach ($ranking as $r => $c) {
                $rmap[$c] = $r + 1;
            }
            $rankingsWithTies[] = $rmap;
        }
        return new ProfileWithTies($rankingsWithTies, $this->rcounts, $this->candidates, $this->cmap);
    }

    /**
     * Returns true if this profile equals another.
     */
    public function equals(Profile $other): bool
    {
        if ($this->numCands !== $other->numCands || $this->numVoters !== $other->numVoters) {
            return false;
        }

        // Canonicalize and compare rankings and counts
        $canonSelf = $this->getCanonicalRankingsCounts();
        $canonOther = $other->getCanonicalRankingsCounts();

        return $canonSelf === $canonOther;
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
     * Returns a canonical representation of rankings and counts for comparison.
     * @return array<string, int>
     */
    private function getCanonicalRankingsCounts(): array
    {
        $canon = [];
        foreach ($this->rankings as $i => $r) {
            $key = implode(',', $r);
            $canon[$key] = ($canon[$key] ?? 0) + $this->rcounts[$i];
        }
        ksort($canon);
        return $canon;
    }

    /**
     * String representation showing profile description.
     */
    public function __toString(): string
    {
        return sprintf(
            "Profile(%d candidates, %d voters, %d ranking types)",
            $this->numCands,
            $this->numVoters,
            count($this->rankings)
        );
    }
}
