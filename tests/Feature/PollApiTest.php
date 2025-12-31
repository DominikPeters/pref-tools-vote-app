<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;

class PollApiTest extends TestCase
{
    public function test_can_create_poll_without_auth(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'My First Poll',
            'description' => 'Testing vote creation',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('poll', $response);
        $this->assertEquals('My First Poll', $response['poll']['title']);
        $this->assertArrayHasKey('public_id', $response['poll']);
        $this->assertArrayHasKey('admin_token', $response['poll']);
        $this->assertArrayHasKey('admin_url', $response);
        $this->assertArrayHasKey('public_url', $response);
    }

    public function test_poll_created_with_auth_is_linked_to_user(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'User Poll',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($user->id, $response['poll']['user_id']);
    }

    public function test_can_create_poll_with_questions(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Poll with Questions',
            'questions' => [
                [
                    'type' => 'single_choice',
                    'text' => 'What is your favorite color?',
                    'options' => [
                        ['label' => 'Red'],
                        ['label' => 'Blue'],
                        ['label' => 'Green'],
                    ],
                ],
                [
                    'type' => 'approval',
                    'text' => 'Which fruits do you like?',
                    'options' => [
                        ['label' => 'Apple'],
                        ['label' => 'Banana'],
                    ],
                ],
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertCount(2, $response['poll']['questions']);
        $this->assertEquals('single_choice', $response['poll']['questions'][0]['type']);
        $this->assertCount(3, $response['poll']['questions'][0]['options']);
    }

    public function test_can_get_poll_by_public_id(): void
    {
        $poll = $this->createPoll(['title' => 'Public Poll']);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}");

        $this->assertSuccess($response);
        $this->assertEquals('Public Poll', $response['poll']['title']);
        $this->assertArrayNotHasKey('admin_token', $response['poll']); // Should not expose admin token
    }

    public function test_get_nonexistent_poll_returns_404(): void
    {
        $response = $this->callApi('GET', '/api/polls/NONEXISTENT');

        $this->assertError($response, 'NOT_FOUND');
    }

    public function test_can_get_poll_admin_data_with_token(): void
    {
        $poll = $this->createPoll(['title' => 'Admin Poll']);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}");

