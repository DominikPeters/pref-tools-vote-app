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
use PrefVoting\SupportGraph;
use PrefVoting\Profile;

class SupportGraphTest extends TestCase
{
    private SupportGraph $exampleGraph;

    protected function setUp(): void
    {
        $this->exampleGraph = new SupportGraph(
            [0, 1, 2],
            [[0, 1, [4, 3]], [1, 2, [5, 2]], [2, 0, [6, 1]]]
        );
    }

    public function testInit(): void
    {
        $sg = new SupportGraph(
            [0, 1, 2],
            [[0, 1, [4, 3]], [1, 2, [5, 2]], [2, 0, [6, 1]]]
        );
        $this->assertEquals([0, 1, 2], $sg->candidates);
        $expectedMatrix = [[0, 4, 1], [3, 0, 5], [6, 2, 0]];
        $this->assertEquals($expectedMatrix, $sg->sMatrix);
    }

    public function testEdges(): void
    {
        $expectedEdges = [[0, 1, [4, 3]], [1, 2, [5, 2]], [2, 0, [6, 1]]];
        $edges = $this->exampleGraph->getEdges();
        usort($edges, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $this->assertEquals($expectedEdges, $edges);
    }

    public function testMargin(): void
    {
        $this->assertEquals(1, $this->exampleGraph->margin(0, 1));
        $this->assertEquals(3, $this->exampleGraph->margin(1, 2));
        $this->assertEquals(5, $this->exampleGraph->margin(2, 0));
    }

    public function testSupport(): void
    {
        $this->assertEquals(4, $this->exampleGraph->support(0, 1));
        $this->assertEquals(3, $this->exampleGraph->support(1, 0));
        $this->assertEquals(1, $this->exampleGraph->support(0, 2));
        $this->assertEquals(6, $this->exampleGraph->support(2, 0));
        $this->assertEquals(5, $this->exampleGraph->support(1, 2));
        $this->assertEquals(2, $this->exampleGraph->support(2, 1));
    }

    public function testMajorityPrefers(): void
    {
        $this->assertTrue($this->exampleGraph->majorityPrefers(0, 1));
        $this->assertFalse($this->exampleGraph->majorityPrefers(1, 0));
    }

    public function testIsTied(): void
    {
        $this->assertFalse($this->exampleGraph->isTied(0, 1));
    }

    public function testStrengthMatrix(): void
    {
        [$strengthMatrix, $candToIdx] = $this->exampleGraph->strengthMatrix();
        $expectedMatrix = [[0, 4, 1], [3, 0, 5], [6, 2, 0]];
        $this->assertEquals($expectedMatrix, $strengthMatrix);
    }

    public function testRemoveCandidates(): void
    {
        $newGraph = $this->exampleGraph->removeCandidates([1]);
        $this->assertEqualsCanonicalizing([0, 2], $newGraph->candidates);
        $edges = $newGraph->getEdges();
        $this->assertCount(1, $edges);
        $this->assertEquals([2, 0, [6, 1]], $edges[0]);
    }

    public function testFromProfile(): void
    {
        $prof = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]]);
        $sg = SupportGraph::fromProfile($prof);
        $this->assertEquals([0, 1, 2], $sg->candidates);
        $this->assertEquals($prof->support(0, 1), $sg->support(0, 1));
        $this->assertEquals($prof->support(1, 0), $sg->support(1, 0));
        $this->assertEquals($prof->support(0, 2), $sg->support(0, 2));
        $this->assertEquals($prof->support(2, 0), $sg->support(2, 0));
        $this->assertEquals($prof->support(1, 2), $sg->support(1, 2));
        $this->assertEquals($prof->support(2, 1), $sg->support(2, 1));
    }
}
