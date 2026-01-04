<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ApportionmentRulesRegistry;
use Apportionment\Instance;

class ApportionmentRulesRegistryTest extends TestCase
{
    public function test_compute_all_rules(): void
    {
        $instance = new Instance(10, [60, 40], ['A', 'B']);
        
        foreach (array_keys(ApportionmentRulesRegistry::RULES) as $rule) {
            $result = ApportionmentRulesRegistry::compute($rule, $instance);
            $this->assertArrayHasKey('representatives', $result);
            $this->assertEquals(10, array_sum($result['representatives']));
        }
    }

    public function test_get_rules_as_options(): void
    {
        $options = ApportionmentRulesRegistry::getRulesAsOptions();
        $this->assertNotEmpty($options);
        $this->assertEquals('hamilton', $options[0]['value']);
    }
}
