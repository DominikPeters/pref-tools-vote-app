<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Option;
use App\Models\Response;
use App\Services\LogService;

class PollApiController extends ApiController
{
    /**
     * POST /api/polls - Create a new poll
     */
    public function create(array $params): array
    {
        $data = $this->getBody() ?? [];

        $userId = $this->user()?->id;

        try {
            $poll = Poll::create($data, $userId);

            // Create questions if provided
            if (!empty($data['questions'])) {
                foreach ($data['questions'] as $qData) {
                    Question::create($poll->id, $qData);
                }
            }

            $poll->loadQuestions();

            LogService::getInstance()->log('poll.created', $poll->id, $userId, null, [
                'title' => $poll->title,
            ]);

            return $this->success([
                'poll' => $poll->toAdminArray(),
                'admin_url' => url("{$poll->publicId}/admin/{$poll->adminToken}"),
                'public_url' => url($poll->publicId),
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to create vote: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    /**
     * GET /api/polls/:publicId - Get poll (public data)
     */
    public function show(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        // Check access based on access mode
        if ($poll->accessMode === 'password') {
            // Password check would happen on frontend, here we just return that it's required
        }

        $poll->loadQuestions();

        return $this->success(['poll' => $poll->toPublicArray()]);
    }

    /**
     * GET /api/polls/:publicId/admin/:adminToken - Get poll (admin data)
     */
    public function showAdmin(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll->loadQuestions();

        return $this->success(['poll' => $poll->toAdminArray()]);
    }

    /**
     * PUT /api/polls/:publicId/admin/:adminToken - Update poll
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

        $data = $this->getBody() ?? [];

        try {
            $poll = $poll->update($data);

            // Handle questions update
            if (isset($data['questions'])) {
                $this->syncQuestions($poll, $data['questions']);
            }

            $poll->loadQuestions();

            LogService::getInstance()->log('poll.updated', $poll->id, $this->user()?->id, null, [
                'fields' => array_keys($data),
            ]);

            return $this->success(['poll' => $poll->toAdminArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to update vote: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * DELETE /api/polls/:publicId/admin/:adminToken - Delete poll
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

        $title = $poll->title;

        LogService::getInstance()->log('poll.deleted', $poll->id, $this->user()?->id, null, [
            'title' => $title,
        ]);

        $poll->delete();

        return $this->success();
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/close - Close voting
     */
    public function close(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll = $poll->close();
        $poll->loadQuestions();

        LogService::getInstance()->log('poll.closed', $poll->id, $this->user()?->id);

        return $this->success(['poll' => $poll->toAdminArray()]);
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/reopen - Reopen voting
     */
    public function reopen(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll = $poll->reopen();
        $poll->loadQuestions();

        LogService::getInstance()->log('poll.reopened', $poll->id, $this->user()?->id);

        return $this->success(['poll' => $poll->toAdminArray()]);
    }

    /**
     * POST /api/polls/:publicId/responses - Submit a poll response
     */
    public function submitResponse(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if ($poll->status !== 'open') {
            return $this->error('Poll is not open for submissions', 'POLL_NOT_OPEN', 400);
        }

        $data = $this->getBody() ?? [];

        // Check for existing response by voter token (from cookie)
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $existingResponse = null;

        if ($voterToken) {
            $existingResponse = Response::findByVoterToken($poll->id, $voterToken);
        }

        if ($existingResponse && !$poll->allowEditOwn && !$poll->allowEditAny) {
            return $this->error('You have already submitted a response', 'ALREADY_SUBMITTED', 400);
        }

        try {
            $responseData = [
                'voter_name' => $poll->collectName ? ($data['voter_name'] ?? null) : null,
                'user_id' => $this->user()?->id,
                'answers' => $data['answers'] ?? [],
            ];

            if ($existingResponse) {
                $response = $existingResponse->update($responseData);

                LogService::getInstance()->log('response.edited', $poll->id, $this->user()?->id, $response->id, [
                    'by' => 'voter',
                ]);
            } else {
                $response = Response::create($poll->id, $responseData);

                // Set voter token cookie
                setcookie(
                    'voter_token_' . $poll->publicId,
                    $response->voterToken,
                    [
                        'expires' => time() + 86400 * 365, // 1 year
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );

                LogService::getInstance()->log('response.submitted', $poll->id, $this->user()?->id, $response->id, [
                    'voter_name' => $response->voterName,
                ]);
            }

            $response->loadAnswers();

            return $this->success([
                'response' => $response->toArray(),
                'voter_token' => $response->voterToken,
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to submit response: ' . $e->getMessage(), 'SUBMIT_FAILED', 500);
        }
    }

    /**
     * GET /api/polls/:publicId/responses - Get all responses
     */
    public function listResponses(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        // Check visibility
        $canSee = $this->canSeeResponses($poll);
        if (!$canSee) {
            return $this->error('Responses are not visible', 'NOT_VISIBLE', 403);
        }

        $responses = Response::findByPollId($poll->id);

        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        $includeNames = $this->canSeeVoterNames($poll);

        return $this->success([
            'responses' => array_map(fn($r) => $r->toArray($includeNames), $responses),
        ]);
    }

    /**
     * GET /api/polls/:publicId/responses/:responseId - Get single response
     */
    public function getResponse(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        $response->loadAnswers();

        return $this->success(['response' => $response->toArray()]);
    }

    /**
     * PUT /api/polls/:publicId/responses/:responseId - Update response
     */
    public function updateResponse(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check edit permissions
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $canEdit = false;

        if ($poll->allowEditAny) {
            $canEdit = true;
        } elseif ($poll->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
            $canEdit = true;
        }

        if (!$canEdit) {
            return $this->error('Cannot edit this response', 'EDIT_NOT_ALLOWED', 403);
        }

        $data = $this->getBody() ?? [];

        try {
            $response = $response->update([
                'voter_name' => $poll->collectName ? ($data['voter_name'] ?? $response->voterName) : null,
                'answers' => $data['answers'] ?? [],
            ]);

            LogService::getInstance()->log('response.edited', $poll->id, $this->user()?->id, $response->id, [
                'by' => 'voter',
            ]);

            return $this->success(['response' => $response->toArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to update response: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * DELETE /api/polls/:publicId/responses/:responseId - Delete response
     */
    public function deleteResponse(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check delete permissions
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $canDelete = false;

        if ($poll->allowEditAny) {
            $canDelete = true;
        } elseif ($poll->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
            $canDelete = true;
        }

        if (!$canDelete) {
            return $this->error('Cannot delete this response', 'DELETE_NOT_ALLOWED', 403);
        }

        $voterName = $response->voterName;

        LogService::getInstance()->log('response.deleted', $poll->id, $this->user()?->id, $response->id, [
            'by' => 'voter',
            'voter_name' => $voterName,
        ]);

        $response->delete();

        return $this->success();
    }

    /**
     * GET /api/polls/:publicId/admin/:adminToken/export - Export poll data
     */
    public function export(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll->loadQuestions();
        $responses = Response::findByPollId($poll->id);

        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        $format = $_GET['format'] ?? 'json';

        switch ($format) {
            case 'csv':
                return $this->exportCsv($poll, $responses);

            case 'preflib':
                return $this->exportPreflib($poll, $responses);

            case 'json':
            default:
                return $this->success([
                    'poll' => $poll->toAdminArray(),
                    'responses' => array_map(fn($r) => $r->toArray(), $responses),
                ]);
        }
    }

    /**
     * Sync questions for a poll
     */
    private function syncQuestions(Poll $poll, array $questionsData): void
    {
        $db = \App\Database::getInstance();

        // Get existing question IDs
        $existingIds = array_map(fn($q) => $q->id, Question::findByPollId($poll->id));

        $newIds = [];

        foreach ($questionsData as $index => $qData) {
            if (!empty($qData['id']) && in_array($qData['id'], $existingIds)) {
                // Update existing question
                $question = Question::find($qData['id']);
                $question->update(array_merge($qData, ['sort_order' => $index]));
                $newIds[] = $question->id;

                // Sync options
                if (isset($qData['options'])) {
                    $this->syncOptions($question, $qData['options']);
                }
            } else {
                // Create new question
                $qData['sort_order'] = $index;
                $question = Question::create($poll->id, $qData);
                $newIds[] = $question->id;
            }
        }

        // Delete removed questions
        $toDelete = array_diff($existingIds, $newIds);
        foreach ($toDelete as $id) {
            $question = Question::find($id);
            if ($question) {
                $question->delete();
            }
        }
    }

    /**
     * Sync options for a question
     */
    private function syncOptions(Question $question, array $optionsData): void
    {
        $existingIds = array_map(fn($o) => $o->id, Option::findByQuestionId($question->id));

        $newIds = [];

        foreach ($optionsData as $index => $oData) {
            if (!empty($oData['id']) && in_array($oData['id'], $existingIds)) {
                // Update existing option
                $option = Option::find($oData['id']);
                $option->update(array_merge($oData, ['sort_order' => $index]));
                $newIds[] = $option->id;
            } else {
                // Create new option
                $oData['sort_order'] = $index;
                $option = Option::create($question->id, $oData);
                $newIds[] = $option->id;
            }
        }

        // Delete removed options
        $toDelete = array_diff($existingIds, $newIds);
        foreach ($toDelete as $id) {
            $option = Option::find($id);
            if ($option) {
                $option->delete();
            }
        }
    }

    /**
     * Check if current user/visitor can see responses
     */
    private function canSeeResponses(Poll $poll): bool
    {
        // Admin token in request
        $adminToken = $_GET['admin_token'] ?? null;
        if ($adminToken && $poll->verifyAdminToken($adminToken)) {
            return true;
        }

        if ($poll->visibility === 'private') {
            return false;
        }

        if ($poll->visibilityTiming === 'after_close' && $poll->status !== 'closed') {
            return false;
        }

        return true;
    }

    /**
     * Check if voter names are visible
     */
    private function canSeeVoterNames(Poll $poll): bool
    {
        $adminToken = $_GET['admin_token'] ?? null;
        if ($adminToken && $poll->verifyAdminToken($adminToken)) {
            return true;
        }

        return in_array($poll->visibility, ['full', 'names_only']);
    }

    /**
     * Export as CSV
     */
    private function exportCsv(Poll $poll, array $responses): array
    {
        // Build CSV content
        $headers = ['Response ID', 'Voter Name', 'Created At'];
        foreach ($poll->questions as $question) {
            $headers[] = $question->text;
        }

        $rows = [];
        foreach ($responses as $response) {
            $row = [
                $response->id,
                $response->voterName ?? '',
                $response->createdAt->format('Y-m-d H:i:s'),
            ];

            foreach ($poll->questions as $question) {
                $answer = null;
                foreach ($response->answers as $a) {
                    if ($a->questionId === $question->id) {
                        $answer = $a->getValue();
                        break;
                    }
                }

                if (is_array($answer)) {
                    $row[] = json_encode($answer);
                } else {
                    $row[] = $answer ?? '';
                }
            }

            $rows[] = $row;
        }

        return $this->success([
            'format' => 'csv',
            'headers' => $headers,
            'rows' => $rows,
        ]);
    }

    /**
     * Export in PrefLib format (for ranking questions)
     */
    private function exportPreflib(Poll $poll, array $responses): array
    {
        $exports = [];

        foreach ($poll->questions as $question) {
            if (!in_array($question->type, ['ranking', 'ranking_truncated', 'ranking_with_ties'])) {
                continue;
            }

            $alternatives = [];
            foreach ($question->options as $option) {
                $alternatives[$option->id] = $option->label;
            }

            $ballots = [];
            foreach ($responses as $response) {
                foreach ($response->answers as $answer) {
                    if ($answer->questionId === $question->id) {
                        $value = $answer->getValue();
                        if (is_array($value)) {
                            $ballots[] = $value;
                        }
                        break;
                    }
                }
            }

            $exports[] = [
                'question_id' => $question->id,
                'question_text' => $question->text,
                'type' => $question->type,
                'alternatives' => $alternatives,
                'ballots' => $ballots,
            ];
        }

        return $this->success([
            'format' => 'preflib',
            'questions' => $exports,
        ]);
    }
}
