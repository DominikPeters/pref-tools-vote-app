<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Vote;
use App\Models\Question;
use App\Models\Option;
use App\Models\Response;
use App\Services\LogService;

class VoteApiController extends ApiController
{
    /**
     * POST /api/votes - Create a new vote
     */
    public function create(array $params): array
    {
        $data = $this->getBody() ?? [];

        $userId = $this->user()?->id;

        try {
            $vote = Vote::create($data, $userId);

            // Create questions if provided
            if (!empty($data['questions'])) {
                foreach ($data['questions'] as $qData) {
                    Question::create($vote->id, $qData);
                }
            }

            $vote->loadQuestions();

            LogService::getInstance()->log('vote.created', $vote->id, $userId, null, [
                'title' => $vote->title,
            ]);

            return $this->success([
                'vote' => $vote->toAdminArray(),
                'admin_url' => url("{$vote->publicId}/admin/{$vote->adminToken}"),
                'public_url' => url($vote->publicId),
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to create vote: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    /**
     * GET /api/votes/:publicId - Get vote (public data)
     */
    public function show(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        // Check access based on access mode
        if ($vote->accessMode === 'password') {
            // Password check would happen on frontend, here we just return that it's required
        }

        $vote->loadQuestions();

        return $this->success(['vote' => $vote->toPublicArray()]);
    }

    /**
     * GET /api/votes/:publicId/admin/:adminToken - Get vote (admin data)
     */
    public function showAdmin(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $vote->loadQuestions();

        return $this->success(['vote' => $vote->toAdminArray()]);
    }

    /**
     * PUT /api/votes/:publicId/admin/:adminToken - Update vote
     */
    public function update(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $data = $this->getBody() ?? [];

        try {
            $vote = $vote->update($data);

            // Handle questions update
            if (isset($data['questions'])) {
                $this->syncQuestions($vote, $data['questions']);
            }

            $vote->loadQuestions();

            LogService::getInstance()->log('vote.updated', $vote->id, $this->user()?->id, null, [
                'fields' => array_keys($data),
            ]);

            return $this->success(['vote' => $vote->toAdminArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to update vote: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * DELETE /api/votes/:publicId/admin/:adminToken - Delete vote
     */
    public function delete(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $title = $vote->title;

        LogService::getInstance()->log('vote.deleted', $vote->id, $this->user()?->id, null, [
            'title' => $title,
        ]);

        $vote->delete();

        return $this->success();
    }

    /**
     * POST /api/votes/:publicId/admin/:adminToken/close - Close voting
     */
    public function close(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $vote = $vote->close();
        $vote->loadQuestions();

        LogService::getInstance()->log('vote.closed', $vote->id, $this->user()?->id);

        return $this->success(['vote' => $vote->toAdminArray()]);
    }

    /**
     * POST /api/votes/:publicId/admin/:adminToken/reopen - Reopen voting
     */
    public function reopen(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $vote = $vote->reopen();
        $vote->loadQuestions();

        LogService::getInstance()->log('vote.reopened', $vote->id, $this->user()?->id);

        return $this->success(['vote' => $vote->toAdminArray()]);
    }

    /**
     * POST /api/votes/:publicId/responses - Submit a vote response
     */
    public function submitResponse(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if ($vote->status !== 'open') {
            return $this->error('Vote is not open for submissions', 'VOTE_NOT_OPEN', 400);
        }

        $data = $this->getBody() ?? [];

        // Check for existing response by voter token (from cookie)
        $voterToken = $_COOKIE['voter_token_' . $vote->publicId] ?? null;
        $existingResponse = null;

        if ($voterToken) {
            $existingResponse = Response::findByVoterToken($vote->id, $voterToken);
        }

        if ($existingResponse && !$vote->allowEditOwn && !$vote->allowEditAny) {
            return $this->error('You have already submitted a response', 'ALREADY_SUBMITTED', 400);
        }

        try {
            $responseData = [
                'voter_name' => $vote->collectName ? ($data['voter_name'] ?? null) : null,
                'user_id' => $this->user()?->id,
                'answers' => $data['answers'] ?? [],
            ];

            if ($existingResponse) {
                $response = $existingResponse->update($responseData);

                LogService::getInstance()->log('response.edited', $vote->id, $this->user()?->id, $response->id, [
                    'by' => 'voter',
                ]);
            } else {
                $response = Response::create($vote->id, $responseData);

                // Set voter token cookie
                setcookie(
                    'voter_token_' . $vote->publicId,
                    $response->voterToken,
                    [
                        'expires' => time() + 86400 * 365, // 1 year
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );

                LogService::getInstance()->log('response.submitted', $vote->id, $this->user()?->id, $response->id, [
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
     * GET /api/votes/:publicId/responses - Get all responses
     */
    public function listResponses(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        // Check visibility
        $canSee = $this->canSeeResponses($vote);
        if (!$canSee) {
            return $this->error('Responses are not visible', 'NOT_VISIBLE', 403);
        }

        $responses = Response::findByVoteId($vote->id);

        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        $includeNames = $this->canSeeVoterNames($vote);

        return $this->success([
            'responses' => array_map(fn($r) => $r->toArray($includeNames), $responses),
        ]);
    }

    /**
     * GET /api/votes/:publicId/responses/:responseId - Get single response
     */
    public function getResponse(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->voteId !== $vote->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        $response->loadAnswers();

        return $this->success(['response' => $response->toArray()]);
    }

    /**
     * PUT /api/votes/:publicId/responses/:responseId - Update response
     */
    public function updateResponse(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->voteId !== $vote->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check edit permissions
        $voterToken = $_COOKIE['voter_token_' . $vote->publicId] ?? null;
        $canEdit = false;

        if ($vote->allowEditAny) {
            $canEdit = true;
        } elseif ($vote->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
            $canEdit = true;
        }

        if (!$canEdit) {
            return $this->error('Cannot edit this response', 'EDIT_NOT_ALLOWED', 403);
        }

        $data = $this->getBody() ?? [];

        try {
            $response = $response->update([
                'voter_name' => $vote->collectName ? ($data['voter_name'] ?? $response->voterName) : null,
                'answers' => $data['answers'] ?? [],
            ]);

            LogService::getInstance()->log('response.edited', $vote->id, $this->user()?->id, $response->id, [
                'by' => 'voter',
            ]);

            return $this->success(['response' => $response->toArray()]);
        } catch (\Exception $e) {
            return $this->error('Failed to update response: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * DELETE /api/votes/:publicId/responses/:responseId - Delete response
     */
    public function deleteResponse(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->voteId !== $vote->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check delete permissions
        $voterToken = $_COOKIE['voter_token_' . $vote->publicId] ?? null;
        $canDelete = false;

        if ($vote->allowEditAny) {
            $canDelete = true;
        } elseif ($vote->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
            $canDelete = true;
        }

        if (!$canDelete) {
            return $this->error('Cannot delete this response', 'DELETE_NOT_ALLOWED', 403);
        }

        $voterName = $response->voterName;

        LogService::getInstance()->log('response.deleted', $vote->id, $this->user()?->id, $response->id, [
            'by' => 'voter',
            'voter_name' => $voterName,
        ]);

        $response->delete();

        return $this->success();
    }

    /**
     * GET /api/votes/:publicId/admin/:adminToken/export - Export vote data
     */
    public function export(array $params): array
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $vote->loadQuestions();
        $responses = Response::findByVoteId($vote->id);

        foreach ($responses as $response) {
            $response->loadAnswers();
        }

        $format = $_GET['format'] ?? 'json';

        switch ($format) {
            case 'csv':
                return $this->exportCsv($vote, $responses);

            case 'preflib':
                return $this->exportPreflib($vote, $responses);

            case 'json':
            default:
                return $this->success([
                    'vote' => $vote->toAdminArray(),
                    'responses' => array_map(fn($r) => $r->toArray(), $responses),
                ]);
        }
    }

    /**
     * Sync questions for a vote
     */
    private function syncQuestions(Vote $vote, array $questionsData): void
    {
        $db = \App\Database::getInstance();

        // Get existing question IDs
        $existingIds = array_map(fn($q) => $q->id, Question::findByVoteId($vote->id));

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
                $question = Question::create($vote->id, $qData);
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
    private function canSeeResponses(Vote $vote): bool
    {
        // Admin token in request
        $adminToken = $_GET['admin_token'] ?? null;
        if ($adminToken && $vote->verifyAdminToken($adminToken)) {
            return true;
        }

        if ($vote->visibility === 'private') {
            return false;
        }

        if ($vote->visibilityTiming === 'after_close' && $vote->status !== 'closed') {
            return false;
        }

        return true;
    }

    /**
     * Check if voter names are visible
     */
    private function canSeeVoterNames(Vote $vote): bool
    {
        $adminToken = $_GET['admin_token'] ?? null;
        if ($adminToken && $vote->verifyAdminToken($adminToken)) {
            return true;
        }

        return in_array($vote->visibility, ['full', 'names_only']);
    }

    /**
     * Export as CSV
     */
    private function exportCsv(Vote $vote, array $responses): array
    {
        // Build CSV content
        $headers = ['Response ID', 'Voter Name', 'Created At'];
        foreach ($vote->questions as $question) {
            $headers[] = $question->text;
        }

        $rows = [];
        foreach ($responses as $response) {
            $row = [
                $response->id,
                $response->voterName ?? '',
                $response->createdAt->format('Y-m-d H:i:s'),
            ];

            foreach ($vote->questions as $question) {
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
    private function exportPreflib(Vote $vote, array $responses): array
    {
        $exports = [];

        foreach ($vote->questions as $question) {
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
