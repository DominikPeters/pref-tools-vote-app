<?php

/**
 * This file is based on a translation of the abcvoting python package
 * (https://github.com/martinlackner/abcvoting)
 * Copyright (c) 2019 Martin Lackner, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace AbcVoting;

class Rule
{
    public string $ruleId;
    public string $shortName;
    public string $longName;
    public bool $resolute;

    public function __construct(string $ruleId, string $shortName, string $longName, bool $resolute = false)
    {
        $this->ruleId = $ruleId;
        $this->shortName = $shortName;
        $this->longName = $longName;
        $this->resolute = $resolute;
    }

    public static function getMainRules(): array
    {
        return [
            new Rule('av', 'AV', 'Approval Voting', false),
            new Rule('sav', 'SAV', 'Satisfaction Approval Voting', false),
            new Rule('pav', 'PAV', 'Proportional Approval Voting', false),
            new Rule('slav', 'SLAV', 'Sainte-Laguë Approval Voting', false),
            new Rule('cc', 'CC', 'Chamberlin-Courant', false),
            new Rule('seqpav', 'Seq-PAV', 'Sequential PAV', true),
            new Rule('seqcc', 'Seq-CC', 'Sequential CC', true),
            new Rule('seqphragmen', 'Seq-Phragmén', 'Sequential Phragmén', true),
            new Rule('equal-shares', 'Rule X', 'Method of Equal Shares', true),
            new Rule('monroe-greedy', 'Greedy Monroe', 'Greedy Monroe', true),
            new Rule('phragmen-enestroem', 'Phragmén-Enestroem', 'Phragmén-Enestroem', false),
            new Rule('mav', 'MAV', 'Minimax AV', false),
        ];
    }
}
