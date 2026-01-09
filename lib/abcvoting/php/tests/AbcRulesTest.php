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
use AbcVoting\SimpleRules;
use AbcVoting\ThieleRules;
use AbcVoting\SequentialRules;
use AbcVoting\PhragmenRules;
use AbcVoting\ProportionalRules;
use AbcVoting\OtherRules;

class AbcRulesTest extends TestCase
{
    private function assertCommitteesEqual(array $expected, array $actual): void
    {
        $this->assertEquals(count($expected), count($actual), "Number of committees differs");
        
        $expectedSorted = array_map(function($c) { sort($c); return implode(',', $c); }, $expected);
        $actualSorted = array_map(function($c) { sort($c); return implode(',', $c); }, $actual);
        
        sort($expectedSorted);
        sort($actualSorted);
        
        $this->assertEquals($expectedSorted, $actualSorted);
    }

    public function testProfile1(): void
    {
        $profile = new Profile(6);
        $approvalSets = [[0, 4, 5], [0], [1, 4, 5], [1], [2, 4, 5], [2], [3, 4, 5], [3]];
        $profile->addVoters($approvalSets);
        $k = 4;

        // AV
        $expectedAv = [[0, 1, 4, 5], [0, 2, 4, 5], [0, 3, 4, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]];
        $this->assertCommitteesEqual($expectedAv, SimpleRules::computeAv($profile, $k));

        // SAV
        $expectedSav = [
            [0, 1, 2, 3], [0, 1, 2, 4], [0, 1, 2, 5], [0, 1, 3, 4], [0, 1, 3, 5], 
            [0, 1, 4, 5], [0, 2, 3, 4], [0, 2, 3, 5], [0, 2, 4, 5], [0, 3, 4, 5],
            [1, 2, 3, 4], [1, 2, 3, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]
        ];
        $this->assertCommitteesEqual($expectedSav, SimpleRules::computeSav($profile, $k));

        // PAV
        $expectedPav = [[0, 1, 4, 5], [0, 2, 4, 5], [0, 3, 4, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]];
        $this->assertCommitteesEqual($expectedPav, ThieleRules::computePav($profile, $k));

        // Seq-PAV
        $expectedSeqPav = [[0, 1, 4, 5], [0, 2, 4, 5], [0, 3, 4, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]];
        $this->assertCommitteesEqual($expectedSeqPav, SequentialRules::computeSeqPav($profile, $k, false));

        // Seq-Phragmen
        $expectedSeqPhragmen = [[0, 1, 4, 5], [0, 2, 4, 5], [0, 3, 4, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]];
        $this->assertCommitteesEqual($expectedSeqPhragmen, PhragmenRules::computeSeqPhragmen($profile, $k));

        // Adams
        $this->assertCommitteesEqual([[0, 1, 2, 3]], ThieleRules::computeAdams($profile, $k));

        // Lex-CC
        $this->assertCommitteesEqual([[0, 1, 2, 3]], ThieleRules::computeLexCc($profile, $k));

        // Rule X (Equal Shares)
        $expectedEqualShares = [[0, 1, 4, 5], [0, 2, 4, 5], [0, 3, 4, 5], [1, 2, 4, 5], [1, 3, 4, 5], [2, 3, 4, 5]];
        // Since my implementation is currently simple resolute for ties, let's check if the result is in the expected set
        $actualEqualShares = ProportionalRules::computeEqualShares($profile, $k, true, null, "seqphragmen");
        $this->assertCount(1, $actualEqualShares);
        $res = $actualEqualShares[0];
        sort($res);
        $found = false;
        foreach ($expectedEqualShares as $exp) {
            sort($exp);
            if ($res === $exp) { $found = true; break; }
        }
        $this->assertTrue($found, "Rule X result not in expected committees");
    }

    public function testProfile2(): void
    {
        $profile = new Profile(5);
        $approvalSets = [[0, 1, 2], [0, 1, 2], [0, 1, 2], [0, 1, 2], [0, 1, 2], [0, 1], [3, 4], [3, 4], [3]];
        $profile->addVoters($approvalSets);
        $k = 3;

        // AV
        $this->assertCommitteesEqual([[0, 1, 2]], SimpleRules::computeAv($profile, $k));

        // Seq-PAV
        $this->assertCommitteesEqual([[0, 1, 3]], SequentialRules::computeSeqPav($profile, $k));

        // PAV
        $this->assertCommitteesEqual([[0, 1, 3]], ThieleRules::computePav($profile, $k));

        // Adams
        $this->assertCommitteesEqual([[0, 1, 3]], ThieleRules::computeAdams($profile, $k));

        // Lex-CC
        $this->assertCommitteesEqual([[0, 1, 3]], ThieleRules::computeLexCc($profile, $k));

        // MAV
        $expectedMav = [[0, 1, 3], [0, 2, 3], [1, 2, 3]];
        $this->assertCommitteesEqual($expectedMav, OtherRules::computeMinimaxAv($profile, $k));
    }

