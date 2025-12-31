<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\Models\Poll;
use App\Models\Response;
use App\Models\SiteSetting;
use App\Services\LogService;
use App\Services\MailService;
use App\Services\TokenService;
use App\Services\TurnstileService;

class AuthApiController extends ApiController
{
    /**
     * POST /api/auth/register
     */
    public function register(array $params): array
    {
        // Check if registration is enabled
        if (!SiteSetting::getBool('site.registration_enabled', true)) {
            return $this->error('User registration is currently disabled', 'REGISTRATION_DISABLED', 403);
        }

        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'name' => 'required',
        ]);

        if ($validation) {
            return $validation;
        }

        // Verify Turnstile token if configured
        if (TurnstileService::isConfigured()) {
            $turnstileToken = $data['turnstile_token'] ?? '';
            if (!TurnstileService::verify($turnstileToken)) {
                return $this->error('Security verification failed. Please try again.', 'TURNSTILE_FAILED', 400);
            }
        }

        try {
            $auth = Auth::getInstance();
            $user = $auth->register($data['email'], $data['password'], $data['name']);
            $auth->login($user);

            LogService::getInstance()->log('user.registered', null, $user->id);

            // Send verification email if mail is configured
            $this->sendVerificationEmail($user);

            return $this->success(['user' => $user->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'REGISTRATION_FAILED', 400);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(array $params): array
    {
        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validation) {
            return $validation;
        }

        $auth = Auth::getInstance();
        $user = $auth->attempt($data['email'], $data['password']);

        if (!$user) {
            return $this->error('Invalid credentials', 'INVALID_CREDENTIALS', 401);
        }

        LogService::getInstance()->log('user.login', null, $user->id);

        return $this->success(['user' => $user->toArray()]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(array $params): array
    {
        $auth = Auth::getInstance();
        $userId = $auth->id();

        $auth->logout();

        if ($userId) {
            LogService::getInstance()->log('user.logout', null, $userId);
        }

        return $this->success();
    }

    /**
     * GET /api/auth/me
     */
    public function me(array $params): array
    {
        $user = $this->user();

        if (!$user) {
            return $this->error('Not authenticated', 'NOT_AUTHENTICATED', 401);
        }

        return $this->success(['user' => $user->toArray()]);
    }

    /**
     * GET /api/user/polls
     */
    public function userPolls(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $polls = Poll::findByUserId($this->user()->id);

        return $this->success([
            'polls' => array_map(fn($v) => [
                'public_id' => $v->publicId,
                'title' => $v->title,
                'status' => $v->status,
                'response_count' => $v->getResponseCount(),
                'created_at' => $v->createdAt->format('c'),
            ], $polls),
        ]);
    }

    /**
     * GET /api/user/responses
     */
    public function userResponses(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $responses = Response::findByUserId($this->user()->id);

        $result = [];
        foreach ($responses as $response) {
            $poll = Poll::find($response->pollId);
            if ($poll) {
                $result[] = [
                    'poll_public_id' => $poll->publicId,
                    'poll_title' => $poll->title,
                    'created_at' => $response->createdAt->format('c'),
                ];
            }
        }

        return $this->success(['responses' => $result]);
    }

    /**
     * POST /api/user/claim-poll
     */
    public function claimPoll(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        if (empty($data['public_id']) || empty($data['admin_token'])) {
            return $this->error('public_id and admin_token are required', 'MISSING_FIELDS', 400);
        }

        $poll = Poll::findByPublicId($data['public_id']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($data['admin_token'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        if ($poll->userId !== null) {
            return $this->error('Poll already claimed', 'ALREADY_CLAIMED', 400);
        }

        $poll = $poll->update(['user_id' => $this->user()->id]);

        return $this->success(['poll' => $poll->toAdminArray()]);
    }

    /**
     * POST /api/auth/verify-email
     */
    public function verifyEmail(array $params): array
    {
        $data = $this->getBody();

        if (empty($data['token'])) {
            return $this->error('Verification token is required', 'MISSING_TOKEN', 400);
        }

        $user = User::findByVerificationToken($data['token']);

        if (!$user) {
            return $this->error('Invalid or expired verification token', 'INVALID_TOKEN', 400);
        }

        $user->markEmailVerified();

        LogService::getInstance()->log('user.email_verified', null, $user->id);

        // Log in the user if not already logged in
        $auth = Auth::getInstance();
        if (!$auth->check()) {
            $auth->login($user);
        }

        return $this->success([
            'user' => $user->toArray(),
            'message' => 'Email verified successfully',
        ]);
    }

    /**
     * POST /api/auth/resend-verification
     */
    public function resendVerification(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $user = $this->user();

        if ($user->isEmailVerified()) {
            return $this->error('Email is already verified', 'ALREADY_VERIFIED', 400);
        }

        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return $this->error('Email service is not configured', 'MAIL_NOT_CONFIGURED', 500);
        }

        $this->sendVerificationEmail($user);

        LogService::getInstance()->log('user.verification_resent', null, $user->id);

        return $this->success(['message' => 'Verification email sent']);
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(array $params): array
    {
        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'email' => 'required|email',
        ]);

        if ($validation) {
            return $validation;
        }

        // Verify Turnstile token if configured
        if (TurnstileService::isConfigured()) {
            $turnstileToken = $data['turnstile_token'] ?? '';
            if (!TurnstileService::verify($turnstileToken)) {
                return $this->error('Security verification failed. Please try again.', 'TURNSTILE_FAILED', 400);
            }
        }

        $user = User::findByEmail($data['email']);

        // Always return success to prevent email enumeration
        if (!$user) {
            return $this->success(['message' => 'If an account exists with this email, a password reset link will be sent']);
        }

        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return $this->error('Email service is not configured', 'MAIL_NOT_CONFIGURED', 500);
        }

        // Generate reset token
        $token = TokenService::generate(64);
        $user->setPasswordResetToken($token);

        // Send reset email
        $resetUrl = url('login?reset_token=' . $token);

        ob_start();
        $userName = $user->name;
        include __DIR__ . '/../../templates/emails/password-reset.php';
        $emailBody = ob_get_clean();

        try {
            $mailService->send(
                $user->email,
                'Reset your password',
                $emailBody,
                true
            );

            LogService::getInstance()->log('user.password_reset_requested', null, $user->id);
        } catch (\Exception $e) {
            // Log error but don't expose it to user
            error_log('Failed to send password reset email: ' . $e->getMessage());
        }

        return $this->success(['message' => 'If an account exists with this email, a password reset link will be sent']);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(array $params): array
    {
        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'token' => 'required',
            'password' => 'required|min:8',
        ]);

        if ($validation) {
            return $validation;
        }

        $user = User::findByPasswordResetToken($data['token']);

        if (!$user) {
            return $this->error('Invalid or expired reset token', 'INVALID_TOKEN', 400);
        }

        // Update password
        $hashedPassword = Auth::hashPassword($data['password']);
        $user->updatePassword($hashedPassword);

        LogService::getInstance()->log('user.password_reset', null, $user->id);

        // Log in the user
        $auth = Auth::getInstance();
        $auth->login($user);

        return $this->success([
            'user' => $user->toArray(),
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * DELETE /api/user - Delete own account (self-service)
     */
    public function deleteAccount(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        // Require password confirmation
        $validation = $this->validate($data ?? [], [
            'password' => 'required',
            'poll_action' => 'required|in:delete_all,keep_all',
        ]);

        if ($validation) {
            return $validation;
        }

        $user = $this->user();

        // Verify password
        if (!Auth::verifyPassword($data['password'], $user->passwordHash)) {
            return $this->error('Incorrect password', 'INVALID_PASSWORD', 403);
        }

        // Prevent sysadmin self-deletion (they should be demoted first)
        if ($user->isSysadmin()) {
            return $this->error(
                'Sysadmins cannot delete their own account. Please have another sysadmin remove your role first.',
                'SYSADMIN_CANNOT_SELF_DELETE',
                403
            );
        }

        // Handle polls based on poll_action
        $polls = Poll::findByUserId($user->id);
        $pollCount = count($polls);

        if ($data['poll_action'] === 'delete_all') {
            // Delete all polls (cascades to questions, options, responses, answers)
            foreach ($polls as $poll) {
                LogService::getInstance()->log('poll.deleted', $poll->id, $user->id, null, [
                    'title' => $poll->title,
                    'reason' => 'account_deletion',
                ]);
                $poll->delete();
            }
        } else {
            // Keep polls but orphan them (set user_id to NULL)
            foreach ($polls as $poll) {
                $poll->update(['user_id' => null]);
                LogService::getInstance()->log('poll.orphaned', $poll->id, $user->id, null, [
                    'title' => $poll->title,
                    'reason' => 'account_deletion',
                ]);
            }
        }

        // Log the deletion before actually deleting
        LogService::getInstance()->log('user.self_deleted', null, $user->id, null, [
            'email' => $user->email,
            'poll_action' => $data['poll_action'],
            'poll_count' => $pollCount,
        ]);

        // Delete the user
        $user->delete();

        // Clear the session
        $auth = Auth::getInstance();
        $auth->logout();

        return $this->success([
            'message' => 'Account deleted successfully',
            'polls_affected' => $pollCount,
        ]);
    }

    /**
     * GET /api/user/data - Full transparency data access (GDPR Art. 15)
     * Returns all personal data the system has about the user
     */
    public function userData(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $user = $this->user();

        // Get all responses with full metadata
        $responses = Response::findByUserId($user->id);
        $responseData = [];
        foreach ($responses as $response) {
            $poll = Poll::find($response->pollId);
            $response->loadAnswers();

            // Build answer details
            $answerDetails = [];
            if ($poll) {
                $questions = \App\Models\Question::findByPollId($poll->id);
                foreach ($response->answers as $answer) {
                    $question = null;
                    foreach ($questions as $q) {
                        if ($q->id === $answer->questionId) {
                            $question = $q;
                            break;
                        }
                    }

                    $answerDetails[] = [
                        'question_id' => $answer->questionId,
                        'question_text' => $question ? $question->text : null,
                        'answer_value' => $answer->getValue(),
                    ];
                }
            }

            $responseData[] = [
                'id' => $response->id,
                'poll' => $poll ? [
                    'public_id' => $poll->publicId,
                    'title' => $poll->title,
                ] : null,
                'status' => $response->status,
                'voter_name' => $response->voterName,
                'ip_address' => $response->ipAddress,
                'user_agent' => $response->userAgent,
                'created_at' => $response->createdAt?->format('c'),
                'updated_at' => $response->updatedAt?->format('c'),
                'withdrawn_at' => $response->withdrawnAt?->format('c'),
                'answers' => $answerDetails,
            ];
        }

        // Get action logs for this user
        $logs = LogService::getInstance()->getLogsForUser($user->id, 500);
        $logData = array_map(fn($log) => [
            'action' => $log['action'],
            'created_at' => $log['created_at'],
            'ip_address' => $log['ip_address'],
            'data' => $log['data'] ? json_decode($log['data'], true) : null,
        ], $logs);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'email_verified_at' => $user->emailVerifiedAt?->format('c'),
                'created_at' => $user->createdAt?->format('c'),
                'updated_at' => $user->updatedAt?->format('c'),
            ],
            'responses' => $responseData,
            'activity_logs' => $logData,
            'data_collected' => [
                'description' => 'This data shows all information we have stored about you.',
                'ip_addresses' => 'Stored temporarily for security. Anonymized after 90 days.',
                'responses' => 'Your poll responses. You can withdraw individual responses.',
            ],
        ]);
    }

    /**
     * GET /api/user/export - Data portability export (GDPR Art. 20)
     * Returns user data in a portable machine-readable format
     */
    public function exportData(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $user = $this->user();

        // Get all responses with full metadata
        $responses = Response::findByUserId($user->id);
        $responseData = [];
        foreach ($responses as $response) {
            $poll = Poll::find($response->pollId);
            $response->loadAnswers();

            // Build answer details
            $answerDetails = [];
            if ($poll) {
                $questions = \App\Models\Question::findByPollId($poll->id);
                foreach ($response->answers as $answer) {
                    $question = null;
                    foreach ($questions as $q) {
                        if ($q->id === $answer->questionId) {
                            $question = $q;
                            break;
                        }
                    }

                    $answerDetails[] = [
                        'question_id' => $answer->questionId,
                        'question_text' => $question ? $question->text : null,
                        'answer_value' => $answer->getValue(),
                    ];
                }
            }

            $responseData[] = [
                'poll_title' => $poll ? $poll->title : null,
                'poll_public_id' => $poll ? $poll->publicId : null,
                'status' => $response->status,
                'voter_name' => $response->voterName,
                'submitted_at' => $response->createdAt?->format('c'),
                'updated_at' => $response->updatedAt?->format('c'),
                'withdrawn_at' => $response->withdrawnAt?->format('c'),
                'answers' => $answerDetails,
            ];
        }

        // Get polls created by this user
        $polls = Poll::findByUserId($user->id);
        $pollData = array_map(fn($poll) => [
            'public_id' => $poll->publicId,
            'title' => $poll->title,
            'description' => $poll->description,
            'status' => $poll->status,
            'created_at' => $poll->createdAt?->format('c'),
        ], $polls);

        return $this->success([
            'export_format' => 'json',
            'export_date' => date('c'),
            'profile' => [
                'email' => $user->email,
                'name' => $user->name,
                'created_at' => $user->createdAt?->format('c'),
            ],
            'polls_created' => $pollData,
            'poll_responses' => $responseData,
        ]);
    }

    /**
     * GET /api/user/deletion-preview - Preview what will be affected by account deletion
     */
    public function deletionPreview(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $user = $this->user();
        $polls = Poll::findByUserId($user->id);

        $pollSummaries = [];
        foreach ($polls as $poll) {
            $pollSummaries[] = [
                'public_id' => $poll->publicId,
                'title' => $poll->title,
                'status' => $poll->status,
                'response_count' => $poll->getResponseCount(),
                'admin_url' => url("{$poll->publicId}/admin/{$poll->adminToken}"),
            ];
        }

        return $this->success([
            'can_delete' => !$user->isSysadmin(),
            'is_sysadmin' => $user->isSysadmin(),
            'poll_count' => count($polls),
            'polls' => $pollSummaries,
        ]);
    }

    /**
     * PUT /api/auth/password - Change password (while logged in)
     */
    public function changePassword(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        if ($validation) {
            return $validation;
        }

        $user = $this->user();

        // Verify current password
        if (!Auth::verifyPassword($data['current_password'], $user->passwordHash)) {
            return $this->error('Current password is incorrect', 'INVALID_PASSWORD', 403);
        }

        // Check that new password is different
        if (Auth::verifyPassword($data['new_password'], $user->passwordHash)) {
            return $this->error('New password must be different from current password', 'SAME_PASSWORD', 400);
        }

        // Update password
        $hashedPassword = Auth::hashPassword($data['new_password']);
        $user->updatePassword($hashedPassword);

        // Regenerate session to invalidate other sessions
        // This keeps the current session valid but changes the session ID
        session_regenerate_id(true);

        LogService::getInstance()->log('user.password_changed', null, $user->id);

        return $this->success(['message' => 'Password changed successfully']);
    }

    /**
     * PUT /api/user/name - Change user name
     */
    public function changeName(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'name' => 'required|max:255',
        ]);

        if ($validation) {
            return $validation;
        }

        $user = $this->user();
        $newName = trim($data['name']);

        // Check that name is not empty after trimming
        if (empty($newName)) {
            return $this->error('Name cannot be empty', 'EMPTY_NAME', 400);
        }

        // Update name
        $user->updateName($newName);

        LogService::getInstance()->log('user.name_changed', null, $user->id);

        return $this->success([
            'message' => 'Name changed successfully',
            'user' => $user->toArray(),
        ]);
    }

    /**
     * Helper: Send verification email to user
     */
    private function sendVerificationEmail(User $user): void
    {
        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return;
        }

        // Generate verification token
        $token = TokenService::generate(64);
        $user->setVerificationToken($token);

        // Build verification URL
        $verifyUrl = url('login?verify_token=' . $token);

        // Render email template
        ob_start();
        $userName = $user->name;
        include __DIR__ . '/../../templates/emails/verification.php';
        $emailBody = ob_get_clean();

        try {
            $mailService->send(
                $user->email,
                'Verify your email address',
                $emailBody,
                true
            );
        } catch (\Exception $e) {
            // Log error but don't fail registration
            error_log('Failed to send verification email: ' . $e->getMessage());
        }
    }
}
