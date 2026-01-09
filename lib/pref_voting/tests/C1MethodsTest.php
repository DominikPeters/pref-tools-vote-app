<?php

/**
 * This file is based on a translation of the pref_voting python package
 * (https://github.com/voting-tools/pref_voting/)
 * Copyright (c) 2024 Wes Holliday and Eric Pacuit, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace PrefVoting\Tests;

use PHPUnit\Framework\TestCase;
use PrefVoting\Profile;
use PrefVoting\ProfileWithTies;
use PrefVoting\C1Methods;
use PHPUnit\Framework\Attributes\DataProvider;

class C1MethodsTest extends TestCase
{
    private Profile $condorcetCycleProf;
    private ProfileWithTies $condorcetCycleProfWithTies;
    private Profile $profileSingleVoter;

    protected function setUp(): void
    {
        $this->condorcetCycleProf = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]]);
        $this->condorcetCycleProfWithTies = new ProfileWithTies([
            [0 => 1, 1 => 2, 2 => 3], 
            [0 => 2, 1 => 3, 2 => 1], 
            [0 => 3, 1 => 1, 2 => 2]
        ]);
        $this->profileSingleVoter = new Profile([[0, 1, 2]]);
    }

    #[DataProvider('c1MethodsProvider')]
    public function testC1Methods(callable $votingMethod, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected['cycle'], $votingMethod($this->condorcetCycleProf));
        $this->assertEqualsCanonicalizing($expected['cycle_ties'], $votingMethod($this->condorcetCycleProfWithTies));
        $this->assertEqualsCanonicalizing($expected['single'], $votingMethod($this->profileSingleVoter));
    }

    public static function c1MethodsProvider(): array
    {
        return [
            [C1Methods::condorcet(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::copeland(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::llull(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::ucGill(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::ucFish(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::ucBordes(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::ucMcKelvey(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::topCycle(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::gocha(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::banks(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
            [C1Methods::slater(), ['cycle' => [0, 1, 2], 'cycle_ties' => [0, 1, 2], 'single' => [0]]],
        ];
    }

    public function testC1MethodsOnGraphs(): void
    {
        $mg = new \PrefVoting\MajorityGraph([0, 1, 2], [[0, 1], [1, 2], [2, 0]]);
        $this->assertEqualsCanonicalizing([0, 1, 2], C1Methods::condorcet()($mg));
        $this->assertEqualsCanonicalizing([0, 1, 2], C1Methods::copeland()($mg));
        $this->assertEqualsCanonicalizing([0, 1, 2], C1Methods::topCycle()($mg));

        $mg2 = new \PrefVoting\MajorityGraph([0, 1, 2], [[0, 1], [0, 2], [1, 2]]);
        $this->assertEqualsCanonicalizing([0], C1Methods::condorcet()($mg2));
        $this->assertEqualsCanonicalizing([0], C1Methods::copeland()($mg2));
        $this->assertEqualsCanonicalizing([0], C1Methods::topCycle()($mg2));
        
        $this->assertEqualsCanonicalizing([1], C1Methods::condorcet()($mg2, [1, 2]));

        $mrg = new \PrefVoting\MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 5], [2, 0, 3]]);
        $this->assertEqualsCanonicalizing([0, 1, 2], C1Methods::condorcet()($mrg));
        $this->assertEqualsCanonicalizing([0, 1, 2], C1Methods::copeland()($mrg));
    }
}
