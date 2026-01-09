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

class Profile
{
    /** @var int Number of candidates */
    public int $numCand;

    /** @var string[] Candidate symbolic names */
    public array $candNames;

    /** @var Voter[] Internal list of voters */
    private array $voters = [];

    /**
     * @param int $numCand
     * @param string[]|null $candNames
     */
    public function __construct(int $numCand, ?array $candNames = null)
    {
        if ($numCand <= 0) {
            throw new \InvalidArgumentException("$numCand is not a valid number of candidates");
        }
        $this->numCand = $numCand;
        $this->candNames = array_map('strval', range(0, $numCand - 1));

        if ($candNames !== null) {
            if (count($candNames) < $numCand) {
                throw new \InvalidArgumentException("candNames has length " . count($candNames) . " < numCand ($numCand)");
            }
            $this->candNames = array_map('strval', array_slice($candNames, 0, $numCand));
        }
    }

    public function addVoter($voter): void
    {
        if ($voter instanceof Voter) {
            $this->voters[] = new Voter($voter->approved, $voter->weight, $this->numCand);
        } else {
            $this->voters[] = new Voter((array)$voter, 1.0, $this->numCand);
        }
    }

    /**
     * @param iterable $voters
     */
    public function addVoters(iterable $voters): void
    {
        foreach ($voters as $voter) {
            $this->addVoter($voter);
        }
    }

    public function getVoters(): array
    {
        return $this->voters;
    }

    public function count(): int
    {
        return count($this->voters);
    }

    public function totalWeight(): float
    {
        $total = 0.0;
        foreach ($this->voters as $voter) {
            $total += $voter->weight;
        }
        return $total;
    }

    public function hasUnitWeights(): bool
    {
        foreach ($this->voters as $voter) {
            if ($voter->weight != 1.0) {
                return false;
            }
        }
        return true;
    }

    public function __toString(): string
    {
        $unit = $this->hasUnitWeights();
        $output = ($unit ? "" : "weighted ") . "profile with " . count($this->voters) . " voters and {" . $this->numCand . "} candidates:\n";
        foreach ($this->voters as $i => $voter) {
            $output .= " voter $i:   ";
            if (!$unit) {
                $output .= "{$voter->weight} * ";
            }
            $output .= Utils::strSetOfCandidates($voter->approved, $this->candNames) . ",\n";
        }
        return rtrim($output, ",\n");
    }
}
