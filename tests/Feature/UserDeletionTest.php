<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;
use App\Database;

class UserDeletionTest extends TestCase
{
    public function test_can_get_deletion_preview(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        // Create some polls
        $poll1 = $this->createPoll(['title' => 'Poll 1'], $user->id);
        $poll2 = $this->createPoll(['title' => 'Poll 2'], $user->id);

        $response = $this->callApi('GET', '/api/user/deletion-preview');

        $this->assertSuccess($response);
        $this->assertTrue($response['can_delete']);
        $this->assertFalse($response['is_sysadmin']);
        $this->assertEquals(2, $response['poll_count']);
        $this->assertCount(2, $response['polls']);
    }

    public function test_sysadmin_cannot_delete_self(): void
    {
        $admin = $this->createSysadmin('admin@test.com', 'password123', 'Admin');
        $this->actingAs($admin);

        // Preview should show can_delete = false
        $preview = $this->callApi('GET', '/api/user/deletion-preview');
        $this->assertFalse($preview['can_delete']);
        $this->assertTrue($preview['is_sysadmin']);

        // Actual deletion should fail
        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        $this->assertError($response, 'SYSADMIN_CANNOT_SELF_DELETE');
    }

    public function test_deletion_requires_correct_password(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'wrongpassword',
            'poll_action' => 'delete_all',
        ]);

        $this->assertError($response, 'INVALID_PASSWORD');

        // User should still exist
        $this->assertNotNull(User::find($user->id));
    }

    public function test_deletion_requires_poll_action(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
        ]);

        $this->assertError($response);
    }

    public function test_can_delete_account_with_delete_all_polls(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $poll1 = $this->createPoll(['title' => 'Poll 1'], $user->id);
        $poll2 = $this->createPoll(['title' => 'Poll 2'], $user->id);
        $question = $this->createQuestion($poll1->id);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Account deleted successfully', $response['message']);
        $this->assertEquals(2, $response['polls_affected']);

        // User should be deleted
        $this->assertNull(User::find($user->id));

        // Polls should be deleted
        $this->assertNull(Poll::find($poll1->id));
        $this->assertNull(Poll::find($poll2->id));
    }

    public function test_can_delete_account_with_keep_all_polls(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $poll1 = $this->createPoll(['title' => 'Poll 1'], $user->id);
        $poll2 = $this->createPoll(['title' => 'Poll 2'], $user->id);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'keep_all',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(2, $response['polls_affected']);

        // User should be deleted
        $this->assertNull(User::find($user->id));

        // Polls should still exist but be orphaned (user_id = null)
        $poll1Fresh = Poll::find($poll1->id);
        $poll2Fresh = Poll::find($poll2->id);

        $this->assertNotNull($poll1Fresh);
        $this->assertNotNull($poll2Fresh);
        $this->assertNull($poll1Fresh->userId);
        $this->assertNull($poll2Fresh->userId);

        // Polls should still be accessible via admin token
        $this->assertNotEmpty($poll1Fresh->adminToken);
    }

    public function test_deletion_requires_authentication(): void
    {
        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        $this->assertError($response);
    }

    public function test_deletion_clears_session(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        // Session should be cleared
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_deletion_logs_action(): void
    {
        $user = $this->createUser('logtest@test.com', 'password123', 'Test User');
        $userId = $user->id;
        $this->actingAs($user);

        $poll = $this->createPoll(['title' => 'My Poll'], $user->id);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        $this->assertSuccess($response);

        // Check action log
        $db = Database::getInstance();
        $log = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'user.self_deleted' AND user_id = :user_id",
            ['user_id' => $userId]
        );

        $this->assertNotNull($log, 'Action log entry should exist for user.self_deleted');
        $data = json_decode($log['data'], true);
        $this->assertEquals('logtest@test.com', $data['email']);
        $this->assertEquals('delete_all', $data['poll_action']);
        $this->assertEquals(1, $data['poll_count']);
    }

    public function test_deletion_with_no_polls(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $response = $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(0, $response['polls_affected']);
        $this->assertNull(User::find($user->id));
    }

    public function test_user_responses_are_orphaned_on_deletion(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');

        // Create a poll by another user
        $otherUser = $this->createUser('other@test.com', 'password123', 'Other');
        $poll = $this->createPoll(['status' => 'open'], $otherUser->id);
        $question = $this->createQuestion($poll->id);

        // User submits a response
        $this->actingAs($user);
        $submitResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);
        $responseId = $submitResponse['response']['id'];

        // Delete user account
        $this->callApi('DELETE', '/api/user', [
            'password' => 'password123',
            'poll_action' => 'delete_all',
        ]);

        // Response should still exist but with user_id = null
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM responses WHERE id = :id', ['id' => $responseId]);

        $this->assertNotNull($row);
        $this->assertNull($row['user_id']);
    }
}
