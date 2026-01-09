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
 * Base class for Social Welfare Functions (SWFs).
 *
 * A social welfare function takes a Profile (or ProfileWithTies) and optional
 * curr_cands, and returns an array of Ranking objects of the candidates.
 */
class SocialWelfareFunction
{
    /** @var string Name of the SWF */
    public string $name;

    /** @var callable The SWF function */
    private $swf;

    /**
     * @param callable $swf Function(Profile|ProfileWithTies|GradeProfile|UtilityProfile, ?array $currCands): array<Ranking>
     * @param string $name Human-readable name
     */
    public function __construct(callable $swf, string $name)
    {
        $this->swf = $swf;
        $this->name = $name;
    }

    /**
     * Execute the SWF.
     *
     * @param Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata Election data
     * @param array<int|string>|null $currCands Candidates to consider
     * @return array<Ranking> Array of rankings
     */
    public function __invoke(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): array
    {
        return ($this->swf)($edata, $currCands);
    }

    /**
     * Returns a sorted list of the first place candidates (union of first place in all rankings).
     */
    public function winners(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): array
    {
        $rankings = $this($edata, $currCands);
        $winners = [];
        foreach ($rankings as $ranking) {
            foreach ($ranking->first() as $winner) {
                $winners[] = $winner;
            }
        }
        $winners = array_unique($winners);
        sort($winners);
        return $winners;
    }

    /**
     * Display the result of the social welfare function.
     */
    public function display(Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph|GradeProfile|UtilityProfile $edata, ?array $currCands = null): void
    {
        $rankings = $this($edata, $currCands);
        if (count($rankings) === 1) {
            echo "{$this->name} ranking is {$rankings[0]}\n";
        } else {
            echo "{$this->name} rankings are:\n";
            foreach ($rankings as $ranking) {
                echo "  {$ranking}\n";
            }
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

/**
 * Create an SWF with a decorator-style API.
 */
function swf(callable $fn, string $name): SocialWelfareFunction
{
    return new SocialWelfareFunction($fn, $name);
}
