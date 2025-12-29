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
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('newuser@example.com', $response['user']['email']);
    }

    public function test_registration_requires_email(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'password' => 'password123',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_registration_requires_password_min_length(): void
    {
        $response = $this->callApi('POST', '/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $this->assertError($response, 'VALIDATION_ERROR');
    }

    public function test_duplicate_email_fails(): void
    {
        // First registration
        $this->callApi('POST', '/api/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'password123',
        ]);

        // Second registration with same email
        $response = $this->callApi('POST', '/api/auth/register', [
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
}
