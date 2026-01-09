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
use PrefVoting\ProportionalMethods;

class ProportionalMethodsTest extends TestCase
{
    public function testStvNbBasic(): void
    {
        // Simple profile where 0 should win 1 seat
        $prof = new Profile([
            [0, 1, 2],
            [1, 2, 0],
            [2, 0, 1]
        ], [11, 5, 5]);

        $stvNb = ProportionalMethods::stvNb(1);
        $winners = $stvNb($prof);
        $this->assertEquals([0], $winners);

        // 2 seats
        $stvNb2 = ProportionalMethods::stvNb(2);
        $winners2 = $stvNb2($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners2);
    }

    public function testStvWigBasic(): void
    {
        $prof = new Profile([
            [0, 1, 2],
            [1, 2, 0],
            [2, 0, 1]
        ], [11, 5, 5]);

        $stvWig = ProportionalMethods::stvWig(1);
        $winners = $stvWig($prof);
        $this->assertEquals([0], $winners);

        $stvWig2 = ProportionalMethods::stvWig(2);
        $winners2 = $stvWig2($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners2);
    }

    public function testStvScottishBasic(): void
    {
        $prof = new Profile([
            [0, 1, 2],
            [1, 2, 0],
            [2, 0, 1]
        ], [11, 5, 5]);

        $stvScottish = ProportionalMethods::stvScottish(1);
        $winners = $stvScottish($prof);
        $this->assertEquals([0], $winners);

        $stvScottish2 = ProportionalMethods::stvScottish(2);
        $winners2 = $stvScottish2($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners2);
    }

    public function testApprovalStvBasic(): void
    {
        $prof = new Profile([
            [0, 1, 2],
            [1, 2, 0],
            [2, 0, 1]
        ], [11, 5, 5]);

        $approvalStv = ProportionalMethods::approvalStv(1);
        $winners = $approvalStv($prof);
        $this->assertEquals([0], $winners);

        $approvalStv2 = ProportionalMethods::approvalStv(2);
        $winners2 = $approvalStv2($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners2);
    }

    public function testCpoStvBasic(): void
    {
        $prof = new Profile([
            [0, 1, 2],
            [1, 2, 0],
            [2, 0, 1]
        ], [11, 5, 5]);

        $cpoStv = ProportionalMethods::cpoStv(1);
        $winners = $cpoStv($prof);
        $this->assertEquals([0], $winners);

        // CPO-STV with 2 seats might take a bit longer but should work
        $cpoStv2 = ProportionalMethods::cpoStv(2);
        $winners2 = $cpoStv2($prof);
        $this->assertEqualsCanonicalizing([0, 1], $winners2);
    }
}
