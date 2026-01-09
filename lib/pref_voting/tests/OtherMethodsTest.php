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
use PrefVoting\OtherMethods;
use PrefVoting\SocialWelfareFunctions;

class OtherMethodsTest extends TestCase
{
    public function testKemenyYoung(): void
    {
        // 0>1, 1>2, 2>0 each with margin 1
        $prof = new Profile([
            [0, 1, 2], 
            [1, 2, 0], 
            [2, 0, 1]
        ]);

        // In a cycle, all candidates are Kemeny winners
        $winners = OtherMethods::kemenyYoung()($prof);
        $this->assertEqualsCanonicalizing([0, 1, 2], $winners);

        // Check SWF returns 3 rankings
        $rankings = SocialWelfareFunctions::kemenyYoung()($prof);
        $this->assertCount(3, $rankings);
    }

    public function testKemenyYoungExample(): void
    {
        // Example from Python: prof1 = Profile([[0, 1, 2], [1, 0, 2], [2, 1, 0]], [3, 1, 2])
        // 3: 0>1>2
        // 1: 1>0>2
        // 2: 2>1>0
        
        // s(0,1) = 3, s(1,0) = 1+2=3. m(0,1) = 0.
        // s(0,2) = 3+1=4, s(2,0) = 2. m(0,2) = 2.
        // s(1,2) = 3+1=4, s(2,1) = 2. m(1,2) = 2.
        
        // Rankings scores:
        // 0>1>2: m(0,1) + m(0,2) + m(1,2) = 0 + 2 + 2 = 4
        // 0>2>1: m(0,2) + m(0,1) + m(2,1) = 2 + 0 + -2 = 0
        // 1>0>2: m(1,0) + m(1,2) + m(0,2) = 0 + 2 + 2 = 4
        // 1>2>0: m(1,2) + m(1,0) + m(2,0) = 2 + 0 + -2 = 0
        // 2>0>1: m(2,0) + m(2,1) + m(0,1) = -2 + -2 + 0 = -4
        // 2>1>0: m(2,1) + m(2,0) + m(1,0) = -2 + -2 + 0 = -4
        
        // Best are 0>1>2 and 1>0>2 with score 4.
        // Winners are {0, 1}.
        
        $prof = new Profile([[0, 1, 2], [1, 0, 2], [2, 1, 0]], [3, 1, 2]);
        $winners = OtherMethods::kemenyYoung()($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners);
        
        $rankings = SocialWelfareFunctions::kemenyYoung()($prof);
        $this->assertCount(2, $rankings);
    }

    public function testSquaredKemeny(): void
    {
        // For a cycle, Kemeny-Young might return 3 rankings, 
        // let's see what Squared Kemeny does.
        $prof = new Profile([
            [0, 1, 2], 
            [1, 2, 0], 
            [2, 0, 1]
        ]);

        $rankings = SocialWelfareFunctions::squaredKemeny()($prof);
        // In this perfectly symmetric case, it should also return the 3 symmetric rankings.
        $this->assertCount(3, $rankings);

        // A case where Kemeny and Squared Kemeny might differ.
        $prof2 = new Profile([[0, 1, 2], [2, 1, 0]], [2, 1]);
        $rankings2 = SocialWelfareFunctions::squaredKemeny()($prof2);
        // Costs: d=0: 9, d=1: 6, d=2: 9, d=3: 18.
        // d=1 rankings are [1,0,2] and [0,2,1].
        $this->assertCount(2, $rankings2);

        // Test case from discussion: [0,1,2,3], [0,1,2,3], [3,2,1,0]
        $prof3 = new Profile([[0, 1, 2, 3], [0, 1, 2, 3], [3, 2, 1, 0]]);
        $rankings3 = SocialWelfareFunctions::squaredKemeny()($prof3);
        $this->assertCount(5, $rankings3);
    }
}
