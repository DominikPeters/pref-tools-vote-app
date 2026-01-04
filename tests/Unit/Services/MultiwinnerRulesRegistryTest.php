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

    public function test_compute_abc_rules_detailed(): void
    {
        $profile = new AbcProfile(3, ['A', 'B', 'C']);
        $profile->addVoter([0, 1]);
        $profile->addVoter([0, 2]);

        $rules = ['seqpav', 'seqcc', 'seqslav'];
        foreach ($rules as $rule) {
            $result = MultiwinnerRulesRegistry::compute($rule, $profile, 2, true, true);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('committee', $result[0]);
            $this->assertArrayHasKey('detailed_info', $result[0]);
        }
    }

    public function test_compute_abc_phragmen_and_rule_x(): void
    {
        $profile = new AbcProfile(3, ['A', 'B', 'C']);
        $profile->addVoter([0, 1]);
        
        $result = MultiwinnerRulesRegistry::compute('seqphragmen', $profile, 1);
        $this->assertCount(1, $result);

        $result = MultiwinnerRulesRegistry::compute('equal-shares', $profile, 1);
        $this->assertCount(1, $result);
    }

    public function test_compute_pref_rules_all(): void
    {
        // Simple 2-candidate profile: candidate 0 (A) is preferred by all
        $profile = new PrefProfile([[0, 1]], [1], [0 => 'A', 1 => 'B']);
        
        // Scottish STV and CPO-STV
        foreach (['stv_scottish', 'cpo_stv'] as $rule) {
            $result = MultiwinnerRulesRegistry::compute($rule, $profile, 1);
            $this->assertCount(1, $result);
            $this->assertContains(0, $result[0]);
        }

        // Just verify these return something to cover the registry branches
        foreach (['stv_nb', 'stv_wig'] as $rule) {
            $result = MultiwinnerRulesRegistry::compute($rule, $profile, 1);
            $this->assertCount(1, $result);
        }
    }

    public function test_compute_invalid_rule(): void
    {
        $profile = new AbcProfile(2, ['A', 'B']);
        $this->expectException(\InvalidArgumentException::class);
        MultiwinnerRulesRegistry::compute('invalid_rule', $profile, 1);
    }

    public function test_has_explanation(): void
    {
        $this->assertTrue(MultiwinnerRulesRegistry::hasExplanation('seqpav'));
        $this->assertFalse(MultiwinnerRulesRegistry::hasExplanation('av'));
    }

    public function test_get_rules_as_options_approval(): void
    {
        $options = MultiwinnerRulesRegistry::getRulesAsOptions('approval');
        $this->assertNotEmpty($options);
        $this->assertEquals('av', $options[0]['value']);
    }
}