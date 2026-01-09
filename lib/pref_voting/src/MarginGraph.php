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
 * A margin graph is a weighted asymmetric directed graph. The nodes are the 
 * candidates and an edge from candidate c to d with weight w means that 
 * c is majority preferred to d by a margin of w.
 */
class MarginGraph extends MajorityGraph
{
    /** @var array<array<int|float>> Margin matrix: [c1_idx][c2_idx] is the margin of c1 over c2 */
    public array $marginMatrix;

    /**
     * @param array<int|string> $candidates List of candidates
     * @param array<array{0: int|string, 1: int|string, 2: int}> $wEdges List of (c1, c2, margin) edges
     * @param array<int|string, string>|null $cmap Candidate name map
     */
    public function __construct(array $candidates, array $wEdges, ?array $cmap = null)
    {
        $edges = [];
        foreach ($wEdges as $we) {
            $edges[] = [$we[0], $we[1]];
        }
        parent::__construct($candidates, $edges, $cmap);

        $this->marginMatrix = array_fill(0, $this->numCands, array_fill(0, $this->numCands, 0));

        foreach ($wEdges as $we) {
            $c1 = $we[0];
            $c2 = $we[1];
            $margin = $we[2];
            if (isset($this->candToCindex[$c1]) && isset($this->candToCindex[$c2])) {
                $idx1 = $this->candToCindex[$c1];
                $idx2 = $this->candToCindex[$c2];
                $this->marginMatrix[$idx1][$idx2] = $margin;
                $this->marginMatrix[$idx2][$idx1] = -1 * $margin;
            }
        }
    }

    public function margin(int|string $c1, int|string $c2): int|float
    {
        if (isset($this->candToCindex[$c1]) && isset($this->candToCindex[$c2])) {
            return $this->marginMatrix[$this->candToCindex[$c1]][$this->candToCindex[$c2]];
        }
        return 0;
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
                    $matrix[$i][$j] = $this->margin($cands[$i], $cands[$j]);
                }
            }
        }

        return [$matrix, $candToIdx];
    }

    /**
     * Returns a list of the weighted edges in the margin graph.
     * @return array<array{0: int|string, 1: int|string, 2: int}>
     */
    public function getEdges(): array
    {
        $wEdges = [];
        foreach (parent::getEdges() as $edge) {
            $wEdges[] = [$edge[0], $edge[1], $this->margin($edge[0], $edge[1])];
        }
        return $wEdges;
    }

    public function removeCandidates(array $candsToIgnore): MarginGraph
    {
        $newCands = array_values(array_filter($this->candidates, fn($c) => !in_array($c, $candsToIgnore, true)));
        $newWEdges = [];
        foreach ($this->getEdges() as $we) {
            if (in_array($we[0], $newCands, true) && in_array($we[1], $newCands, true)) {
                $newWEdges[] = $we;
            }
        }
        $newCmap = array_filter($this->cmap, fn($c) => in_array($c, $newCands, true), ARRAY_FILTER_USE_KEY);
        return new MarginGraph($newCands, $newWEdges, $newCmap);
    }

    public function majorityPrefers(int|string $c1, int|string $c2): bool
    {
        return $this->margin($c1, $c2) > 0;
    }

    public function isTied(int|string $c1, int|string $c2): bool
    {
        return $this->margin($c1, $c2) === 0;
    }

    public function isUniquelyWeighted(): bool
    {
        $margins = [];
        foreach ($this->candidates as $c1) {
            foreach ($this->candidates as $c2) {
                if ($c1 === $c2) continue;
                $m = $this->margin($c1, $c2);
                if ($m === 0) return false;
                if ($m > 0) {
                    if (in_array($m, $margins, true)) return false;
                    $margins[] = $m;
                }
            }
        }
        return true;
    }

    public function description(): string
    {
        return sprintf("MarginGraph(%s, %s)", json_encode($this->candidates), json_encode($this->getEdges()));
    }

    public function equals(MajorityGraph $other): bool
    {
        if (!($other instanceof MarginGraph)) return false;
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

    public function add(MajorityGraph|Profile|ProfileWithTies $other): MarginGraph
    {
        $newWEdges = [];
        $candidates = $this->candidates;
        for ($i = 0; $i < count($candidates); $i++) {
            for ($j = $i + 1; $j < count($candidates); $j++) {
                $c1 = $candidates[$i];
                $c2 = $candidates[$j];
                $newMargin = $this->margin($c1, $c2) + $other->margin($c1, $c2);
                if ($newMargin > 0) {
                    $newWEdges[] = [$c1, $c2, $newMargin];
                } elseif ($newMargin < 0) {
                    $newWEdges[] = [$c2, $c1, -1 * $newMargin];
                }
            }
        }
        return new MarginGraph($candidates, $newWEdges, $this->cmap);
    }

    public function normalizeOrderedWeights(): MarginGraph
    {
        $edges = $this->getEdges();
        usort($edges, fn($a, $b) => $a[2] <=> $b[2]);

        $positiveMargins = [];
        foreach ($this->candidates as $c1) {
            foreach ($this->candidates as $c2) {
                $m = $this->margin($c1, $c2);
                if ($m > 0) $positiveMargins[] = $m;
            }
        }
        sort($positiveMargins);
        if (empty($positiveMargins)) return new MarginGraph($this->candidates, [], $this->cmap);

        $currMargin = $positiveMargins[0];
        $newMarginValue = 2;
        $newWEdges = [];
        foreach ($edges as $e) {
            if ($e[2] > $currMargin) {
                $currMargin = $e[2];
                $newMarginValue += 2;
            }
            $newWEdges[] = [$e[0], $e[1], $newMarginValue];
        }

        return new MarginGraph($this->candidates, $newWEdges, $this->cmap);
    }

    public static function fromProfile(Profile|ProfileWithTies $profile, ?array $cmap = null): MarginGraph
    {
        $wEdges = [];
        foreach ($profile->candidates as $c1) {
            foreach ($profile->candidates as $c2) {
                if ($c1 === $c2) continue;
                $margin = $profile->margin($c1, $c2);
                if ($margin > 0) {
                    $wEdges[] = [$c1, $c2, $margin];
                }
            }
        }
        return new MarginGraph($profile->candidates, $wEdges, $cmap ?? $profile->cmap);
    }
}
