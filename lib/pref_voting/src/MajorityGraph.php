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
 * A majority graph is an asymmetric directed graph. The nodes are the candidates 
 * and an edge from candidate c to d means that c is majority preferred to d.
 */
class MajorityGraph
{
    /** @var array<int|string> List of the candidates. */
    public array $candidates;

    /** @var int Number of candidates. */
    public int $numCands;

    /** @var array<int|string, string> Candidate name map. */
    public array $cmap;

    /** @var array<int|string, array<int|string>> Adjacency list: c1 => [c2, ...] where c1 -> c2 */
    protected array $adj;

    /** @var array<int|string, int> Candidate to index map */
    protected array $candToCindex;

    /** @var array<int, int|string> Index to candidate map */
    protected array $cindexToCand;

    /** @var bool[][] Majority matrix: [c1_idx][c2_idx] is true if c1 -> c2 */
    public array $majMatrix;

    /**
     * @param array<int|string> $candidates List of candidates
     * @param array<array{0: int|string, 1: int|string}> $edges List of (c1, c2) edges
     * @param array<int|string, string>|null $cmap Candidate name map
     */
    public function __construct(array $candidates, array $edges, ?array $cmap = null)
    {
        $this->candidates = array_values($candidates);
        $this->numCands = count($this->candidates);
        $this->cmap = $cmap ?? array_combine(
            $this->candidates,
            array_map('strval', $this->candidates)
        );

        $this->candToCindex = [];
        $this->cindexToCand = [];
        foreach ($this->candidates as $idx => $cand) {
            $this->candToCindex[$cand] = $idx;
            $this->cindexToCand[$idx] = $cand;
        }

        $this->adj = [];
        foreach ($this->candidates as $cand) {
            $this->adj[$cand] = [];
        }

        $this->majMatrix = array_fill(0, $this->numCands, array_fill(0, $this->numCands, false));

        foreach ($edges as $edge) {
            $c1 = $edge[0];
            $c2 = $edge[1];
            if (isset($this->adj[$c1]) && isset($this->adj[$c2])) {
                $this->adj[$c1][] = $c2;
                $this->majMatrix[$this->candToCindex[$c1]][$this->candToCindex[$c2]] = true;
            }
        }
    }

    public function margin(int|string $c1, int|string $c2): int|float
    {
        throw new \Exception("margin is not implemented for majority graphs.");
    }

    public function support(int|string $c1, int|string $c2): int
    {
        throw new \Exception("support is not implemented for majority graphs.");
    }

    public function ratio(int|string $c1, int|string $c2): float
    {
        throw new \Exception("ratio is not implemented for majority graphs.");
    }

    /**
     * Returns a list of the edges in the majority graph.
     * @return array<array{0: int|string, 1: int|string}>
     */
    public function getEdges(): array
    {
        $edges = [];
        foreach ($this->adj as $c1 => $targets) {
            foreach ($targets as $c2) {
                $edges[] = [$c1, $c2];
            }
        }
        return $edges;
    }

