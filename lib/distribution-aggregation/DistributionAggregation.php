<?php

/**
 * Distribution Aggregation Library
 *
 * Implements mechanisms for aggregating probability distributions from multiple voters
 * into a single consensus distribution. Includes the mean rule and several moving
 * phantom mechanisms (median, independent markets, ladder).
 *
 * Based on:
 * - Freeman, Pennock, Peters, Vaughan: "Truthful Aggregation of Budget Proposals"
 * - Freeman, Schmidt-Kraepelin: "Project-Fair and Truthful Mechanisms for Budget Aggregation"
 */

class DistributionAggregation
{
    public const RULES = [
        'mean' => [
            'name' => 'Mean Rule',
            'description' => 'Simple average of input distributions'
        ],
        'median' => [
            'name' => 'Median Rule',
            'description' => 'Moving phantom mechanism with uniform phantom progression'
        ],
        'independent_markets' => [
            'name' => 'Independent Markets',
            'description' => 'Moving phantom mechanism modeling independent market shares'
        ],
        'ladder' => [
            'name' => 'Ladder Rule',
            'description' => 'Moving phantom mechanism with ladder-shaped phantom progression'
        ],
    ];

    private const TOLERANCE = 1e-10;
    private const MAX_ITERATIONS = 100;

    /**
     * Compute distribution aggregation using the specified rule
     *
     * @param string $rule One of: 'mean', 'median', 'independent_markets', 'ladder'
     * @param array $distributions Array of distributions, each [optionId => points/fraction]
     * @return array Aggregated distribution [optionId => fraction]
     */
    public static function compute(string $rule, array $distributions): array
    {
        if (empty($distributions)) {
            return [];
        }

        // Normalize all input distributions
        $normalized = self::normalizeAll($distributions);

        return match ($rule) {
            'mean' => self::mean($normalized),
            'median' => self::movingPhantom($normalized, 'median'),
            'independent_markets' => self::movingPhantom($normalized, 'independent_markets'),
            'ladder' => self::movingPhantom($normalized, 'ladder'),
            default => throw new InvalidArgumentException("Unknown rule: {$rule}"),
        };
    }

    /**
     * Mean rule: simple coordinate-wise average
     *
     * @param array $distributions Normalized distributions
     * @return array Aggregated distribution
     */
    public static function mean(array $distributions): array
    {
        if (empty($distributions)) {
            return [];
        }

        $n = count($distributions);
        $result = [];

        // Get all option IDs from the first distribution
        $optionIds = array_keys(reset($distributions));

        foreach ($optionIds as $optionId) {
            $sum = 0.0;
            foreach ($distributions as $dist) {
                $sum += $dist[$optionId] ?? 0.0;
            }
            $result[$optionId] = $sum / $n;
        }

        return $result;
    }

    /**
     * Moving phantom mechanism
     *
     * @param array $distributions Normalized distributions
     * @param string $phantomType One of: 'median', 'independent_markets', 'ladder'
     * @return array Aggregated distribution
     */
    public static function movingPhantom(array $distributions, string $phantomType): array
    {
        if (empty($distributions)) {
            return [];
        }

        $n = count($distributions);
        $optionIds = array_keys(reset($distributions));
        $m = count($optionIds);

        // Edge case: 0 voters returns uniform distribution
        if ($n === 0) {
            $result = [];
            foreach ($optionIds as $optionId) {
                $result[$optionId] = 1.0 / $m;
            }
            return $result;
        }

        // Get phantom function based on type
        $phantomFn = match ($phantomType) {
            'median' => fn($t, $k, $n) => self::medianPhantom($t, $k, $n),
            'independent_markets' => fn($t, $k, $n) => self::independentMarketsPhantom($t, $k, $n),
            'ladder' => fn($t, $k, $n) => self::ladderPhantom($t, $k, $n),
            default => throw new InvalidArgumentException("Unknown phantom type: {$phantomType}"),
        };

        // Binary search for t* where sum of medians = 1
        $tStar = self::findTStar($distributions, $phantomFn);

        // Compute final output distribution at t*
        $phantomValues = self::computePhantomValues($tStar, $n, $phantomFn);
        $result = [];

        foreach ($optionIds as $optionId) {
            $voterValues = [];
            foreach ($distributions as $dist) {
                $voterValues[] = $dist[$optionId] ?? 0.0;
            }
            $result[$optionId] = self::generalizedMedian($phantomValues, $voterValues);
        }

        // Final normalization to correct small numerical errors
        $sum = array_sum($result);
        if ($sum > 0 && abs($sum - 1.0) > self::TOLERANCE) {
            foreach ($result as &$val) {
                $val /= $sum;
            }
        }

        return $result;
    }

