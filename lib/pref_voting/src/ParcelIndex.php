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
 * Track which pieces belong to which parcel for last-parcel transfer rules.
 *
 * In some STV variants (like Australian Senate rules), surplus transfers use only the
 * "last parcel" of votes received by a candidate, rather than all votes. This class
 * tracks which pieces arrived in which order so the last parcel can be identified.
 *
 * A "parcel" is a group of pieces that arrived together during a single transfer operation.
 */
class ParcelIndex
{
    /** @var array<int|string, int[]> Map from candidate to list of piece indices */
    private array $last = [];

    public function startNewParcel($cand): void
    {
        $this->last[$cand] = [];
    }

    public function noteArrival($cand, int $pieceIdx): void
    {
        if (!isset($this->last[$cand])) {
            $this->last[$cand] = [];
        }
        $this->last[$cand][] = $pieceIdx;
    }

    public function lastParcel($cand): array
    {
        return $this->last[$cand] ?? [];
    }

    public function clearParcel($cand): void
    {
        $this->last[$cand] = [];
    }

    /**
     * @param array<int, int> $mapping
     */
    public function remapIndices(array $mapping): void
    {
        if (empty($mapping)) {
            return;
        }
        foreach ($this->last as $cand => $idxs) {
            $remapped = [];
            foreach ($idxs as $idx) {
                if (isset($mapping[$idx])) {
                    $remapped[] = $mapping[$idx];
                }
            }
            $this->last[$cand] = $remapped;
        }
    }
}
