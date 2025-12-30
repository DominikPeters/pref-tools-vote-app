<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Database;

class UserDataAccessTest extends TestCase
{
    public function test_can_get_user_data(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        // Create a poll with collectName enabled and submit a response
        $poll = $this->createPoll([
            'title' => 'Test Poll',
            'status' => 'open',
            'collect_name' => true,
        ], $user->id);
        $question = $this->createQuestion($poll->id);

        $submitResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
            'voter_name' => 'Test Voter',
        ]);
        $this->assertSuccess($submitResponse);

        // Get user data
        $response = $this->callApi('GET', '/api/user/data');

        $this->assertSuccess($response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('user@test.com', $response['user']['email']);
        $this->assertEquals('Test User', $response['user']['name']);

        $this->assertArrayHasKey('responses', $response);
        $this->assertCount(1, $response['responses']);

        $responseData = $response['responses'][0];
        $this->assertEquals($poll->title, $responseData['poll']['title']);
        $this->assertEquals('Test Voter', $responseData['voter_name']);
        $this->assertArrayHasKey('ip_address', $responseData);
        $this->assertArrayHasKey('user_agent', $responseData);
        $this->assertArrayHasKey('created_at', $responseData);
        $this->assertArrayHasKey('answers', $responseData);

        $this->assertArrayHasKey('activity_logs', $response);
        $this->assertArrayHasKey('data_collected', $response);
    }

    public function test_user_data_requires_authentication(): void
    {
        $response = $this->callApi('GET', '/api/user/data');
        $this->assertError($response);
    }

    public function test_user_data_includes_answer_details(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id, [
            'text' => 'What is your favorite color?',
            'options' => [
                ['label' => 'Red'],
                ['label' => 'Blue'],
            ],
        ]);

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('GET', '/api/user/data');

        $this->assertSuccess($response);
        $answers = $response['responses'][0]['answers'];
        $this->assertCount(1, $answers);
        $this->assertEquals('What is your favorite color?', $answers[0]['question_text']);
    }

    public function test_user_data_shows_withdrawn_responses(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        // Submit and withdraw response
        $submitResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);
        $responseId = $submitResponse['response']['id'];

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses/{$responseId}/withdraw");

        // Get user data
        $response = $this->callApi('GET', '/api/user/data');

        $this->assertSuccess($response);
        $this->assertCount(1, $response['responses']);
        $this->assertEquals('withdrawn', $response['responses'][0]['status']);
    }

    public function test_can_export_user_data(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        // Create a poll with collectName enabled
        $poll = $this->createPoll([
            'title' => 'My Poll',
            'status' => 'open',
            'collect_name' => true,
        ], $user->id);
        $question = $this->createQuestion($poll->id);

        // Submit a response
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
            'voter_name' => 'Self Vote',
        ]);

        // Export data
        $response = $this->callApi('GET', '/api/user/export');

        $this->assertSuccess($response);
        $this->assertEquals('json', $response['export_format']);
        $this->assertArrayHasKey('export_date', $response);

        // Check profile
        $this->assertEquals('user@test.com', $response['profile']['email']);
        $this->assertEquals('Test User', $response['profile']['name']);

        // Check polls created
        $this->assertCount(1, $response['polls_created']);
        $this->assertEquals('My Poll', $response['polls_created'][0]['title']);

        // Check responses
        $this->assertCount(1, $response['poll_responses']);
        $this->assertEquals('My Poll', $response['poll_responses'][0]['poll_title']);
        $this->assertEquals('Self Vote', $response['poll_responses'][0]['voter_name']);
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->callApi('GET', '/api/user/export');
        $this->assertError($response);
    }

    public function test_export_includes_multiple_polls_and_responses(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        // Create multiple polls
        $poll1 = $this->createPoll(['title' => 'Poll 1', 'status' => 'open'], $user->id);
        $poll2 = $this->createPoll(['title' => 'Poll 2', 'status' => 'open'], $user->id);
        $question1 = $this->createQuestion($poll1->id);
        $question2 = $this->createQuestion($poll2->id);

        // Vote in both polls
        $this->callApi('POST', "/api/polls/{$poll1->publicId}/responses", [
            'answers' => [$question1->id => $question1->options[0]->id],
        ]);
        $this->callApi('POST', "/api/polls/{$poll2->publicId}/responses", [
            'answers' => [$question2->id => $question2->options[0]->id],
        ]);

        $response = $this->callApi('GET', '/api/user/export');

        $this->assertSuccess($response);
        $this->assertCount(2, $response['polls_created']);
        $this->assertCount(2, $response['poll_responses']);
    }

    public function test_user_data_shows_activity_logs(): void
    {
        $user = $this->createUser('user@test.com', 'password123', 'Test User');
        $this->actingAs($user);

        // Generate some activity via API which logs actions
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('GET', '/api/user/data');

        $this->assertSuccess($response);
        $this->assertArrayHasKey('activity_logs', $response);
        $this->assertIsArray($response['activity_logs']);

        // Should include response submission log
        $actions = array_column($response['activity_logs'], 'action');
        $this->assertContains('response.submitted', $actions);
    }
}
