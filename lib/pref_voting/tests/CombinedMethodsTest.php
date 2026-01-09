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
use PrefVoting\CombinedMethods;
use PHPUnit\Framework\Attributes\DataProvider;

class CombinedMethodsTest extends TestCase
{
    private Profile $condorcetCycle;
    private Profile $linearProfile0;

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
    }

    public function testBlacks(): void
    {
        $blacks = CombinedMethods::blacks();
        $this->assertEqualsCanonicalizing([0, 1, 2], $blacks($this->condorcetCycle));
        $this->assertEqualsCanonicalizing([0], $blacks($this->linearProfile0));
    }

    public function testCondorcetPlurality(): void
    {
        $cp = CombinedMethods::condorcetPlurality();
        $this->assertEqualsCanonicalizing([0, 1, 2], $cp($this->condorcetCycle));
        $this->assertEqualsCanonicalizing([0], $cp($this->linearProfile0));
    }

    public function testSmithMinimax(): void
    {
        $sm = CombinedMethods::smithMinimax();
        $this->assertEqualsCanonicalizing([0, 1, 2], $sm($this->condorcetCycle));
        $this->assertEqualsCanonicalizing([0], $sm($this->linearProfile0));
    }
}
