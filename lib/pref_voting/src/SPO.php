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
 * A strict partial order class.
 * Maintains transitive closure as pairs are added.
 */
class SPO
{
    public int $n;
    /** @var bool[][] Transitive closure matrix */
    public array $P;
    /** @var int[][] Predecessors of each element */
    public array $preds;
    /** @var int[][] Successors of each element */
    public array $succs;

    public function __construct(int $n)
    {
        $this->n = $n;
        $this->P = array_fill(0, $n, array_fill(0, $n, false));
        $this->preds = array_fill(0, $n, []);
        $this->succs = array_fill(0, $n, []);
    }

    /**
     * Add a > b and all transitive consequences.
     */
    public function add(int $a, int $b): void
    {
        if (!$this->P[$a][$b]) {
            $this->P[$a][$b] = true;
            $this->preds[$b][] = $a;
            $this->succs[$a][] = $b;

            foreach ($this->preds[$a] as $c) {
                $this->register($c, $b);
                foreach ($this->succs[$b] as $d) {
                    $this->register($c, $d);
                }
            }
            foreach ($this->succs[$b] as $d) {
                $this->register($a, $d);
            }
        }
    }

    /**
     * Register a > b without full transitive closure (used internally).
     */
    private function register(int $a, int $b): void
    {
        if (!$this->P[$a][$b]) {
            $this->P[$a][$b] = true;
            $this->preds[$b][] = $a;
            $this->succs[$a][] = $b;
        }
    }

    /**
     * Return elements with no predecessors.
     * @return int[]
     */
    public function initialElements(): array
    {
        $initial = [];
        for ($i = 0; $i < $this->n; $i++) {
            if (empty($this->preds[$i])) {
                $initial[] = $i;
            }
        }
        return $initial;
    }
}
