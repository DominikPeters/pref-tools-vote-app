<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\MultiRuleComparisonReport;

class MultiRuleComparisonReportTest extends TestCase
{
    private Poll $poll;
    private MultiRuleComparisonReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new MultiRuleComparisonReport();
    }

    public function test_compute_multi_rule_comparison(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        // A > B > C
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB, $optC]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        // Compare Schulze and Borda
        $result = $this->report->compute($question, $responses, [
            'rules' => ['schulze', 'borda']
        ]);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        
        $schulze = $result['results'][0];
        $this->assertEquals('schulze', $schulze['rule']);
        $this->assertEquals($optA, $schulze['winners'][0]['option_id']);

        $borda = $result['results'][1];
        $this->assertEquals('borda', $borda['rule']);
        $this->assertEquals($optA, $borda['winners'][0]['option_id']);
    }

    public function test_default_rules_used_when_none_provided(): void
    {
        $question = $this->createQuestion($this->poll->id, ['type' => 'ranking', 'options' => [['label' => 'A']]]);
        $responses = [];

        $result = $this->report->compute($question, $responses, null);
        
        $this->assertNotEmpty($result['results']);
        // Should have at least the default rules (schulze, ranked_pairs, irv, borda for ranking)
        $rules = array_column($result['results'], 'rule');
        $this->assertContains('schulze', $rules);
    }
}
