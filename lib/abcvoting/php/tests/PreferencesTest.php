<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace AbcVoting\Tests;

use PHPUnit\Framework\TestCase;
use AbcVoting\Profile;
use AbcVoting\Voter;

class PreferencesTest extends TestCase
{
    public function testInvalidApprovalSets(): void
    {
        // Voter index >= numCand should throw exception in constructor if numCand is provided
        $this->expectException(\InvalidArgumentException::class);
        new Voter([1], 1.0, 1);
    }

    public function testInvalidProfiles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Profile(0);
    }

    public function testInvalidProfilesNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Profile(-8);
    }

    public function testInvalidCandNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Profile(4, ["a", "b", "c"]);
    }

    public function testInvalidWeights(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Voter([0, 1], -1.0);
    }

    public function testInvalidWeightsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Voter([0, 1], 0.0);
    }

    public function testEmptyApproval(): void
    {
        $profile = new Profile(5);
        $profile->addVoter([]);
        $profile->addVoters([[]]);
        $profile->addVoters([[], [0, 3], []]);

        $this->assertEquals(5, $profile->count());
    }

    public function testUnitWeights(): void
    {
        $profile = new Profile(6);
        $profile->addVoter(new Voter([0, 4, 5]));
        $profile->addVoter([0, 4, 5]);
        $p1 = new Voter([0, 4, 5]);
        $p2 = new Voter([1, 2]);
        $profile->addVoters([$p1, $p2]);
        
        $this->assertTrue($profile->hasUnitWeights());
        $this->assertEquals(4, $profile->count());
        $this->assertEquals(4.0, $profile->totalWeight());

        $profile->addVoter(new Voter([0, 1], 2.4));
        $this->assertFalse($profile->hasUnitWeights());
        $this->assertEquals(6.4, $profile->totalWeight());
    }

    public function testIterate(): void
    {
        $profile = new Profile(6);
        $profile->addVoter(new Voter([1, 3, 5], 3.0));
        $profile->addVoter([0, 4, 5]);
        $this->assertEquals(2, $profile->count());
        foreach ($profile->getVoters() as $voter) {
            $this->assertInstanceOf(Voter::class, $voter);
        }
    }
}
