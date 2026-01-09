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
use PrefVoting\MajorityGraph;
use PrefVoting\Profile;

class MajorityGraphTest extends TestCase
{
    private MajorityGraph $condorcetCycle;
    private MajorityGraph $exampleGraph;
    private MajorityGraph $exampleGraph2;

    protected function setUp(): void
    {
        $this->condorcetCycle = new MajorityGraph([0, 1, 2], [[0, 1], [1, 2], [2, 0]]);
        $this->exampleGraph = new MajorityGraph([0, 1, 2], [[0, 1], [1, 2], [0, 2]]);
        $this->exampleGraph2 = new MajorityGraph([0, 1, 2], [[0, 2], [1, 2]]);
    }

    public function testConstructor(): void
    {
        $mg = new MajorityGraph(['a', 'b', 'c'], [['a', 'b'], ['b', 'c'], ['c', 'a']]);
        $this->assertEquals(['a', 'b', 'c'], $mg->candidates);
        $this->assertCount(3, $mg->getEdges());
        $this->assertEquals(3, $mg->numCands);
        
        $expectedMatrix = [
            [false, true, false],
            [false, false, true],
            [true, false, false]
        ];
        $this->assertEquals($expectedMatrix, $mg->majMatrix);
    }

    public function testMargin(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("margin is not implemented for majority graphs");
        $this->condorcetCycle->margin(0, 1);
    }

    public function testSupport(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("support is not implemented for majority graphs");
        $this->condorcetCycle->support(0, 1);
    }

    public function testRatio(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("ratio is not implemented for majority graphs");
        $this->condorcetCycle->ratio(0, 1);
    }

