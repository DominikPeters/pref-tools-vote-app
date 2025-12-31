<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;
use App\Models\SiteSetting;

class SysadminApiTest extends TestCase
{
    // ==========================================
    // Authentication & Authorization
    // ==========================================

    public function test_sysadmin_endpoints_require_authentication(): void
    {
        $response = $this->callApi('GET', '/api/sysadmin/stats');

        $this->assertError($response);
        $this->assertEquals('Authentication required', $response['error']);
    }

    public function test_sysadmin_endpoints_require_sysadmin_role(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('GET', '/api/sysadmin/stats');

        $this->assertError($response);
        $this->assertEquals('Sysadmin access required', $response['error']);
    }

    public function test_sysadmin_can_access_stats(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/stats');

        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('polls', $response);
        $this->assertArrayHasKey('responses', $response);
        $this->assertArrayHasKey('logs', $response);
    }

    // ==========================================
    // Stats Endpoint
    // ==========================================

    public function test_stats_returns_correct_counts(): void
    {
        $admin = $this->createSysadmin();
        $this->createUser('user1@test.com');
        $this->createUser('user2@test.com');
        $this->createPoll(['status' => 'open']);
        $this->createPoll(['status' => 'draft']);

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/stats');

        $this->assertEquals(3, $response['users']['total']);
        $this->assertEquals(1, $response['users']['sysadmins']);
        $this->assertEquals(2, $response['polls']['total']);
        $this->assertEquals(1, $response['polls']['open']);
        $this->assertEquals(1, $response['polls']['draft']);
    }

    // ==========================================
    // Users Endpoints
    // ==========================================

    public function test_can_list_users(): void
    {
        $admin = $this->createSysadmin();
        $this->createUser('user1@test.com');
        $this->createUser('user2@test.com');

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/users');

        $this->assertArrayHasKey('users', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertEquals(3, $response['total']);
        $this->assertCount(3, $response['users']);
    }

    public function test_users_list_includes_role(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/users');

        $adminInList = array_filter($response['users'], fn($u) => $u['role'] === 'sysadmin');
        $this->assertCount(1, $adminInList);
    }

    public function test_can_update_user_role(): void
    {
        $admin = $this->createSysadmin();
        $user = $this->createUser();

        $this->actingAs($admin);

        $response = $this->callApi('PUT', "/api/sysadmin/users/{$user->id}", [
            'role' => User::ROLE_SYSADMIN
        ]);

        $this->assertArrayHasKey('user', $response);
        $this->assertEquals(User::ROLE_SYSADMIN, $response['user']['role']);

        // Verify persisted
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->isSysadmin());
    }

    public function test_cannot_remove_sysadmin_from_self(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('PUT', "/api/sysadmin/users/{$admin->id}", [
            'role' => User::ROLE_USER
        ]);

        $this->assertError($response);
        $this->assertEquals('Cannot remove sysadmin role from yourself', $response['error']);
    }

    public function test_cannot_update_nonexistent_user(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('PUT', '/api/sysadmin/users/99999', [
            'role' => User::ROLE_SYSADMIN
        ]);

        $this->assertError($response);
        $this->assertEquals('User not found', $response['error']);
    }

    public function test_can_delete_user(): void
    {
        $admin = $this->createSysadmin();
        $user = $this->createUser();
        $userId = $user->id;

        $this->actingAs($admin);

        $response = $this->callApi('DELETE', "/api/sysadmin/users/{$userId}");

        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        $this->assertNull(User::find($userId));
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('DELETE', "/api/sysadmin/users/{$admin->id}");

        $this->assertError($response);
        $this->assertEquals('Cannot delete yourself', $response['error']);
    }

    public function test_cannot_delete_nonexistent_user(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('DELETE', '/api/sysadmin/users/99999');

        $this->assertError($response);
        $this->assertEquals('User not found', $response['error']);
    }

    // ==========================================
    // Polls Endpoints
    // ==========================================

