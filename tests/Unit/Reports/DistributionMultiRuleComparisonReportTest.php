<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\DistributionMultiRuleComparisonReport;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class DistributionMultiRuleComparisonReportTest extends TestCase
{
    private DistributionMultiRuleComparisonReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new DistributionMultiRuleComparisonReport();
    }

    public function test_compute_multi_rule_comparison(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'distribution',
            'settings' => ['budget' => 100],
            'options' => [
                ['label' => 'Option A'],
                ['label' => 'Option B'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];

        // Three voters: (80, 20), (50, 50), (20, 80)
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 80, $optB->id => 20]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 50, $optB->id => 50]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 20, $optB->id => 80]]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rules' => ['mean', 'median', 'ladder']];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('total_responses', $result);

        $this->assertCount(3, $result['results']); // 3 rules
        $this->assertCount(2, $result['options']); // 2 options
        $this->assertEquals(3, $result['total_responses']);

        // Check each result has correct structure
        foreach ($result['results'] as $ruleResult) {
            $this->assertArrayHasKey('rule', $ruleResult);
            $this->assertArrayHasKey('rule_name', $ruleResult);
            $this->assertArrayHasKey('distribution', $ruleResult);

            // Sum of percentages should be ~100%
            $totalPercentage = array_sum(array_column($ruleResult['distribution'], 'percentage'));
            $this->assertEqualsWithDelta(100.0, $totalPercentage, 0.1);
        }
    }

    public function test_compute_with_default_rules(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'distribution',
            'settings' => ['budget' => 100],
            'options' => [
                ['label' => 'Option A'],
                ['label' => 'Option B'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];

        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 60, $optB->id => 40]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 40, $optB->id => 60]]]);

        $responses = Response::findByPollId($poll->id);
        // No rules specified, should use defaults
        $config = ['rules' => []];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertArrayHasKey('results', $result);
        // Should have at least some results from default rules
        $this->assertGreaterThan(0, count($result['results']));
    }

    public function test_compute_no_responses(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, [
            'type' => 'distribution',
            'settings' => ['budget' => 100],
        ]);

        $result = $this->report->compute($question, [], ['rules' => ['mean']]);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No valid responses for this question.', $result['error']);
    }

    public function test_get_metadata(): void
    {
        $this->assertEquals('distribution_multi_rule_comparison', $this->report->getType());
        $this->assertEquals(['distribution'], $this->report->getSupportedQuestionTypes());
        $this->assertEquals('distribution_aggregation', $this->report->getCategory());
        $this->assertIsArray($this->report->getConfigSchema());

        // Should have checkboxes field for rules
        $schema = $this->report->getConfigSchema();
        $this->assertArrayHasKey('fields', $schema);
        $rulesField = $schema['fields'][0];
        $this->assertEquals('rules', $rulesField['name']);
        $this->assertEquals('checkboxes', $rulesField['type']);
    }
}
