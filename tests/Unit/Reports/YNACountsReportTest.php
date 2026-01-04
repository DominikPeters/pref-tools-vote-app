<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\YNACountsReport;

class YNACountsReportTest extends TestCase
{
    private Poll $poll;
    private YNACountsReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new YNACountsReport();
    }

    public function test_compute_yna_counts(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'yes_no_abstain',
            'options' => [['label' => 'A']]
        ]);

        $optA = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'yes']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'yes']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'no']]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertArrayHasKey('results', $result);
        $this->assertEquals(2, $result['results'][0]['yes']);
        $this->assertEquals(1, $result['results'][0]['no']);
        $this->assertEquals(0, $result['results'][0]['abstain']);
        $this->assertEquals(3, $result['total_responses']);
    }
}