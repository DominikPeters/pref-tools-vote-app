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
use PrefVoting\GradeProfile;
use PrefVoting\GradeMethods;
use PrefVoting\Grade;

class GradeProfileTest extends TestCase
{
    private GradeProfile $gprof;

    protected function setUp(): void
    {
        $g1 = new Grade([0 => 5, 1 => 3, 2 => 1], [0, 1, 2, 3, 4, 5]);
        $g2 = new Grade([0 => 1, 1 => 4, 2 => 2], [0, 1, 2, 3, 4, 5]);
        $this->gprof = new GradeProfile([$g1, $g2], [0, 1, 2, 3, 4, 5], [2, 3]);
    }

    public function testInitialization(): void
    {
        $this->assertEquals(3, count($this->gprof->candidates));
        $this->assertEquals(5, $this->gprof->numVoters);
        $this->assertTrue($this->gprof->canSumGrades);
    }

    public function testSum(): void
    {
        // 0: 5*2 + 1*3 = 10 + 3 = 13
        // 1: 3*2 + 4*3 = 6 + 12 = 18
        // 2: 1*2 + 2*3 = 2 + 6 = 8
        $this->assertEquals(13, $this->gprof->sum(0));
        $this->assertEquals(18, $this->gprof->sum(1));
        $this->assertEquals(8, $this->gprof->sum(2));
    }

    public function testAvg(): void
    {
        $this->assertEquals(13 / 5, $this->gprof->avg(0));
    }

    public function testMax(): void
    {
        $this->assertEquals(5, $this->gprof->max(0));
        $this->assertEquals(4, $this->gprof->max(1));
    }

    public function testMedian(): void
    {
        // 1: grades are [3, 3, 4, 4, 4]. Median is 4.
        $this->assertEquals(4, $this->gprof->median(1));
        // 0: grades are [5, 5, 1, 1, 1]. Sorted: [1, 1, 1, 5, 5]. Median is 1.
        $this->assertEquals(1, $this->gprof->median(0));
    }

    public function testScoreVoting(): void
    {
        $winners = GradeMethods::scoreVoting('sum')($this->gprof);
        $this->assertEquals([1], $winners);
    }

    public function testMajorityJudgement(): void
    {
        $g1 = new Grade([0 => 4, 1 => 3], [0, 1, 2, 3, 4, 5]);
        $g2 = new Grade([0 => 3, 1 => 4], [0, 1, 2, 3, 4, 5]);
        $prof = new GradeProfile([$g1, $g2], [0, 1, 2, 3, 4, 5], [1, 1]);
        // both have median 3 (lower median of [3, 4]).
        // MJ should break tie.
        $winners = GradeMethods::majorityJudgement()($prof);
        $this->assertEquals([0, 1], $winners);
    }
}
