<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Report;
use App\Models\Response;

class ReportApiTest extends TestCase
{
    private Poll $poll;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->question = $this->createQuestion($this->poll->id, [
            'type' => 'single_choice',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);
    }

    public function test_can_get_available_report_types(): void
    {
        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/types");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('types_by_question', $response);
        $this->assertArrayHasKey('all_types', $response);
        $this->assertArrayHasKey($this->question->id, $response['types_by_question']);
        
        // choice_counts should be available for single_choice
        $types = array_map(fn($t) => $t['type'], $response['types_by_question'][$this->question->id]);
        $this->assertContains('choice_counts', $types);
    }

    public function test_can_create_report(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports", [
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'is_public' => true
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('report', $response);
        $this->assertEquals('choice_counts', $response['report']['report_type']);
        $this->assertTrue($response['report']['is_public']);
        $this->assertEquals($this->question->id, $response['report']['question_id']);
    }

    public function test_cannot_create_unsupported_report_type(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports", [
            'question_id' => $this->question->id,
            'report_type' => 'borda_scores', // Borda requires ranking, not single_choice
        ]);

        $this->assertError($response, 'UNSUPPORTED_REPORT_TYPE');
    }

    public function test_can_list_reports_admin(): void
    {
        Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports");

        $this->assertSuccess($response);
        $this->assertCount(1, $response['reports']);
        $this->assertEquals('choice_counts', $response['reports'][0]['report_type']);
    }

    public function test_can_list_reports_public(): void
    {
        $this->poll->update(['visibility' => 'full']);

        Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'is_public' => true
        ]);
        Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'is_public' => false
        ]);

        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/reports");

        $this->assertSuccess($response);
        $this->assertCount(1, $response['reports']);
        $this->assertArrayNotHasKey('is_public', $response['reports'][0]); // toPublicArray excludes it
    }

    public function test_can_update_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'is_public' => false
        ]);

        $response = $this->callApi('PUT', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/{$report->id}", [
            'is_public' => true,
            'config' => ['some' => 'config']
        ]);

        $this->assertSuccess($response);
        $this->assertTrue($response['report']['is_public']);
        $this->assertEquals(['some' => 'config'], $response['report']['config']);
    }

    public function test_can_delete_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        $response = $this->callApi('DELETE', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/{$report->id}");

        $this->assertSuccess($response);
        $this->assertNull(Report::find($report->id));
    }

    public function test_can_reorder_reports(): void
    {
        $r1 = Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts']);
        $r2 = Report::create(['question_id' => $this->question->id, 'report_type' => 'choice_counts']);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/reorder", [
            'order' => [$r2->id, $r1->id]
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(0, Report::find($r2->id)->position);
        $this->assertEquals(1, Report::find($r1->id)->position);
    }

    public function test_can_recompute_report(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);

        // Add a response
        Response::create($this->poll->id, [
            'answers' => [$this->question->id => $this->question->options[0]->id]
        ]);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/{$report->id}/compute");

        $this->assertSuccess($response);
        $this->assertNotNull($response['report']['cached_result']);
        $this->assertNotNull($response['report']['computed_at']);
        
        // Verify results (choice_counts for 1 vote on option 0)
        $result = $response['report']['cached_result'];
        $this->assertArrayHasKey('scores', $result);
        
        // Find score for option 0
        $option0Id = $this->question->options[0]->id;
        $option0Score = null;
        foreach ($result['scores'] as $score) {
            if ($score['option_id'] == $option0Id) {
                $option0Score = $score;
                break;
            }
        }
        
        $this->assertNotNull($option0Score);
        $this->assertEquals(1, $option0Score['count']);
    }

    public function test_reports_are_computed_lazily_on_list(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts'
        ]);
        
        $this->assertNull($report->cachedResult);

        // This should trigger computation
        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports");

        $this->assertSuccess($response);
        $this->assertNotNull($response['reports'][0]['cached_result']);
    }

    public function test_public_visibility_rules(): void
    {
        // Private visibility blocks access
        $this->poll->update(['visibility' => 'private']);

        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/reports");
        $this->assertError($response, 'NOT_VISIBLE');

        // Non-private visibility allows access
        $this->poll->update(['visibility' => 'full']);

        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/reports");
        $this->assertSuccess($response);
    }

    public function test_voting_rule_winner_report(): void
    {
        $rankingQuestion = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $report = Report::create([
            'question_id' => $rankingQuestion->id,
            'report_type' => 'voting_rule_winner',
            'config' => ['rule' => 'schulze']
        ]);

        // Submit a ranking response: A > B > C
        Response::create($this->poll->id, [
            'answers' => [$rankingQuestion->id => [
                $rankingQuestion->options[0]->id,
                $rankingQuestion->options[1]->id,
                $rankingQuestion->options[2]->id
            ]]
        ]);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/{$report->id}/compute");

        $this->assertSuccess($response);
        $result = $response['report']['cached_result'];

        $this->assertEquals('schulze', $result['rule']);
        $this->assertCount(1, $result['winners']);
        $this->assertEquals($rankingQuestion->options[0]->id, $result['winners'][0]['option_id']);
    }

    public function test_rank_aggregation_report(): void
    {
        $rankingQuestion = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $report = Report::create([
            'question_id' => $rankingQuestion->id,
            'report_type' => 'rank_aggregation',
            'config' => ['swf' => 'kemeny_young']
        ]);

        // Submit responses
        Response::create($this->poll->id, ['answers' => [$rankingQuestion->id => [
            $rankingQuestion->options[0]->id,
            $rankingQuestion->options[1]->id,
            $rankingQuestion->options[2]->id
        ]]]);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}/reports/{$report->id}/compute");

        $this->assertSuccess($response);
        $result = $response['report']['cached_result'];

        $this->assertEquals('kemeny_young', $result['swf']);
        $this->assertNotEmpty($result['rankings']);
        $this->assertEquals($rankingQuestion->options[0]->id, $result['rankings'][0][0][0]['option_id']);
    }

    // ========== Raw Data Export Tests ==========

    public function test_admin_can_export_raw_data(): void
    {
        $rankingQuestion = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'Alice'], ['label' => 'Bob'], ['label' => 'Carol']]
        ]);

        $report = Report::create([
            'question_id' => $rankingQuestion->id,
            'report_type' => 'raw_data_export',
            'is_public' => false
        ]);

        // Submit a ranking response
        Response::create($this->poll->id, [
            'answers' => [$rankingQuestion->id => [
                $rankingQuestion->options[0]->id,
                $rankingQuestion->options[1]->id,
                $rankingQuestion->options[2]->id
            ]]
        ]);

        $response = $this->callApi(
            'GET',
            "/api/polls/{$this->poll->publicId}/reports/{$report->id}/export",
            [],
            ['admin_token' => $this->poll->adminToken]
        );

        $this->assertSuccess($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('file_name', $response);
        $this->assertArrayHasKey('data_type', $response);
        $this->assertEquals('SOC', $response['data_type']);
        $this->assertEquals('export.soc', $response['file_name']);
        $this->assertStringContainsString('# DATA TYPE: soc', $response['data']);
        $this->assertStringContainsString('# ALTERNATIVE NAME 1: Alice', $response['data']);
    }

    public function test_public_can_export_when_report_is_public(): void
    {
        $this->poll->update(['visibility' => 'full']);

        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'raw_data_export',
            'is_public' => true
        ]);

        Response::create($this->poll->id, [
            'answers' => [$this->question->id => $this->question->options[0]->id]
        ]);

        // No admin token - public access
        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/reports/{$report->id}/export");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('SOI', $response['data_type']);
    }

    public function test_public_cannot_export_when_report_is_private(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'raw_data_export',
            'is_public' => false
        ]);

        // No admin token - should fail
        $response = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/reports/{$report->id}/export");

        $this->assertError($response, 'UNAUTHORIZED');
    }

    public function test_export_only_works_for_raw_data_export_reports(): void
    {
        $report = Report::create([
            'question_id' => $this->question->id,
            'report_type' => 'choice_counts',
            'is_public' => true
        ]);

        $response = $this->callApi(
            'GET',
            "/api/polls/{$this->poll->publicId}/reports/{$report->id}/export",
            [],
            ['admin_token' => $this->poll->adminToken]
        );

        $this->assertError($response, 'INVALID_REPORT_TYPE');
    }

    public function test_export_returns_404_for_nonexistent_report(): void
    {
        $response = $this->callApi(
            'GET',
            "/api/polls/{$this->poll->publicId}/reports/99999/export",
            [],
            ['admin_token' => $this->poll->adminToken]
        );

        $this->assertError($response, 'REPORT_NOT_FOUND');
    }

    public function test_export_returns_404_for_report_from_different_poll(): void
    {
        $otherPoll = $this->createPoll();
        $otherQuestion = $this->createQuestion($otherPoll->id, [
            'type' => 'single_choice',
            'options' => [['label' => 'X']]
        ]);

        $report = Report::create([
            'question_id' => $otherQuestion->id,
            'report_type' => 'raw_data_export',
            'is_public' => true
        ]);

        // Try to access report from other poll
        $response = $this->callApi(
            'GET',
            "/api/polls/{$this->poll->publicId}/reports/{$report->id}/export",
            [],
            ['admin_token' => $this->poll->adminToken]
        );

        $this->assertError($response, 'REPORT_NOT_FOUND');
    }
}
