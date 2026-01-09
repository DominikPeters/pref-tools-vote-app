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
 * Base class for voting methods.
 *
 * A voting method takes a Profile (or ProfileWithTies) and optional curr_cands,
 * and returns a sorted list of winning candidates.
 */
class VotingMethod
{
    /** @var string Name of the voting method */
    public string $name;

    /** @var callable The voting function */
    private $vm;

    /**
     * @param callable $vm Function(Profile|ProfileWithTies|GradeProfile|UtilityProfile, ?array $currCands): array
     * @param string $name Human-readable name
     */
    public function __construct(callable $vm, string $name)
    {
        $this->vm = $vm;
        $this->name = $name;
    }

    /**
     * Execute the voting method.
     *
     * @param Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata Election data
     * @param array<int|string>|null $currCands Candidates to consider
     * @return array<int|string> Sorted list of winners
     */
    public function __invoke(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): array
    {
        if (($currCands !== null && empty($currCands)) || empty($edata->candidates)) {
            return [];
        }
        return ($this->vm)($edata, $currCands);
    }

    /**
     * Execute the voting method (alias for __invoke).
     */
    public function call(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): array
    {
        return $this($edata, $currCands);
    }

    /**
     * Choose a random winner from the winning set.
     *
     * @return int|string|null
     */
    public function choose(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): int|string|null
    {
        $winners = $this($edata, $currCands);
        if (empty($winners)) {
            return null;
        }
        return $winners[array_rand($winners)];
    }

    /**
     * Return probability distribution for even-chance tiebreaking.
     *
     * @return array<int|string, float>
     */
    public function prob(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): array
    {
        $winners = $this($edata, $currCands);
        $prob = [];
        foreach ($edata->candidates as $c) {
            $prob[$c] = in_array($c, $winners, true) ? 1.0 / count($winners) : 0.0;
        }
        return $prob;
    }

    /**
     * Display the winning set.
     */
    public function display(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null, ?array $cmap = null): void
    {
        $cmap = $cmap ?? $edata->cmap;
        $winners = $this($edata, $currCands);

        if (empty($winners)) {
            echo "{$this->name} winning set is empty\n";
        } else {
            $label = count($winners) === 1 ? "{$this->name} winner is" : "{$this->name} winners are";
            $names = array_map(fn($c) => $cmap[$c], $winners);
            echo "$label {" . implode(", ", $names) . "}\n";
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

/**
 * Create a voting method with a decorator-style API.
 *
 * @param callable $fn The voting function
 * @param string $name Method name
 * @return VotingMethod
 */
function vm(callable $fn, string $name): VotingMethod
{
    return new VotingMethod($fn, $name);
}
