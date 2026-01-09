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
use AbcVoting\Scores;

use PHPUnit\Framework\Attributes\DataProvider;

class ScoresTest extends TestCase
{
    #[DataProvider('thieleScoreProvider')]
    public function testThieleScores(string $scorefctId, float $expectedScore): void
    {
        $profile = new Profile(8);
        $approvalSets = [[0, 1], [1], [1, 3], [4], [1, 2, 3, 4, 5], [1, 5, 3], [0, 1, 2, 4, 5]];
        $profile->addVoters($approvalSets);
        
        $committee = [6, 7];
        $this->assertEqualsWithDelta(0.0, Scores::thieleScore($scorefctId, $profile, $committee), 1e-12);
        
        $committee = [1, 2, 3, 4];
        $this->assertEqualsWithDelta($expectedScore, Scores::thieleScore($scorefctId, $profile, $committee), 1e-12);
    }

    public static function thieleScoreProvider(): array
    {
        return [
            ['pav', 119/12.0],
            ['av', 14.0],
            ['slav', 932/105.0],
            ['cc', 7.0],
            ['geom2', 77/8.0],
        ];
    }
}
