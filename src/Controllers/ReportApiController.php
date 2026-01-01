<?php

namespace App\Controllers;

use App\Models\Poll;
use App\Models\Question;
use App\Models\Report;
use App\Models\Response;
use App\Services\LogService;
use App\Services\PrefLibExporter;
use App\Services\ReportRegistry;
use App\Services\VotingRulesRegistry;

class ReportApiController extends ApiController
{
    /**
     * GET /api/polls/:publicId/reports - List public reports
     */
    public function listPublic(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        // Check if results are visible
        if (!$this->canSeeResults($poll)) {
            return $this->error('Results are not visible', 'NOT_VISIBLE', 403);
        }

        $reports = Report::findPublicByPollId($poll->id);

        // Ensure reports have computed results (public view)
        $this->ensureReportsComputed($poll, $reports, false);

        return $this->success([
            'reports' => array_map(fn($r) => $r->toPublicArray(), $reports),
        ]);
    }

    /**
     * GET /api/polls/:publicId/admin/:adminToken/reports - List all reports (admin)
     */
    public function list(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $reports = Report::findByPollId($poll->id);

        // Ensure reports have computed results (admin view)
        $this->ensureReportsComputed($poll, $reports, true);

        return $this->success([
            'reports' => array_map(fn($r) => $r->toArray(), $reports),
        ]);
    }

