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
use PrefVoting\MarginGraph;
use PrefVoting\Profile;

class MarginGraphTest extends TestCase
{
    private MarginGraph $simpleMarginGraph;
    private MarginGraph $simpleMarginGraph2;

    protected function setUp(): void
    {
        $this->simpleMarginGraph = new MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 3], [2, 0, 5]]);
        $this->simpleMarginGraph2 = new MarginGraph(['a', 'b', 'c'], [['a', 'b', 1], ['b', 'c', 3], ['c', 'a', 5]]);
    }

    public function testMarginGraphInit(): void
    {
        $this->assertCount(3, $this->simpleMarginGraph->candidates);
        $this->assertEquals([0, 1, 2], $this->simpleMarginGraph->candidates);

        $this->assertCount(3, $this->simpleMarginGraph2->candidates);
        $this->assertEquals(['a', 'b', 'c'], $this->simpleMarginGraph2->candidates);
    }

    public function testMargin(): void
    {
        $this->assertEquals(1, $this->simpleMarginGraph->margin(0, 1));
        $this->assertEquals(3, $this->simpleMarginGraph->margin(1, 2));
        $this->assertEquals(5, $this->simpleMarginGraph->margin(2, 0));
        $this->assertEquals(-1, $this->simpleMarginGraph->margin(1, 0));
    }

    public function testStrengthMatrix(): void
    {
        [$sMatrix, $candToIdx] = $this->simpleMarginGraph->strengthMatrix();
        $expectedMatrix = [[0, 1, -5], [-1, 0, 3], [5, -3, 0]];
        $this->assertEquals($expectedMatrix, $sMatrix);
        $this->assertEquals(0, $candToIdx[0]);
        $this->assertEquals(1, $candToIdx[1]);
        $this->assertEquals(2, $candToIdx[2]);

        [$sMatrix2, $candToIdx2] = $this->simpleMarginGraph2->strengthMatrix();
        $this->assertEquals($expectedMatrix, $sMatrix2);
        $this->assertEquals(0, $candToIdx2['a']);
        $this->assertEquals(1, $candToIdx2['b']);
        $this->assertEquals(2, $candToIdx2['c']);

        [$sMatrix3, $candToIdx3] = $this->simpleMarginGraph2->strengthMatrix(['a', 'c']);
        $expectedMatrix3 = [[0, -5], [5, 0]];
        $this->assertEquals($expectedMatrix3, $sMatrix3);
        $this->assertEquals(0, $candToIdx3['a']);
        $this->assertEquals(1, $candToIdx3['c']);
    }

    public function testEdgesProperty(): void
    {
        $expectedEdges = [[0, 1, 1], [1, 2, 3], [2, 0, 5]];
        $edges = $this->simpleMarginGraph->getEdges();
        usort($edges, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $this->assertEquals($expectedEdges, $edges);
    }

    public function testRemoveCandidates(): void
    {
        $newGraph = $this->simpleMarginGraph->removeCandidates([1]);
        $this->assertNotContains(1, $newGraph->candidates);
        $edges = $newGraph->getEdges();
        $this->assertCount(1, $edges);
        $this->assertEquals([2, 0, 5], $edges[0]);
    }

    public function testMajorityPrefers(): void
    {
        $this->assertTrue($this->simpleMarginGraph->majorityPrefers(0, 1));
        $this->assertFalse($this->simpleMarginGraph->majorityPrefers(1, 0));
        $this->assertTrue($this->simpleMarginGraph->majorityPrefers(1, 2));
        $this->assertFalse($this->simpleMarginGraph->majorityPrefers(2, 1));
        $this->assertFalse($this->simpleMarginGraph->majorityPrefers(0, 2));
        $this->assertTrue($this->simpleMarginGraph->majorityPrefers(2, 0));
    }

    public function testIsTied(): void
    {
        $this->assertFalse($this->simpleMarginGraph->isTied(0, 1));
        $this->assertFalse($this->simpleMarginGraph->isTied(1, 0));
        $this->assertFalse($this->simpleMarginGraph->isTied(1, 2));
        $this->assertTrue($this->simpleMarginGraph->isTied(0, 0));
        
        $mg = new MarginGraph([0, 1, 2], [[0, 2, 1]]);
        $this->assertTrue($mg->isTied(1, 0));
        $this->assertTrue($mg->isTied(0, 1));
        $this->assertFalse($mg->isTied(2, 0));
        $this->assertFalse($mg->isTied(0, 2));
    }

    public function testIsUniquelyWeighted(): void
    {
        $mg = new MarginGraph([0, 1, 2], [[0, 2, 1]]);
        $this->assertFalse($mg->isUniquelyWeighted());
        $this->assertTrue($this->simpleMarginGraph->isUniquelyWeighted());
    }

    public function testNormalizeOrderedWeights(): void
    {
        $mg = new MarginGraph([0, 1, 2], [[0, 1, 5], [1, 2, 7], [2, 0, 11]]);
        $normalizedGraph = $mg->normalizeOrderedWeights();
        $this->assertInstanceOf(MarginGraph::class, $normalizedGraph);
        $this->assertEquals($mg->candidates, $normalizedGraph->candidates);
        $this->assertEquals(2, $normalizedGraph->margin(0, 1));
        $this->assertEquals(-2, $normalizedGraph->margin(1, 0));
        $this->assertEquals(4, $normalizedGraph->margin(1, 2));
        $this->assertEquals(-4, $normalizedGraph->margin(2, 1));
        $this->assertEquals(-6, $normalizedGraph->margin(0, 2));
        $this->assertEquals(6, $normalizedGraph->margin(2, 0));

        $mg2 = new MarginGraph([0, 1, 2], [[0, 1, 20], [2, 1, 10]]);
        $normalizedGraph2 = $mg2->normalizeOrderedWeights();
        $this->assertEquals(4, $normalizedGraph2->margin(0, 1));
        $this->assertEquals(2, $normalizedGraph2->margin(2, 1));
        $this->assertEquals(0, $normalizedGraph2->margin(0, 2));
    }

    public function testFromProfile(): void
    {
        $prof = new Profile([[0, 1, 2], [1, 0, 2], [2, 0, 1]]);
        $mg = MarginGraph::fromProfile($prof);
        $this->assertInstanceOf(MarginGraph::class, $mg);
        $this->assertEqualsCanonicalizing([0, 1, 2], $mg->candidates);
        $this->assertEquals($prof->margin(0, 1), $mg->margin(0, 1));
        $this->assertEquals($prof->margin(1, 2), $mg->margin(1, 2));
        $this->assertEquals($prof->margin(2, 0), $mg->margin(2, 0));
    }

    public function testAdd(): void
    {
        $mg1 = new MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 3], [2, 0, 5]]);
        $mg2 = new MarginGraph([0, 1, 2], [[1, 0, 1], [2, 1, 1], [2, 0, 3]]);
        $mg3 = $mg1->add($mg2);
        $this->assertInstanceOf(MarginGraph::class, $mg3);
        $this->assertEquals([0, 1, 2], $mg3->candidates);
        $this->assertEquals(0, $mg3->margin(0, 1));
        $this->assertEquals(2, $mg3->margin(1, 2));
        $this->assertEquals(8, $mg3->margin(2, 0));
    }

    public function testEq(): void
    {
        $mg1 = new MarginGraph([0, 1, 2], [[0, 1, 1], [1, 2, 3], [2, 0, 5]]);
        $mg2 = new MarginGraph([0, 1, 2], [[2, 0, 5], [0, 1, 1], [1, 2, 3]]);
        $mg3 = new MarginGraph([0, 1, 2], [[0, 1, 2], [1, 2, 4], [2, 0, 6]]);

        $this->assertTrue($mg1->equals($mg2));
        $this->assertFalse($mg1->equals($mg3));
    }
}
