<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MultiwinnerRulesRegistry;

// These will trigger autoloading of the libraries
require_once SRC_PATH . '/Services/ABCProfileBuilder.php';
require_once SRC_PATH . '/Services/ProfileBuilder.php';

use AbcVoting\Profile as AbcProfile;
use PrefVoting\Profile as PrefProfile;

class MultiwinnerRulesRegistryTest extends TestCase
{
    public function test_get_rules_approval(): void
    {
        $rules = MultiwinnerRulesRegistry::getRules('approval');
        $this->assertArrayHasKey('av', $rules);
        $this->assertArrayHasKey('equal-shares', $rules);
    }

    public function test_get_rules_ranking(): void
    {
        $rules = MultiwinnerRulesRegistry::getRulesAsOptions('ranking');
        $this->assertNotContains('approval_stv', array_column($rules, 'value'));
        
        $rulesWithTies = MultiwinnerRulesRegistry::getRulesAsOptions('ranking_with_ties');
        $this->assertEquals('approval_stv', $rulesWithTies[0]['value']);
    }

    public function test_compute_abc_rule(): void
    {
        // Simple 2-candidate profile
        $profile = new AbcProfile(2, ['A', 'B']);
        $profile->addVoter([0]); // One voter for candidate 0 (A)
        
        $result = MultiwinnerRulesRegistry::compute('av', $profile, 1);
        
        // AV should return [[0]] as the winning committee
        $this->assertCount(1, $result);
        $this->assertEquals([0], $result[0]);
    }

    public function test_compute_pref_rule(): void
    {
        // Simple 2-candidate profile: candidate 0 (A) is preferred by all
        // [[0, 1]] means A > B.
        $profile = new PrefProfile([[0, 1]], [1], ['A', 'B']);
        
        // Try stv_scottish which might be more straightforward for this simple case
        $result = MultiwinnerRulesRegistry::compute('stv_scottish', $profile, 1);
        
        // STV methods in ProportionalMethods return winners as sorted array of candidate IDs
        // and compute returns an array of such committees.
        $this->assertCount(1, $result);
        $this->assertEquals([0], $result[0]);
    }
}