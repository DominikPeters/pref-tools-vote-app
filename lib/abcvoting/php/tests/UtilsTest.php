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
use AbcVoting\Utils;

class UtilsTest extends TestCase
{
    public function testHamming(): void
    {
        $this->assertEquals(2, Utils::hamming([1, 2, 3], [1, 3, 4]));
        $this->assertEquals(5, Utils::hamming([1, 2, 3], [0, 4]));
        $this->assertEquals(0, Utils::hamming([1, 2], [2, 1]));
        $this->assertEquals(4, Utils::hamming([0, 1, 2, 3], [2, 3, 4, 5]));
    }

    public function testCombinations(): void
    {
        $iterable = [0, 1, 2];
        $r = 2;
        $result = iterator_to_array(Utils::combinations($iterable, $r));
        $this->assertCount(3, $result);
        $this->assertEquals([[0, 1], [0, 2], [1, 2]], $result);
    }
}
