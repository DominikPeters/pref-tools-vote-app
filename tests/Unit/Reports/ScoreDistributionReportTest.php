<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Response;
use App\Services\Reports\ScoreDistributionReport;

class ScoreDistributionReportTest extends TestCase
{
    private Poll $poll;
    private ScoreDistributionReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new ScoreDistributionReport();
    }

    public function test_compute_star_distribution(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'Apple'], ['label' => 'Banana']]
        ]);

        $apple = $question->options[0]->id;
        $banana = $question->options[1]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$apple => 3, $banana => 5]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$apple => 3, $banana => 4]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$apple => 1, $banana => 5]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertTrue($result['is_numeric']);
        $this->assertEquals(5, $result['max_grade']);
        $this->assertEquals(3, $result['total_responses']);

        // Sorted by average descending: Banana (4.67) before Apple (2.33)
        $this->assertEquals($banana, $result['options'][0]['option_id']);
        $this->assertEquals($apple, $result['options'][1]['option_id']);
        $this->assertEqualsWithDelta(4.67, $result['options'][0]['average'], 0.01);
        $this->assertEqualsWithDelta(2.33, $result['options'][1]['average'], 0.01);

        // Banana counts: two 5s, one 4 (grades are listed highest first)
        $bananaCounts = [];
        foreach ($result['options'][0]['counts'] as $entry) {
            $bananaCounts[$entry['grade']] = $entry['count'];
        }
        $this->assertEquals(2, $bananaCounts[5]);
        $this->assertEquals(1, $bananaCounts[4]);
        $this->assertEquals(0, $bananaCounts[3]);
        $this->assertEquals(3, $result['options'][0]['num_graded']);
    }

    public function test_compute_grade_distribution_sorted_by_median(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'settings' => ['preset' => 'a-f'],
            'options' => [['label' => 'Cand 1'], ['label' => 'Cand 2']]
        ]);

        $c1 = $question->options[0]->id;
        $c2 = $question->options[1]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'A', $c2 => 'F']]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertFalse($result['is_numeric']);
        $this->assertNull($result['options'][0]['average']);

        // Cand 2 (median A) sorts before Cand 1 (median F)
        $this->assertEquals($c2, $result['options'][0]['option_id']);
        $this->assertEquals('A', $result['options'][0]['median']);
        $this->assertEquals('F', $result['options'][1]['median']);
    }

    public function test_compute_with_no_responses(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'Apple']]
        ]);

        $result = $this->report->compute($question, [], null);

        $this->assertEquals(0, $result['total_responses']);
        $this->assertSame([], $result['options']);
    }
}
