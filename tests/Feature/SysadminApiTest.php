<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;

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

        // Perform an action that logs
        $user = $this->createUser('testuser@test.com');
        $user->updateRole(User::ROLE_SYSADMIN);

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
}
