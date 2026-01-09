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
 * A support graph is a weighted asymmetric directed graph. The nodes are the 
 * candidates and an edge from candidate c to d with weight (n, m) means 
 * that n voters rank c over d and m voters rank d over c.
 */
class SupportGraph extends MajorityGraph
{
    /** @var int[][] Support matrix: [c1_idx][c2_idx] is the support of c1 over c2 */
    public array $sMatrix;

    /**
     * @param array<int|string> $candidates List of candidates
     * @param array<array{0: int|string, 1: int|string, 2: array{0: int, 1: int}}> $wEdges List of (c1, c2, [s12, s21]) edges
     * @param array<int|string, string>|null $cmap Candidate name map
     */
    public function __construct(array $candidates, array $wEdges, ?array $cmap = null)
    {
        $edges = [];
        foreach ($wEdges as $we) {
            if ($we[2][0] > $we[2][1]) {
                $edges[] = [$we[0], $we[1]];
            } elseif ($we[2][1] > $we[2][0]) {
                $edges[] = [$we[1], $we[0]];
            }
        }
        parent::__construct($candidates, $edges, $cmap);

        $this->sMatrix = array_fill(0, $this->numCands, array_fill(0, $this->numCands, 0));

        foreach ($wEdges as $we) {
            $c1 = $we[0];
            $c2 = $we[1];
            $s12 = $we[2][0];
            $s21 = $we[2][1];
            if (isset($this->candToCindex[$c1]) && isset($this->candToCindex[$c2])) {
                $idx1 = $this->candToCindex[$c1];
                $idx2 = $this->candToCindex[$c2];
                $this->sMatrix[$idx1][$idx2] = $s12;
                $this->sMatrix[$idx2][$idx1] = $s21;
            }
        }
    }

    /**
     * Returns a list of the weighted edges in the support graph.
     * @return array<array{0: int|string, 1: int|string, 2: array{0: int, 1: int}}>
     */
    public function getEdges(): array
    {
        $wEdges = [];
        foreach (parent::getEdges() as $edge) {
            $c1 = $edge[0];
            $c2 = $edge[1];
            $wEdges[] = [$c1, $c2, [$this->support($c1, $c2), $this->support($c2, $c1)]];
        }
        return $wEdges;
    }

    public function margin(int|string $c1, int|string $c2): int
    {
        if (isset($this->candToCindex[$c1]) && isset($this->candToCindex[$c2])) {
            $idx1 = $this->candToCindex[$c1];
            $idx2 = $this->candToCindex[$c2];
            return $this->sMatrix[$idx1][$idx2] - $this->sMatrix[$idx2][$idx1];
        }
        return 0;
    }

    public function support(int|string $c1, int|string $c2): int
    {
        if (isset($this->candToCindex[$c1]) && isset($this->candToCindex[$c2])) {
            return $this->sMatrix[$this->candToCindex[$c1]][$this->candToCindex[$c2]];
        }
        return 0;
    }

    public function majorityPrefers(int|string $c1, int|string $c2): bool
    {
        return $this->support($c1, $c2) > $this->support($c2, $c1);
    }

    public function isTied(int|string $c1, int|string $c2): bool
    {
        return $this->support($c1, $c2) === $this->support($c2, $c1);
    }

    /**
     * Return the strength matrix of the profile.
     * @return array{0: array<array<int|float>>, 1: array<int|string, int>}
     */
    public function strengthMatrix(?array $currCands = null, ?callable $strengthFunction = null): array
    {
        $candidates = $currCands ?? $this->candidates;
        $cands = array_values($candidates);
        $n = count($cands);
        $candToIdx = array_flip($cands);

        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = array_fill(0, $n, 0);
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;
                if ($strengthFunction !== null) {
                    $matrix[$i][$j] = $strengthFunction($cands[$i], $cands[$j]);
                } else {
                    $matrix[$i][$j] = $this->support($cands[$i], $cands[$j]);
                }
            }
        }

        return [$matrix, $candToIdx];
    }

    public function removeCandidates(array $candsToIgnore): SupportGraph
    {
        $newCands = array_values(array_filter($this->candidates, fn($c) => !in_array($c, $candsToIgnore, true)));
        $newWEdges = [];
        // We need to collect all pairs to preserve support information
        foreach ($newCands as $c1) {
            foreach ($newCands as $c2) {
                if ($c1 < $c2) {
                    $newWEdges[] = [$c1, $c2, [$this->support($c1, $c2), $this->support($c2, $c1)]];
                }
            }
        }
        $newCmap = array_filter($this->cmap, fn($c) => in_array($c, $newCands, true), ARRAY_FILTER_USE_KEY);
        return new SupportGraph($newCands, $newWEdges, $newCmap);
    }

    public function description(): string
    {
        return sprintf("SupportGraph(%s, %s)", json_encode($this->candidates), json_encode($this->getEdges()));
    }

    public function equals(MajorityGraph $other): bool
    {
        if (!($other instanceof SupportGraph)) return false;
        if ($this->numCands !== $other->numCands) return false;
        $diff = array_diff($this->candidates, $other->candidates);
        if (!empty($diff)) return false;

        $edges1 = $this->getEdges();
        $edges2 = $other->getEdges();
        if (count($edges1) !== count($edges2)) return false;

        usort($edges1, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        usort($edges2, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        return $edges1 === $edges2;
    }

    public static function fromProfile(Profile|ProfileWithTies $profile, ?array $cmap = null): SupportGraph
    {
        $wEdges = [];
        foreach ($profile->candidates as $c1) {
            foreach ($profile->candidates as $c2) {
                if ($c1 < $c2) {
                    $wEdges[] = [$c1, $c2, [$profile->support($c1, $c2), $profile->support($c2, $c1)]];
                }
            }
        }
        return new SupportGraph($profile->candidates, $wEdges, $cmap ?? $profile->cmap);
    }
}
