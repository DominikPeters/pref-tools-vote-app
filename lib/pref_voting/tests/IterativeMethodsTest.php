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
use PrefVoting\Ranking;
use PrefVoting\IterativeMethods;
use PHPUnit\Framework\Attributes\DataProvider;

class IterativeMethodsTest extends TestCase
{
    private Profile $condorcetCycle;
    private Profile $linearProfile0;
    private ProfileWithTies $profileWithTiesLinear0;
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

        $this->profileSingleVoter = new Profile([[0, 1, 2, 3]]);
    }

    #[DataProvider('iterativeMethodsProvider')]
    public function testIterativeMethods(callable $votingMethod, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected['condorcet_cycle'], $votingMethod($this->condorcetCycle), "Failed for condorcet_cycle");
        $this->assertEqualsCanonicalizing($expected['linear_profile_0'], $votingMethod($this->linearProfile0), "Failed for linear_profile_0");
        if (isset($expected['linear_profile_0_curr_cands'])) {
            $this->assertEqualsCanonicalizing($expected['linear_profile_0_curr_cands'], $votingMethod($this->linearProfile0, [1, 2]), "Failed for linear_profile_0 restricted to [1, 2]");
        }
        $this->assertEqualsCanonicalizing($expected['profile_single_voter'], $votingMethod($this->profileSingleVoter), "Failed for profile_single_voter");
    }

    public static function iterativeMethodsProvider(): array
    {
        return [
            [IterativeMethods::instantRunoff(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::coombs(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::baldwin(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::strictNanson(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::weakNanson(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::benham(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::bottomTwoRunoffInstantRunoff(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::raynaud(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::woodall(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
            [IterativeMethods::knockout(), [
                'condorcet_cycle' => [0, 1, 2], 
                'linear_profile_0' => [0], 
                'linear_profile_0_curr_cands' => [1],
                'profile_single_voter' => [0]
            ]],
        ];
    }

    public function testInstantRunoffOnProfileWithTies(): void
    {
        $irv = IterativeMethods::instantRunoff();
        $this->assertEquals([0], $irv($this->profileWithTiesLinear0));
    }

    public function testInstantRunoffForTruncatedLinearOrders(): void
    {
        $prof = new ProfileWithTies([
            [0 => 1, 1 => 2],
            [1 => 1, 2 => 2],
            [2 => 1, 0 => 2]
        ]);
        $this->assertEqualsCanonicalizing([0, 1, 2], IterativeMethods::instantRunoffForTruncatedLinearOrders($prof));

        $prof2 = new ProfileWithTies([
            [0 => 1],
            [0 => 1],
            [1 => 1],
            [2 => 1]
        ]);
        $this->assertEqualsCanonicalizing([0], IterativeMethods::instantRunoffForTruncatedLinearOrders($prof2));
    }

    public function testInstantRunoffForTruncatedLinearOrdersPut(): void
    {
        $prof = new ProfileWithTies([
            [0 => 1, 1 => 2],
            [1 => 1, 2 => 2],
            [2 => 1, 0 => 2],
            [0 => 1]
        ]);
        // Scores: 0: 2, 1: 1, 2: 1. Total 4. Maj 3.
        // Branch 1: Eliminate 1. Scores: 0: 2, 2: 2. Tie, both win.
        // Branch 2: Eliminate 2. Scores: 0: 3, 1: 1. 0 wins.
        $this->assertEqualsCanonicalizing([0, 2], IterativeMethods::instantRunoffForTruncatedLinearOrdersPut($prof));

        $prof2 = new ProfileWithTies([
            [0 => 1, 1 => 2],
            [1 => 1, 0 => 2],
            [2 => 1]
        ]);
        // Scores: 0:1, 1:1, 2:1. Total 3. Maj 2.
        // Eliminate 0: 1 gets 2 votes. 1 wins.
        // Eliminate 1: 0 gets 2 votes. 0 wins.
        // Eliminate 2: Voters 1&2 remain. Total 2. Maj 2. Scores 0:1, 1:1.
        //    Eliminate 0: 1 wins.
        //    Eliminate 1: 0 wins.
        $this->assertEqualsCanonicalizing([0, 1], IterativeMethods::instantRunoffForTruncatedLinearOrdersPut($prof2));
    }

    public function testInstantRunoffForTruncatedLinearOrdersDynamicThreshold(): void
    {
        // Profile where a ballot becomes empty, changing the majority threshold.
        // 5 voters total. Maj size = 3.
        // Voter 1, 2: 0 > 1
        // Voter 3, 4: 1 > 2
        // Voter 5: 2 (truncated, only ranks 2)
        $prof = new ProfileWithTies([
            [0 => 1, 1 => 2],
            [0 => 1, 1 => 2],
            [1 => 1, 2 => 2],
            [1 => 1, 2 => 2],
            [2 => 1]
        ]);

        // Round 1: Scores 0: 2, 1: 2, 2: 1. 
        // Min is 2. Eliminate 2.
        // Voter 5's ballot becomes empty.
        // Remaining voters: 4. New Maj size = 3 (int(4/2) + 1).
        // Round 2: Scores 0: 2, 1: 2.
        // Both 0 and 1 are tied for min. Both eliminated.
        // Results in [0, 1].
        $this->assertEqualsCanonicalizing([0, 1], IterativeMethods::instantRunoffForTruncatedLinearOrders($prof));
    }

    #[DataProvider('putConsistencyProvider')]
    public function testInstantRunoffPutConsistency(array $rankings, ?array $rcounts): void
    {
        $prof = new Profile($rankings, $rcounts);
        
        // Convert to ProfileWithTies
        $rankingsWithTies = [];
        foreach ($prof->getRankings() as $r) {
            $rmap = [];
            foreach ($r as $pos => $cand) {
                $rmap[$cand] = $pos + 1;
            }
            $rankingsWithTies[] = $rmap;
        }
        $profWithTies = new ProfileWithTies($rankingsWithTies, null, $prof->candidates);
        
        $expected = IterativeMethods::instantRunoffPut()($prof);
        $actual = IterativeMethods::instantRunoffForTruncatedLinearOrdersPut($profWithTies);
        
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public static function putConsistencyProvider(): array
    {
        return [
            [[[0, 1, 2], [1, 2, 0], [2, 0, 1]], null],
            [[[0, 1, 2], [2, 1, 0]], [2, 1]],
            [[[0, 1, 2, 3]], null],
            [[[0, 1, 2], [1, 2, 0], [2, 0, 1], [0, 2, 1]], null],
            [[[0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0]], null],
            // 4-alternative examples
            [[[0, 1, 2, 3], [1, 2, 3, 0], [2, 3, 0, 1], [3, 0, 1, 2]], null], // 4-cand cycle
            [[[0, 1, 2, 3], [3, 2, 1, 0]], [5, 4]], // Weighted linear
            [[[0, 1, 2, 3], [1, 0, 2, 3], [2, 0, 1, 3], [3, 0, 1, 2]], null], // Multiple paths
        ];
    }

    /**
     * Test Approval-IRV and Split-IRV on the main example from the paper (Figure 1).
     *
     * v1: {a,b} > {c} > {d}
     * v2: {a,b,d} > {c}
     * v3: {b} > {a,c} > {d}
     * v4: {c} > {a} > {b,d}
     * v5: {d} > {a} > {c} > {b}
     *
     * Approval-IRV: a wins
     * Split-IRV: b wins
     */
    public function testApprovalIrvAndSplitIrvPaperExample1(): void
    {
        // Using candidates 0=a, 1=b, 2=c, 3=d
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1], [2], [3]]),      // v1: {a,b} > {c} > {d}
            Ranking::fromIndiffList([[0, 1, 3], [2]]),        // v2: {a,b,d} > {c}
            Ranking::fromIndiffList([[1], [0, 2], [3]]),      // v3: {b} > {a,c} > {d}
            Ranking::fromIndiffList([[2], [0], [1, 3]]),      // v4: {c} > {a} > {b,d}
            Ranking::fromIndiffList([[3], [0], [2], [1]]),    // v5: {d} > {a} > {c} > {b}
        ], null, [0, 1, 2, 3]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        $this->assertEquals([0], $approvalIrv($prof), "Approval-IRV should elect a");
        $this->assertEquals([1], $splitIrv($prof), "Split-IRV should elect b");
    }

    /**
     * Test the "majority alternative" example from the paper (Figure 2).
     *
     * 47%: {a,b} > {c} > {d}
     * 4%: {a} > {b} > {c} > {d}
     * 25%: {c} > {b} > {d} > {a}
     * 24%: {d} > {b} > {c} > {a}
     *
     * Approval-IRV: b wins (Condorcet winner)
     * Split-IRV: a wins
     */
    public function testApprovalIrvAndSplitIrvMajorityAlternative(): void
    {
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1], [2], [3]]),      // {a,b} > {c} > {d}
            Ranking::fromIndiffList([[0], [1], [2], [3]]),    // {a} > {b} > {c} > {d}
            Ranking::fromIndiffList([[2], [1], [3], [0]]),    // {c} > {b} > {d} > {a}
            Ranking::fromIndiffList([[3], [1], [2], [0]]),    // {d} > {b} > {c} > {a}
        ], [47, 4, 25, 24], [0, 1, 2, 3]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        $this->assertEquals([1], $approvalIrv($prof), "Approval-IRV should elect b (Condorcet winner)");
        $this->assertEquals([0], $splitIrv($prof), "Split-IRV should elect a");
    }

    /**
     * Test the "cohesive majorities" example from the paper (Figure 3).
     *
     * 9: {a,b,c} > {d}
     * 5: {a,b} > {d} > {c}
     * 5: {a,c} > {d} > {b}
     * 8: {b,c,d} > {a}
     * 10: {d} > {a,b,c}
     *
     * Approval-IRV elimination: d (score 18), then a (score 29), then b and c are tied (32 each)
     * With PUT: both b and c are winners.
     * (The paper claims "a wins" but this appears to describe one tie-breaking path, not PUT.)
     *
     * Split-IRV elimination: a, then b and c tied, then d wins.
     */
    public function testApprovalIrvAndSplitIrvCohesiveMajorities(): void
    {
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1, 2], [3]]),        // {a,b,c} > {d}
            Ranking::fromIndiffList([[0, 1], [3], [2]]),      // {a,b} > {d} > {c}
            Ranking::fromIndiffList([[0, 2], [3], [1]]),      // {a,c} > {d} > {b}
            Ranking::fromIndiffList([[1, 2, 3], [0]]),        // {b,c,d} > {a}
            Ranking::fromIndiffList([[3], [0, 1, 2]]),        // {d} > {a,b,c}
        ], [9, 5, 5, 8, 10], [0, 1, 2, 3]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        // With PUT, Approval-IRV gives b and c as co-winners (tied in final round)
        $this->assertEqualsCanonicalizing([1, 2], $approvalIrv($prof), "Approval-IRV PUT should elect b and c");
        $this->assertEquals([3], $splitIrv($prof), "Split-IRV should elect d");
    }

    /**
     * Test the "indifference monotonicity" examples from the paper (Figure 4).
     *
     * Profile 1 (linear orders):
     * 1: a > c > b
     * 3: a > b > c
     * 4: b > c > a
     * 5: c > a > b
     * Scores: a=4, b=4, c=5. With PUT, a and b are tied for lowest.
     * - Eliminate a: b vs c → b=7, c=6 → c eliminated → b wins
     * - Eliminate b: a vs c → a=4, c=9 → a eliminated → c wins
     * So PUT gives {b, c} as winners.
     * (The paper describes eliminating b, leading to c winning.)
     *
     * Profile 2 (after c-hover on first voter):
     * 1: {a,c} > {b}
     * 3: a > b > c
     * 4: b > c > a
     * 5: c > a > b
     * Scores: a=3.5, b=4, c=5.5. a is unique lowest.
     * After a eliminated: b vs c. b=3+4=7, c=1+5=6. c eliminated, b wins.
     */
    public function testSplitIrvIndifferenceMonotonicity(): void
    {
        // Profile 1
        $prof1 = new ProfileWithTies([
            Ranking::fromIndiffList([[0], [2], [1]]),         // a > c > b
            Ranking::fromIndiffList([[0], [1], [2]]),         // a > b > c
            Ranking::fromIndiffList([[1], [2], [0]]),         // b > c > a
            Ranking::fromIndiffList([[2], [0], [1]]),         // c > a > b
        ], [1, 3, 4, 5], [0, 1, 2]);

        // Profile 2 (c-hover on first voter)
        $prof2 = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 2], [1]]),           // {a,c} > {b}
            Ranking::fromIndiffList([[0], [1], [2]]),         // a > b > c
            Ranking::fromIndiffList([[1], [2], [0]]),         // b > c > a
            Ranking::fromIndiffList([[2], [0], [1]]),         // c > a > b
        ], [1, 3, 4, 5], [0, 1, 2]);

        $splitIrv = IterativeMethods::splitIrvPut();

        // Profile 1: a and b tied at 4. PUT explores both paths → {b, c} are co-winners.
        $this->assertEqualsCanonicalizing([1, 2], $splitIrv($prof1), "Split-IRV PUT should elect b and c in profile 1");
        // Profile 2: a=3.5 (unique lowest), eliminated first. Then b beats c. b wins.
        $this->assertEquals([1], $splitIrv($prof2), "Split-IRV should elect b in profile 2");
    }

    /**
     * Test that Approval-IRV and Split-IRV agree on linear orders (truncated linear profiles).
     * Both should reduce to standard IRV behavior.
     */
    public function testApprovalAndSplitAgreeOnLinearOrders(): void
    {
        // Standard 3-candidate cycle - all linear orders
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0], [1], [2]]),
            Ranking::fromIndiffList([[1], [2], [0]]),
            Ranking::fromIndiffList([[2], [0], [1]]),
        ], null, [0, 1, 2]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        $approvalResult = $approvalIrv($prof);
        $splitResult = $splitIrv($prof);

        $this->assertEqualsCanonicalizing($approvalResult, $splitResult,
            "Approval-IRV and Split-IRV should agree on linear orders");
        $this->assertEqualsCanonicalizing([0, 1, 2], $approvalResult,
            "Both should return all candidates for a perfect cycle");
    }

    /**
     * Test PUT multiple winners for Approval-IRV.
     * Create a profile where different elimination paths lead to different winners.
     */
    public function testApprovalIrvPutMultipleWinners(): void
    {
        // 3 voters, 3 candidates with symmetric ties
        // v1: {a} > {b} > {c}
        // v2: {b} > {c} > {a}
        // v3: {c} > {a} > {b}
        // This is a Condorcet cycle with all candidates tied at 1 vote each.
        // Eliminating any one leads to a different winner.
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0], [1], [2]]),
            Ranking::fromIndiffList([[1], [2], [0]]),
            Ranking::fromIndiffList([[2], [0], [1]]),
        ], null, [0, 1, 2]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $result = $approvalIrv($prof);

        $this->assertEqualsCanonicalizing([0, 1, 2], $result,
            "PUT should return all candidates as winners in a symmetric cycle");
    }

    /**
     * Test PUT multiple winners for Split-IRV with ties in the top indifference class.
     */
    public function testSplitIrvPutMultipleWinners(): void
    {
        // Create a profile where ties lead to fractional scores and multiple winners
        // v1: {a,b} > {c}  - gives 0.5 to a, 0.5 to b
        // v2: {b,c} > {a}  - gives 0.5 to b, 0.5 to c
        // v3: {a,c} > {b}  - gives 0.5 to a, 0.5 to c
        // Scores: a=1, b=1, c=1 - all tied
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1], [2]]),
            Ranking::fromIndiffList([[1, 2], [0]]),
            Ranking::fromIndiffList([[0, 2], [1]]),
        ], null, [0, 1, 2]);

        $splitIrv = IterativeMethods::splitIrvPut();
        $result = $splitIrv($prof);

        $this->assertEqualsCanonicalizing([0, 1, 2], $result,
            "PUT should return all candidates when all have equal split scores");
    }

    /**
     * Test a simple case where Approval-IRV has multiple winners due to ties.
     */
    public function testApprovalIrvPutWithTiedTopClasses(): void
    {
        // v1: {a,b} > {c}  - approval: a=1, b=1
        // v2: {a,b} > {c}  - approval: a=1, b=1
        // v3: {c} > {a,b}  - approval: c=1
        // Round 1 scores: a=2, b=2, c=1. Eliminate c.
        // Round 2: a and b both have 2 votes, tied.
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1], [2]]),
            Ranking::fromIndiffList([[0, 1], [2]]),
            Ranking::fromIndiffList([[2], [0, 1]]),
        ], null, [0, 1, 2]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $result = $approvalIrv($prof);

        $this->assertEqualsCanonicalizing([0, 1], $result,
            "Both a and b should be winners when they're tied after elimination");
    }

    /**
     * Test edge case: single candidate.
     */
    public function testSingleCandidate(): void
    {
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0]]),
            Ranking::fromIndiffList([[0]]),
        ], null, [0]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        $this->assertEquals([0], $approvalIrv($prof));
        $this->assertEquals([0], $splitIrv($prof));
    }

    /**
     * Test edge case: all voters rank all candidates in one indifference class.
     */
    public function testAllCandidatesTied(): void
    {
        // Everyone ranks {a,b,c} together
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1, 2]]),
            Ranking::fromIndiffList([[0, 1, 2]]),
            Ranking::fromIndiffList([[0, 1, 2]]),
        ], null, [0, 1, 2]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        // Approval: all get 3 votes, all tied
        $this->assertEqualsCanonicalizing([0, 1, 2], $approvalIrv($prof));
        // Split: all get 1 vote (3 * 1/3), all tied
        $this->assertEqualsCanonicalizing([0, 1, 2], $splitIrv($prof));
    }

    /**
     * Test restricting to a subset of candidates (currCands parameter).
     */
    public function testRestrictedCandidates(): void
    {
        $prof = new ProfileWithTies([
            Ranking::fromIndiffList([[0, 1], [2], [3]]),
            Ranking::fromIndiffList([[2], [1], [0], [3]]),
            Ranking::fromIndiffList([[3], [2], [1], [0]]),
        ], null, [0, 1, 2, 3]);

        $approvalIrv = IterativeMethods::approvalIrvPut();
        $splitIrv = IterativeMethods::splitIrvPut();

        // Restrict to candidates 1 and 2 only
        $approvalResult = $approvalIrv($prof, [1, 2]);
        $splitResult = $splitIrv($prof, [1, 2]);

        // After restriction:
        // v1: {1} > {2}  (a is removed from top tie)
        // v2: {2} > {1}
        // v3: {2} > {1}
        // Both methods: 2 has majority (2 votes vs 1), so 2 wins
        $this->assertEquals([2], $approvalResult);
        $this->assertEquals([2], $splitResult);
    }
}
