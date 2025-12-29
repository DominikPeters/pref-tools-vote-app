<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\ChoiceCountsReport;

class ChoiceCountsReportTest extends TestCase
{
    private ChoiceCountsReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ChoiceCountsReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_single_choice(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'single_choice',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // 2 votes for A, 1 vote for B
        Response::create($this->poll->id, ['answers' => [$question->id => $o1]]);
        Response::create($this->poll->id, ['answers' => [$question->id => $o1]]);
        Response::create($this->poll->id, ['answers' => [$question->id => $o2]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertEquals(3, $result['total_responses']);
        $this->assertEquals(2, $result['max_score']);
        
        // Results are sorted by count desc
        $this->assertEquals($o1, $result['scores'][0]['option_id']);
        $this->assertEquals(2, $result['scores'][0]['count']);
        $this->assertEquals(66.7, $result['scores'][0]['percentage']);

        $this->assertEquals($o2, $result['scores'][1]['option_id']);
        $this->assertEquals(1, $result['scores'][1]['count']);
        $this->assertEquals(33.3, $result['scores'][1]['percentage']);
    }

    public function test_compute_approval(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Vote 1: [A, B], Vote 2: [A]
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertEquals(2, $result['total_responses']);
        $this->assertEquals(2, $result['scores'][0]['count']); // A has 2 votes
        $this->assertEquals(1, $result['scores'][1]['count']); // B has 1 vote
    }
}
