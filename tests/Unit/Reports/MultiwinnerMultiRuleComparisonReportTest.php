<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Question;
use App\Models\Option;
use App\Services\Reports\MultiwinnerMultiRuleComparisonReport;

class MultiwinnerMultiRuleComparisonReportTest extends TestCase
{
    private MultiwinnerMultiRuleComparisonReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new MultiwinnerMultiRuleComparisonReport();
    }

    public function test_get_type(): void
    {
        $this->assertEquals('multiwinner_multi_rule_comparison', $this->report->getType());
    }

    public function test_compute_multiwinner_multi_rule_comparison(): void
    {
        // Basic test for type and supported questions
        $this->assertContains('approval', $this->report->getSupportedQuestionTypes());
        $this->assertContains('ranking', $this->report->getSupportedQuestionTypes());
    }
}