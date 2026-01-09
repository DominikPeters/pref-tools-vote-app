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
use PrefVoting\ProfileWithTies;
use PrefVoting\Ranking;

class ProfileWithTiesTest extends TestCase
{
    private ProfileWithTies $testProfile;

    protected function setUp(): void
    {
        $this->testProfile = new ProfileWithTies(
            [
                [0 => 1, 1 => 2],
                [1 => 1, 2 => 2, 0 => 3],
                [2 => 1, 0 => 1]
            ], 
            [2, 3, 1]);
    }

    public function testCreateProfileWithTies(): void
    {
        $prof = new ProfileWithTies([
            [0 => 1, 1 => 2], 
            [2 => 1], 
            [0 => 3, 1 => 1, 2 => 2]], 
            [2, 3, 1]);
        $this->assertEquals(3, $prof->numCands);
        $this->assertEquals([0, 1, 2], $prof->candidates);
        $this->assertEquals(6, $prof->numVoters);
    }

    public function testSupport(): void
    {
        $this->assertEquals(2, $this->testProfile->support(0, 1));
        $this->assertEquals(3, $this->testProfile->support(1, 0));
        $this->assertEquals(3, $this->testProfile->support(2, 0));
        $this->assertEquals(0, $this->testProfile->support(0, 2));
        $this->assertEquals(3, $this->testProfile->support(1, 2));
        $this->assertEquals(0, $this->testProfile->support(2, 1));
    }
    
    public function testMargin(): void
    {
        $this->assertEquals(-1, $this->testProfile->margin(0, 1)); 
        $this->assertEquals(1, $this->testProfile->margin(1, 0)); 
        $this->assertEquals(3, $this->testProfile->margin(2, 0));
        $this->assertEquals(-3, $this->testProfile->margin(0, 2));
        $this->assertEquals(3, $this->testProfile->margin(1, 2));
        $this->assertEquals(-3, $this->testProfile->margin(2, 1));
    }

    public function testExtendedStrictPreference(): void
    {
        $this->testProfile->useExtendedStrictPreference();
        $this->assertEquals(3, $this->testProfile->support(0, 1));
        $this->assertEquals(3, $this->testProfile->support(1, 0));
        $this->assertEquals(3, $this->testProfile->support(2, 0));
        $this->assertEquals(2, $this->testProfile->support(0, 2));
        $this->assertEquals(5, $this->testProfile->support(1, 2));
        $this->assertEquals(1, $this->testProfile->support(2, 1));

        $this->testProfile->useStrictPreference();
        $this->assertEquals(2, $this->testProfile->support(0, 1));
        $this->assertEquals(3, $this->testProfile->support(1, 0));
    }

    public function testIsTied(): void
    {
        $prof2 = new ProfileWithTies([
            [0 => 1, 1 => 2],
            [0 => 2, 1 => 1],
            [0 => 1, 1 => 1]
        ]);
        $this->assertFalse($this->testProfile->isTied(0, 1)); 
        $this->assertTrue($prof2->isTied(0, 1));
    }

    public function testDominators(): void
    {
        $this->assertEqualsCanonicalizing([1, 2], $this->testProfile->dominators(0));
        $this->assertEqualsCanonicalizing([1], $this->testProfile->dominators(0, [0, 1]));
        $this->assertEqualsCanonicalizing([], $this->testProfile->dominators(1));
    }

    public function testDominates(): void
    {
        $this->assertEqualsCanonicalizing([0, 2], $this->testProfile->dominates(1));
        $this->assertEqualsCanonicalizing([2], $this->testProfile->dominates(1, [1, 2]));
        $this->assertEqualsCanonicalizing([0], $this->testProfile->dominates(2));
    }

    public function testRatio(): void
    {
        // test_profile has 6 voters.
        // support(0,1) = 2, support(1,0) = 3 -> 2/3 = 0.666...
        $this->assertEqualsWithDelta(2/3, $this->testProfile->ratio(0, 1), 0.0001);
        // support(2,0) = 3, support(0,2) = 0 -> numVoters + support(2,0) = 6 + 3 = 9
        $this->assertEquals(9.0, $this->testProfile->ratio(2, 0));
        // support(0,2) = 0, support(2,0) = 3 -> 1 / (numVoters + support(2,0)) = 1/9
        $this->assertEqualsWithDelta(1/9, $this->testProfile->ratio(0, 2), 0.0001);
    }

    public function testMajorityPrefers(): void
    {
        $this->assertFalse($this->testProfile->majorityPrefers(0, 1));
        $this->assertTrue($this->testProfile->majorityPrefers(1, 0));
        $this->assertTrue($this->testProfile->majorityPrefers(2, 0));
    }
}
