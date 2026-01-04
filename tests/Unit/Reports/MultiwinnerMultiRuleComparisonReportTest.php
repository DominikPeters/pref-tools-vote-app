<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Question;
use App\Models\Option;
use App\Services\Reports\MultiwinnerMultiRuleComparisonReport;

class MultiwinnerMultiRuleComparisonReportTest extends TestCase
{
    private MultiwinnerMultiRuleComparisonReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new MultiwinnerMultiRuleComparisonReport();
    }

    public function test_get_type(): void
    {
        $this->assertEquals('multiwinner_multi_rule_comparison', $this->report->getType());
    }

    public function test_compute_approval(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'options' => [
                ['label' => 'Cand 1'],
                ['label' => 'Cand 2'],
                ['label' => 'Cand 3'],
            ]
        ]);
        $question->loadOptions();
        $opt1 = $question->options[0];
        $opt2 = $question->options[1];

        // 2 voters for {1, 2}
        for ($i = 0; $i < 2; $i++) {
            \App\Models\Response::create($poll->id, [
                'answers' => [$question->id => [$opt1->id, $opt2->id]]
            ]);
        }

        $responses = \App\Models\Response::findByPollId($poll->id);
        $config = [
            'rules' => ['av', 'pav'],
            'committee_size' => 2,
            'include_user_options' => true
        ];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertCount(2, $result['results']);
        $this->assertNotEmpty($result['summary']);
        $this->assertEquals(2, $result['committee_size']);
        $this->assertEquals(2, $result['total_responses']);
    }

    public function test_compute_ranking(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'ranking',
            'options' => [
                ['label' => 'Cand 1'],
                ['label' => 'Cand 2'],
            ]
        ]);
        $question->loadOptions();
        $opt1 = $question->options[0];
        $opt2 = $question->options[1];

        \App\Models\Response::create($poll->id, [
            'answers' => [$question->id => [$opt1->id, $opt2->id]]
        ]);

        $responses = \App\Models\Response::findByPollId($poll->id);
        $config = [
            'rules' => ['stv_scottish'],
            'committee_size' => 1
        ];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals('stv_scottish', $result['results'][0]['rule']);
    }

    public function test_compute_exclude_user_added(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'options' => [
                ['label' => 'Standard'],
                ['label' => 'User Added', 'features' => ['isUserAdded' => true]],
            ]
        ]);
        $question->loadOptions();
        $opt1 = $question->options[0];
        $opt2 = $question->options[1];

        \App\Models\Response::create($poll->id, [
            'answers' => [$question->id => [$opt1->id, $opt2->id]]
        ]);

        $responses = \App\Models\Response::findByPollId($poll->id);
        
        // Committee size 2 should fail if we exclude user added (only 1 option left)
        $config = [
            'rules' => ['av'],
            'committee_size' => 2,
            'include_user_options' => false
        ];

        $result = $this->report->compute($question, $responses, $config);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_compute_invalid_committee_size(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);
        $question->loadOptions();

        $result = $this->report->compute($question, [], ['committee_size' => 0]);
        $this->assertArrayHasKey('error', $result);
        
        $result = $this->report->compute($question, [], ['committee_size' => 100]);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_compute_no_responses(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);
        $question->loadOptions();

        $result = $this->report->compute($question, [], ['committee_size' => 1]);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No valid responses for this question.', $result['error']);
    }

    public function test_compute_default_rules(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, ['type' => 'approval']);
        $question->loadOptions();
        \App\Models\Response::create($poll->id, [
            'answers' => [$question->id => [$question->options[0]->id]]
        ]);

        $responses = \App\Models\Response::findByPollId($poll->id);
        $result = $this->report->compute($question, $responses, ['rules' => [], 'committee_size' => 1]);

        $this->assertNotEmpty($result['results']);
    }
}