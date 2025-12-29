<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\BordaScoresReport;

class BordaScoresReportTest extends TestCase
{
    private BordaScoresReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new BordaScoresReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_borda(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Borda for 3 candidates: 1st=2 pts, 2nd=1 pt, 3rd=0 pts
        
        // Vote 1: A > B > C (A:2, B:1, C:0)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Vote 2: B > A > C (B:2, A:1, C:0)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2, $o1, $o3]]]);

        // Totals: A: 3, B: 3, C: 0

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, null);

        $this->assertEquals(2, $result['total_responses']);
        
        // A and B should be tied for 1st
        $this->assertEquals(3, $result['scores'][0]['score']);
        $this->assertEquals(3, $result['scores'][1]['score']);
        $this->assertEquals(0, $result['scores'][2]['score']);
    }
}
