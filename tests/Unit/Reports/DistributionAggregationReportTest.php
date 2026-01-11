<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\DistributionAggregationReport;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class DistributionAggregationReportTest extends TestCase
{
    private DistributionAggregationReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new DistributionAggregationReport();
    }

    public function test_compute_mean_rule(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'distribution',
            'settings' => ['budget' => 100],
            'options' => [
                ['label' => 'Option A'],
                ['label' => 'Option B'],
                ['label' => 'Option C'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];
        $optC = $question->options[2];

        // Voter 1: 60, 30, 10
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 60, $optB->id => 30, $optC->id => 10]]]);
        // Voter 2: 40, 40, 20
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 40, $optB->id => 40, $optC->id => 20]]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rule' => 'mean'];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('mean', $result['rule']);
        $this->assertEquals('Mean Rule', $result['rule_name']);
        $this->assertEquals(2, $result['total_responses']);
        $this->assertCount(3, $result['distribution']);

        // Mean should be: (60+40)/2=50, (30+40)/2=35, (10+20)/2=15
        // As percentages: 50%, 35%, 15%
        $distMap = [];
        foreach ($result['distribution'] as $item) {
            $distMap[$item['option']] = $item['percentage'];
        }
        $this->assertEquals(50.0, $distMap['Option A']);
        $this->assertEquals(35.0, $distMap['Option B']);
        $this->assertEquals(15.0, $distMap['Option C']);
    }

    public function test_compute_median_rule(): void
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
        $config = ['rule' => 'median'];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('median', $result['rule']);
        $this->assertEquals('Median Rule', $result['rule_name']);
        $this->assertEquals(3, $result['total_responses']);
        $this->assertCount(2, $result['distribution']);

        // Sum of fractions should be 100% (within tolerance)
        $totalPercentage = array_sum(array_column($result['distribution'], 'percentage'));
        $this->assertEqualsWithDelta(100.0, $totalPercentage, 0.01);
    }

    public function test_compute_independent_markets(): void
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

        // Two voters: (70, 30), (30, 70)
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 70, $optB->id => 30]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 30, $optB->id => 70]]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rule' => 'independent_markets'];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('independent_markets', $result['rule']);
        $this->assertEquals(2, $result['total_responses']);

        // Sum should be 100%
        $totalPercentage = array_sum(array_column($result['distribution'], 'percentage'));
        $this->assertEqualsWithDelta(100.0, $totalPercentage, 0.01);
    }

    public function test_compute_ladder_rule(): void
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

        // Two voters: (60, 40), (40, 60)
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 60, $optB->id => 40]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$optA->id => 40, $optB->id => 60]]]);

        $responses = Response::findByPollId($poll->id);
        $config = ['rule' => 'ladder'];

        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('ladder', $result['rule']);
        $this->assertEquals('Ladder Rule', $result['rule_name']);

        // Sum should be 100%
        $totalPercentage = array_sum(array_column($result['distribution'], 'percentage'));
        $this->assertEqualsWithDelta(100.0, $totalPercentage, 0.01);
    }

    public function test_compute_no_responses(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, [
            'type' => 'distribution',
            'settings' => ['budget' => 100],
        ]);

        $result = $this->report->compute($question, [], ['rule' => 'mean']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No valid responses for this question.', $result['error']);
    }

    public function test_get_metadata(): void
    {
        $this->assertEquals('distribution_aggregation', $this->report->getType());
        $this->assertEquals(['distribution'], $this->report->getSupportedQuestionTypes());
        $this->assertEquals('distribution_aggregation', $this->report->getCategory());
        $this->assertIsArray($this->report->getConfigSchema());
    }
}
