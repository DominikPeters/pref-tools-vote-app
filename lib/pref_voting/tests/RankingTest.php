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
use PrefVoting\Ranking;
use PHPUnit\Framework\Attributes\DataProvider;

class RankingTest extends TestCase
{
    public function testRankingInitialization(): void
    {
        $rmap = [0 => 1, 1 => 3, 2 => 2];
        $cmap = [0 => "Alice", 1 => "Bob", 2 => "Charlie"];
        $rank = new Ranking($rmap, $cmap);
        $this->assertEquals($rmap, $rank->rmap);
        $this->assertEquals($cmap, $rank->cmap);
    }

    #[DataProvider('rankingCandsProvider')]
    public function testRankingCands(array $rmap, array $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->getCands());
    }

    public static function rankingCandsProvider(): array
    {
        return [
            [[0 => 1, 1 => 3, 2 => 2], [0, 1, 2]],
            [[0 => 1, 1 => 1, 2 => 2], [0, 1, 2]],
            [[0 => 1, 1 => 3], [0, 1]],
        ];
    }

    #[DataProvider('rankingRanksProvider')]
    public function testRankingRanks(array $rmap, array $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->getRanks());
    }

    public static function rankingRanksProvider(): array
    {
        return [
            [[0 => 1, 1 => 3, 2 => 2], [1, 2, 3]],
            [[0 => 1, 1 => 1, 2 => 5], [1, 5]],
            [[0 => 1, 1 => 3], [1, 3]],
        ];
    }

    #[DataProvider('candsAtRankProvider')]
    public function testCandsAtRank(array $rmap, int $r, array $expected): void
    {
        $rank = new Ranking($rmap);
        $actual = $rank->candsAtRank($r);
        sort($actual);
        sort($expected);
        $this->assertEquals($expected, $actual);
    }

    public static function candsAtRankProvider(): array
    {
        return [
            [[0 => 1, 1 => 3, 2 => 2], 1, [0]],
            [[0 => 1, 1 => 1, 2 => 5], 1, [0, 1]],
            [[0 => 1, 1 => 1, 2 => 5], 2, []],
            [[0 => 1, 1 => 1, 2 => 5], 5, [2]],
            [[0 => 1, 1 => 3], 2, []],
            [[0 => 1, 1 => 3], 3, [1]],
        ];
    }

    #[DataProvider('strictPrefProvider')]
    public function testStrictPref(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->strictPref($c1, $c2));
    }

    public static function strictPrefProvider(): array
    {
        return [
            [[0 => 1, 1 => 3, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 3, 2 => 2], 1, 0, false],
            [[0 => 1, 1 => 3], 0, 1, true],
            [[0 => 1, 1 => 3], 2, 3, false],
            [[0 => 1, 1 => 3], 0, 2, false],
        ];
    }

    #[DataProvider('extendedStrictPrefProvider')]
    public function testExtendedStrictPref(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->extendedStrictPref($c1, $c2));
    }

    public static function extendedStrictPrefProvider(): array
    {
        return [
            [[0 => 1, 1 => 3, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 3, 2 => 2], 1, 0, false],
            [[0 => 1, 1 => 3], 0, 1, true],
            [[0 => 1, 1 => 3], 2, 3, false],
            [[0 => 1, 1 => 3], 0, 2, true],
        ];
    }

    #[DataProvider('indiffProvider')]
    public function testIndiff(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->indiff($c1, $c2));
    }

    public static function indiffProvider(): array
    {
        return [
            [[0 => 1, 1 => 1, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 0, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 2, false],
            [[0 => 1, 1 => 3], 0, 2, false],
            [[0 => 1, 1 => 3], 2, 3, false],
        ];
    }

    #[DataProvider('extendedIndiffProvider')]
    public function testExtendedIndiff(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->extendedIndiff($c1, $c2));
    }

    public static function extendedIndiffProvider(): array
    {
        return [
            [[0 => 1, 1 => 1, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 0, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 2, false],
            [[0 => 1, 1 => 3], 0, 2, false],
            [[0 => 1, 1 => 3], 2, 3, true],
        ];
    }

    #[DataProvider('weakPrefProvider')]
    public function testWeakPref(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->weakPref($c1, $c2));
    }

    public static function weakPrefProvider(): array
    {
        return [
            [[0 => 1, 1 => 1, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 0, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 2, true],
            [[0 => 1, 1 => 1, 2 => 2], 2, 1, false],
            [[0 => 1, 1 => 3], 0, 2, false],
            [[0 => 1, 1 => 3], 2, 3, false],
        ];
    }

    #[DataProvider('extendedWeakPrefProvider')]
    public function testExtendedWeakPref(array $rmap, $c1, $c2, bool $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->extendedWeakPref($c1, $c2));
    }

    public static function extendedWeakPrefProvider(): array
    {
        return [
            [[0 => 1, 1 => 1, 2 => 2], 0, 1, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 0, true],
            [[0 => 1, 1 => 1, 2 => 2], 1, 2, true],
            [[0 => 1, 1 => 1, 2 => 2], 2, 1, false],
            [[0 => 1, 1 => 3], 0, 2, true],
            [[0 => 1, 1 => 3], 2, 3, true],
        ];
    }

    public function testRemoveCand(): void
    {
        $linearRanking = new Ranking([0 => 1, 1 => 3, 2 => 2]);
        
        $r2 = $linearRanking->removeCand(0);
        $this->assertEquals([1, 2], $r2->getCands());
        $this->assertEquals([2, 3], $r2->getRanks());

        $r2 = $linearRanking->removeCand(1);
        $this->assertEquals([0, 2], $r2->getCands());
        $this->assertEquals([1, 2], $r2->getRanks());

        $rankingWithTie = new Ranking([0 => 1, 1 => 1, 2 => 5]);
        $r2 = $rankingWithTie->removeCand(0);
        $this->assertEquals([1, 2], $r2->getCands());
        $this->assertEquals([1, 5], $r2->getRanks());
    }

    #[DataProvider('firstProvider')]
    public function testFirst(array $rmap, array $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->first());
    }

    public static function firstProvider(): array
    {
        return [
            [[0 => 1, 1 => 2, 2 => 2], [0]],
            [[0 => 1, 1 => 1, 2 => 2], [0, 1]],
            [[0 => 3, 1 => 3, 2 => 5], [0, 1]],
            [[0 => 3], [0]],
            [[], []],
        ];
    }

    #[DataProvider('lastProvider')]
    public function testLast(array $rmap, array $expected): void
    {
        $rank = new Ranking($rmap);
        $this->assertEquals($expected, $rank->last());
    }

    public static function lastProvider(): array
    {
        return [
            [[0 => 1, 1 => 2, 2 => 2], [1, 2]],
            [[0 => 1, 1 => 1, 2 => 2], [2]],
            [[0 => 3, 1 => 3, 2 => 5], [2]],
            [[0 => 3, 1 => 5, 2 => 5], [1, 2]],
            [[0 => 3], [0]],
            [[], []],
        ];
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue((new Ranking([]))->isEmpty());
        $this->assertFalse((new Ranking([0 => 1, 1 => 1]))->isEmpty());
    }

    public function testHasTie(): void
    {
        $this->assertFalse((new Ranking([0 => 1, 1 => 3, 2 => 2]))->hasTie());
        $this->assertTrue((new Ranking([0 => 1, 1 => 1, 2 => 5]))->hasTie());
        $this->assertFalse((new Ranking([0 => 1, 1 => 3]))->hasTie());
        $this->assertFalse((new Ranking([]))->hasTie());
    }

    public function testIsLinear(): void
    {
        $linearRanking = new Ranking([0 => 1, 1 => 3, 2 => 2]);
        $rankingWithTie = new Ranking([0 => 1, 1 => 1, 2 => 5]);
        $truncatedRanking = new Ranking([0 => 1, 1 => 3]);

        $this->assertTrue($linearRanking->isLinear(3));
        $this->assertFalse($linearRanking->isLinear(2));
        $this->assertFalse($rankingWithTie->isLinear(3));
        $this->assertFalse($truncatedRanking->isLinear(3));
        $this->assertTrue($truncatedRanking->isLinear(2));
    }

    public function testToLinear(): void
    {
        $linearRanking = new Ranking([0 => 1, 1 => 3, 2 => 2]);
        $rankingWithTie = new Ranking([0 => 1, 1 => 1, 2 => 5]);
        $truncatedRanking = new Ranking([0 => 1, 1 => 3]);

        $this->assertEquals([0, 2, 1], $linearRanking->toLinear());
        $this->assertNull($rankingWithTie->toLinear());
        $this->assertEquals([0, 1], $truncatedRanking->toLinear());
        $this->assertEquals([], (new Ranking([]))->toLinear());
    }

    public function testToIndiffList(): void
    {
        $r = new Ranking([0 => 1, 1 => 1, 2 => 2]);
        $this->assertEquals([[0, 1], [2]], $r->toIndiffList());
    }

    #[DataProvider('normalizeRanksProvider')]
    public function testNormalizeRanks(array $rmap, array $expected): void
    {
        $r = new Ranking($rmap);
        $r->normalizeRanks();
        $this->assertEquals($expected, $r->rmap);
    }

    public static function normalizeRanksProvider(): array
    {
        return [
            [[0 => 1, 1 => 2, 2 => 3], [0 => 1, 1 => 2, 2 => 3]],
            [[0 => 1000, 1 => -10, 2 => 0], [0 => 3, 1 => 1, 2 => 2]],
            [[0 => 1, 1 => 5, 2 => 5, 3 => 3], [0 => 1, 1 => 3, 2 => 3, 3 => 2]],
            [[0 => 1, 1 => 1, 2 => 4, 3 => 4], [0 => 1, 1 => 1, 2 => 2, 3 => 2]],
        ];
    }

    #[DataProvider('strProvider')]
    public function testStr(array $rmap, string $expected): void
    {
        $this->assertEquals($expected, (string)(new Ranking($rmap)));
    }

    public static function strProvider(): array
    {
        return [
            [[0 => 1, 1 => 2, 2 => 3, 3 => 3], "0 1 ( 2  3 )"],
            [[0 => 1, 1 => 1, 2 => 2], "( 0  1 ) 2"],
            [[0 => 1, 1 => 2, 2 => 3], "0 1 2"],
        ];
    }

    #[DataProvider('equalsProvider')]
    public function testEquals(array $rmap1, array $rmap2, bool $expected): void
    {
        $r1 = new Ranking($rmap1);
        $r2 = new Ranking($rmap2);
        $this->assertEquals($expected, $r1->equals($r2));
    }

    public static function equalsProvider(): array
    {
        return [
            [[0 => 1, 1 => 2, 2 => 3, 3 => 3], [0 => 1, 1 => 2, 2 => 3, 3 => 3], true],
            [[0 => 1, 1 => 2, 2 => 3, 3 => 3], [0 => 2, 1 => 1, 2 => 3, 3 => 3], false],
            [[0 => 1, 1 => 2, 2 => 3], [0 => -10, 1 => 20, 2 => 300], true],
            [[0 => 1, 1 => 1], [1 => 1, 0 => 1], true],
        ];
    }

    public function testHasSkippedRank(): void
    {
        $linearRanking = new Ranking([0 => 1, 1 => 3, 2 => 2]);
        $this->assertFalse($linearRanking->hasSkippedRank());
        $this->assertTrue((new Ranking([0 => 1, 1 => 4, 2 => 4]))->hasSkippedRank());
        $this->assertFalse((new Ranking([0 => 1, 1 => 1, 2 => 2]))->hasSkippedRank());
        $this->assertTrue((new Ranking([0 => 1, 1 => 1, 2 => 3]))->hasSkippedRank());
    }

    public function testHasOvervote(): void
    {
        $this->assertFalse((new Ranking([0 => 1, 1 => 3, 2 => 2]))->hasOvervote());
        $this->assertTrue((new Ranking([0 => 1, 1 => 1, 2 => 5]))->hasOvervote());
    }

    public function testTruncateOvervote(): void
    {
        $r = new Ranking([0 => 1, 1 => 2, 2 => 3, 3 => 3]);
        $r->truncateOvervote();
        $this->assertEquals([0 => 1, 1 => 2], $r->rmap);

        $r = new Ranking([0 => 1, 1 => 1, 2 => 2]);
        $r->truncateOvervote();
        $this->assertEquals([], $r->rmap);

        $r = new Ranking([0 => 1, 1 => 2, 2 => 5, 3 => 3]);
        $r->truncateOvervote();
        $this->assertEquals([0 => 1, 1 => 2, 2 => 5, 3 => 3], $r->rmap);
    }

    public function testAAdom(): void
    {
        $r = new Ranking([0 => 1, 1 => 3, 2 => 1, 3 => 4]);
        $this->assertTrue($r->AAdom([0, 2], [1, 3]));
        $this->assertTrue($r->AAdom([0, 1], [1, 3]));
        $this->assertFalse($r->AAdom([0, 1], [2, 3]));
        $this->assertTrue($r->AAdom([0], [1, 2, 3]));
        $this->assertTrue($r->AAdom([0], [0, 1, 2, 3]));
        $this->assertFalse($r->AAdom([0, 3], [0, 1]));
    }

    public function testStrongDom(): void
    {
        $r = new Ranking([0 => 1, 1 => 3, 2 => 1, 3 => 4]);
        $this->assertTrue($r->strongDom([0, 2], [1, 3]));
        $this->assertTrue($r->strongDom([0, 1], [1, 3]));
        $this->assertFalse($r->strongDom([0, 1], [2, 3]));
        $this->assertFalse($r->strongDom([0], [1, 2, 3]));
        $this->assertFalse($r->strongDom([0], [0, 1, 2, 3]));
        $this->assertFalse($r->strongDom([0, 3], [0, 1]));
    }

    public function testWeakDom(): void
    {
        $r = new Ranking([0 => 1, 1 => 3, 2 => 1, 3 => 4]);
        $this->assertTrue($r->weakDom([0, 2], [1, 3]));
        $this->assertTrue($r->weakDom([0, 1], [1, 3]));
        $this->assertFalse($r->weakDom([0, 1], [2, 3]));
        $this->assertTrue($r->weakDom([0], [1, 2, 3]));
        $this->assertTrue($r->weakDom([0], [0, 1, 2, 3]));
        $this->assertFalse($r->weakDom([0, 3], [0, 1]));
    }

    public function testGetItem(): void
    {
        $r = new Ranking([0 => 1, 1 => 2, 2 => 3]);
        $this->assertEquals(0, $r->getItem(0));
        $this->assertEquals(1, $r->getItem(1));
        $this->assertEquals(2, $r->getItem(2));
        
        $r = new Ranking([0 => 1, 1 => 1, 2 => 3]);
        $actual = $r->getItem(0);
        sort($actual);
        $this->assertEquals([0, 1], $actual);
        $this->assertEquals(2, $r->getItem(1));
    }
}