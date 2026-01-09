<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace AbcVoting;

class Voter
{
    /** @var int[] Approved candidate indices */
    public array $approved;

    /** @var float Weight of the voter */
    public float $weight;

    /**
     * @param int[] $approved
     * @param float $weight
     * @param int|null $numCand
     */
    public function __construct(array $approved, float $weight = 1.0, ?int $numCand = null)
    {
        $this->approved = array_values(array_unique($approved));
        $this->weight = $weight;

        if ($this->weight <= 0) {
            throw new \InvalidArgumentException("Weight should be a number > 0.");
        }

        if ($numCand !== null) {
            foreach ($this->approved as $cand) {
                if ($cand >= $numCand) {
                    throw new \InvalidArgumentException("Candidate index $cand is >= numCand ($numCand).");
                }
            }
        }
    }

    public function __toString(): string
    {
        return Utils::strSetOfCandidates($this->approved);
    }
}
