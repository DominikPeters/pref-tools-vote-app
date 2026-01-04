<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\MajorityJudgmentReport;

class MajorityJudgmentReportTest extends TestCase
{
    private Poll $poll;
    private MajorityJudgmentReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->report = new MajorityJudgmentReport();
    }

    public function test_compute_majority_judgment(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'settings' => ['preset' => 'a-f'], // A (best) to F (worst)
            'options' => [['label' => 'Cand 1'], ['label' => 'Cand 2']]
        ]);

        $c1 = $question->options[0]->id;
        $c2 = $question->options[1]->id;

        // Cand 1: 4x F, 1x A -> Median F
        // Cand 2: 4x A, 1x F -> Median A
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'A', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 'F', $c2 => 'F']]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $dist1 = null;
        $dist2 = null;
        foreach ($result['distributions'] as $dist) {
            if ($dist['option_id'] == $c1) $dist1 = $dist;
            if ($dist['option_id'] == $c2) $dist2 = $dist;
        }

        $this->assertEquals('F', $dist1['median_grade']);
        $this->assertEquals('A', $dist2['median_grade']);
        
        $winnerIds = array_column($result['winners'], 'option_id');
        $this->assertContains($c2, $winnerIds);
    }

    public function test_compute_majority_judgment_stars(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5], // 5 (best) to 1 (worst)
            'options' => [['label' => 'Cand 1'], ['label' => 'Cand 2']]
        ]);

        $c1 = $question->options[0]->id;
        $c2 = $question->options[1]->id;

        // Cand 1: 5, 5, 5, 4, 1 -> Median 5
        // Cand 2: 2, 2, 2, 2, 5 -> Median 2
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 5, $c2 => 2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 5, $c2 => 2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 5, $c2 => 2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 4, $c2 => 2]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$c1 => 1, $c2 => 5]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $dist1 = null;
        foreach ($result['distributions'] as $dist) {
            if ($dist['option_id'] == $c1) $dist1 = $dist;
        }

        $this->assertEquals(5, $dist1['median_grade']);
        $this->assertEquals($c1, $result['winners'][0]['option_id']);
    }

    public function test_supported_types(): void
    {
        $this->assertContains('grade', $this->report->getSupportedQuestionTypes());
        $this->assertContains('star', $this->report->getSupportedQuestionTypes());
    }
}