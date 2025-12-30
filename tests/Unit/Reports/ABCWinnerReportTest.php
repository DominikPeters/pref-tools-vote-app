<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\ABCWinnerReport;

class ABCWinnerReportTest extends TestCase
{
    private ABCWinnerReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ABCWinnerReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_abc_winner_av(): void
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

        // Committee size 1, Rule AV
        $result = $this->report->compute($question, $responses, [
            'rule' => 'av',
            'committee_size' => 1
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEquals('av', $result['rule']);
        $this->assertEquals(1, $result['committee_size']);
        $this->assertEquals(3, $result['total_responses']);
        $this->assertCount(1, $result['committees']);
        $this->assertEquals('A', $result['committees'][0][0]['option']);
    }

    public function test_compute_abc_winner_pav_size_2(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Simple PAV case:
        // 2 voters approve {A, B}
        // 1 voter approves {C}
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$o3]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        // Committee size 2, Rule PAV
        $result = $this->report->compute($question, $responses, [
            'rule' => 'pav',
            'committee_size' => 2
        ]);

        $this->assertArrayNotHasKey('error', $result);
        // With size 2, winners should be {A, C} or {B, C} depending on ties.
        // PAV score for {A, B} = 1 + 1/2 = 1.5 (voter 1) + 1.5 (voter 2) = 3.0
        // PAV score for {A, C} = 1 (voter 1) + 1 (voter 2) + 1 (voter 3) = 3.0
        // PAV score for {B, C} = 1 (voter 1) + 1 (voter 2) + 1 (voter 3) = 3.0
        // So it's a tie between {A, B}, {A, C}, {B, C}. Wait.
        // Actually:
        // V1: {A, B}
        // V2: {A, B}
        // V3: {C}
        // Committee {A, B}: V1 gives 1+1/2=1.5, V2 gives 1+1/2=1.5, V3 gives 0. Total = 3.0
        // Committee {A, C}: V1 gives 1, V2 gives 1, V3 gives 1. Total = 3.0
        // Committee {B, C}: V1 gives 1, V2 gives 1, V3 gives 1. Total = 3.0
        
        $this->assertTrue($result['is_tie']);
        $this->assertGreaterThanOrEqual(1, count($result['committees']));
    }

    public function test_invalid_committee_size(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A']]
        ]);

        $responses = [];

        $result = $this->report->compute($question, $responses, [
            'committee_size' => 2 // Too large
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid committee size', $result['error']);
    }
}