    /**
     * Binary search to find t* where sum of medians = 1
     */
    private static function findTStar(array $distributions, callable $phantomFn): float
    {
        $lo = 0.0;
        $hi = 1.0;

        for ($iter = 0; $iter < self::MAX_ITERATIONS; $iter++) {
            if ($hi - $lo < self::TOLERANCE) {
                break;
            }

            $mid = ($lo + $hi) / 2.0;
            $sum = self::computeSumOfMedians($mid, $distributions, $phantomFn);

            if ($sum < 1.0) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return ($lo + $hi) / 2.0;
    }

    /**
     * Compute sum of medians for all options at time t
     */
    private static function computeSumOfMedians(float $t, array $distributions, callable $phantomFn): float
    {
        $n = count($distributions);
        $optionIds = array_keys(reset($distributions));

        // Pre-compute phantom values (only depend on t, not on option)
        $phantomValues = self::computePhantomValues($t, $n, $phantomFn);

        $sum = 0.0;
        foreach ($optionIds as $optionId) {
            $voterValues = [];
            foreach ($distributions as $dist) {
                $voterValues[] = $dist[$optionId] ?? 0.0;
            }
            $sum += self::generalizedMedian($phantomValues, $voterValues);
        }

        return $sum;
    }

    /**
     * Compute all phantom values at time t
     *
     * @return array Array of n+1 phantom values
     */
    private static function computePhantomValues(float $t, int $n, callable $phantomFn): array
    {
        $phantoms = [];
        for ($k = 0; $k <= $n; $k++) {
            $phantoms[] = $phantomFn($t, $k, $n);
        }
        return $phantoms;
    }

    /**
     * Compute generalized median of phantom values and voter values
     *
     * The median is taken over 2n+1 values: n+1 phantoms and n voters
     */
    private static function generalizedMedian(array $phantomValues, array $voterValues): float
    {
        $allValues = array_merge($phantomValues, $voterValues);
        sort($allValues);

        $count = count($allValues);
        $midIndex = intdiv($count, 2);

        // For odd count (which we have: 2n+1), return the middle element
        return $allValues[$midIndex];
    }

    /**
     * Median phantom function: f_k(t) = clamp(t*(n+1) - k, 0, 1)
     *
     * All phantoms start at 0, and one by one move to 1.
     * At each t, at most one phantom is strictly between 0 and 1.
     */
    private static function medianPhantom(float $t, int $k, int $n): float
    {
        $value = $t * ($n + 1) - $k;
        return max(0.0, min(1.0, $value));
    }

    /**
     * Independent Markets phantom function: f_k(t) = min(t*(n-k), 1)
     *
     * Phantoms rise at different speeds, with f_0 rising fastest.
     * Note: n is the number of voters, phantoms are indexed k=0..n
     */
    private static function independentMarketsPhantom(float $t, int $k, int $n): float
    {
        return min($t * ($n - $k), 1.0);
    }

    /**
     * Ladder phantom function: f_k(t) = max(t - k/n, 0)
     *
     * Phantoms are offset versions of each other, creating a ladder pattern.
     */
    private static function ladderPhantom(float $t, int $k, int $n): float
    {
        if ($n === 0) {
            return $t;
        }
        return max($t - (float)$k / $n, 0.0);
    }

    /**
     * Normalize all distributions to sum to 1
     */
    private static function normalizeAll(array $distributions): array
    {
        $normalized = [];
        foreach ($distributions as $key => $dist) {
            $normalized[$key] = self::normalize($dist);
        }
        return $normalized;
    }

    /**
     * Normalize a single distribution to sum to 1
     */
    private static function normalize(array $distribution): array
    {
        $sum = array_sum($distribution);

        if ($sum <= 0) {
            // Return uniform distribution if sum is 0 or negative
            $m = count($distribution);
            $uniform = 1.0 / max($m, 1);
            return array_fill_keys(array_keys($distribution), $uniform);
        }

        $normalized = [];
        foreach ($distribution as $key => $value) {
            $normalized[$key] = $value / $sum;
        }
        return $normalized;
    }

    /**
     * Get all available rules
     */
    public static function getRules(): array
    {
        return self::RULES;
    }
}
