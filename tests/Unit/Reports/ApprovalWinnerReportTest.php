<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\ApprovalWinnerReport;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class ApprovalWinnerReportTest extends TestCase
{
    private ApprovalWinnerReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new ApprovalWinnerReport();
    }

    public function test_compute_approval_winner(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'options' => [
                ['label' => 'Cand A'],
                ['label' => 'Cand B'],
                ['label' => 'Cand C'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];

        // 3 votes for A, 2 for B
        for ($i = 0; $i < 3; $i++) Response::create($poll->id, ['answers' => [$question->id => [$optA->id]]]);
        for ($i = 0; $i < 2; $i++) Response::create($poll->id, ['answers' => [$question->id => [$optB->id]]]);

        $responses = Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, []);

        $this->assertCount(1, $result['winners']);
        $this->assertEquals($optA->id, $result['winners'][0]['option_id']);
        $this->assertEquals(3, $result['winners'][0]['count']);
        $this->assertFalse($result['is_tie']);
        $this->assertEquals(5, $result['total_responses']);
    }

    public function test_compute_tie(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];

        Response::create($poll->id, ['answers' => [$question->id => [$optA->id]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optB->id]]]);

        $responses = Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, []);

        $this->assertCount(2, $result['winners']);
        $this->assertTrue($result['is_tie']);
    }

    public function test_compute_exclude_user_added(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'options' => [
                ['label' => 'Standard'],
                ['label' => 'UserAdded', 'features' => ['isUserAdded' => true]],
            ]
        ]);
        $question->loadOptions();
        $opt1 = $question->options[0];
        $opt2 = $question->options[1];

        // 1 vote for each
        Response::create($poll->id, ['answers' => [$question->id => [$opt1->id]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$opt2->id]]]);

        $responses = Response::findByPollId($poll->id);
        
        // Exclude user added
        $result = $this->report->compute($question, $responses, ['include_user_options' => false]);

        $this->assertCount(1, $result['winners']);
        $this->assertEquals($opt1->id, $result['winners'][0]['option_id']);
    }

    public function test_compute_no_votes(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);
        
        $result = $this->report->compute($question, [], []);
        $this->assertCount(0, $result['winners']);
        $this->assertEquals(0, $result['total_responses']);
    }

    public function test_get_metadata(): void
    {
        $this->assertEquals('approval_winner', $this->report->getType());
        $this->assertEquals('Approval Winner', $this->report->getName());
        $this->assertNotEmpty($this->report->getDescription());
        $this->assertEquals('trophy', $this->report->getIcon());
        $this->assertEquals('vote_tallies', $this->report->getCategory());
        $this->assertContains('approval', $this->report->getSupportedQuestionTypes());
        $this->assertContains('single_choice', $this->report->getSupportedQuestionTypes());
        $this->assertIsArray($this->report->getConfigSchema());

        // Check metadata includes category
        $metadata = $this->report->getMetadata();
        $this->assertArrayHasKey('category', $metadata);
        $this->assertEquals('vote_tallies', $metadata['category']);
    }

    public function test_compute_single_choice(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);
        $question->loadOptions();
        
        Response::create($poll->id, ['answers' => [$question->id => $question->options[0]->id]]);
        
        $responses = Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, []);
        
        $this->assertCount(1, $result['winners']);
        $this->assertEquals('single_choice', $result['question_type']);
    }
}
