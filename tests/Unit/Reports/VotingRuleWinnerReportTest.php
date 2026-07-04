<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Response;
use App\Services\Reports\VotingRuleWinnerReport;

class VotingRuleWinnerReportTest extends TestCase
{
    /**
     * Regression test: score sums must be compared numerically, not with the
     * grade-order comparator (which only knows the grade values themselves).
     */
    public function test_score_sum_picks_single_winner_on_star_question(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'Food'], ['label' => 'Location'], ['label' => 'Program']],
        ]);

        [$food, $location, $program] = array_map(fn($o) => $o->id, $question->options);

        // Sums: Food 11, Location 12, Program 11
        Response::create($poll->id, ['answers' => [$question->id => [$food => 4, $location => 5, $program => 3]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$food => 2, $location => 4, $program => 4]]]);
        Response::create($poll->id, ['answers' => [$question->id => [$food => 5, $location => 3, $program => 4]]]);

        $responses = Response::findByPollId($poll->id);
        foreach ($responses as $r) {
            $r->loadAnswers();
        }

        $report = new VotingRuleWinnerReport();
        $result = $report->compute($question, $responses, ['rule' => 'score_sum']);

        $this->assertFalse($result['is_tie']);
        $this->assertCount(1, $result['winners']);
        $this->assertEquals($location, $result['winners'][0]['option_id']);
    }

    public function test_zero_responses_returns_no_winner(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B']],
        ]);

        $report = new VotingRuleWinnerReport();
        $result = $report->compute($question, [], ['rule' => 'schulze']);

        $this->assertSame([], $result['winners']);
        $this->assertFalse($result['is_tie']);
        $this->assertEquals(0, $result['total_responses']);
    }
}