        $this->assertSuccess($response);
        $this->assertEquals('Admin Poll', $response['poll']['title']);
        $this->assertArrayHasKey('admin_token', $response['poll']); // Admin data includes token
    }

    public function test_admin_data_requires_correct_token(): void
    {
        $poll = $this->createPoll();

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/WRONGTOKEN");

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_can_update_poll(): void
    {
        $poll = $this->createPoll(['title' => 'Original Title']);

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}", [
            'title' => 'Updated Title',
            'description' => 'New description',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Updated Title', $response['poll']['title']);
        $this->assertEquals('New description', $response['poll']['description']);
    }

    public function test_can_delete_poll(): void
    {
        $poll = $this->createPoll();
        $publicId = $poll->publicId;
        $adminToken = $poll->adminToken;

        $response = $this->callApi('DELETE', "/api/polls/{$publicId}/admin/{$adminToken}");

        $this->assertSuccess($response);

        // Verify it's gone
        $getResponse = $this->callApi('GET', "/api/polls/{$publicId}");
        $this->assertError($getResponse, 'NOT_FOUND');
    }

    public function test_can_close_poll(): void
    {
        $poll = $this->createPoll(['status' => 'open']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/close");

        $this->assertSuccess($response);
        $this->assertEquals('closed', $response['poll']['status']);
        $this->assertNotNull($response['poll']['closed_at']);
    }

    public function test_can_reopen_poll(): void
    {
        $poll = $this->createPoll(['status' => 'open']);

        // Close first
        $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/close");

        // Now reopen
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/reopen");

        $this->assertSuccess($response);
        $this->assertEquals('open', $response['poll']['status']);
    }

    public function test_poll_settings_are_saved(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Configured Poll',
            'visibility' => 'anonymous',
            'collect_name' => true,
            'allow_edit_own' => false,
            'randomize_options' => true,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('anonymous', $response['poll']['visibility']);
        $this->assertTrue($response['poll']['collect_name']);
        $this->assertFalse($response['poll']['allow_edit_own']);
        $this->assertTrue($response['poll']['randomize_options']);
    }

    public function test_notify_on_response_setting(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Create poll without notifications
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Notification Test Poll',
        ]);

        $this->assertSuccess($response);
        $this->assertFalse($response['poll']['notify_on_response']);

        // Enable notifications
        $updateResponse = $this->callApi('PUT', "/api/polls/{$response['poll']['public_id']}/admin/{$response['poll']['admin_token']}", [
            'notify_on_response' => true,
        ]);

        $this->assertSuccess($updateResponse);
        $this->assertTrue($updateResponse['poll']['notify_on_response']);

        // Disable notifications
        $updateResponse2 = $this->callApi('PUT', "/api/polls/{$response['poll']['public_id']}/admin/{$response['poll']['admin_token']}", [
            'notify_on_response' => false,
        ]);

        $this->assertSuccess($updateResponse2);
        $this->assertFalse($updateResponse2['poll']['notify_on_response']);
    }

    public function test_response_count_is_included(): void
    {
        $poll = $this->createPoll(['status' => 'open']);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('response_count', $response['poll']);
        $this->assertEquals(0, $response['poll']['response_count']);
    }

    public function test_can_export_responses_csv(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        \App\Models\Response::create($poll->id, [
            'answers' => [$question->id => $question->options[0]->id]
        ]);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/export", [], [
            'format' => 'csv'
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('csv', $response['format']);
        $this->assertArrayHasKey('headers', $response);
        $this->assertArrayHasKey('rows', $response);
        $this->assertCount(1, $response['rows']);
    }

    public function test_can_export_responses_preflib(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'ranking',
            'text' => 'Rank things'
        ]);
        
        $ranking = [$question->options[0]->id, $question->options[1]->id, $question->options[2]->id];
        \App\Models\Response::create($poll->id, [
            'answers' => [$question->id => $ranking]
        ]);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/export", [], [
            'format' => 'preflib'
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('preflib', $response['format']);
        $this->assertCount(1, $response['questions']);
        $this->assertEquals('Rank things', $response['questions'][0]['question_text']);
        $this->assertCount(3, $response['questions'][0]['alternatives']);
    }

    public function test_can_sync_questions_during_update(): void
    {
        $poll = $this->createPoll();
        $q1 = $this->createQuestion($poll->id, ['text' => 'Question 1']);
        $q2 = $this->createQuestion($poll->id, ['text' => 'Question 2']);

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}", [
            'questions' => [
                [
                    'id' => $q1->id,
                    'text' => 'Updated Question 1',
                    'type' => 'single_choice',
                    'options' => [
                        ['label' => 'New Option']
                    ]
                ],
                [
                    'text' => 'New Question 3',
                    'type' => 'text_single'
                ]
            ]
        ]);

        $this->assertSuccess($response);
        $this->assertCount(2, $response['poll']['questions']);
        $this->assertEquals('Updated Question 1', $response['poll']['questions'][0]['text']);
        $this->assertEquals('New Question 3', $response['poll']['questions'][1]['text']);
        
        // Verify q2 is deleted
        $this->assertNull(\App\Models\Question::find($q2->id));
    }

    public function test_can_duplicate_poll(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $poll = $this->createPoll([
            'title' => 'Original Poll',
            'description' => 'Test description',
            'visibility' => 'anonymous',
            'collect_name' => true,
        ]);
        $q1 = $this->createQuestion($poll->id, [
            'text' => 'Question 1',
            'type' => 'single_choice',
        ]);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/duplicate");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('poll', $response);
        $this->assertArrayHasKey('admin_url', $response);

        // Check duplicated poll properties
        $this->assertEquals('Original Poll (copy)', $response['poll']['title']);
        $this->assertEquals('Test description', $response['poll']['description']);
        $this->assertEquals('anonymous', $response['poll']['visibility']);
        $this->assertTrue($response['poll']['collect_name']);
        $this->assertEquals('draft', $response['poll']['status']); // Should be draft

        // Check new IDs
        $this->assertNotEquals($poll->publicId, $response['poll']['public_id']);
        $this->assertNotEquals($poll->adminToken, $response['poll']['admin_token']);

        // Check questions are duplicated
        $this->assertCount(1, $response['poll']['questions']);
        $this->assertEquals('Question 1', $response['poll']['questions'][0]['text']);
        $this->assertNotEquals($q1->id, $response['poll']['questions'][0]['id']);
    }

    public function test_duplicate_poll_copies_all_questions_and_options(): void
    {
        $poll = $this->createPoll(['title' => 'Multi-question Poll']);
        $q1 = $this->createQuestion($poll->id, [
            'text' => 'Single choice question',
            'type' => 'single_choice',
        ]);
        $q2 = $this->createQuestion($poll->id, [
            'text' => 'Ranking question',
            'type' => 'ranking',
        ]);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/duplicate");

        $this->assertSuccess($response);
        $this->assertCount(2, $response['poll']['questions']);
        $this->assertEquals('Single choice question', $response['poll']['questions'][0]['text']);
        $this->assertEquals('Ranking question', $response['poll']['questions'][1]['text']);

        // Check options are duplicated
        $this->assertCount(3, $response['poll']['questions'][0]['options']);
        $this->assertCount(3, $response['poll']['questions'][1]['options']);
    }

    public function test_duplicate_poll_requires_valid_admin_token(): void
    {
        $poll = $this->createPoll();

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/WRONGTOKEN/duplicate");

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_duplicate_nonexistent_poll_returns_404(): void
    {
        $response = $this->callApi('POST', '/api/polls/NONEXISTENT/admin/SOMETOKEN/duplicate');

        $this->assertError($response, 'NOT_FOUND');
    }
}
