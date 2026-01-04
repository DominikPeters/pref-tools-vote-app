<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\PairwiseMarginsReport;

class PairwiseMarginsReportTest extends TestCase
{
    private Poll $poll;
    private PairwiseMarginsReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new PairwiseMarginsReport();
    }

    public function test_compute_pairwise_margins(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;

        // 3 voters: A > B
        // 1 voter: B > A
        // Margin A vs B = 3 - 1 = 2
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optB, $optA]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertArrayHasKey('edges', $result);
        $this->assertArrayHasKey('candidates', $result);
        
        // Edge should be from A to B with margin 2
        $this->assertCount(1, $result['edges']);
        $this->assertEquals($optA, $result['edges'][0]['from']);
        $this->assertEquals($optB, $result['edges'][0]['to']);
        $this->assertEquals(2, $result['edges'][0]['margin']);
    }
}