    public function testEdgesProperty(): void
    {
        $edges1 = $this->condorcetCycle->getEdges();
        usort($edges1, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $this->assertEquals([[0, 1], [1, 2], [2, 0]], $edges1);

        $edges2 = $this->exampleGraph->getEdges();
        usort($edges2, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $this->assertEquals([[0, 1], [0, 2], [1, 2]], $edges2);

        $edges3 = $this->exampleGraph2->getEdges();
        usort($edges3, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $this->assertEquals([[0, 2], [1, 2]], $edges3);
    }

    public function testIsTournamentProperty(): void
    {
        $this->assertTrue($this->condorcetCycle->isTournament());
        $this->assertTrue($this->exampleGraph->isTournament());
        $this->assertFalse($this->exampleGraph2->isTournament());
    }

    public function testMajorityPrefers(): void
    {
        $this->assertTrue($this->condorcetCycle->majorityPrefers(0, 1));
        $this->assertFalse($this->condorcetCycle->majorityPrefers(1, 0));
        $this->assertFalse($this->condorcetCycle->majorityPrefers(0, 2));
        $this->assertTrue($this->condorcetCycle->majorityPrefers(2, 0));
        $this->assertFalse($this->condorcetCycle->majorityPrefers(2, 1));
        $this->assertTrue($this->condorcetCycle->majorityPrefers(1, 2));
        
        $this->assertTrue($this->exampleGraph->majorityPrefers(0, 1));
        $this->assertFalse($this->exampleGraph->majorityPrefers(1, 0));
        
        $this->assertFalse($this->exampleGraph2->majorityPrefers(0, 1));
        $this->assertFalse($this->exampleGraph2->majorityPrefers(1, 0));
    }

    public function testIsTied(): void
    {
        $this->assertFalse($this->condorcetCycle->isTied(0, 1));
        $this->assertFalse($this->condorcetCycle->isTied(1, 0));
        $this->assertFalse($this->exampleGraph->isTied(0, 1));
        $this->assertFalse($this->exampleGraph->isTied(1, 0));
        $this->assertTrue($this->exampleGraph2->isTied(0, 1));
        $this->assertTrue($this->exampleGraph2->isTied(1, 0));
    }

    public function testCopelandScores(): void
    {
        $this->assertEquals([0 => 0.0, 1 => 0.0, 2 => 0.0], $this->condorcetCycle->copelandScores());
        $this->assertEquals([0 => -1.0, 2 => 1.0], $this->condorcetCycle->copelandScores([0, 2]));
        $this->assertEquals([0 => 2.0, 1 => 0.0, 2 => -2.0], $this->exampleGraph->copelandScores());
        $this->assertEquals([0 => 1.0, 1 => 1.0, 2 => -2.0], $this->exampleGraph2->copelandScores());
        $this->assertEquals([0 => 0.0, 1 => 0.0], $this->exampleGraph2->copelandScores([0, 1]));
    }

    public function testDominators(): void
    {
        $this->assertEqualsCanonicalizing([2], $this->condorcetCycle->dominators(0));
        $this->assertEqualsCanonicalizing([0], $this->condorcetCycle->dominators(1));
        $this->assertEqualsCanonicalizing([1], $this->condorcetCycle->dominators(2));
        
        $this->assertEqualsCanonicalizing([], $this->exampleGraph->dominators(0));
        $this->assertEqualsCanonicalizing([0], $this->exampleGraph->dominators(1));
        $this->assertEqualsCanonicalizing([0, 1], $this->exampleGraph->dominators(2));

        $this->assertEqualsCanonicalizing([], $this->exampleGraph2->dominators(0));
        $this->assertEqualsCanonicalizing([], $this->exampleGraph2->dominators(1));
        $this->assertEqualsCanonicalizing([0, 1], $this->exampleGraph2->dominators(2));
    }

    public function testDominates(): void
    {
        $this->assertEqualsCanonicalizing([1], $this->condorcetCycle->dominates(0));
        $this->assertEqualsCanonicalizing([2], $this->condorcetCycle->dominates(1));
        $this->assertEqualsCanonicalizing([0], $this->condorcetCycle->dominates(2));
        
        $this->assertEqualsCanonicalizing([1, 2], $this->exampleGraph->dominates(0));
        $this->assertEqualsCanonicalizing([2], $this->exampleGraph->dominates(1));
        $this->assertEqualsCanonicalizing([], $this->exampleGraph->dominates(2));

        $this->assertEqualsCanonicalizing([2], $this->exampleGraph2->dominates(0));
        $this->assertEqualsCanonicalizing([2], $this->exampleGraph2->dominates(1));
        $this->assertEqualsCanonicalizing([], $this->exampleGraph2->dominates(2));
    }

    public function testCondorcetWinner(): void
    {
        $this->assertNull($this->condorcetCycle->condorcetWinner());
        $this->assertEquals(1, $this->condorcetCycle->condorcetWinner([1, 2]));
        $this->assertEquals(0, $this->condorcetCycle->condorcetWinner([0]));
        
        $this->assertEquals(0, $this->exampleGraph->condorcetWinner());
        $this->assertEquals(1, $this->exampleGraph->condorcetWinner([1, 2]));
        $this->assertEquals(0, $this->exampleGraph->condorcetWinner([0]));

        $this->assertNull($this->exampleGraph2->condorcetWinner());
        $this->assertEquals(1, $this->exampleGraph2->condorcetWinner([1, 2]));
        $this->assertNull($this->exampleGraph2->condorcetWinner([0, 1]));
        $this->assertEquals(0, $this->exampleGraph2->condorcetWinner([0]));
    }

    public function testWeakCondorcetWinner(): void
    {
        $this->assertNull($this->condorcetCycle->weakCondorcetWinner());
        $this->assertEquals([1], $this->condorcetCycle->weakCondorcetWinner([1, 2]));
        $this->assertEquals([0], $this->condorcetCycle->weakCondorcetWinner([0]));
        
        $this->assertEquals([0], $this->exampleGraph->weakCondorcetWinner());
        $this->assertEquals([1], $this->exampleGraph->weakCondorcetWinner([1, 2]));
        $this->assertEquals([0], $this->exampleGraph->weakCondorcetWinner([0]));

        $this->assertEquals([0, 1], $this->exampleGraph2->weakCondorcetWinner());
        $this->assertEquals([1], $this->exampleGraph2->weakCondorcetWinner([1, 2]));
        $this->assertEquals([0, 1], $this->exampleGraph2->weakCondorcetWinner([0, 1]));
        $this->assertEquals([0], $this->exampleGraph2->weakCondorcetWinner([0]));
    }

    public function testCondorcetLoser(): void
    {
        $this->assertNull($this->condorcetCycle->condorcetLoser());
        $this->assertEquals(2, $this->condorcetCycle->condorcetLoser([1, 2]));
        $this->assertEquals(0, $this->condorcetCycle->condorcetLoser([0]));
        
        $this->assertEquals(2, $this->exampleGraph->condorcetLoser());
        $this->assertEquals(2, $this->exampleGraph->condorcetLoser([1, 2]));
        $this->assertEquals(0, $this->exampleGraph->condorcetLoser([0]));

        $this->assertEquals(2, $this->exampleGraph2->condorcetLoser());
        $this->assertEquals(2, $this->exampleGraph2->condorcetLoser([1, 2]));
        $this->assertNull($this->exampleGraph2->condorcetLoser([0, 1]));
        $this->assertEquals(0, $this->exampleGraph2->condorcetLoser([0]));
    }

    public function testCycles(): void
    {
        $this->assertEquals([[0, 1, 2]], $this->condorcetCycle->cycles());
        $this->assertEquals([], $this->condorcetCycle->cycles([1, 2]));
        $this->assertEquals([], $this->condorcetCycle->cycles([0]));
        
        $this->assertEquals([], $this->exampleGraph->cycles());
        $this->assertEquals([], $this->exampleGraph->cycles([1, 2]));
        $this->assertEquals([], $this->exampleGraph->cycles([0]));

        $this->assertEquals([], $this->exampleGraph2->cycles());
        $this->assertEquals([], $this->exampleGraph2->cycles([1, 2]));
        $this->assertEquals([], $this->exampleGraph2->cycles([0, 1]));
        $this->assertEquals([], $this->exampleGraph2->cycles([0]));
    }

    public function testHasCycle(): void
    {
        $this->assertTrue($this->condorcetCycle->hasCycle());
        $this->assertFalse($this->condorcetCycle->hasCycle([1, 2]));
        $this->assertFalse($this->condorcetCycle->hasCycle([0]));
        
        $this->assertFalse($this->exampleGraph->hasCycle());
        $this->assertFalse($this->exampleGraph->hasCycle([1, 2]));
        $this->assertFalse($this->exampleGraph->hasCycle([0]));

        $this->assertFalse($this->exampleGraph2->hasCycle());
        $this->assertFalse($this->exampleGraph2->hasCycle([1, 2]));
        $this->assertFalse($this->exampleGraph2->hasCycle([0, 1]));
        $this->assertFalse($this->exampleGraph2->hasCycle([0]));
    }

    public function testRemoveCandidates(): void
    {
        $mg = $this->condorcetCycle->removeCandidates([1]);
        $edges = $mg->getEdges();
        $this->assertCount(1, $edges);
        $this->assertEquals([2, 0], $edges[0]);

        $mg2 = $this->condorcetCycle->removeCandidates([0]);
        $edges2 = $mg2->getEdges();
        $this->assertCount(1, $edges2);
        $this->assertEquals([1, 2], $edges2[0]);
    }

    public function testFromProfile(): void
    {
        $prof = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]]);
        $mg = MajorityGraph::fromProfile($prof);
        $this->assertTrue($mg->equals($this->condorcetCycle));
    }

    public function testAdd(): void
    {
        $mg1 = new MajorityGraph([0, 1], [[0, 1]]);
        $mg2 = new MajorityGraph([0, 2], [[2, 0]]);
        $mg3 = new MajorityGraph([1, 2], [[1, 2]]);

        $mg = $mg1->add($mg2)->add($mg3);
        $this->assertEqualsCanonicalizing([0, 1, 2], $mg->candidates);
        $this->assertTrue($mg->equals($this->condorcetCycle));
    }

    public function testEq(): void
    {
        $mg = new MajorityGraph([0, 1, 2], [[2, 0], [1, 2], [0, 1]]);
        $this->assertTrue($mg->equals($this->condorcetCycle));
    }
}
