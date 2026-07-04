<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Report;
use App\Services\DefaultReportsService;

class DefaultReportsTest extends TestCase
{
    public function test_poll_creation_adds_default_reports(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Default Reports Poll',
            'questions' => [
                [
                    'type' => 'single_choice',
                    'text' => 'Pick one',
                    'options' => [['label' => 'A'], ['label' => 'B']],
                ],
                [
                    'type' => 'ranking',
                    'text' => 'Rank these',
                    'options' => [['label' => 'X'], ['label' => 'Y'], ['label' => 'Z']],
                ],
            ],
        ]);

        $this->assertSuccess($response);
        $poll = $response['poll'];

        $reports = $this->callApi(
            'GET',
            "/api/polls/{$poll['public_id']}/admin/{$poll['admin_token']}/reports"
        );
        $this->assertSuccess($reports);

        $byQuestion = [];
        foreach ($reports['reports'] as $report) {
            $byQuestion[$report['question_id']][] = $report;
            $this->assertTrue($report['is_public']);
        }

        [$singleChoiceQ, $rankingQ] = $poll['questions'];

        $singleTypes = array_column($byQuestion[$singleChoiceQ['id']], 'report_type');
        $this->assertEquals(['choice_counts'], $singleTypes);

        $rankingTypes = array_column($byQuestion[$rankingQ['id']], 'report_type');
        sort($rankingTypes);
        $this->assertEquals(['borda_scores', 'voting_rule_winner'], $rankingTypes);

        foreach ($byQuestion[$rankingQ['id']] as $report) {
            if ($report['report_type'] === 'voting_rule_winner') {
                $this->assertEquals('schulze', $report['config']['rule']);
            }
        }
    }

    /**
     * Default reports are computed lazily on the first results view, which
     * can happen before any votes exist — none of them may crash on an
     * empty response set.
     */
    public function test_default_reports_compute_with_zero_responses(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Zero Response Poll',
            'questions' => [
                ['type' => 'single_choice', 'text' => 'C', 'options' => [['label' => 'A'], ['label' => 'B']]],
                ['type' => 'approval', 'text' => 'AP', 'options' => [['label' => 'A'], ['label' => 'B']]],
                ['type' => 'ranking', 'text' => 'R', 'options' => [['label' => 'X'], ['label' => 'Y'], ['label' => 'Z']]],
                ['type' => 'grade', 'text' => 'G', 'settings' => ['preset' => 'a-f'], 'options' => [['label' => 'A'], ['label' => 'B']]],
                ['type' => 'star', 'text' => 'S', 'settings' => ['starCount' => 5], 'options' => [['label' => 'A'], ['label' => 'B']]],
                ['type' => 'yes_no_abstain', 'text' => 'Y', 'options' => [['label' => 'Motion']]],
            ],
        ]);
        $this->assertSuccess($response);
        $poll = $response['poll'];

        // Fetching the reports computes all of them with zero responses
        $reports = $this->callApi(
            'GET',
            "/api/polls/{$poll['public_id']}/admin/{$poll['admin_token']}/reports"
        );
        $this->assertSuccess($reports);
        $this->assertNotEmpty($reports['reports']);
        foreach ($reports['reports'] as $report) {
            $this->assertNotNull(
                $report['computed_at'],
                "Report {$report['report_type']} failed to compute with zero responses"
            );
        }
    }

    public function test_types_endpoint_exposes_intents_and_defaults(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, ['type' => 'ranking']);

        $response = $this->callApi(
            'GET',
            "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/reports/types"
        );

        $this->assertSuccess($response);
        $this->assertArrayHasKey('intents', $response);
        $this->assertArrayHasKey('who_wins', $response['intents']);
        $this->assertArrayHasKey('default_reports_by_question', $response);

        $defaults = $response['default_reports_by_question'][$question->id];
        $this->assertEquals('voting_rule_winner', $defaults[0]['report_type']);

        foreach ($response['all_types'] as $type) {
            $this->assertArrayHasKey('intent', $type, "Type {$type['type']} has no intent");
        }
    }

    public function test_duplicate_copies_report_setup(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, ['type' => 'ranking']);
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'voting_rule_winner',
            'config' => ['rule' => 'irv'],
            'is_public' => false,
        ]);

        $response = $this->callApi(
            'POST',
            "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/duplicate"
        );
        $this->assertSuccess($response);

        $newQuestionId = $response['poll']['questions'][0]['id'];
        $reports = Report::findByQuestionId($newQuestionId);
        $this->assertCount(1, $reports);
        $this->assertEquals('voting_rule_winner', $reports[0]->reportType);
        $this->assertEquals(['rule' => 'irv'], $reports[0]->config);
        $this->assertFalse($reports[0]->isPublic);
    }

    public function test_duplicate_adds_defaults_when_source_has_no_reports(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $this->createQuestion($poll->id, ['type' => 'ranking']);

        $response = $this->callApi(
            'POST',
            "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/duplicate"
        );
        $this->assertSuccess($response);

        $newQuestionId = $response['poll']['questions'][0]['id'];
        $types = array_map(fn($r) => $r->reportType, Report::findByQuestionId($newQuestionId));
        sort($types);
        $this->assertEquals(['borda_scores', 'voting_rule_winner'], $types);
    }

    public function test_new_question_added_in_update_gets_defaults(): void
    {
        $poll = $this->createPoll(['status' => 'draft']);
        $question = $this->createQuestion($poll->id, ['type' => 'single_choice']);

        $response = $this->callApi(
            'PUT',
            "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}",
            [
                'questions' => [
                    ['id' => $question->id, 'type' => 'single_choice', 'text' => 'Existing'],
                    [
                        'type' => 'yes_no_abstain',
                        'text' => 'New question',
                        'options' => [['label' => 'Motion 1']],
                    ],
                ],
            ]
        );
        $this->assertSuccess($response);

        // The pre-existing question was created directly (no defaults) and
        // its type did not change, so it must remain untouched
        $this->assertCount(0, Report::findByQuestionId($question->id));

        $newQuestion = null;
        foreach ($response['poll']['questions'] as $q) {
            if ($q['id'] !== $question->id) {
                $newQuestion = $q;
            }
        }
        $types = array_map(fn($r) => $r->reportType, Report::findByQuestionId($newQuestion['id']));
        $this->assertEquals(['yna_counts'], $types);
    }

    public function test_question_update_invalidates_default_report_cache(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Cache Invalidation Poll',
            'questions' => [
                [
                    'type' => 'single_choice',
                    'text' => 'Pick one',
                    'options' => [['label' => 'A'], ['label' => 'B']],
                ],
            ],
        ]);
        $this->assertSuccess($response);

        $poll = $response['poll'];
        $question = $poll['questions'][0];
        $options = $question['options'];

        // First results load computes and caches the default choice_counts report.
        $reportsResponse = $this->callApi(
            'GET',
            "/api/polls/{$poll['public_id']}/admin/{$poll['admin_token']}/reports"
        );
        $this->assertSuccess($reportsResponse);

        $report = Report::findByQuestionId($question['id'])[0];
        $this->assertNotNull($report->cachedResult);

        $update = $this->callApi(
            'PUT',
            "/api/polls/{$poll['public_id']}/admin/{$poll['admin_token']}",
            [
                'questions' => [
                    [
                        'id' => $question['id'],
                        'type' => 'single_choice',
                        'text' => 'Pick one',
                        'options' => [
                            ['id' => $options[0]['id'], 'label' => 'Renamed A'],
                            ['id' => $options[1]['id'], 'label' => 'B'],
                        ],
                    ],
                ],
            ]
        );
        $this->assertSuccess($update);

        $report = Report::find($report->id);
        $this->assertNull($report->cachedResult);
        $this->assertNull($report->computedAt);

        $reportsResponse = $this->callApi(
            'GET',
            "/api/polls/{$poll['public_id']}/admin/{$poll['admin_token']}/reports"
        );
        $this->assertSuccess($reportsResponse);

        $scores = $reportsResponse['reports'][0]['cached_result']['scores'];
        $this->assertContains('Renamed A', array_column($scores, 'option'));
    }

    public function test_type_change_in_update_applies_default_policy(): void
    {
        $poll = $this->createPoll(['status' => 'draft']);
        $question = $this->createQuestion($poll->id, ['type' => 'ranking']);
        DefaultReportsService::addDefaultsForQuestion($question);

        $response = $this->callApi(
            'PUT',
            "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}",
            [
                'questions' => [
                    ['id' => $question->id, 'type' => 'approval', 'text' => 'Now approval'],
                ],
            ]
        );
        $this->assertSuccess($response);

        // Untouched defaults are replaced by the new type's defaults
        $types = array_map(fn($r) => $r->reportType, Report::findByQuestionId($question->id));
        sort($types);
        $this->assertEquals(['approval_winner', 'choice_counts'], $types);
    }
}
