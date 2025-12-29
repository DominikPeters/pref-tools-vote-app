<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Report;
use App\Models\Poll;
use App\Models\Question;

class ReportTest extends TestCase
{
    private Poll $poll;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll();
        $this->question = $this->createQuestion($this->poll->id);
    }

    public function test_can_create_and_find_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'config' => ['show_percentages' => true],
            'is_public' => true
        ]);

        $this->assertNotNull($report->id);
        $this->assertEquals($this->question->id, $report->questionId);
        $this->assertEquals('choice_counts', $report->reportType);
        $this->assertEquals(['show_percentages' => true], $report->config);
        $this->assertTrue($report->isPublic);

        $found = Report::find($report->id);
        $this->assertEquals($report->id, $found->id);
    }

    public function test_can_update_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        $report->update([
            'config' => ['new' => 'config'],
            'is_public' => true,
            'position' => 5
        ]);

        $updated = Report::find($report->id);
        $this->assertEquals(['new' => 'config'], $updated->config);
        $this->assertTrue($updated->isPublic);
        $this->assertEquals(5, $updated->position);
    }

    public function test_can_delete_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        $report->delete();
        $this->assertNull(Report::find($report->id));
    }

    public function test_can_update_and_invalidate_cache(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        $result = ['data' => 'test'];
        $report->updateCache($result);

        $updated = Report::find($report->id);
        $this->assertEquals($result, $updated->cachedResult);
        $this->assertNotNull($updated->computedAt);

        $report->invalidateCache();
        $invalidated = Report::find($report->id);
        $this->assertNull($invalidated->cachedResult);
        $this->assertNull($invalidated->computedAt);
    }

    public function test_can_find_by_poll_id(): void
    {
        Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts']);
        
        $reports = Report::findByPollId($this->poll->id);
        $this->assertCount(1, $reports);
        $this->assertEquals($this->question->id, $reports[0]->questionId);
    }

    public function test_can_find_public_by_poll_id(): void
    {
        Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts', 'is_public' => true]);
        Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts', 'is_public' => false]);
        
        $reports = Report::findPublicByPollId($this->poll->id);
        $this->assertCount(1, $reports);
    }

    public function test_invalidate_cache_for_poll(): void
    {
        $report = Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts']);
        $report->updateCache(['foo' => 'bar']);

        Report::invalidateCacheForPoll($this->poll->id);

        $updated = Report::find($report->id);
        $this->assertNull($updated->cachedResult);
    }
}