    /**
     * Returns True if the majority graph is a tournament.
     */
    public function isTournament(): bool
    {
        foreach ($this->candidates as $c1) {
            foreach ($this->candidates as $c2) {
                if ($c1 === $c2) continue;
                if (!$this->majorityPrefers($c1, $c2) && !$this->majorityPrefers($c2, $c1)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns true if there is an edge from c1 to c2.
     */
    public function majorityPrefers(int|string $c1, int|string $c2): bool
    {
        return isset($this->candToCindex[$c1]) && 
               isset($this->candToCindex[$c2]) && 
               $this->majMatrix[$this->candToCindex[$c1]][$this->candToCindex[$c2]];
    }

    /**
     * Returns true if there is no edge from c1 to c2 or from c2 to c1.
     */
    public function isTied(int|string $c1, int|string $c2): bool
    {
        return !$this->majorityPrefers($c1, $c2) && !$this->majorityPrefers($c2, $c1);
    }

    /**
     * Returns the Copeland scores.
     * @param array<int|string>|null $currCands
     * @param array{0: float, 1: float, 2: float} $scores
     * @return array<int|string, float>
     */
    public function copelandScores(?array $currCands = null, array $scores = [1, 0, -1]): array
    {
        $candidates = $currCands ?? $this->candidates;
        [$wscore, $tscore, $lscore] = $scores;
        $cScores = [];
        foreach ($candidates as $c) $cScores[$c] = 0.0;

        foreach ($candidates as $c1) {
            foreach ($candidates as $c2) {
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
     * Returns the list of candidates that are majority preferred to cand.
     * @param array<int|string>|null $currCands
     * @return array<int|string>
     */
    public function dominators(int|string $cand, ?array $currCands = null): array
    {
        $candidates = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $candidates,
            fn($c) => $this->majorityPrefers($c, $cand)
        ));
    }

    /**
     * Returns the list of candidates that cand is majority preferred to.
     * @param array<int|string>|null $currCands
     * @return array<int|string>
     */
    public function dominates(int|string $cand, ?array $currCands = null): array
    {
        $candidates = $currCands ?? $this->candidates;
        return array_values(array_filter(
            $candidates,
            fn($c) => $this->majorityPrefers($cand, $c)
        ));
    }

    /**
     * Returns the Condorcet winner if one exists, null otherwise.
     * @param array<int|string>|null $currCands
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
            if ($beatsAll) return $c1;
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
            if ($notBeaten) $weakCw[] = $c1;
        }
        if (empty($weakCw)) return null;
        sort($weakCw);
        return $weakCw;
    }

    /**
     * Returns the Condorcet loser if one exists.
     * @param array<int|string>|null $currCands
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
            if ($losesToAll) return $c1;
        }
        return null;
    }

    /**
     * Returns all simple cycles in the graph.
     * Implements a simple backtracking algorithm.
     * @param array<int|string>|null $currCands
     * @return array<array<int|string>>
     */
    public function cycles(?array $currCands = null): array
    {
        $candidates = $currCands ?? $this->candidates;
        $cycles = [];
        $path = [];
        $visited = [];

        $findCycles = function ($v, $start) use (&$findCycles, &$cycles, &$path, &$visited, $candidates) {
            $visited[$v] = true;
            $path[] = $v;

            foreach ($this->dominates($v, $candidates) as $neighbor) {
                if ($neighbor === $start) {
                    $cycles[] = $path;
                } elseif (!isset($visited[$neighbor])) {
                    $findCycles($neighbor, $start);
                }
            }

            array_pop($path);
            unset($visited[$v]);
        };

        // Note: this is a simple cycle finding, not as efficient as Johnson's
        // but sufficient for small number of candidates.
        // To avoid permutations of the same cycle, we only start from each node
        // and ensure we only visit nodes "greater" than it (if we want unique cycles).
        // Python's networkx.simple_cycles returns each cycle once.
        
        $allCycles = [];
        for ($i = 0; $i < count($candidates); $i++) {
            $startNode = $candidates[$i];
            $subCandidates = array_slice($candidates, $i);
            
            $tempCycles = [];
            $path = [];
            $visited = [];

            $innerFind = function ($v) use (&$innerFind, &$tempCycles, &$path, &$visited, $startNode, $subCandidates) {
                $visited[$v] = true;
                $path[] = $v;

                foreach ($this->dominates($v, $subCandidates) as $neighbor) {
                    if ($neighbor === $startNode) {
                        if (count($path) >= 3 || (count($path) == 2 && $this->majorityPrefers($neighbor, $path[1]))) {
                             // networkx simple_cycles returns [0, 1, 2] for 0->1->2->0
                             $tempCycles[] = $path;
                        } elseif (count($path) == 2) {
                             // for 2-cycles, it would be [0, 1]
                             $tempCycles[] = $path;
                        }
                    } elseif (!isset($visited[$neighbor])) {
                        $innerFind($neighbor);
                    }
                }
                array_pop($path);
                unset($visited[$v]);
            };

            $innerFind($startNode);
            foreach ($tempCycles as $c) $allCycles[] = $c;
        }

        return $allCycles;
    }

    /**
     * Returns true if there is a cycle in the majority graph.
     */
    public function hasCycle(?array $currCands = null): bool
    {
        $candidates = $currCands ?? $this->candidates;
        if (empty($candidates)) return false;

        $visited = [];
        $stack = [];

        foreach ($candidates as $cand) {
            if (!isset($visited[$cand])) {
                if ($this->checkCycleDFS($cand, $candidates, $visited, $stack)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function checkCycleDFS(int|string $v, array $currCands, array &$visited, array &$stack): bool
    {
        $visited[$v] = true;
        $stack[$v] = true;

        foreach ($this->dominates($v, $currCands) as $neighbor) {
            if (!isset($visited[$neighbor])) {
                if ($this->checkCycleDFS($neighbor, $currCands, $visited, $stack)) {
                    return true;
                }
            } elseif (isset($stack[$neighbor])) {
                return true;
            }
        }

        unset($stack[$v]);
        return false;
    }

    /**
     * Remove all candidates from candsToIgnore.
     */
    public function removeCandidates(array $candsToIgnore): MajorityGraph
    {
        $newCands = array_values(array_filter($this->candidates, fn($c) => !in_array($c, $candsToIgnore, true)));
        $newEdges = [];
        foreach ($this->getEdges() as $edge) {
            if (in_array($edge[0], $newCands, true) && in_array($edge[1], $newCands, true)) {
                $newEdges[] = $edge;
            }
        }
        $newCmap = array_filter($this->cmap, fn($c) => in_array($c, $newCands, true), ARRAY_FILTER_USE_KEY);
        return new MajorityGraph($newCands, $newEdges, $newCmap);
    }

    public function description(): string
    {
        return sprintf("MajorityGraph(%s, %s)", json_encode($this->candidates), json_encode($this->getEdges()));
    }

    public function equals(MajorityGraph $other): bool
    {
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

    public function add(MajorityGraph|Profile|ProfileWithTies $other): MajorityGraph
    {
        $otherCands = ($other instanceof MajorityGraph) ? $other->candidates : $other->candidates;
        $newCands = array_values(array_unique(array_merge($this->candidates, $otherCands)));
        $allEdges = array_merge($this->getEdges(), $other->getEdges());
        // Deduplicate edges
        $uniqueEdges = [];
        foreach ($allEdges as $e) {
            $key = $e[0] . '|' . $e[1];
            $uniqueEdges[$key] = $e;
        }

        $newEdges = [];
        foreach ($uniqueEdges as $e) {
            $c1 = $e[0];
            $c2 = $e[1];
            if (($this->majorityPrefers($c1, $c2) && $other->majorityPrefers($c2, $c1)) ||
                ($this->majorityPrefers($c2, $c1) && $other->majorityPrefers($c1, $c2))) {
                continue;
            }
            $newEdges[] = $e;
        }

        return new MajorityGraph($newCands, $newEdges);
    }

    public function __toString(): string
    {
        return $this->description();
    }

    public static function fromProfile(Profile|ProfileWithTies $profile, ?array $cmap = null): MajorityGraph
    {
        $edges = [];
        foreach ($profile->candidates as $c1) {
            foreach ($profile->candidates as $c2) {
                if ($c1 === $c2) continue;
                if ($profile->majorityPrefers($c1, $c2)) {
                    $edges[] = [$c1, $c2];
                }
            }
        }
        return new MajorityGraph($profile->candidates, $edges, $cmap ?? $profile->cmap);
    }
}
