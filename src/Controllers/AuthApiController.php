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
            return $this->error(__('error_user_registration_is_currently_disabled'), 'REGISTRATION_DISABLED', 403);
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
                return $this->error(__('error_security_verification_failed_please_try_again'), 'TURNSTILE_FAILED', 400);
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
            return $this->error(__('error_invalid_credentials'), 'INVALID_CREDENTIALS', 401);
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
            return $this->error(__('error_not_authenticated'), 'NOT_AUTHENTICATED', 401);
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
            return $this->error(__('error_public_id_and_admin_token_are_required'), 'MISSING_FIELDS', 400);
        }

        $poll = Poll::findByPublicId($data['public_id']);

        if (!$poll) {
            return $this->error(__('error_poll_not_found'), 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($data['admin_token'])) {
            return $this->error(__('error_invalid_admin_token'), 'INVALID_TOKEN', 403);
        }

        if ($poll->userId !== null) {
            return $this->error(__('error_poll_already_claimed'), 'ALREADY_CLAIMED', 400);
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
            return $this->error(__('error_verification_token_is_required'), 'MISSING_TOKEN', 400);
        }

        $user = User::findByVerificationToken($data['token']);

        if (!$user) {
            return $this->error(__('error_invalid_or_expired_verification_token'), 'INVALID_TOKEN', 400);
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
            'message' => __('backend_email_verified_successfully'),
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
            return $this->error(__('error_email_is_already_verified'), 'ALREADY_VERIFIED', 400);
        }

        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return $this->error(__('error_email_service_is_not_configured'), 'MAIL_NOT_CONFIGURED', 500);
        }

        $this->sendVerificationEmail($user);

        LogService::getInstance()->log('user.verification_resent', null, $user->id);

        return $this->success(['message' => __('backend_verification_email_sent')]);
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
                return $this->error(__('error_security_verification_failed_please_try_again'), 'TURNSTILE_FAILED', 400);
            }
        }

        $user = User::findByEmail($data['email']);

        // Always return success to prevent email enumeration
        if (!$user) {
            return $this->success(['message' => __('backend_if_an_account_exists_with_this_email_a_password_reset_link_will_be_sent')]);
        }

        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return $this->error(__('error_email_service_is_not_configured'), 'MAIL_NOT_CONFIGURED', 500);
        }

        // Generate reset token
        $token = TokenService::generate(64);
        $user->setPasswordResetToken($token);

        // Send reset email
        $resetUrl = url('login?reset_token=' . $token);

        $emailLocale = $user->getPreferredLocale();
        $emailBody = \App\Services\EmailTemplate::render('password-reset', $emailLocale, [
            'userName' => $user->name, 'resetUrl' => $resetUrl,
        ]);

        try {
            $mailService->send(
                $user->email,
                \App\i18n\Translator::translateForLocale($emailLocale, 'email_reset_your_password'),
                $emailBody,
                true
            );

            LogService::getInstance()->log('user.password_reset_requested', null, $user->id);
        } catch (\Exception $e) {
            // Log error but don't expose it to user
            error_log('Failed to send password reset email: ' . $e->getMessage());
        }

        return $this->success(['message' => __('backend_if_an_account_exists_with_this_email_a_password_reset_link_will_be_sent')]);
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
            return $this->error(__('error_invalid_or_expired_reset_token'), 'INVALID_TOKEN', 400);
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
            'message' => __('backend_password_reset_successfully'),
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
            return $this->error(__('error_incorrect_password'), 'INVALID_PASSWORD', 403);
        }

        // Prevent sysadmin self-deletion (they should be demoted first)
        if ($user->isSysadmin()) {
            return $this->error(
                __('error_sysadmins_cannot_delete_their_own_account_please_have_another_sysadmin_remove_your_role_first'),
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
            'message' => __('backend_account_deleted_successfully'),
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
                'preferred_locale' => $user->preferredLocale,
                'created_at' => $user->createdAt?->format('c'),
                'updated_at' => $user->updatedAt?->format('c'),
            ],
            'responses' => $responseData,
            'activity_logs' => $logData,
            'data_collected' => [
                'description' => __('backend_this_data_shows_all_information_we_have_stored_about_you'),
                'ip_addresses' => __('backend_stored_temporarily_for_security_anonymized_after_90_days'),
                'responses' => __('backend_your_poll_responses_you_can_withdraw_individual_responses'),
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
            return $this->error(__('error_current_password_is_incorrect'), 'INVALID_PASSWORD', 403);
        }

        // Check that new password is different
        if (Auth::verifyPassword($data['new_password'], $user->passwordHash)) {
            return $this->error(__('error_new_password_must_be_different_from_current_password'), 'SAME_PASSWORD', 400);
        }

        // Update password
        $hashedPassword = Auth::hashPassword($data['new_password']);
        $user->updatePassword($hashedPassword);

        // Regenerate session to invalidate other sessions
        // This keeps the current session valid but changes the session ID
        session_regenerate_id(true);

        LogService::getInstance()->log('user.password_changed', null, $user->id);

        return $this->success(['message' => __('backend_password_changed_successfully')]);
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
            return $this->error(__('error_name_cannot_be_empty'), 'EMPTY_NAME', 400);
        }

        // Update name
        $user->updateName($newName);

        LogService::getInstance()->log('user.name_changed', null, $user->id);

        return $this->success([
            'message' => __('backend_name_changed_successfully'),
            'user' => $user->toArray(),
        ]);
    }

    /**
     * Helper: Send verification email to user
     */
    private function sendVerificationEmail(User $user): void
    {
        // Always generate verification token so it exists in DB
        $token = TokenService::generate(64);
        $user->setVerificationToken($token);

        $mailService = new MailService();
        if (!$mailService->isConfigured()) {
            return;
        }

        // Build verification URL
        $verifyUrl = url('login?verify_token=' . $token);

        // Render email template
        $emailLocale = $user->getPreferredLocale();
        $emailBody = \App\Services\EmailTemplate::render('verification', $emailLocale, [
            'userName' => $user->name, 'verifyUrl' => $verifyUrl,
        ]);

        try {
            $mailService->send(
                $user->email,
                \App\i18n\Translator::translateForLocale($emailLocale, 'email_verify_your_email_address'),
                $emailBody,
                true
            );

            LogService::getInstance()->log('email.verification_sent', null, $user->id);
        } catch (\Exception $e) {
            // Log error but don't fail registration
            error_log('Failed to send verification email: ' . $e->getMessage());
        }
    }
}
