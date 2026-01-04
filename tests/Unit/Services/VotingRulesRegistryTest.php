<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\VotingRulesRegistry;

class VotingRulesRegistryTest extends TestCase
{
    public function test_get_ranking_method(): void
    {
        $this->assertIsCallable(VotingRulesRegistry::getRankingMethod('schulze'));
        $this->assertIsCallable(VotingRulesRegistry::getRankingMethod('borda'));
        $this->assertIsCallable(VotingRulesRegistry::getRankingMethod('irv'));
        $this->assertNull(VotingRulesRegistry::getRankingMethod('nonexistent'));
    }

    public function test_get_grade_method(): void
    {
        $this->assertIsCallable(VotingRulesRegistry::getGradeMethod('majority_judgment'));
        $this->assertIsCallable(VotingRulesRegistry::getGradeMethod('score_sum'));
        $this->assertNull(VotingRulesRegistry::getGradeMethod('nonexistent'));
    }

    public function test_get_rules_for_question_type(): void
    {
        $rankingRules = VotingRulesRegistry::getRulesForQuestionType('ranking');
        $this->assertArrayHasKey('schulze', $rankingRules);
        $this->assertArrayHasKey('borda', $rankingRules);

        $gradeRules = VotingRulesRegistry::getRulesForQuestionType('grade');
        $this->assertArrayHasKey('majority_judgment', $gradeRules);

        $emptyRules = VotingRulesRegistry::getRulesForQuestionType('single_choice');
        $this->assertEmpty($emptyRules);
    }

    public function test_get_method_auto_detect(): void
    {
        $this->assertIsCallable(VotingRulesRegistry::getMethod('schulze', 'ranking'));
        $this->assertIsCallable(VotingRulesRegistry::getMethod('majority_judgment', 'grade'));
        $this->assertNull(VotingRulesRegistry::getMethod('schulze', 'grade'));
    }

    public function test_get_default_rules(): void
    {
        $defaults = VotingRulesRegistry::getDefaultRules('ranking');
        $this->assertContains('schulze', $defaults);
        $this->assertContains('borda', $defaults);
    }

    public function test_get_rules_as_options(): void
    {
        $options = VotingRulesRegistry::getRulesAsOptions('ranking');
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('value', $options[0]);
        $this->assertArrayHasKey('label', $options[0]);
        
        // Defaults should be first
        $this->assertTrue($options[0]['default']);
    }

    public function test_get_profile_type(): void
    {
        $this->assertEquals('ranking', VotingRulesRegistry::getProfileType('ranking'));
        $this->assertEquals('ranking', VotingRulesRegistry::getProfileType('ranking_with_ties'));
        $this->assertEquals('grade', VotingRulesRegistry::getProfileType('star'));
        $this->assertEquals('unknown', VotingRulesRegistry::getProfileType('single_choice'));
    }
}
