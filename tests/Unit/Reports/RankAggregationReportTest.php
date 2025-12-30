<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\RankAggregationReport;

class RankAggregationReportTest extends TestCase
{
    private RankAggregationReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new RankAggregationReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_kemeny_young(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Voter 1: A > B > C
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Voter 2: A > B > C
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Voter 3: B > C > A
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2, $o3, $o1]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $config = ['swf' => 'kemeny_young'];
        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals('kemeny_young', $result['swf']);
        $this->assertEquals(3, $result['total_responses']);
        $this->assertNotEmpty($result['rankings']);
        
        // Kemeny-Young for these votes should definitely have A and B above C.
        // Most likely A > B > C since 2/3 voters preferred A over B.
        $firstRanking = $result['rankings'][0];
        
        // Check first place
        $this->assertEquals($o1, $firstRanking[0][0]['option_id']); // A should be first
        $this->assertEquals($o2, $firstRanking[1][0]['option_id']); // B should be second
        $this->assertEquals($o3, $firstRanking[2][0]['option_id']); // C should be third
    }

    public function test_compute_borda_ranking(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Voter 1: A > B > C (A:2, B:1, C:0)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Voter 2: C > B > A (C:2, B:1, A:0)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o3, $o2, $o1]]]);
        // Voter 3: B > A > C (B:2, A:1, C:0)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2, $o1, $o3]]]);

        // Totals: B: 1+1+2 = 4, A: 2+0+1 = 3, C: 0+2+0 = 2
        // Ranking: B > A > C

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $config = ['swf' => 'borda_ranking'];
        $result = $this->report->compute($question, $responses, $config);

        $firstRanking = $result['rankings'][0];
        $this->assertEquals($o2, $firstRanking[0][0]['option_id']); // B
        $this->assertEquals($o1, $firstRanking[1][0]['option_id']); // A
        $this->assertEquals($o3, $firstRanking[2][0]['option_id']); // C
    }
}
