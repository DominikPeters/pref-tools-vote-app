<?php

namespace Tests\Unit\Services;

use App\Services\SocialWelfareFunctionRegistry;
use PHPUnit\Framework\TestCase;
use PrefVoting\SocialWelfareFunction;

class SocialWelfareFunctionRegistryTest extends TestCase
{
    public function testGetRankingMethodReturnsSocialWelfareFunction()
    {
        $swfs = SocialWelfareFunctionRegistry::RANKING_SWFS;
        
        foreach (array_keys($swfs) as $swfKey) {
            $method = SocialWelfareFunctionRegistry::getRankingMethod($swfKey);
            $this->assertInstanceOf(SocialWelfareFunction::class, $method, "SWF '$swfKey' should return a SocialWelfareFunction instance");
        }
    }

    public function testGetSWFsForQuestionType()
    {
        $rankingSWFs = SocialWelfareFunctionRegistry::getSWFsForQuestionType('ranking');
        $this->assertNotEmpty($rankingSWFs);
        $this->assertArrayHasKey('kemeny_young', $rankingSWFs);

        $invalidSWFs = SocialWelfareFunctionRegistry::getSWFsForQuestionType('invalid');
        $this->assertEmpty($invalidSWFs);
    }

    public function testGetMethod()
    {
        $method = SocialWelfareFunctionRegistry::getMethod('kemeny_young', 'ranking');
        $this->assertInstanceOf(SocialWelfareFunction::class, $method);

        $invalidMethod = SocialWelfareFunctionRegistry::getMethod('kemeny_young', 'invalid');
        $this->assertNull($invalidMethod);

        $nonExistentMethod = SocialWelfareFunctionRegistry::getMethod('non_existent', 'ranking');
        $this->assertNull($nonExistentMethod);
    }

    public function testGetSWFsAsOptions()
    {
        $options = SocialWelfareFunctionRegistry::getSWFsAsOptions('ranking');
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('description', $option);
            $this->assertArrayHasKey('default', $option);
        }

        // Verify defaults are first (or at least present)
        $this->assertTrue($options[0]['default'], "First option should be a default SWF");
    }
}