    public function test_can_list_all_polls(): void
    {
        $admin = $this->createSysadmin();
        $user = $this->createUser();

        $this->createPoll(['title' => 'Poll 1'], $user->id);
        $this->createPoll(['title' => 'Poll 2']);
        $this->createPoll(['title' => 'Poll 3'], $admin->id);

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/polls');

        $this->assertArrayHasKey('polls', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertEquals(3, $response['total']);
        $this->assertCount(3, $response['polls']);
    }

    public function test_polls_list_includes_owner_email(): void
    {
        $admin = $this->createSysadmin();
        $user = $this->createUser('owner@test.com');

        $this->createPoll(['title' => 'Owned Poll'], $user->id);

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/polls');

        $ownedPoll = array_filter($response['polls'], fn($p) => $p['title'] === 'Owned Poll');
        $ownedPoll = array_values($ownedPoll)[0];

        $this->assertEquals('owner@test.com', $ownedPoll['owner_email']);
    }

    public function test_polls_list_shows_null_for_anonymous_polls(): void
    {
        $admin = $this->createSysadmin();

        $this->createPoll(['title' => 'Anonymous Poll']);

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/polls');

        $anonPoll = array_filter($response['polls'], fn($p) => $p['title'] === 'Anonymous Poll');
        $anonPoll = array_values($anonPoll)[0];

        $this->assertNull($anonPoll['owner_email']);
    }

    public function test_can_delete_poll(): void
    {
        $admin = $this->createSysadmin();
        $poll = $this->createPoll();
        $pollId = $poll->id;

        $this->actingAs($admin);

        $response = $this->callApi('DELETE', "/api/sysadmin/polls/{$pollId}");

        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        $this->assertNull(Poll::find($pollId));
    }

    public function test_cannot_delete_nonexistent_poll(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('DELETE', '/api/sysadmin/polls/99999');

        $this->assertError($response);
        $this->assertEquals('Poll not found', $response['error']);
    }

    // ==========================================
    // Logs Endpoint
    // ==========================================

    public function test_can_list_logs(): void
    {
        $admin = $this->createSysadmin();

        // Creating users generates log entries
        $this->createUser('user1@test.com');

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/logs');

        $this->assertArrayHasKey('logs', $response);
        $this->assertArrayHasKey('total', $response);
    }

    public function test_logs_include_user_email(): void
    {
        $admin = $this->createSysadmin('admin@test.com');

        // Register a user via API - this creates a log entry with user_id
        $this->callApi('POST', '/api/auth/register', [
            'email' => 'testuser@test.com',
            'password' => 'password123',
            'name' => 'Test User',
        ]);

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/logs');

        // At least one log should have a user email
        $logsWithEmail = array_filter($response['logs'], fn($l) => !empty($l['user_email']));
        $this->assertNotEmpty($logsWithEmail);
    }

    // ==========================================
    // Pagination
    // ==========================================

    public function test_users_pagination_works(): void
    {
        $admin = $this->createSysadmin();
        for ($i = 1; $i <= 5; $i++) {
            $this->createUser("user{$i}@test.com");
        }

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/users', [], ['limit' => '2', 'offset' => '0']);

        $this->assertEquals(6, $response['total']); // 5 users + 1 admin
        $this->assertCount(2, $response['users']);
        $this->assertEquals(2, $response['limit']);
        $this->assertEquals(0, $response['offset']);
    }

    public function test_polls_pagination_works(): void
    {
        $admin = $this->createSysadmin();
        for ($i = 1; $i <= 5; $i++) {
            $this->createPoll(['title' => "Poll {$i}"]);
        }

        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/polls', [], ['limit' => '3', 'offset' => '2']);

        $this->assertEquals(5, $response['total']);
        $this->assertCount(3, $response['polls']);
        $this->assertEquals(3, $response['limit']);
        $this->assertEquals(2, $response['offset']);
    }

    // ==========================================
    // Settings Endpoints
    // ==========================================

    public function test_can_get_settings(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('GET', '/api/sysadmin/settings');

        $this->assertArrayHasKey('settings', $response);
        $this->assertArrayHasKey('site.name', $response['settings']);
        $this->assertArrayHasKey('mail.enabled', $response['settings']);
    }

    public function test_settings_require_sysadmin(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('GET', '/api/sysadmin/settings');

        $this->assertError($response);
        $this->assertEquals('Sysadmin access required', $response['error']);
    }

    public function test_can_update_settings(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('PUT', '/api/sysadmin/settings', [
            'settings' => [
                'site.name' => 'My Custom Site',
                'mail.smtp_host' => 'smtp.test.com',
                'site.registration_enabled' => '0',
            ]
        ]);

        $this->assertArrayHasKey('settings', $response);
        $this->assertArrayHasKey('updated', $response);
        $this->assertEquals('My Custom Site', $response['settings']['site.name']);
        $this->assertEquals('smtp.test.com', $response['settings']['mail.smtp_host']);
        $this->assertEquals('0', $response['settings']['site.registration_enabled']);

        // Verify persisted
        $this->assertEquals('My Custom Site', SiteSetting::get('site.name'));
        $this->assertEquals('smtp.test.com', SiteSetting::get('mail.smtp_host'));
    }

    public function test_update_settings_ignores_unknown_keys(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('PUT', '/api/sysadmin/settings', [
            'settings' => [
                'unknown.key' => 'value',
                'site.name' => 'Valid Update',
            ]
        ]);

        // Should succeed with valid key
        $this->assertEquals('Valid Update', $response['settings']['site.name']);

        // Unknown key should not be stored
        $this->assertNull(SiteSetting::get('unknown.key'));
    }

    public function test_update_settings_skips_masked_values(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        // First, set a secret
        SiteSetting::set('mail.smtp_password', 'original_secret');

        // Update with masked value (as if user didn't change it)
        $response = $this->callApi('PUT', '/api/sysadmin/settings', [
            'settings' => [
                'mail.smtp_password' => '••••••••cret',
            ]
        ]);

        // Original should be preserved
        $this->assertEquals('original_secret', SiteSetting::get('mail.smtp_password'));
    }

    public function test_update_settings_replaces_secret_with_new_value(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        // First, set a secret
        SiteSetting::set('mail.smtp_password', 'original_secret');

        // Update with new value
        $response = $this->callApi('PUT', '/api/sysadmin/settings', [
            'settings' => [
                'mail.smtp_password' => 'new_secret_value',
            ]
        ]);

        // Should be updated
        $this->assertEquals('new_secret_value', SiteSetting::get('mail.smtp_password'));

        // Response should show masked version
        $this->assertEquals('••••••••alue', $response['settings']['mail.smtp_password']);
    }

    public function test_settings_secrets_are_masked_in_response(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        SiteSetting::set('mail.smtp_password', 'super_secret_password');
        SiteSetting::set('api.openai_key', 'sk-1234567890abcdef');

        $response = $this->callApi('GET', '/api/sysadmin/settings');

        // Secrets should be masked
        $this->assertEquals('••••••••word', $response['settings']['mail.smtp_password']);
        $this->assertEquals('••••••••cdef', $response['settings']['api.openai_key']);
    }

    public function test_update_settings_requires_settings_object(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        $response = $this->callApi('PUT', '/api/sysadmin/settings', [
            'invalid' => 'data'
        ]);

        $this->assertError($response);
        $this->assertEquals('Settings object required', $response['error']);
    }

    public function test_test_email_requires_mail_enabled(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        SiteSetting::set('mail.enabled', '0');

        $response = $this->callApi('POST', '/api/sysadmin/settings/test-email');

        $this->assertError($response);
        $this->assertEquals('Email is not enabled in settings', $response['error']);
    }

    public function test_test_email_requires_sysadmin_email(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);

        SiteSetting::set('mail.enabled', '1');
        SiteSetting::set('notifications.sysadmin_email', '');

        $response = $this->callApi('POST', '/api/sysadmin/settings/test-email');

        $this->assertError($response);
        $this->assertEquals('Sysadmin notification email is not configured', $response['error']);
    }
}
