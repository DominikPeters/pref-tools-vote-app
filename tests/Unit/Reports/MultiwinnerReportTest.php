<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\MultiwinnerReport;

class MultiwinnerReportTest extends TestCase
{
    private MultiwinnerReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new MultiwinnerReport();
        $this->poll = $this->createPoll();
    }

    public function test_get_type(): void
    {
        $this->assertEquals('multiwinner', $this->report->getType());
    }

    public function test_get_supported_question_types(): void
    {
        $this->assertContains('approval', $this->report->getSupportedQuestionTypes());
        $this->assertContains('ranking', $this->report->getSupportedQuestionTypes());
    }

    public function test_compute_invalid_committee_size(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $result = $this->report->compute($question, [], ['committee_size' => 3]);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid committee size', $result['error']);
    }

    public function test_compute_ranking_stv(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'Option A'], ['label' => 'Option B']]
        ]);
        
        $opt1Id = $question->options[0]->id;
        $opt2Id = $question->options[1]->id;

        // Create a response where Opt A is preferred
        Response::create($this->poll->id, [
            'answers' => [$question->id => [$opt1Id, $opt2Id]]
        ]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $config = [
            'rule' => 'stv_scottish',
            'committee_size' => 1
        ];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');
        $this->assertEquals('stv_scottish', $result['rule']);
        $this->assertCount(1, $result['committees']);
        $this->assertEquals('Option A', $result['committees'][0][0]['option']);
    }

    public function test_compute_approval_abc(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'Option A'], ['label' => 'Option B']]
        ]);
        
        $opt2Id = $question->options[1]->id;

        // Create a response where Opt B is approved
        Response::create($this->poll->id, [
            'answers' => [$question->id => [$opt2Id]]
        ]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $config = [
            'rule' => 'av',
            'committee_size' => 1
        ];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');
        $this->assertEquals('av', $result['rule']);
        $this->assertCount(1, $result['committees']);
        $this->assertEquals('Option B', $result['committees'][0][0]['option']);
    }
}