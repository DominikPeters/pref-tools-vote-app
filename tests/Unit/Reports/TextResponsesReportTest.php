<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Response;
use App\Services\Reports\TextResponsesReport;

class TextResponsesReportTest extends TestCase
{
    public function test_compute_lists_text_answers(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'text_single',
            'options' => [],
        ]);

        Response::create($poll->id, ['answers' => [$question->id => 'First answer']]);
        Response::create($poll->id, ['answers' => [$question->id => 'Second answer']]);
        Response::create($poll->id, ['answers' => [$question->id => '   ']]); // blank, ignored

        $responses = Response::findByPollId($poll->id);
        foreach ($responses as $r) {
            $r->loadAnswers();
        }

        $report = new TextResponsesReport();
        $result = $report->compute($question, $responses, null);

        $this->assertEquals(['First answer', 'Second answer'], $result['answers']);
        $this->assertEquals(2, $result['total_responses']);
    }

    public function test_compute_with_no_responses(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, ['type' => 'text_single', 'options' => []]);

        $report = new TextResponsesReport();
        $result = $report->compute($question, [], null);

        $this->assertSame([], $result['answers']);
        $this->assertEquals(0, $result['total_responses']);
    }
}
