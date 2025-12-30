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
