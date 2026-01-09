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
use PrefVoting\Mapping;

class MappingTest extends TestCase
{
    private Mapping $simpleMapping;

    protected function setUp(): void
    {
        $this->simpleMapping = new Mapping([1 => 100, 2 => 200, 3 => 300]);
    }

    public function testInitialization(): void
    {
        $mapping = new Mapping([1 => 10, 2 => 20], [1, 2], [10, 20]);
        $this->assertEquals([1, 2], $mapping->domain);
        $this->assertEquals([10, 20], $mapping->codomain);
        $this->assertEquals(10, $mapping->val(1));
    }

    public function testVal(): void
    {
        $this->assertEquals(100, $this->simpleMapping->val(1));
        
        $this->expectException(\InvalidArgumentException::class);
        $this->simpleMapping->val(4);
    }

    public function testHasValue(): void
    {
        $this->assertTrue($this->simpleMapping->hasValue(1));
        $this->assertFalse($this->simpleMapping->hasValue(4));
    }

    public function testDefinedDomain(): void
    {
        $this->assertEquals([1, 2, 3], $this->simpleMapping->definedDomain());
        $mapping2 = new Mapping([1 => 10, 2 => 20], [0, 1, 2, 3]);
        $this->assertEquals([1, 2], $mapping2->definedDomain());
    }

    public function testInverseImage(): void
    {
        $this->assertEquals([1], $this->simpleMapping->inverseImage(100));
        $this->assertEquals([], $this->simpleMapping->inverseImage(400));
    }

    public function testImage(): void
    {
        $this->assertEquals([100, 200, 300], $this->simpleMapping->image());
        $this->assertEquals([100, 300], $this->simpleMapping->image([1, 3]));
    }

    public function testRange(): void
    {
        $this->assertEquals([100, 200, 300], $this->simpleMapping->range());
    }

    public function testAverage(): void
    {
        $this->assertEquals(200, $this->simpleMapping->average());
    }

    public function testMedian(): void
    {
        $this->assertEquals(200, $this->simpleMapping->median());
        
        $mapping2 = new Mapping([1 => 10, 2 => 20]);
        $this->assertEquals(15, $mapping2->median());
    }

    public function testCompare(): void
    {
        $this->assertEquals(-1, $this->simpleMapping->compare(1, 2));
        $this->assertEquals(0, $this->simpleMapping->compare(2, 2));
        $this->assertEquals(1, $this->simpleMapping->compare(2, 1));
        
        $mapping = new Mapping([1 => 10, 2 => 20], [1, 2, 3, 4]);
        $this->assertNull($mapping->compare(3, 1));
    }

    public function testExtendedCompare(): void
    {
        $mapping = new Mapping([1 => 10, 2 => 20], [1, 2, 3, 4]);
        $this->assertEquals(-1, $mapping->extendedCompare(1, 2));
        $this->assertEquals(0, $mapping->extendedCompare(2, 2));
        $this->assertEquals(1, $mapping->extendedCompare(2, 1));
        $this->assertEquals(-1, $mapping->extendedCompare(3, 1));
        $this->assertEquals(1, $mapping->extendedCompare(1, 3));
        $this->assertEquals(0, $mapping->extendedCompare(3, 4));
    }

    public function testSortedDomain(): void
    {
        $this->assertEquals([[3], [2], [1]], $this->simpleMapping->sortedDomain());
        $mapping = new Mapping([1 => 10, 2 => 10], [1, 2, 3, 4]);
        $this->assertEquals([[1, 2]], $mapping->sortedDomain());
        $this->assertEquals([[1, 2], [3, 4]], $mapping->sortedDomain(true));
    }
}
