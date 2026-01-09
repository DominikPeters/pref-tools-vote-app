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
 * A piece represents a fractional portion of a voter's ballot weight allocated to a specific candidate.
 *
 * In STV (Single Transferable Vote), when candidates receive surplus votes above the quota or when
 * candidates are eliminated, ballot weights must be transferred to other candidates according to
 * voter preferences. Rather than transferring whole ballots, the system creates "pieces", which are fractional
 * portions of ballot weight that can be allocated independently.
 */
class RankingPiece
{
    /** @var Ranking The original voter's preference ranking */
    public Ranking $ranking;

    /** @var float The fractional weight of this piece (0.0 to 1.0) */
    public float $weight;

    /** @var int The preference level this piece is currently at */
    public int $currentRank;

    /** @var int|string The candidate this piece is currently allocated to */
    public $cand;

    /** @var float The value credited to this candidate when this piece ARRIVED */
    public float $arrivedValue;

    public function __construct(Ranking $ranking, float $weight, int $currentRank, $cand, ?float $arrivedValue = null)
    {
        $this->ranking = $ranking;
        $this->weight = $weight;
        $this->currentRank = $currentRank;
        $this->cand = $cand;
        $this->arrivedValue = $arrivedValue ?? $weight;
    }

    /**
     * When a piece moves to a new candidate, its arrival value at that candidate is the amount moved.
     */
    public function cloneTo($cand, int $newRank, float $weight): RankingPiece
    {
        return new RankingPiece($this->ranking, $weight, $newRank, $cand, $weight);
    }
}
