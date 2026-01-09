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
use PHPUnit\Framework\Attributes\DataProvider;

class ProfileTest extends TestCase
{
    private Profile $testProfile;

    protected function setUp(): void
    {
        $this->testProfile = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]], [2, 3, 1]);
    }

    public function testCreateProfile(): void
    {
        $prof = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]], [2, 3, 1]);
        $this->assertEquals(3, $prof->numCands);
        $this->assertEquals([0, 1, 2], $prof->candidates);
        $this->assertEquals(6, $prof->numVoters);
    }

    public function testRankingsCounts(): void
    {
        [$rankings, $counts] = $this->testProfile->getRankingsCounts();
        $this->assertEquals([[0, 1, 2], [1, 2, 0], [2, 0, 1]], $rankings);
        $this->assertEquals([2, 3, 1], $counts);
    }

    public function testRankingTypes(): void
    {
        $types = $this->testProfile->getRankingTypes();
        $this->assertEqualsCanonicalizing([[0, 1, 2], [1, 2, 0], [2, 0, 1]], $types);

        $prof2 = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1], [2, 0, 1]], [2, 3, 1, 2]);
        $types2 = $prof2->getRankingTypes();
        $this->assertEqualsCanonicalizing([[0, 1, 2], [1, 2, 0], [2, 0, 1]], $types2);
    }

    public function testGetRankings(): void
    {
        $rankings = $this->testProfile->getRankings();
        $expected = [
            [0, 1, 2], [0, 1, 2],
            [1, 2, 0], [1, 2, 0], [1, 2, 0],
            [2, 0, 1]
        ];
        $this->assertEqualsCanonicalizing($expected, $rankings);
    }

    public function testSupport(): void
    {
        $this->assertEquals(3, $this->testProfile->support(0, 1));
        $this->assertEquals(3, $this->testProfile->support(1, 0));
        $this->assertEquals(4, $this->testProfile->support(2, 0));
        $this->assertEquals(2, $this->testProfile->support(0, 2));
        $this->assertEquals(5, $this->testProfile->support(1, 2));
        $this->assertEquals(1, $this->testProfile->support(2, 1));
    }

    public function testMargin(): void
    {
        $this->assertEquals(0, $this->testProfile->margin(0, 1));
        $this->assertEquals(0, $this->testProfile->margin(1, 0));
        $this->assertEquals(2, $this->testProfile->margin(2, 0));
        $this->assertEquals(-2, $this->testProfile->margin(0, 2));
        $this->assertEquals(4, $this->testProfile->margin(1, 2));
        $this->assertEquals(-4, $this->testProfile->margin(2, 1));
    }

    public function testMajorityPrefers(): void
    {
        $this->assertFalse($this->testProfile->majorityPrefers(0, 1));
        $this->assertFalse($this->testProfile->majorityPrefers(1, 0));
        $this->assertTrue($this->testProfile->majorityPrefers(2, 0));
        $this->assertFalse($this->testProfile->majorityPrefers(0, 2));
        $this->assertTrue($this->testProfile->majorityPrefers(1, 2));
        $this->assertFalse($this->testProfile->majorityPrefers(2, 1));
    }

    public function testIsTied(): void
    {
        $this->assertTrue($this->testProfile->isTied(0, 1));
        $this->assertTrue($this->testProfile->isTied(1, 0));
        $this->assertFalse($this->testProfile->isTied(2, 0));
        $this->assertFalse($this->testProfile->isTied(0, 2));
        $this->assertFalse($this->testProfile->isTied(1, 2));
        $this->assertFalse($this->testProfile->isTied(2, 1));
    }

    public function testStrictMajSize(): void
    {
        $this->assertEquals(4, $this->testProfile->strictMajSize());
    }

    public function testCondorcetWinner(): void
    {
        $prof = new Profile([[0, 1, 2], [0, 2, 1], [1, 2, 0]]);
        $this->assertNull($this->testProfile->condorcetWinner());
        $this->assertEquals(0, $prof->condorcetWinner());
    }

    public function testWeakCondorcetWinner(): void
    {
        $prof = new Profile([[0, 1, 2], [1, 0, 2]]);
        $this->assertEquals([1], $this->testProfile->weakCondorcetWinner());
        $this->assertEquals([0, 1], $prof->weakCondorcetWinner());
    }

    public function testIsUniquelyWeighted(): void
    {
        $this->assertFalse($this->testProfile->isUniquelyWeighted());
        // A uniquely weighted profile
        $prof = new Profile([[0, 1, 2], [1, 2, 0], [2, 0, 1]], [10, 5, 1]);
        // margins: 
        // 0 vs 1: 10 - (5+1) = 4
        // 1 vs 2: (10+5) - 1 = 14
        // 0 vs 2: (10+1) - 5 = 6
        // All non-zero and unique: 4, 14, 6.
        $this->assertTrue($prof->isUniquelyWeighted());
    }

    public function testRemoveCandidates(): void
    {
        $updatedProf = new Profile([[0, 1], [1, 0], [1, 0]], [2, 3, 1]);
        [$newProf, $origNames] = $this->testProfile->removeCandidates([1]);
        $this->assertTrue($newProf->equals($updatedProf));
        $this->assertEquals([0 => 0, 1 => 2], $origNames);
    }

    public function testApplyCandPermutation(): void
    {
        $prof = new Profile([[2, 0, 1]], [1]);
        $perm = [0 => 1, 1 => 2, 2 => 0];
        $newProf = $prof->applyCandPermutation($perm);
        [$rankings, $counts] = $newProf->getRankingsCounts();
        $this->assertEquals([[0, 1, 2]], $rankings);
        $this->assertEquals([1], $counts);
    }
}
