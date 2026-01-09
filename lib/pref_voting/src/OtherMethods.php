<?php

/**
 * This file is based on a translation of the pref_voting python package
 * (https://github.com/voting-tools/pref_voting/)
 * Copyright (c) 2024 Wes Holliday and Eric Pacuit, MIT licensed.
 *
 * This file Copyright (c) 2026 Dominik Peters, also MIT licensed.
 */

declare(strict_types=1);

namespace PrefVoting;

/**
 * Implementations of other voting methods.
 */
class OtherMethods
{
    /**
     * Kemeny-Young method.
     * 
     * A Kemeny-Young ranking is a ranking that maximizes the sum of the margins 
     * of pairs of candidates in the ranking. Equivalently, a Kemeny-Young 
     * ranking is a ranking that minimizes the sum of the Kendall tau distances 
     * to the voters' rankings. The Kemeny-Young winners are the candidates 
     * that are ranked first by some Kemeny-Young ranking.
     */
    public static function kemenyYoung(): VotingMethod
    {
        return new VotingMethod(
            function (Profile|ProfileWithTies|MajorityGraph|MarginGraph|SupportGraph $edata, ?array $currCands = null): array {
                return SocialWelfareFunctions::kemenyYoung()->winners($edata, $currCands);
            },
            'Kemeny-Young'
        );
    }

    /**
     * @param array $items
     * @return \Generator
     */
    private static function permutations(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;
            return;
        }

        foreach ($items as $key => $item) {
            $remaining = $items;
            unset($remaining[$key]);
            foreach (self::permutations($remaining) as $perm) {
                array_unshift($perm, $item);
                yield $perm;
            }
        }
    }
}

// Pre-instantiated methods
$kemenyYoung = OtherMethods::kemenyYoung();
