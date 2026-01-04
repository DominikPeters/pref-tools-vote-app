<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\ApportionmentWinnerReport;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class ApportionmentWinnerReportTest extends TestCase
{
    private ApportionmentWinnerReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ApportionmentWinnerReport();
    }

    public function test_compute_apportionment_winner(): void
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

        // 60 votes for A, 40 for B. 10 seats. Hamilton should give 6 and 4.
        for ($i = 0; $i < 6; $i++) Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        for ($i = 0; $i < 4; $i++) Response::create($poll->id, ['answers' => [$question->id => $optB->id]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rule' => 'hamilton', 'seats' => 10];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('hamilton', $result['rule']);
        $this->assertEquals(10, $result['seats']);
        $this->assertEquals(10, $result['total_votes']);
        $this->assertCount(2, $result['allocation']);
        
        $allocationA = array_values(array_filter($result['allocation'], fn($a) => $a['option'] === 'Party A'))[0];
        $allocationB = array_values(array_filter($result['allocation'], fn($a) => $a['option'] === 'Party B'))[0];
        
        $this->assertEquals(6, $allocationA['seats']);
        $this->assertEquals(4, $allocationB['seats']);
        $this->assertNotEmpty($result['explanation']);
    }

    public function test_compute_no_responses(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);
        
        $result = $this->report->compute($question, [], ['seats' => 10]);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No valid responses for this question.', $result['error']);
    }

    public function test_compute_invalid_rule(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        $question->loadOptions();
        Response::create($poll->id, ['answers' => [$question->id => $question->options[0]->id]]);

        $responses = Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, ['rule' => 'invalid', 'seats' => 10]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Error computing result', $result['error']);
    }

    public function test_get_metadata(): void
    {
        $this->assertEquals('apportionment_winner', $this->report->getType());
        $this->assertEquals(['single_choice'], $this->report->getSupportedQuestionTypes());
        $this->assertIsArray($this->report->getConfigSchema());
    }
}
