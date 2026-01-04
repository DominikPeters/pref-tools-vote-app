<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\ApportionmentMultiRuleComparisonReport;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class ApportionmentMultiRuleComparisonReportTest extends TestCase
{
    private ApportionmentMultiRuleComparisonReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ApportionmentMultiRuleComparisonReport();
    }

    public function test_compute_apportionment_comparison(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'options' => [
                ['label' => 'Party A'],
                ['label' => 'Party B'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];

        Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optB->id]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rules' => ['hamilton', 'dhondt'], 'seats' => 5];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertCount(2, $result['results']);
        $this->assertCount(2, $result['options']);
        $this->assertEquals(5, $result['seats']);
        $this->assertEquals(2, $result['total_votes']);
    }

    public function test_compute_default_rules(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        $question->loadOptions();
        Response::create($poll->id, ['answers' => [$question->id => $question->options[0]->id]]);

        $responses = Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, ['rules' => [], 'seats' => 10]);

        $this->assertNotEmpty($result['results']);
    }

    public function test_compute_no_responses(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);
        
        $result = $this->report->compute($question, [], ['seats' => 10]);
        $this->assertArrayHasKey('error', $result);
    }
}
