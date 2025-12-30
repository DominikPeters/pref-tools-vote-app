<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\ABCMultiRuleComparisonReport;

class ABCMultiRuleComparisonReportTest extends TestCase
{
    private ABCMultiRuleComparisonReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ABCMultiRuleComparisonReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_abc_multi_rule_comparison(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // V1: {A, B}, V2: {A}, V3: {C}
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$o3]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, [
            'rules' => ['av', 'equal-shares'],
            'committee_size' => 1
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEquals(2, $result['total_rules']);
        $this->assertCount(2, $result['results']);
        $this->assertNotEmpty($result['summary']);
        
        // Both rules should have A as winner for size 1 in this simple case
        $this->assertEquals($o1, $result['summary'][0]['option_id']);
        $this->assertEquals(2, $result['summary'][0]['count']); // Won under 2 rules
    }

    public function test_invalid_committee_size(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A']]
        ]);

        $responses = [];

        $result = $this->report->compute($question, $responses, [
            'rules' => ['av'],
            'committee_size' => 0 // Too small
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid committee size', $result['error']);
    }
}
