<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\MultiSWFComparisonReport;

class MultiSWFComparisonReportTest extends TestCase
{
    private MultiSWFComparisonReport $report;
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new MultiSWFComparisonReport();
        $this->poll = $this->createPoll();
    }

    public function test_compute_comparison(): void
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
        // Voter 2: B > C > A
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2, $o3, $o1]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $config = ['swfs' => ['kemeny_young', 'borda_ranking']];
        $result = $this->report->compute($question, $responses, $config);

        $this->assertEquals(2, $result['total_swfs']);
        $this->assertCount(2, $result['results']);

        $resultsMap = [];
        foreach ($result['results'] as $res) {
            $resultsMap[$res['swf']] = $res;
        }

        $this->assertArrayHasKey('kemeny_young', $resultsMap);
        $this->assertArrayHasKey('borda_ranking', $resultsMap);
        
        $this->assertEquals('Kemeny-Young', $resultsMap['kemeny_young']['swf_name']);
        $this->assertEquals('Borda Ranking', $resultsMap['borda_ranking']['swf_name']);
    }
}
