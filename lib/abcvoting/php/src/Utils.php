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

class Utils
{
    /**
     * Hamming distance between two sets (arrays of integers).
     *
     * @param int[] $set1
     * @param int[] $set2
     * @return int
     */
    public static function hamming(array $set1, array $set2): int
    {
        $s1 = array_flip($set1);
        $s2 = array_flip($set2);
        $diff1 = array_diff_key($s1, $s2);
        $diff2 = array_diff_key($s2, $s1);
        return count($diff1) + count($diff2);
    }

    /**
     * Python's itertools.combinations equivalent.
     *
     * @param array $iterable
     * @param int $r
     * @return \Generator
     */
    public static function combinations(array $iterable, int $r): \Generator
    {
        $n = count($iterable);
        if ($r > $n) {
            return;
        }
        $indices = range(0, $r - 1);
        yield array_values(array_intersect_key($iterable, array_flip($indices)));

        while (true) {
            $found = false;
            for ($i = $r - 1; $i >= 0; $i--) {
                if ($indices[$i] != $i + $n - $r) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return;
            }
            $indices[$i]++;
            for ($j = $i + 1; $j < $r; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
            yield array_values(array_intersect_key($iterable, array_flip($indices)));
        }
    }

    /**
     * Nicely format a set of candidates.
     *
     * @param int[] $candset
     * @param string[]|null $candNames
     * @return string
     */
    public static function strSetOfCandidates(array $candset, ?array $candNames = null): string
    {
        if ($candNames === null) {
            $named = array_map('strval', $candset);
            sort($named);
        } else {
            $named = [];
            foreach ($candset as $cand) {
                $named[] = (string)$candNames[$cand];
            }
            sort($named);
        }
        return '{' . implode(', ', $named) . '}';
    }
}
