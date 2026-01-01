<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Option;
use App\Models\Response;
use App\Models\Report;
use App\Models\User;
use App\Models\AccessToken;
use App\Models\EmailInvitation;
use App\Models\SiteSetting;
use App\Services\LogService;
use App\Services\AccessControlService;
use App\Services\MailService;
use App\Services\TurnstileService;
use App\Services\ModerationService;

class PollApiController extends ApiController
{
    /**
     * POST /api/polls - Create a new poll
     */
    public function create(array $params): array
    {
        $data = $this->getBody() ?? [];

        $userId = $this->user()?->id;

        // Verify Turnstile token for anonymous users if configured
        if ($userId === null && TurnstileService::isConfigured()) {
            $turnstileToken = $data['turnstile_token'] ?? '';
            if (!TurnstileService::verify($turnstileToken)) {
                return $this->error('Security verification failed. Please try again.', 'TURNSTILE_FAILED', 400);
            }
        }

        // Content moderation check
        $moderationResult = $this->moderateContent($data);
        if ($moderationResult !== null) {
            return $moderationResult;
        }

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

        // Prevent voting_mode change if mode is locked
        if (isset($data['voting_mode']) && $poll->isModeLocked()) {
            if ($data['voting_mode'] !== $poll->votingMode) {
                return $this->error(
                    'Cannot change voting mode after responses exist. Delete all responses first.',
                    'MODE_LOCKED',
                    400
                );
            }
        }

        // Content moderation check (only if content changed)
        if ($this->hasContentChanges($data)) {
            $fullData = $this->mergeWithExistingPoll($poll, $data);
            $moderationResult = $this->moderateContent($fullData);
            if ($moderationResult !== null) {
                return $moderationResult;
            }
        }

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
     * POST /api/polls/:publicId/admin/:adminToken/duplicate - Duplicate poll
     */
    public function duplicate(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        $poll->loadQuestions();

        try {
            // Create new poll with same settings
            $newPoll = Poll::create([
                'title' => $poll->title . ' (copy)',
                'description' => $poll->description,
                'visibility' => $poll->visibility,
                'collect_name' => $poll->collectName,
                'allow_edit_own' => $poll->allowEditOwn,
                'allow_edit_any' => $poll->allowEditAny,
                'randomize_options' => $poll->randomizeOptions,
                'access_mode' => $poll->accessMode,
                'status' => 'draft', // Always create as draft
            ], $this->user()?->id);

            // Duplicate questions and options
            foreach ($poll->questions as $question) {
                $qData = [
                    'type' => $question->type,
                    'text' => $question->text,
                    'description' => $question->description,
                    'required' => $question->required,
                    'settings' => $question->settings,
                    'sort_order' => $question->sortOrder,
                    'options' => [],
                ];

                foreach ($question->options as $option) {
                    $qData['options'][] = [
                        'label' => $option->label,
                        'description' => $option->description,
                        'sort_order' => $option->sortOrder,
                    ];
                }

                Question::create($newPoll->id, $qData);
            }

            $newPoll->loadQuestions();

            LogService::getInstance()->log('poll.duplicated', $newPoll->id, $this->user()?->id, null, [
                'source_poll_id' => $poll->id,
                'title' => $newPoll->title,
            ]);

            return $this->success([
                'poll' => $newPoll->toAdminArray(),
                'admin_url' => url("{$newPoll->publicId}/admin/{$newPoll->adminToken}"),
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to duplicate poll: ' . $e->getMessage(), 'DUPLICATE_FAILED', 500);
        }
    }

    /**
     * POST /api/polls/:publicId/report - Report a poll for inappropriate content
     */
    public function report(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        // Always return success to prevent probing for valid poll IDs
        if (!$poll) {
            return $this->success(['message' => 'Report received']);
        }

        $data = $this->getBody() ?? [];
        $reason = $data['reason'] ?? '';
        $note = $data['note'] ?? '';

        // Validate reason
        $validReasons = [
            'spam' => 'Spam or misleading content',
            'harassment' => 'Harassment or hate speech',
            'doxxing' => 'Personal information exposure (doxxing)',
            'illegal' => 'Illegal activity or content',
            'impersonation' => 'Impersonation or fraud',
            'phishing' => 'Malware or phishing attempt',
            'copyright' => 'Copyright or trademark violation',
            'other' => 'Other',
        ];

        if (!isset($validReasons[$reason])) {
            return $this->error('Invalid reason', 'INVALID_REASON', 400);
        }

        // Require note for "other" reason
        if ($reason === 'other' && empty(trim($note))) {
            return $this->error('Please provide details for your report', 'NOTE_REQUIRED', 400);
        }

        // Get reporter IP
        $reporterIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Get sysadmin email
        $sysadminEmail = SiteSetting::get('notifications.sysadmin_email', '');

        if (!empty($sysadminEmail)) {
            try {
                $mailService = new MailService();

                if ($mailService->isConfigured()) {
                    // Render email template
                    $emailHtml = $this->renderReportEmail($poll, $reason, $validReasons[$reason], $note, $reporterIp);

                    $mailService->send(
                        $sysadminEmail,
                        '[Poll Report] ' . $poll->title,
                        $emailHtml,
                        true
                    );

                    LogService::getInstance()->log('poll.reported', $poll->id, $this->user()?->id, null, [
                        'reason' => $reason,
                        'reporter_ip' => $reporterIp,
                    ]);
                }
            } catch (\Exception $e) {
                // Log but don't expose email failures to user
                error_log("Failed to send poll report email: " . $e->getMessage());
            }
        }

        return $this->success(['message' => 'Report received']);
    }

    /**
     * Render the poll report email template
     */
    private function renderReportEmail(Poll $poll, string $reasonKey, string $reasonLabel, string $note, string $reporterIp): string
    {
        $pollUrl = url($poll->publicId);
        $timestamp = date('Y-m-d H:i:s T');

        ob_start();
        include __DIR__ . '/../../templates/emails/report.php';
        return ob_get_clean();
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

        // Process "Other" answers - convert { other: "text" } to real option IDs
        $poll->loadQuestions();
        if (!empty($data['answers'])) {
            $data['answers'] = $this->processOtherAnswers($poll, $data['answers']);
        }

        $identity = null;

        // For identified/secret ballot modes, validate access
        if ($poll->requiresIdentity()) {
            $accessService = new AccessControlService();
            $token = $_GET['token'] ?? $_SESSION['poll_token_' . $poll->publicId] ?? null;
            $access = $accessService->validateAccess($poll, $token);

            if (!$access['allowed']) {
                return $this->error($access['error'], 'ACCESS_DENIED', 403);
            }

            $identity = $access['identity'];
        }

        // Check for existing response by voter token (from cookie) - only for open/identified modes
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $existingResponse = null;

        if ($voterToken && $poll->votingMode !== 'secret_ballot') {
            $existingResponse = Response::findByVoterToken($poll->id, $voterToken);
        }

        // Secret ballot: never allow editing
        if ($poll->votingMode === 'secret_ballot' && $existingResponse) {
            return $this->error('You have already voted in this secret ballot', 'ALREADY_VOTED', 400);
        }

        // Check edit permissions for non-secret modes
        if ($existingResponse && !$poll->canEditResponse()) {
            return $this->error('You have already submitted a response', 'ALREADY_SUBMITTED', 400);
        }

        try {
            // Build response data based on voting mode
            $responseData = [
                'answers' => $data['answers'] ?? [],
            ];

            // Secret ballot: no identity, no names, no user linking
            if ($poll->votingMode === 'secret_ballot') {
                $responseData['voter_name'] = null;
                $responseData['user_id'] = null;
                $responseData['access_token_id'] = null;
            } else {
                // Identified or open mode
                $responseData['voter_name'] = $poll->canCollectName() && $poll->collectName
                    ? ($data['voter_name'] ?? null)
                    : null;
                $responseData['user_id'] = $this->user()?->id;

                // Link to access token/invitation for identified mode
                if ($identity) {
                    if ($identity['type'] === 'token') {
                        $responseData['access_token_id'] = $identity['token_id'];
                    }
                    // For email invitations, we can optionally use email as name
                    if ($identity['type'] === 'email' && !$responseData['voter_name'] && $poll->collectName) {
                        $responseData['voter_name'] = $identity['email'];
                    }
                }
            }

            $isSecretBallot = $poll->votingMode === 'secret_ballot';

            if ($existingResponse) {
                $response = $existingResponse->update($responseData);

                LogService::getInstance()->log('response.edited', $poll->id, $this->user()?->id, $response->id, [
                    'by' => 'voter',
                ]);
            } else {
                // For secret ballot, don't store IP/user_agent for privacy
                $response = Response::create($poll->id, $responseData, $isSecretBallot);

                // Lock the voting mode on first response
                $poll->lockMode();

                // Mark access token/invitation as used
                if ($identity) {
                    $accessService = new AccessControlService();
                    $accessService->markAccessUsed($poll, $identity, $response->id);
                }

                // Set voter token cookie (not for secret ballot - they can't edit anyway)
                if (!$isSecretBallot) {
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
                }

                // For secret ballot, don't log response details to prevent any correlation
                if (!$isSecretBallot) {
                    LogService::getInstance()->log('response.submitted', $poll->id, $this->user()?->id, $response->id, [
                        'voter_name' => $response->voterName,
                        'voting_mode' => $poll->votingMode,
                    ]);
                }
            }

            // Invalidate report caches
            Report::invalidateCacheForPoll($poll->id);

            // Send email notification to poll owner if enabled
            $this->sendResponseNotification($poll, $response);

            $response->loadAnswers();

            return $this->success([
                'response' => $response->toArray(),
                'voter_token' => $poll->votingMode !== 'secret_ballot' ? $response->voterToken : null,
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

        // Secret ballot responses cannot be edited
        if ($poll->votingMode === 'secret_ballot') {
            return $this->error('Secret ballot responses cannot be edited', 'EDIT_NOT_ALLOWED', 403);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check edit permissions
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $canEdit = false;

        if ($poll->canEditResponse()) {
            if ($poll->allowEditAny) {
                $canEdit = true;
            } elseif ($poll->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
                $canEdit = true;
            }
        }

        if (!$canEdit) {
            return $this->error('Cannot edit this response', 'EDIT_NOT_ALLOWED', 403);
        }

        $data = $this->getBody() ?? [];

        // Process "Other" answers - convert { other: "text" } to real option IDs
        $poll->loadQuestions();
        if (!empty($data['answers'])) {
            $data['answers'] = $this->processOtherAnswers($poll, $data['answers']);
        }

        try {
            $response = $response->update([
                'voter_name' => $poll->collectName ? ($data['voter_name'] ?? $response->voterName) : null,
                'answers' => $data['answers'] ?? [],
            ]);

            LogService::getInstance()->log('response.edited', $poll->id, $this->user()?->id, $response->id, [
                'by' => 'voter',
            ]);

            // Invalidate report caches
            Report::invalidateCacheForPoll($poll->id);

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

        // Secret ballot responses cannot be deleted by voters
        if ($poll->votingMode === 'secret_ballot') {
            return $this->error('Secret ballot responses cannot be deleted', 'DELETE_NOT_ALLOWED', 403);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Check delete permissions
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $canDelete = false;

        if ($poll->canEditResponse()) {
            if ($poll->allowEditAny) {
                $canDelete = true;
            } elseif ($poll->allowEditOwn && $voterToken && $response->verifyVoterToken($voterToken)) {
                $canDelete = true;
            }
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

        // Invalidate report caches
        Report::invalidateCacheForPoll($poll->id);

        return $this->success();
    }

    /**
     * POST /api/polls/:publicId/responses/:responseId/withdraw - Withdraw response (soft delete)
     * Unlike delete, this is always available for non-secret-ballot responses
     * Marks the response as withdrawn, clears answers and personal data, but prevents re-voting
     */
    public function withdrawResponse(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        // Secret ballot responses cannot be withdrawn (voter has no way to identify their response)
        if ($poll->votingMode === 'secret_ballot') {
            return $this->error('Secret ballot responses cannot be withdrawn', 'WITHDRAW_NOT_ALLOWED', 403);
        }

        $response = Response::find((int) $params['responseId']);

        if (!$response || $response->pollId !== $poll->id) {
            return $this->error('Response not found', 'NOT_FOUND', 404);
        }

        // Already withdrawn
        if ($response->isWithdrawn()) {
            return $this->error('Response has already been withdrawn', 'ALREADY_WITHDRAWN', 400);
        }

        // Check ownership - voter must prove they own this response
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        $canWithdraw = false;

        // Method 1: Voter token from cookie
        if ($voterToken && $response->verifyVoterToken($voterToken)) {
            $canWithdraw = true;
        }

        // Method 2: Logged in user owns this response
        if ($this->user() && $response->userId === $this->user()->id) {
            $canWithdraw = true;
        }

        if (!$canWithdraw) {
            return $this->error('Cannot withdraw this response', 'WITHDRAW_NOT_ALLOWED', 403);
        }

        $voterName = $response->voterName;

        LogService::getInstance()->log('response.withdrawn', $poll->id, $this->user()?->id, $response->id, [
            'voter_name' => $voterName,
        ]);

        $response->withdraw();

        // Invalidate report caches
        Report::invalidateCacheForPoll($poll->id);

        return $this->success(['response' => $response->toArray()]);
    }

    /**
     * DELETE /api/polls/:publicId/admin/:adminToken/responses - Delete all responses
     */
    public function deleteAllResponses(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        // Get confirmation from request body
        $data = $this->getBody();
        if (empty($data['confirm']) || $data['confirm'] !== true) {
            return $this->error('Confirmation required', 'CONFIRMATION_REQUIRED', 400);
        }

        // Get all responses (including withdrawn ones for complete cleanup)
        $responses = Response::findByPollId($poll->id, true);
        $count = count($responses);

        // Delete all responses
        foreach ($responses as $response) {
            $response->delete();
        }

        // Invalidate report caches
        Report::invalidateCacheForPoll($poll->id);

        // Log the action
        LogService::getInstance()->log('poll.responses_deleted_all', $poll->id, $this->user()?->id, null, [
            'count' => $count,
        ]);

        return $this->success([
            'message' => 'All responses deleted',
            'count' => $count,
        ]);
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

        // Delete removed options (but preserve user-added options from "Other" responses)
        $toDelete = array_diff($existingIds, $newIds);
        foreach ($toDelete as $id) {
            $option = Option::find($id);
            if ($option && !($option->features['isUserAdded'] ?? false)) {
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

        return $poll->visibility !== 'private';
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

    /**
     * Check content through moderation service
     * Returns error response if flagged, null if OK
     */
    private function moderateContent(array $data): ?array
    {
        $content = ModerationService::buildContentString($data);
        $result = ModerationService::moderate($content);

        if (!$result['ok']) {
            // Log API errors for debugging
            LogService::getInstance()->log('moderation.error', null, $this->user()?->id, null, [
                'error' => $result['error'] ?? 'Unknown error',
                'content' => $content,
            ]);
            return $this->error(
                'Unable to process content. Please try again later.',
                'MODERATION_ERROR',
                503
            );
        }

        if ($result['flagged']) {
            $message = ModerationService::getFlaggedMessage($result);
            // Log full details for sysadmin review
            LogService::getInstance()->log('moderation.flagged', null, $this->user()?->id, null, [
                'content' => $content,
                'flagged_categories' => $result['flagged_categories'] ?? [],
                'all_scores' => $result['all_scores'] ?? [],
                'api_flagged' => $result['api_flagged'] ?? false,
            ]);
            return $this->error($message, 'CONTENT_FLAGGED', 400);
        }

        return null;
    }

    /**
     * Check if the update contains content changes (vs just settings)
     */
    private function hasContentChanges(array $data): bool
    {
        $contentKeys = ['title', 'description', 'questions'];
        foreach ($contentKeys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Merge update data with existing poll for full moderation
     */
    private function mergeWithExistingPoll(Poll $poll, array $data): array
    {
        $poll->loadQuestions();

        $merged = [
            'title' => $data['title'] ?? $poll->title,
            'description' => $data['description'] ?? $poll->description,
        ];

        // If questions provided, use those; otherwise use existing
        if (isset($data['questions'])) {
            $merged['questions'] = $data['questions'];
        } else {
            $merged['questions'] = [];
            foreach ($poll->questions as $q) {
                $qData = [
                    'text' => $q->text,
                    'description' => $q->description,
                    'options' => [],
                ];
                foreach ($q->options as $o) {
                    $qData['options'][] = [
                        'label' => $o->label,
                        'description' => $o->description,
                    ];
                }
                $merged['questions'][] = $qData;
            }
        }

        return $merged;
    }

    /**
     * Send email notification to poll owner when a response is submitted
     */
    private function sendResponseNotification(Poll $poll, Response $response): void
    {
        // Check if notifications are enabled
        if (!$poll->notifyOnResponse) {
            return;
        }

        // Check if poll has an owner
        if (!$poll->userId) {
            return;
        }

        // Get the poll owner
        $owner = User::find($poll->userId);
        if (!$owner || !$owner->emailVerifiedAt) {
            return;
        }

        // Check if mail is configured
        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return;
        }

        // Get response count for the "Response #X" display
        $responseNumber = $poll->getResponseCount();

        // Build the view URL with the response ID parameter
        $viewUrl = url($poll->publicId . '/admin/' . $poll->adminToken . '/responses?r=' . $response->id);

        // Get voter name if collected
        $voterName = $response->voterName;

        // Format the submission time
        $submittedAt = $response->createdAt->format('M j, Y \a\t g:i A');

        // Render email template
        ob_start();
        include __DIR__ . '/../../templates/emails/response-notification.php';
        $emailBody = ob_get_clean();

        // Build subject line
        $subject = 'New response to "' . $poll->title . '"';
        if ($voterName) {
            $subject .= ' from ' . $voterName;
        }

        try {
            $mailService->send(
                $owner->email,
                $subject,
                $emailBody,
                true
            );
        } catch (\Exception $e) {
            // Log error but don't fail the response submission
            error_log('Failed to send response notification: ' . $e->getMessage());
        }
    }

    /**
     * Process "Other" answers - convert { other: "text" } to real option IDs
     *
     * For questions with allowOther enabled:
     * - Single choice: { other: "Pizza" } → option ID (creates "Other: Pizza" option)
     * - Approval: [1, 2, { other: "Pizza" }] → [1, 2, optionId]
     *
     * @param Poll $poll Poll with questions loaded
     * @param array $answers Answers keyed by question ID
     * @return array Processed answers with "other" converted to option IDs
     */
    private function processOtherAnswers(Poll $poll, array $answers): array
    {
        $questionMap = [];
        foreach ($poll->questions as $question) {
            $questionMap[$question->id] = $question;
        }

        foreach ($answers as $questionId => $answer) {
            $question = $questionMap[(int) $questionId] ?? null;
            if (!$question) {
                continue;
            }

            // Only process for question types that support "Other"
            if (!in_array($question->type, ['single_choice', 'approval'])) {
                continue;
            }

            // Check if allowOther is enabled
            if (!($question->settings['allowOther'] ?? false)) {
                continue;
            }

            // Handle single choice: { other: "text" }
            if ($question->type === 'single_choice' && is_array($answer) && isset($answer['other'])) {
                $otherText = trim($answer['other']);
                if ($otherText !== '') {
                    $option = Option::findOrCreateUserAdded($question->id, 'Other: ' . $otherText);
                    $answers[$questionId] = $option->id;
                } else {
                    // Empty "other" text - treat as no answer
                    $answers[$questionId] = null;
                }
            }

            // Handle approval: [1, 2, { other: "text" }]
            if ($question->type === 'approval' && is_array($answer)) {
                $processedAnswer = [];
                foreach ($answer as $item) {
                    if (is_array($item) && isset($item['other'])) {
                        $otherText = trim($item['other']);
                        if ($otherText !== '') {
                            $option = Option::findOrCreateUserAdded($question->id, 'Other: ' . $otherText);
                            $processedAnswer[] = $option->id;
                        }
                        // Skip empty "other" text
                    } else {
                        $processedAnswer[] = $item;
                    }
                }
                $answers[$questionId] = $processedAnswer;
            }
        }

        return $answers;
    }
}
