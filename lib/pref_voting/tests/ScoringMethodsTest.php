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
use PrefVoting\ScoringMethods;
use PrefVoting\Ranking;
use PrefVoting\VotingMethod;

class ScoringMethodsTest extends TestCase
{
    private Profile $condorcetCycle;
    private Profile $linearProfile0;
    private ProfileWithTies $profileWithTiesLinear0;
    private ProfileWithTies $profileWithTies;
    private Profile $profileSingleVoter;

    protected function setUp(): void
    {
        $this->condorcetCycle = new Profile([
            [0, 1, 2], 
            [1, 2, 0], 
            [2, 0, 1]]);

        $this->linearProfile0 = new Profile([
            [0, 1, 2], 
            [2, 1, 0]], 
            [2, 1]);

        $this->profileWithTiesLinear0 = new ProfileWithTies([
            [0 => 1, 1 => 2, 2 => 3], 
            [0 => 3, 1 => 2, 2 => 1]],
            [2, 1]);

        $this->profileWithTies = new ProfileWithTies([
            [0 => 1, 1 => 1, 2 => 2], 
            [0 => 2, 1 => 2, 2 => 1]],
            [2, 1]);

        $this->profileSingleVoter = new Profile([[0, 1, 2, 3]]);
    }

    public function testPlurality(): void
    {
        $plurality = ScoringMethods::plurality();
        $this->assertEquals([0, 1, 2], $plurality($this->condorcetCycle));
        $this->assertEquals([0], $plurality($this->linearProfile0));
        $this->assertEquals([1], $plurality($this->linearProfile0, [1, 2]));
        $this->assertEquals([0], $plurality($this->profileSingleVoter));
        $this->assertEquals([0], $plurality($this->profileSingleVoter, [0, 1]));
    }

    public function testBorda(): void
    {
        $borda = ScoringMethods::borda();
        $this->assertEquals([0, 1, 2], $borda($this->condorcetCycle));
        $this->assertEquals([0], $borda($this->linearProfile0));
        $this->assertEquals([1], $borda($this->linearProfile0, [1, 2]));
        $this->assertEquals([0], $borda($this->profileSingleVoter));
        $this->assertEquals([0], $borda($this->profileSingleVoter, [0, 1]));
    }

    public function testDowdall(): void
    {
        $dowdall = ScoringMethods::dowdall();
        $this->assertEquals([0, 1, 2], $dowdall($this->condorcetCycle));
        $this->assertEquals([0], $dowdall($this->linearProfile0));
        $this->assertEquals([1], $dowdall($this->linearProfile0, [1, 2]));
        $this->assertEquals([0], $dowdall($this->profileSingleVoter));
        $this->assertEquals([0], $dowdall($this->profileSingleVoter, [0, 1]));
    }

    public function testAntiPlurality(): void
    {
        $antiPlurality = ScoringMethods::antiPlurality();
        $this->assertEquals([0, 1, 2], $antiPlurality($this->condorcetCycle));
        $this->assertEquals([1], $antiPlurality($this->linearProfile0));
        $this->assertEquals([1], $antiPlurality($this->linearProfile0, [1, 2]));
        $winners = $antiPlurality($this->profileSingleVoter);
        // echo "AntiPlurality winners for single voter: " . json_encode($winners) . "\n";
        $this->assertEqualsCanonicalizing([0, 1, 2], $winners);
        $this->assertEquals([0], $antiPlurality($this->profileSingleVoter, [0, 1]));
    }

    public function testPositiveNegativeVoting(): void
    {
        $pn = ScoringMethods::positiveNegativeVoting();
        $this->assertEquals([0, 1, 2], $pn($this->condorcetCycle));
        $this->assertEquals([0], $pn($this->linearProfile0));
        $this->assertEquals([1], $pn($this->linearProfile0, [1, 2]));
        $this->assertEquals([0], $pn($this->profileSingleVoter));
        $this->assertEquals([0], $pn($this->profileSingleVoter, [0, 1]));
    }

    public function testScoringRule(): void
    {
        $scoringRule = ScoringMethods::scoringRule(fn($ncs, $rank) => $rank == 2 ? 1.0 : 0.0);
        $this->assertEquals([0, 1, 2], $scoringRule($this->condorcetCycle));
        $this->assertEquals([1], $scoringRule($this->condorcetCycle, [0, 1]));
        $this->assertEquals([1], $scoringRule($this->linearProfile0));
    }

    public function testPluralityOnProfilesWithTies(): void
    {
        $plurality = ScoringMethods::plurality();
        $this->assertEquals([0], $plurality($this->profileWithTiesLinear0));
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Cannot find plurality scores unless all voters rank a unique candidate in first place.");
        $plurality($this->profileWithTies);
    }

    public function testBordaOnProfilesWithTies(): void
    {
        $bordaTies = ScoringMethods::bordaForProfileWithTies();
        $this->assertEquals([0], $bordaTies($this->profileWithTiesLinear0));
        $this->assertEqualsCanonicalizing([0, 1], $bordaTies($this->profileWithTies));
        
        $domBorda = ScoringMethods::bordaForProfileWithTies([ScoringMethods::class, 'dominationBordaScores']);
        $this->assertEqualsCanonicalizing([0, 1, 2], $domBorda($this->profileWithTies));

        $this->assertEquals([1], $bordaTies($this->profileWithTies, [1, 2]));
    }

    public function testPluralityRanking(): void
    {
        $pluralityRanking = ScoringMethods::pluralityRanking();
        $this->assertTrue($pluralityRanking($this->condorcetCycle)[0]->equals(new Ranking([0 => 1, 1 => 1, 2 => 1])));
        
        $pluralityRankingAlpha = ScoringMethods::pluralityRanking(true, 'alphabetic');
        $this->assertTrue($pluralityRankingAlpha($this->condorcetCycle)[0]->equals(new Ranking([0 => 1, 1 => 2, 2 => 3])));
    }

    public function testBordaRanking(): void
    {
        $bordaRanking = ScoringMethods::bordaRanking();
        $this->assertTrue($bordaRanking($this->condorcetCycle)[0]->equals(new Ranking([0 => 1, 1 => 1, 2 => 1])));
        $this->assertTrue($bordaRanking($this->linearProfile0)[0]->equals(new Ranking([0 => 1, 1 => 2, 2 => 3])));
    }

    public function testCreateScoringMethod(): void
    {
        $scoringMethod = ScoringMethods::createScoringMethod(fn($ncs, $x) => 1.0, "test");
        $this->assertInstanceOf(VotingMethod::class, $scoringMethod);
        $this->assertEquals("test", $scoringMethod->name);
        $this->assertEqualsCanonicalizing([0, 1, 2], $scoringMethod($this->condorcetCycle));
    }
}
