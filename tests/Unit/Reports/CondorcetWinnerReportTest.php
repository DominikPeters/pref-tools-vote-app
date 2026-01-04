<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\CondorcetWinnerReport;

class CondorcetWinnerReportTest extends TestCase
{
    private Poll $poll;
    private CondorcetWinnerReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new CondorcetWinnerReport();
    }

    public function test_compute_condorcet_winner_exists(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        // A beats B and C (A > B > C)
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB, $optC]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertTrue($result['exists']);
        $this->assertNotNull($result['winner']);
        $this->assertEquals($optA, $result['winner']['option_id']);
        $this->assertEquals('A', $result['winner']['option']);
    }

    public function test_compute_condorcet_cycle(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        // Smith cycle / Condorcet paradox
        // 1: A > B > C
        // 1: B > C > A
        // 1: C > A > B
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB, $optC]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optB, $optC, $optA]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optC, $optA, $optB]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertFalse($result['exists']);
        $this->assertNull($result['winner']);
    }
}