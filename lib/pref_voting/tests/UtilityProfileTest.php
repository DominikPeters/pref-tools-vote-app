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
use PrefVoting\UtilityProfile;
use PrefVoting\UtilityMethods;
use PrefVoting\Utility;

class UtilityProfileTest extends TestCase
{
    private UtilityProfile $uprof;

    protected function setUp(): void
    {
        $u1 = new Utility([0 => 1.0, 1 => 0.5, 2 => 0.0]);
        $u2 = new Utility([0 => 0.0, 1 => 0.5, 2 => 1.0]);
        $u3 = new Utility([0 => 0.6, 1 => 0.7, 2 => 0.0]);
        $this->uprof = new UtilityProfile([$u1, $u2, $u3], [2, 1, 3]);
    }

    public function testInitialization(): void
    {
        $this->assertEquals(3, $this->uprof->getNumCands());
        $this->assertEquals(6, $this->uprof->numVoters);
        $this->assertEquals([0, 1, 2], $this->uprof->domain);
    }

    public function testUtilSum(): void
    {
        // 0: 1.0*2 + 0.0*1 + 0.6*3 = 2.0 + 1.8 = 3.8
        // 1: 0.5*2 + 0.5*1 + 0.7*3 = 1.0 + 0.5 + 2.1 = 3.6
        // 2: 0.0*2 + 1.0*1 + 0.0*3 = 1.0
        $this->assertEqualsWithDelta(3.8, $this->uprof->utilSum(0), 1e-12);
        $this->assertEqualsWithDelta(3.6, $this->uprof->utilSum(1), 1e-12);
        $this->assertEqualsWithDelta(1.0, $this->uprof->utilSum(2), 1e-12);
    }

    public function testUtilAvg(): void
    {
        $this->assertEqualsWithDelta(3.8 / 6, $this->uprof->utilAvg(0), 1e-12);
    }

    public function testUtilMax(): void
    {
        $this->assertEquals(1.0, $this->uprof->utilMax(0));
        $this->assertEquals(1.0, $this->uprof->utilMax(2));
    }

    public function testUtilMin(): void
    {
        $this->assertEquals(0.0, $this->uprof->utilMin(0));
        $this->assertEquals(0.0, $this->uprof->utilMin(2));
    }

    public function testSumUtilitarian(): void
    {
        $winners = UtilityMethods::sumUtilitarian()($this->uprof);
        $this->assertEquals([0], $winners);
    }

    public function testMaximin(): void
    {
        $winners = UtilityMethods::maximin()($this->uprof);
        // mins: 0: 0.0, 1: 0.5, 2: 0.0
        $this->assertEquals([1], $winners);
    }

    public function testLexicographicMaximin(): void
    {
        $u1 = new Utility([0 => 1.0, 1 => 0.5]);
        $u2 = new Utility([0 => 0.5, 1 => 1.0]);
        $prof = new UtilityProfile([$u1, $u2], [1, 1]);
        $winners = UtilityMethods::lexicographicMaximin()($prof);
        $this->assertEquals([0, 1], $winners);
    }
}
