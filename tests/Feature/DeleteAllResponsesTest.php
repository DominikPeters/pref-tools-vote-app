<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Response;
use App\Models\Poll;
use App\Database;

class DeleteAllResponsesTest extends TestCase
{
    public function test_can_delete_all_responses(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        // Create multiple responses
        $this->actingAs($user);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[1]->id],
        ]);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Verify we have 3 responses
        $this->assertEquals(3, Response::countByPollId($poll->id));

        // Delete all responses
        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/responses", [
            'confirm' => true,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(3, $response['count']);
        $this->assertEquals('All responses deleted', $response['message']);

        // Verify no responses remain
        $this->assertEquals(0, Response::countByPollId($poll->id, true));
    }

    public function test_delete_all_requires_confirmation(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        $this->actingAs($user);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Try without confirmation
        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/responses", []);

        $this->assertError($response, 'CONFIRMATION_REQUIRED');

        // Verify response still exists
        $this->assertEquals(1, Response::countByPollId($poll->id));
    }

    public function test_delete_all_requires_valid_admin_token(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        $this->actingAs($user);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/invalid-token/responses", [
            'confirm' => true,
        ]);

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_delete_all_handles_no_responses(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll([], $user->id);

        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/responses", [
            'confirm' => true,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(0, $response['count']);
    }

    public function test_delete_all_includes_withdrawn_responses(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        $this->actingAs($user);

        // Create and withdraw a response
        $submitResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);
        $responseId = $submitResponse['response']['id'];
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses/{$responseId}/withdraw");

        // Create another active response
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[1]->id],
        ]);

        // Delete all (should include withdrawn)
        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/responses", [
            'confirm' => true,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals(2, $response['count']);
    }

    public function test_delete_all_logs_action(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/responses", [
            'confirm' => true,
        ]);

        // Check action log
        $db = Database::getInstance();
        $log = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'poll.responses_deleted_all' AND poll_id = :poll_id",
            ['poll_id' => $poll->id]
        );

        $this->assertNotNull($log);
        $data = json_decode($log['data'], true);
        $this->assertEquals(1, $data['count']);
    }
}