    public function testProfile3(): void
    {
        $profile = new Profile(6);
        $approvalSets = [
            [0, 3, 4, 5], [1, 2], [0, 2, 5], [2], 
            [0, 1, 2, 3, 4], [0, 3, 4], [0, 2, 4], [0, 1]
        ];
        $profile->addVoters($approvalSets);
        $k = 4;

        $this->assertCommitteesEqual([[0, 1, 2, 4]], SequentialRules::computeSeqPav($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 2, 4]], ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 2, 4]], ThieleRules::computeAdams($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 2, 4]], ThieleRules::computeLexCc($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 2, 4]], PhragmenRules::computeSeqPhragmen($profile, $k, [], null, true));
    }

    public function testSeqPhragmenIrresolute(): void
    {
        $profile = new Profile(3);
        $profile->addVoters([[0, 1], [0, 1], [0], [1, 2], [2]]);
        $k = 2;
        $committees = PhragmenRules::computeSeqPhragmen($profile, $k, [], null, false);
        $this->assertCommitteesEqual([[0, 1], [0, 2]], $committees);

        $committeesRes = PhragmenRules::computeSeqPhragmen($profile, $k, [], null, true);
        $this->assertCommitteesEqual([[0, 2]], $committeesRes);
    }

    public function testSeqPavIrresolute(): void
    {
        $profile = new Profile(3);
        $profile->addVoters([[0, 1], [0, 1], [0, 1], [0], [1, 2], [2], [2]]);
        $k = 2;

        $committees = SequentialRules::computeSeqPav($profile, $k, false);
        $this->assertCommitteesEqual([[0, 1], [0, 2], [1, 2]], $committees);

        $committeesRes = SequentialRules::computeSeqPav($profile, $k, true);
        $this->assertCommitteesEqual([[0, 2]], $committeesRes);
    }

    public function testJansonExamples(): void
    {
        $profile = new Profile(6);
        $a = 0; $b = 1; $c = 2; $p = 3; $q = 4; $r = 5;
        $profile->addVoters(array_merge(
            array_fill(0, 1034, [$a, $b, $c]),
            array_fill(0, 519, [$p, $q, $r]),
            array_fill(0, 90, [$a, $b, $q]),
            array_fill(0, 90, [$a, $p, $q])
        ));
        $k = 3;

        $this->assertCommitteesEqual([[0, 1, 4]], PhragmenRules::computeSeqPhragmen($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 4]], ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 4]], SequentialRules::computeSeqPav($profile, $k));
        $this->assertCommitteesEqual([[0, 1, 4]], OtherRules::computePhragmenEnestroem($profile, $k));
    }

    public function testRuleXIncrement(): void
    {
        // Example where Rule X needs increment completion
        $profile = new Profile(3);
        $profile->addVoters([[0], [1]]);
        $k = 2;
        
        // Rule X without completion would only return {0, 1} if budget is enough.
        // With k=2, n=2, budget per voter is 1. Cost is 1.
        // So both can be bought.
        $res = ProportionalRules::computeEqualShares($profile, $k, true, null, "increment");
        $this->assertCommitteesEqual([[0, 1]], $res);
        
        // Another example from a known case where increment is needed
        $profile = new Profile(4);
        $profile->addVoters([[0], [0], [1], [2]]);
        $k = 3;
        // Total weight = 4. k=3. Budget = 0.75 per voter.
        // Cand 0: 2 approvers * 0.75 = 1.5. Can be bought. q=0.5. Remaining budgets: 0.25, 0.25, 0.75, 0.75.
        // Cand 1: 1 approver * 0.75 = 0.75. Cannot be bought (needs 1).
        // Cand 2: 1 approver * 0.75 = 0.75. Cannot be bought.
        // Only Cand 0 is bought. We need 2 more.
        // Incrementing: try incSize = 4. Budget = 1.0 per voter.
        // Cand 0 bought (q=0.5). Budgets: 0.5, 0.5, 1.0, 1.0.
        // Cand 1 bought (q=1.0). Budgets: 0.5, 0.5, 0.0, 1.0.
        // Cand 2 bought (q=1.0). Budgets: 0.5, 0.5, 0.0, 0.0.
        // All bought. Result {0, 1, 2}.
        $res = ProportionalRules::computeEqualShares($profile, $k, true, null, "increment");
        $this->assertCommitteesEqual([[0, 1, 2]], $res);
    }

    public function testMmsProfile(): void
    {
        // Maximin Support Example from test_abcrules.py
        $profile = new Profile(7);
        $k = 4;
        $approvalSets = array_merge(
            array_fill(0, 5, [0, 1, 2, 3, 4]),
            array_fill(0, 4, [5, 1, 2, 3, 4]),
            array_fill(0, 3, [6, 1, 2, 3, 4]),
            [[0], [0], [5], [6]]
        );
        $profile->addVoters($approvalSets);
        
        // seq-Phragmen result
        $expectedSeqPhragmen = [[0, 1, 2, 3], [0, 1, 2, 4], [0, 1, 3, 4], [0, 2, 3, 4]];
        $this->assertCommitteesEqual($expectedSeqPhragmen, PhragmenRules::computeSeqPhragmen($profile, $k));
    }

    public function testSequentialRules(): void
    {
        $profile = new Profile(4);
        $profile->addVoters([[0, 1], [0, 2], [3]]);
        $k = 2;

        // Seq-PAV
        $this->assertCommitteesEqual([[0, 3]], SequentialRules::computeSeqPav($profile, $k));
        
        // Seq-CC
        // Round 1: 0 (2 votes), 1 (1 vote), 2 (1 vote), 3 (1 vote). Pick 0.
        // Round 2: 0 already in. 1 (0 extra), 2 (0 extra), 3 (1 extra). Pick 3.
        $this->assertCommitteesEqual([[0, 3]], SequentialRules::computeSeqCc($profile, $k));
    }

    public function testProfile4(): void
    {
        $profile = new Profile(4);
        $approvalSets = [[0, 1, 3], [0, 1], [0, 1], [0, 3], [2, 3]];
        $profile->addVoters($approvalSets);
        $k = 2;

        $this->assertCommitteesEqual([[0, 1], [0, 3]], SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual([[0, 3]], ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual([[0, 3]], ThieleRules::computeAdams($profile, $k));
        $this->assertCommitteesEqual([[0, 3]], ThieleRules::computeLexCc($profile, $k));
        $this->assertCommitteesEqual([[0, 3]], SequentialRules::computeSeqPav($profile, $k));
        $this->assertCommitteesEqual([[0, 3], [1, 3]], OtherRules::computeMinimaxAv($profile, $k));
    }

    public function testProfile5(): void
    {
        $profile = new Profile(10);
        $approvalSets = [range(0, 4), range(5, 9)];
        $profile->addVoters($approvalSets);
        $k = 2;

        $oneEach = [];
        for ($i = 0; $i < 5; $i++) {
            for ($j = 5; $j < 10; $j++) {
                $oneEach[] = [$i, $j];
            }
        }

        $this->assertCommitteesEqual($oneEach, ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual($oneEach, ThieleRules::computeAdams($profile, $k));
        $this->assertCommitteesEqual($oneEach, ThieleRules::computeLexCc($profile, $k));
        $this->assertCommitteesEqual($oneEach, OtherRules::computeMinimaxAv($profile, $k));
    }

    public function testTooFewCandidates(): void
    {
        $profile = new Profile(5);
        $profile->addVoters([[0, 1, 2], [1], [2], [0]]);
        $k = 4;

        // Most rules should return committees that include all approved candidates {0, 1, 2} 
        // plus one non-approved candidate {3} or {4}.
        $expected = [[0, 1, 2, 3], [0, 1, 2, 4]];
        
        $this->assertCommitteesEqual($expected, SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual($expected, ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual($expected, SequentialRules::computeSeqPav($profile, $k, false));
    }

    public function testWeightsConsidered(): void
    {
        $profile = new Profile(3);
        $profile->addVoter(new Voter([0]));
        $profile->addVoter(new Voter([0]));
        $profile->addVoter(new Voter([1], 5.0));
        $profile->addVoter(new Voter([0]));
        $k = 1;

        // Candidate 0 has total weight 3, Candidate 1 has total weight 5.
        // Rule should pick Candidate 1.
        $this->assertCommitteesEqual([[1]], SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual([[1]], ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual([[1]], SequentialRules::computeSeqPav($profile, $k));
    }

    public function testCorrectSimple(): void
    {
        $profile = new Profile(4);
        $profile->addVoters([[0], [1], [2], [3]]);
        $k = 2;

        // Disjoint voters, size 2 committee. 
        // All pairs should be tied (6 committees total).
        $expected = [[0, 1], [0, 2], [0, 3], [1, 2], [1, 3], [2, 3]];
        
        $this->assertCommitteesEqual($expected, SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual($expected, ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual($expected, SequentialRules::computeSeqPav($profile, $k, false));
        $this->assertCommitteesEqual($expected, PhragmenRules::computeSeqPhragmen($profile, $k));
    }

    public function testCorrectSimple2(): void
    {
        $profile = new Profile(6);
        $profile->addVoters([[0, 1, 2], [1, 3]]);
        $k = 4;

        // These rules should return {0, 1, 2, 3}
        $expected = [[0, 1, 2, 3]];
        $this->assertCommitteesEqual($expected, SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual($expected, ThieleRules::computePav($profile, $k));
        $this->assertCommitteesEqual($expected, SequentialRules::computeSeqPav($profile, $k));
    }

    public function testHandlingEmptyBallots(): void
    {
        $profile = new Profile(4);
        $profile->addVoters([[0], [1], [2]]);
        $k = 3;

        $expected = [[0, 1, 2]];
        $this->assertCommitteesEqual($expected, SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual($expected, ThieleRules::computePav($profile, $k));

        // Add empty ballot
        $profile->addVoter([]);
        $this->assertEquals(4, $profile->count());
        $this->assertCommitteesEqual($expected, SimpleRules::computeAv($profile, $k));
        $this->assertCommitteesEqual($expected, ThieleRules::computePav($profile, $k));
    }
}
