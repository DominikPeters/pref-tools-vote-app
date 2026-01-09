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
use PrefVoting\MarginBasedMethods;
use PHPUnit\Framework\Attributes\DataProvider;

class MarginBasedMethodsTest extends TestCase
{
    private Profile $condorcetCycleProf;
    private Profile $cycle;
    private Profile $linearProfile0;
    private ProfileWithTies $condorcetCycleProfWithTies;

    protected function setUp(): void
    {
        // 0>1, 1>2, 2>0 each with margin 1
        $this->condorcetCycleProf = new Profile([
            [0, 1, 2], 
            [1, 2, 0], 
            [2, 0, 1]
        ]);

        // 0>1 margin 1, 1>2 margin 3, 2>0 margin 5
        // (0,1,1), (1,2,3), (2,0,5) in terms of margins
        $this->cycle = new Profile([
            [0, 1, 2], [1, 2, 0], [1, 2, 0], [2, 0, 1], [2, 0, 1], [2, 0, 1]
        ], [1, 1, 1, 1, 1, 1]); 
        
        $this->linearProfile0 = new Profile([
            [0, 1, 2], 
            [0, 1, 2],
            [1, 2, 0]
        ]);
    }

    #[DataProvider('marginMethodsProvider')]
    public function testMarginMethods(callable $votingMethod, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected['cycle'], $votingMethod($this->condorcetCycleProf));
    }

    public static function marginMethodsProvider(): array
    {
        return [
            [MarginBasedMethods::minimax(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::splitCycle(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::beatPath(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::rankedPairs(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::river(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::simpleStableVoting(), ['cycle' => [0, 1, 2]]],
            [MarginBasedMethods::stableVoting(), ['cycle' => [0, 1, 2]]],
        ];
    }

    public function testCycleMargins(): void
    {
        $prof = new Profile([
            [0, 1, 2], [0, 1, 2], [0, 1, 2], // 3 voters 0>1>2
            [1, 2, 0], [1, 2, 0],             // 2 voters 1>2>0
            [2, 0, 1]                         // 1 voter 2>0>1
        ]);
        // s(0,1) = 4, s(1,0) = 2. m(0,1) = 2.
        // s(1,2) = 5, s(2,1) = 1. m(1,2) = 4.
        // s(2,0) = 3, s(0,2) = 3. m(2,0) = 0.
        // CW is 0.
        
        $this->assertEquals([0], MarginBasedMethods::splitCycle()($prof));
        $this->assertEquals([0], MarginBasedMethods::minimax()($prof));
    }

    public function testMarginMethodsOnGraphs(): void
    {
        // MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 3], [2, 0, 5]])
        $graph = new \PrefVoting\MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 3], [2, 0, 5]]);
        
        // Split Cycle: cycle is 0-1-2-0 with margins 1, 3, 5. Min margin is 1 (0->1).
        // Defeats are 1->2 and 2->0. 1 is undefeated.
        $this->assertEquals([1], MarginBasedMethods::splitCycle()($graph));
        
        // Minimax:
        // 0 max loss: 5 (from 2)
        // 1 max loss: 1 (from 0)
        // 2 max loss: 3 (from 1)
        // Winner is 1.
        $this->assertEquals([1], MarginBasedMethods::minimax()($graph));

        // Beat Path:
        // P(0,1)=1, P(0,2)=1
        // P(1,2)=3, P(1,0)=3
        // P(2,0)=5, P(2,1)=1
        // 1 is winner: P(1,0) >= P(0,1) (3>=1) and P(1,2) >= P(2,1) (3>=1).
        $this->assertEquals([1], MarginBasedMethods::beatPath()($graph));

        // Ranked Pairs:
        // Edges: (2,0,5), (1,2,3), (0,1,1)
        // Winners in Python are [1] if using fixed tie-breaking or [1] if no ties.
        // However, the test expectation for the cycle prof above is [0, 1, 2].
        // Let's see what happens here.
        $this->assertEquals([1], MarginBasedMethods::rankedPairs()($graph));
    }
}