<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Auth;
use App\Models\User;
use App\Models\SiteSetting;

class AuthApiTest extends TestCase
{
    public function test_user_can_register(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('newuser@example.com', $response['user']['email']);
        $this->assertEquals('New User', $response['user']['name']);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_registration_requires_email(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Test User',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_registration_requires_password_min_length(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_duplicate_email_fails(): void
    {
        // First registration
        $this->callApi('POST', '/api/auth/register', [
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
        ]);

        // Second registration with same email
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Second User',
            'email' => 'duplicate@example.com',
            'password' => 'password456',
        ]);

        $this->assertError($response, 'REGISTRATION_FAILED');
    }

    public function test_registration_fails_when_disabled(): void
    {
        // Disable registration
        SiteSetting::set('site.registration_enabled', '0');

        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'REGISTRATION_DISABLED');
        $this->assertEquals('User registration is currently disabled', $response['error']);
    }

    public function test_registration_works_when_enabled(): void
    {
        // Explicitly enable registration
        SiteSetting::set('site.registration_enabled', '1');

        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Enabled User',
            'email' => 'enabled@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('user', $response);
    }

    public function test_user_can_login(): void
    {
        // Create user first
        $this->createUser('login@example.com', 'password123');

        // Now login
        $response = $this->callApi('POST', '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('login@example.com', $response['user']['email']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser('wrong@example.com', 'correctpassword');

        $response = $this->callApi('POST', '/api/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertError($response, 'INVALID_CREDENTIALS');
    }

    public function test_login_fails_with_nonexistent_user(): void
    {
        $response = $this->callApi('POST', '/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'INVALID_CREDENTIALS');
    }

    public function test_get_current_user_when_logged_in(): void
    {
        $user = $this->createUser('me@example.com', 'password123');
        $this->actingAs($user);

        $response = $this->callApi('GET', '/api/auth/me');

        $this->assertSuccess($response);
        $this->assertEquals('me@example.com', $response['user']['email']);
    }

    public function test_get_current_user_when_not_logged_in(): void
    {
        $response = $this->callApi('GET', '/api/auth/me');

        $this->assertError($response, 'NOT_AUTHENTICATED');
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUser('logout@example.com', 'password123');
        $this->actingAs($user);

        $response = $this->callApi('POST', '/api/auth/logout');

        $this->assertSuccess($response);

        // Verify logged out
        $meResponse = $this->callApi('GET', '/api/auth/me');
        $this->assertError($meResponse, 'NOT_AUTHENTICATED');
    }

    public function test_user_can_list_own_polls(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->createPoll(['title' => 'Poll 1'], $user->id);
        $this->createPoll(['title' => 'Poll 2'], $user->id);
        $this->createPoll(['title' => 'Other Poll']); // Not owned by user

        $response = $this->callApi('GET', '/api/user/polls');

        $this->assertSuccess($response);
        $this->assertCount(2, $response['polls']);
    }

    public function test_user_can_list_own_responses(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);

        // Submit response as logged in user
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('GET', '/api/user/responses');

        $this->assertSuccess($response);
        $this->assertCount(1, $response['responses']);
        $this->assertEquals($poll->title, $response['responses'][0]['poll_title']);
    }

    public function test_user_can_claim_poll(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Create an anonymous poll (no user_id)
        $poll = $this->createPoll(['title' => 'Anonymous Poll']);
        $this->assertNull($poll->userId);

        $response = $this->callApi('POST', '/api/user/claim-poll', [
            'public_id' => $poll->publicId,
            'admin_token' => $poll->adminToken,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($user->id, $response['poll']['user_id']);

        // Verify in database
        $updatedPoll = \App\Models\Poll::find($poll->id);
        $this->assertEquals($user->id, $updatedPoll->userId);
    }

    public function test_registration_sets_verification_token(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Token User',
            'email' => 'token@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        
        $user = User::findByEmail('token@example.com');
        $this->assertNotNull($user->emailVerificationToken);
        $this->assertNotNull($user->emailVerificationExpires);
    }

    public function test_new_user_is_not_email_verified(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertFalse($response['user']['email_verified']);
        
        // Verify email was sent
        $this->assertEmailSentTo('unverified@example.com', 'Verify your email address');
    }

    public function test_verify_email_with_valid_token(): void
    {
        $user = $this->createUser('verify@example.com', 'password123');

        // Set verification token directly
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);

        $response = $this->callApi('POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);

        $this->assertSuccess($response);
        $this->assertTrue($response['user']['email_verified']);

        // Verify in database
        $updatedUser = User::find($user->id);
        $this->assertTrue($updatedUser->isEmailVerified());
    }

    public function test_verify_email_with_invalid_token(): void
    {
        $response = $this->callApi('POST', '/api/auth/verify-email', [
            'token' => 'invalid_token_12345',
        ]);

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_verify_email_with_expired_token(): void
    {
        $user = $this->createUser('expired@example.com', 'password123');

        // Set verification token with expired time
        $db = \App\Database::getInstance();
        $token = bin2hex(random_bytes(32));
        $expired = (new \DateTime('-1 day'))->format('Y-m-d H:i:s');
        $db->update('users', [
            'email_verification_token' => $token,
            'email_verification_expires' => $expired,
        ], 'id = :id', ['id' => $user->id]);

        $response = $this->callApi('POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_resend_verification_when_not_verified(): void
    {
        $user = $this->createUser('resend@example.com', 'password123');
        $this->actingAs($user);

        // Clear welcome email from registration (wait, createUser might not send it if MailService wasn't configured when it ran, but in TestCase::setUp we configured it)
        $this->clearEmails();

        $response = $this->callApi('POST', '/api/auth/resend-verification');

        $this->assertSuccess($response);
        $this->assertEmailSentTo('resend@example.com', 'Verify your email address');
    }

    public function test_resend_verification_when_already_verified(): void
    {
        $user = $this->createUser('verified@example.com', 'password123');
        $user->markEmailVerified();
        $this->actingAs($user);

        $response = $this->callApi('POST', '/api/auth/resend-verification');

        $this->assertError($response, 'ALREADY_VERIFIED');
    }

    public function test_forgot_password_sends_email(): void
    {
        $user = $this->createUser('forgot@example.com', 'password123');
        $this->clearEmails();

        $response = $this->callApi('POST', '/api/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $this->assertSuccess($response);
        $this->assertEmailSentTo('forgot@example.com', 'Reset your password');
    }

    public function test_full_registration_and_verification_flow(): void
    {
        $this->clearEmails();

        // 1. Register
        $this->callApi('POST', '/api/auth/register', [
            'name' => 'Flow User',
            'email' => 'flow@example.com',
            'password' => 'password123',
        ]);

        // 2. Get verification link from email
        $email = $this->getLastEmailTo('flow@example.com');
        $token = $this->extractLinkFromEmail($email, '/verify_token=([a-zA-Z0-9]+)/');
        $this->assertNotNull($token, 'Verification token not found in email');

        // 3. Verify email
        $response = $this->callApi('POST', '/api/auth/verify-email', [
            'token' => $token,
        ]);

        $this->assertSuccess($response);
        $this->assertTrue($response['user']['email_verified']);
    }

    public function test_verification_email_is_logged(): void
    {
        $this->clearEmails();

        $response = $this->callApi('POST', '/api/auth/register', [
            'name' => 'Log Test User',
            'email' => 'logtest@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertEmailSentTo('logtest@example.com');

        // Verify email send was logged
        $db = \App\Database::getInstance();
        $log = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'email.verification_sent' AND user_id = :user_id",
            ['user_id' => $response['user']['id']]
        );

        $this->assertNotNull($log, 'Verification email send should be logged');
        $this->assertEquals('email.verification_sent', $log['action']);
    }

    public function test_full_password_reset_flow(): void
    {
        $user = $this->createUser('reset-flow@example.com', 'old-password');
        $this->clearEmails();

        // 1. Request reset
        $this->callApi('POST', '/api/auth/forgot-password', [
            'email' => 'reset-flow@example.com',
        ]);

        // 2. Get reset link from email
        $email = $this->getLastEmailTo('reset-flow@example.com');
        $token = $this->extractLinkFromEmail($email, '/reset_token=([a-zA-Z0-9]+)/');
        $this->assertNotNull($token, 'Reset token not found in email');

        // 3. Reset password
        $response = $this->callApi('POST', '/api/auth/reset-password', [
            'token' => $token,
            'password' => 'new-password-123',
        ]);

        $this->assertSuccess($response);

        // 4. Verify login with new password
        Auth::reset();
        $loginResponse = $this->callApi('POST', '/api/auth/login', [
            'email' => 'reset-flow@example.com',
            'password' => 'new-password-123',
        ]);

        $this->assertSuccess($loginResponse);
    }

    public function test_forgot_password_does_not_reveal_nonexistent_email(): void
    {
        $response = $this->callApi('POST', '/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should still return success to prevent email enumeration
        $this->assertSuccess($response);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = $this->createUser('reset@example.com', 'oldpassword');

        // Set reset token directly
        $token = bin2hex(random_bytes(32));
        $user->setPasswordResetToken($token);

        $response = $this->callApi('POST', '/api/auth/reset-password', [
            'token' => $token,
            'password' => 'newpassword123',
        ]);

        $this->assertSuccess($response);

        // Verify can login with new password
        Auth::reset();
        $loginResponse = $this->callApi('POST', '/api/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
        ]);

        $this->assertSuccess($loginResponse);
    }

    public function test_reset_password_with_invalid_token(): void
    {
        $response = $this->callApi('POST', '/api/auth/reset-password', [
            'token' => 'invalid_token_12345',
            'password' => 'newpassword123',
        ]);

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_reset_password_with_expired_token(): void
    {
        $user = $this->createUser('expired_reset@example.com', 'password123');

        // Set reset token with expired time
        $db = \App\Database::getInstance();
        $token = bin2hex(random_bytes(32));
        $expired = (new \DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $db->update('users', [
            'password_reset_token' => $token,
            'password_reset_expires' => $expired,
        ], 'id = :id', ['id' => $user->id]);

        $response = $this->callApi('POST', '/api/auth/reset-password', [
            'token' => $token,
            'password' => 'newpassword123',
        ]);

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_reset_password_requires_min_length(): void
    {
        $user = $this->createUser('minlen@example.com', 'password123');
        $token = bin2hex(random_bytes(32));
        $user->setPasswordResetToken($token);

        $response = $this->callApi('POST', '/api/auth/reset-password', [
            'token' => $token,
            'password' => 'short',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->createUser('changepass@example.com', 'oldpassword123');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword456',
        ]);

        $this->assertSuccess($response);

        // Verify can login with new password
        Auth::reset();
        $loginResponse = $this->callApi('POST', '/api/auth/login', [
            'email' => 'changepass@example.com',
            'password' => 'newpassword456',
        ]);

        $this->assertSuccess($loginResponse);
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = $this->createUser('wrongcurrent@example.com', 'correctpassword');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword456',
        ]);

        $this->assertError($response, 'INVALID_PASSWORD');
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'anypassword',
            'new_password' => 'newpassword456',
        ]);

        $this->assertError($response, 'AUTH_REQUIRED');
    }

    public function test_change_password_requires_min_length(): void
    {
        $user = $this->createUser('minlenchange@example.com', 'password123');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'password123',
            'new_password' => 'short',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_change_password_fails_if_same_as_current(): void
    {
        $user = $this->createUser('samepass@example.com', 'password123');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'password123',
            'new_password' => 'password123',
        ]);

        $this->assertError($response, 'SAME_PASSWORD');
    }

    public function test_old_password_no_longer_works_after_change(): void
    {
        $user = $this->createUser('oldnowork@example.com', 'oldpassword123');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/auth/password', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword456',
        ]);

        $this->assertSuccess($response);

        // Verify old password no longer works
        Auth::reset();
        $loginResponse = $this->callApi('POST', '/api/auth/login', [
            'email' => 'oldnowork@example.com',
            'password' => 'oldpassword123',
        ]);

        $this->assertError($loginResponse, 'INVALID_CREDENTIALS');
    }

    // =========================================================================
    // Change Name Tests
    // =========================================================================

    public function test_user_can_change_name(): void
    {
        $user = $this->createUser('changename@example.com', 'password123', 'Old Name');
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/user/name', [
            'name' => 'New Name',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('New Name', $response['user']['name']);

        // Verify in database
        $updatedUser = User::find($user->id);
        $this->assertEquals('New Name', $updatedUser->name);
    }

    public function test_change_name_requires_authentication(): void
    {
        $response = $this->callApi('PUT', '/api/user/name', [
            'name' => 'New Name',
        ]);

        $this->assertError($response, 'AUTH_REQUIRED');
    }

    public function test_change_name_requires_name_field(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/user/name', []);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_change_name_rejects_empty_name(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/user/name', [
            'name' => '   ',
        ]);

        $this->assertError($response, 'EMPTY_NAME');
    }

    public function test_change_name_trims_whitespace(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('PUT', '/api/user/name', [
            'name' => '  Trimmed Name  ',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Trimmed Name', $response['user']['name']);
    }
}