    /**
     * GET /api/polls/:publicId/admin/:adminToken/reports/types - Get available report types
     */
    public function availableTypes(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll->loadQuestions();

        // Get available types for each question
        $typesByQuestion = [];
        // Get single-winner voting rules for each question type
        $votingRulesByQuestion = [];
        // Get multi-winner voting rules for each question type
        $multiwinnerRulesByQuestion = [];
        // Get apportionment rules for each question type
        $apportionmentRulesByQuestion = [];
        // Get social welfare functions for each question type
        $swfsByQuestion = [];

        foreach ($poll->questions as $question) {
            $typesByQuestion[$question->id] = ReportRegistry::getTypesForQuestionType($question->type);
            
            $votingRulesByQuestion[$question->id] = VotingRulesRegistry::getRulesAsOptions($question->type);
            $multiwinnerRulesByQuestion[$question->id] = \App\Services\MultiwinnerRulesRegistry::getRulesAsOptions($question->type);

            if ($question->type === 'single_choice') {
                $apportionmentRulesByQuestion[$question->id] = \App\Services\ApportionmentRulesRegistry::getRulesAsOptions();
            }

            $swfsByQuestion[$question->id] = \App\Services\SocialWelfareFunctionRegistry::getSWFsAsOptions($question->type);
        }

        return $this->success([
            'types_by_question' => $typesByQuestion,
            'voting_rules_by_question' => $votingRulesByQuestion,
            'multiwinner_rules_by_question' => $multiwinnerRulesByQuestion,
            'apportionment_rules_by_question' => $apportionmentRulesByQuestion,
            'social_welfare_functions_by_question' => $swfsByQuestion,
            'all_types' => ReportRegistry::all(),
        ]);
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/reports - Create a report
     */
    public function create(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $data = $this->getBody() ?? [];

        // Validate required fields
        if (empty($data['question_id'])) {
            return $this->error('Question ID is required', 'MISSING_QUESTION_ID', 400);
        }

        if (empty($data['report_type'])) {
            return $this->error('Report type is required', 'MISSING_REPORT_TYPE', 400);
        }

        // Verify question belongs to this poll
        $question = Question::find((int) $data['question_id']);
        if (!$question || $question->pollId !== $poll->id) {
            return $this->error('Question not found', 'QUESTION_NOT_FOUND', 404);
        }

        // Verify report type is valid for this question type
        $handler = ReportRegistry::get($data['report_type']);
        if (!$handler) {
            return $this->error('Unknown report type', 'UNKNOWN_REPORT_TYPE', 400);
        }

        if (!in_array($question->type, $handler->getSupportedQuestionTypes())) {
            return $this->error(
                'Report type not supported for this question type',
                'UNSUPPORTED_REPORT_TYPE',
                400
            );
        }

        try {
            $report = Report::create([
                'question_id' => (int) $data['question_id'],
                'report_type' => $data['report_type'],
                'config' => $data['config'] ?? null,
                'is_public' => $data['is_public'] ?? false,
            ]);

            // Compute the report immediately
            $this->computeReport($poll, $report);

            LogService::getInstance()->log('report.created', $poll->id, $this->user()?->id, null, [
                'report_id' => $report->id,
                'report_type' => $report->reportType,
            ]);

            return $this->success(['report' => $report->toArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to create report: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    /**
     * PUT /api/polls/:publicId/admin/:adminToken/reports/:reportId - Update a report
     */
    public function update(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $report = Report::find((int) $params['reportId']);

        if (!$report) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        // Verify report belongs to a question in this poll
        $question = Question::find($report->questionId);
        if (!$question || $question->pollId !== $poll->id) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        $data = $this->getBody() ?? [];

        try {
            $report = $report->update($data);

            // Recompute if config changed
            if (isset($data['config'])) {
                $this->computeReport($poll, $report);
            }

            LogService::getInstance()->log('report.updated', $poll->id, $this->user()?->id, null, [
                'report_id' => $report->id,
            ]);

            return $this->success(['report' => $report->toArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to update report: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * DELETE /api/polls/:publicId/admin/:adminToken/reports/:reportId - Delete a report
     */
    public function delete(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $report = Report::find((int) $params['reportId']);

        if (!$report) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        // Verify report belongs to a question in this poll
        $question = Question::find($report->questionId);
        if (!$question || $question->pollId !== $poll->id) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        LogService::getInstance()->log('report.deleted', $poll->id, $this->user()?->id, null, [
            'report_id' => $report->id,
            'report_type' => $report->reportType,
        ]);

        $report->delete();

        return $this->success();
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/reports/reorder - Reorder reports
     */
    public function reorder(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $data = $this->getBody() ?? [];

        if (!isset($data['order']) || !is_array($data['order'])) {
            return $this->error('Order array is required', 'MISSING_ORDER', 400);
        }

        try {
            foreach ($data['order'] as $position => $reportId) {
                $report = Report::find((int) $reportId);
                if ($report) {
                    // Verify report belongs to this poll
                    $question = Question::find($report->questionId);
                    if ($question && $question->pollId === $poll->id) {
                        $report->update(['position' => $position]);
                    }
                }
            }

            return $this->success();
        } catch (\Exception $e) {
            return $this->error('Failed to reorder reports: ' . $e->getMessage(), 'REORDER_FAILED', 500);
        }
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/reports/:reportId/compute - Recompute a report
     */
    public function recompute(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $report = Report::find((int) $params['reportId']);

        if (!$report) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        // Verify report belongs to a question in this poll
        $question = Question::find($report->questionId);
        if (!$question || $question->pollId !== $poll->id) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        try {
            $this->computeReport($poll, $report);

            return $this->success(['report' => $report->toArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to compute report: ' . $e->getMessage(), 'COMPUTE_FAILED', 500);
        }
    }

    /**
     * GET /api/polls/:publicId/reports/:reportId/export
     * Generate Raw Data export on-demand (not cached)
     * Accessible if report is public OR valid admin token provided
     */
    public function exportRawData(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        $report = Report::find((int) $params['reportId']);

        if (!$report) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        // Verify report belongs to this poll
        $question = Question::find($report->questionId);
        if (!$question || $question->pollId !== $poll->id) {
            return $this->error('Report not found', 'REPORT_NOT_FOUND', 404);
        }

        // Check authorization: report is public OR valid admin token
        $adminToken = $_GET['admin_token'] ?? null;
        $isAdmin = $adminToken && $poll->verifyAdminToken($adminToken);

        if (!$report->isPublic && !$isAdmin) {
            return $this->error('Not authorized', 'UNAUTHORIZED', 403);
        }

        // Verify this is a raw_data_export report
        if ($report->reportType !== 'raw_data_export') {
            return $this->error('Export not available for this report type', 'INVALID_REPORT_TYPE', 400);
        }

        // Load responses
        $responses = Response::findByPollId($poll->id);
        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        $exportData = null;
        $fileName = 'export.txt';
        $dataType = 'TXT';

        if ($question->type === 'participatory_budgeting') {
            $dataType = 'PB';
            $fileName = 'export.pb';
            $exportData = \App\Services\PabulibExporter::export($question, $responses, $poll);
        } elseif (PrefLibExporter::isSupported($question)) {
            // Generate PrefLib export
            $dataType = strtoupper(PrefLibExporter::getDataType($question));
            $fileName = 'export.' . strtolower($dataType);

            // Check if user-added options should be excluded based on report config
            $excludeUserAdded = !($report->config['include_user_options'] ?? true);

            $exportData = PrefLibExporter::export($question, $responses, [
                'file_name' => $fileName,
                'exclude_user_added' => $excludeUserAdded,
            ]);
        } else {
            return $this->error('Question type not supported for export', 'UNSUPPORTED_TYPE', 400);
        }

        if ($exportData === null) {
            return $this->error('Failed to generate export', 'EXPORT_FAILED', 500);
        }

        return $this->success([
            'data' => $exportData,
            'file_name' => $fileName,
            'data_type' => $dataType,
        ]);
    }

    /**
     * Check if current user can see results
     */
    private function canSeeResults(Poll $poll): bool
    {
        // Admin token in request
        $adminToken = $_GET['admin_token'] ?? null;
        if ($adminToken && $poll->verifyAdminToken($adminToken)) {
            return true;
        }

        return $poll->visibility !== 'private';
    }

    /**
     * Compute a single report
     */
    private function computeReport(Poll $poll, Report $report, bool $isAdmin = true): void
    {
        $handler = ReportRegistry::get($report->reportType);
        if (!$handler) {
            return;
        }

        $question = Question::find($report->questionId);
        if (!$question) {
            return;
        }

        $responses = Response::findByPollId($poll->id);
        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        // Build config with privacy context for reports that need it
        $config = $report->config ?? [];
        $config['is_admin'] = $isAdmin;
        $config['poll_visibility'] = $poll->visibility;

        $result = $handler->compute($question, $responses, $config);
        $report->updateCache($result);
    }

    /**
     * Ensure all reports have computed results
     */
    private function ensureReportsComputed(Poll $poll, array $reports, bool $isAdmin = false): void
    {
        $responses = null;

        foreach ($reports as $report) {
            if ($report->cachedResult === null) {
                // Load responses lazily
                if ($responses === null) {
                    $responses = Response::findByPollId($poll->id);
                    foreach ($responses as $response) {
                        $response->loadAnswers();
                    }
                }

                $handler = ReportRegistry::get($report->reportType);
                if ($handler) {
                    $question = Question::find($report->questionId);
                    if ($question) {
                        // Build config with privacy context
                        $config = $report->config ?? [];
                        $config['is_admin'] = $isAdmin;
                        $config['poll_visibility'] = $poll->visibility;

                        $result = $handler->compute($question, $responses, $config);
                        $report->updateCache($result);
                    }
                }
            }
        }
    }
}
