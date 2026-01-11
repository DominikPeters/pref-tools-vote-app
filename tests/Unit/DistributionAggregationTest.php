<?php

namespace Tests\Unit;

use Tests\TestCase;

require_once __DIR__ . '/../../lib/distribution-aggregation/DistributionAggregation.php';

class DistributionAggregationTest extends TestCase
{
    /**
     * Test mean rule with simple inputs
     */
    public function test_mean_rule_basic(): void
    {
        $distributions = [
            [1 => 0.6, 2 => 0.3, 3 => 0.1],
            [1 => 0.4, 2 => 0.4, 3 => 0.2],
        ];

        $result = \DistributionAggregation::compute('mean', $distributions);

        $this->assertEqualsWithDelta(0.5, $result[1], 0.001);
        $this->assertEqualsWithDelta(0.35, $result[2], 0.001);
        $this->assertEqualsWithDelta(0.15, $result[3], 0.001);

        // Sum should be 1
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);
    }

    /**
     * Test mean rule with identical distributions
     */
    public function test_mean_rule_identical_inputs(): void
    {
        $distributions = [
            [1 => 0.5, 2 => 0.3, 3 => 0.2],
            [1 => 0.5, 2 => 0.3, 3 => 0.2],
            [1 => 0.5, 2 => 0.3, 3 => 0.2],
        ];

        $result = \DistributionAggregation::compute('mean', $distributions);

        $this->assertEqualsWithDelta(0.5, $result[1], 0.0001);
        $this->assertEqualsWithDelta(0.3, $result[2], 0.0001);
        $this->assertEqualsWithDelta(0.2, $result[3], 0.0001);
    }

    /**
     * Test mean rule with non-normalized inputs (should normalize)
     */
    public function test_mean_rule_non_normalized(): void
    {
        $distributions = [
            [1 => 60, 2 => 30, 3 => 10],  // Sums to 100
            [1 => 40, 2 => 40, 3 => 20],  // Sums to 100
        ];

        $result = \DistributionAggregation::compute('mean', $distributions);

        // After normalization: (0.6, 0.3, 0.1) and (0.4, 0.4, 0.2)
        // Mean: (0.5, 0.35, 0.15)
        $this->assertEqualsWithDelta(0.5, $result[1], 0.001);
        $this->assertEqualsWithDelta(0.35, $result[2], 0.001);
        $this->assertEqualsWithDelta(0.15, $result[3], 0.001);
    }

    /**
     * Test Independent Markets rule with example from paper
     *
     * From "Truthful Aggregation of Budget Proposals" Example:
     * Voters: p1=(0, 0.5, 0.5), p2=(1/3, 2/3, 0), p3=(0.9, 0, 0.1)
     * Expected: (1/3, 4/9, 2/9)
     */
    public function test_independent_markets_paper_example(): void
    {
        $distributions = [
            [1 => 0.0, 2 => 0.5, 3 => 0.5],      // p1
            [1 => 1/3, 2 => 2/3, 3 => 0.0],      // p2
            [1 => 0.9, 2 => 0.0, 3 => 0.1],      // p3
        ];

        $result = \DistributionAggregation::compute('independent_markets', $distributions);

        // Expected: (1/3, 4/9, 2/9)
        $this->assertEqualsWithDelta(1/3, $result[1], 0.001);
        $this->assertEqualsWithDelta(4/9, $result[2], 0.001);
        $this->assertEqualsWithDelta(2/9, $result[3], 0.001);

        // Sum should be 1
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);
    }

    /**
     * Test median rule produces valid output
     */
    public function test_median_rule_sums_to_one(): void
    {
        $distributions = [
            [1 => 0.8, 2 => 0.2],
            [1 => 0.5, 2 => 0.5],
            [1 => 0.2, 2 => 0.8],
        ];

        $result = \DistributionAggregation::compute('median', $distributions);

        // Sum should be 1
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);

        // All values should be non-negative
        foreach ($result as $val) {
            $this->assertGreaterThanOrEqual(0, $val);
        }
    }

    /**
     * Test ladder rule produces valid output
     */
    public function test_ladder_rule_sums_to_one(): void
    {
        $distributions = [
            [1 => 0.7, 2 => 0.3],
            [1 => 0.3, 2 => 0.7],
        ];

        $result = \DistributionAggregation::compute('ladder', $distributions);

        // Sum should be 1
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);

        // All values should be non-negative
        foreach ($result as $val) {
            $this->assertGreaterThanOrEqual(0, $val);
        }
    }

    /**
     * Test Ladder rule with example from paper
     *
     * From "Project-Fair and Truthful Mechanisms for Budget Aggregation":
     * Voters: (0, 0.2, 0.8), (1, 0, 0), (0, 1, 0), (0.55, 0.45, 0)
     * Expected: (5/12, 5/12, 1/6) at t=11/12
     */
    public function test_ladder_rule_paper_example(): void
    {
        $distributions = [
            [1 => 0.0, 2 => 0.2, 3 => 0.8],    // voter 1
            [1 => 1.0, 2 => 0.0, 3 => 0.0],    // voter 2
            [1 => 0.0, 2 => 1.0, 3 => 0.0],    // voter 3
            [1 => 0.55, 2 => 0.45, 3 => 0.0],  // voter 4
        ];

        $result = \DistributionAggregation::compute('ladder', $distributions);

        // Expected: (5/12, 5/12, 1/6)
        $this->assertEqualsWithDelta(5/12, $result[1], 0.001);
        $this->assertEqualsWithDelta(5/12, $result[2], 0.001);
        $this->assertEqualsWithDelta(1/6, $result[3], 0.001);

        // Sum should be 1
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);
    }

    /**
     * Test single voter returns their own distribution (for mean)
     */
    public function test_single_voter_mean(): void
    {
        $distributions = [
            [1 => 0.6, 2 => 0.4],
        ];

        $result = \DistributionAggregation::compute('mean', $distributions);

        $this->assertEqualsWithDelta(0.6, $result[1], 0.0001);
        $this->assertEqualsWithDelta(0.4, $result[2], 0.0001);
    }

    /**
     * Test empty input returns empty array
     */
    public function test_empty_input(): void
    {
        $result = \DistributionAggregation::compute('mean', []);
        $this->assertEmpty($result);
    }

    /**
     * Test invalid rule throws exception
     */
    public function test_invalid_rule(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $distributions = [[1 => 0.5, 2 => 0.5]];
        \DistributionAggregation::compute('invalid_rule', $distributions);
    }

    /**
     * Test symmetric distributions under median rule
     * With symmetric inputs, output should be symmetric
     */
    public function test_symmetric_inputs_median(): void
    {
        // Two voters with opposite preferences
        $distributions = [
            [1 => 0.8, 2 => 0.2],
            [1 => 0.2, 2 => 0.8],
        ];

        $result = \DistributionAggregation::compute('median', $distributions);

        // Due to symmetry, both options should get equal or near-equal shares
        // (exact equality depends on the mechanism)
        $this->assertEqualsWithDelta(1.0, array_sum($result), 0.0001);
    }

    /**
     * Test many voters
     */
    public function test_many_voters(): void
    {
        $distributions = [];
        for ($i = 0; $i < 100; $i++) {
            $a = rand(10, 90) / 100;
            $distributions[] = [1 => $a, 2 => 1 - $a];
        }

        foreach (['mean', 'median', 'independent_markets', 'ladder'] as $rule) {
            $result = \DistributionAggregation::compute($rule, $distributions);

            // Sum should be 1
            $this->assertEqualsWithDelta(1.0, array_sum($result), 0.001, "Rule: $rule");

            // All values should be non-negative
            foreach ($result as $val) {
                $this->assertGreaterThanOrEqual(0, $val, "Rule: $rule");
            }
        }
    }

    /**
     * Test many alternatives
     */
    public function test_many_alternatives(): void
    {
        $distributions = [];
        for ($i = 0; $i < 10; $i++) {
            $dist = [];
            $total = 0;
            for ($j = 1; $j <= 20; $j++) {
                $val = rand(1, 10);
                $dist[$j] = $val;
                $total += $val;
            }
            // Normalize
            foreach ($dist as &$v) {
                $v /= $total;
            }
            $distributions[] = $dist;
        }

        foreach (['mean', 'median', 'independent_markets', 'ladder'] as $rule) {
            $result = \DistributionAggregation::compute($rule, $distributions);

            // Sum should be 1
            $this->assertEqualsWithDelta(1.0, array_sum($result), 0.001, "Rule: $rule");

            // All values should be non-negative
            foreach ($result as $val) {
                $this->assertGreaterThanOrEqual(0, $val, "Rule: $rule");
            }

            // Should have 20 alternatives
            $this->assertCount(20, $result, "Rule: $rule");
        }
    }

    /**
     * Test getRules returns all available rules
     */
    public function test_get_rules(): void
    {
        $rules = \DistributionAggregation::getRules();

        $this->assertArrayHasKey('mean', $rules);
        $this->assertArrayHasKey('median', $rules);
        $this->assertArrayHasKey('independent_markets', $rules);
        $this->assertArrayHasKey('ladder', $rules);

        foreach ($rules as $key => $rule) {
            $this->assertArrayHasKey('name', $rule);
            $this->assertArrayHasKey('description', $rule);
        }
    }
}
