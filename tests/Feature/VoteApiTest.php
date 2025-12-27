<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Vote;
use App\Models\Question;

class VoteApiTest extends TestCase
{
    public function test_can_create_vote_without_auth(): void
    {
        $response = $this->callApi('POST', '/api/votes', [
            'title' => 'My First Vote',
            'description' => 'Testing vote creation',
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('vote', $response);
        $this->assertEquals('My First Vote', $response['vote']['title']);
        $this->assertArrayHasKey('public_id', $response['vote']);
        $this->assertArrayHasKey('admin_token', $response['vote']);
        $this->assertArrayHasKey('admin_url', $response);
        $this->assertArrayHasKey('public_url', $response);
    }

    public function test_vote_created_with_auth_is_linked_to_user(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->callApi('POST', '/api/votes', [
            'title' => 'User Vote',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($user->id, $response['vote']['user_id']);
    }

    public function test_can_create_vote_with_questions(): void
    {
        $response = $this->callApi('POST', '/api/votes', [
            'title' => 'Vote with Questions',
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
        $this->assertCount(2, $response['vote']['questions']);
        $this->assertEquals('single_choice', $response['vote']['questions'][0]['type']);
        $this->assertCount(3, $response['vote']['questions'][0]['options']);
    }

    public function test_can_get_vote_by_public_id(): void
    {
        $vote = $this->createVote(['title' => 'Public Vote']);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}");

        $this->assertSuccess($response);
        $this->assertEquals('Public Vote', $response['vote']['title']);
        $this->assertArrayNotHasKey('admin_token', $response['vote']); // Should not expose admin token
    }

    public function test_get_nonexistent_vote_returns_404(): void
    {
        $response = $this->callApi('GET', '/api/votes/NONEXISTENT');

        $this->assertError($response, 'NOT_FOUND');
    }

    public function test_can_get_vote_admin_data_with_token(): void
    {
        $vote = $this->createVote(['title' => 'Admin Vote']);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}");

        $this->assertSuccess($response);
        $this->assertEquals('Admin Vote', $response['vote']['title']);
        $this->assertArrayHasKey('admin_token', $response['vote']); // Admin data includes token
    }

    public function test_admin_data_requires_correct_token(): void
    {
        $vote = $this->createVote();

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/admin/WRONGTOKEN");

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_can_update_vote(): void
    {
        $vote = $this->createVote(['title' => 'Original Title']);

        $response = $this->callApi('PUT', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}", [
            'title' => 'Updated Title',
            'description' => 'New description',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Updated Title', $response['vote']['title']);
        $this->assertEquals('New description', $response['vote']['description']);
    }

    public function test_can_delete_vote(): void
    {
        $vote = $this->createVote();
        $publicId = $vote->publicId;
        $adminToken = $vote->adminToken;

        $response = $this->callApi('DELETE', "/api/votes/{$publicId}/admin/{$adminToken}");

        $this->assertSuccess($response);

        // Verify it's gone
        $getResponse = $this->callApi('GET', "/api/votes/{$publicId}");
        $this->assertError($getResponse, 'NOT_FOUND');
    }

    public function test_can_close_vote(): void
    {
        $vote = $this->createVote(['status' => 'open']);

        $response = $this->callApi('POST', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}/close");

        $this->assertSuccess($response);
        $this->assertEquals('closed', $response['vote']['status']);
        $this->assertNotNull($response['vote']['closed_at']);
    }

    public function test_can_reopen_vote(): void
    {
        $vote = $this->createVote(['status' => 'open']);

        // Close first
        $this->callApi('POST', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}/close");

        // Now reopen
        $response = $this->callApi('POST', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}/reopen");

        $this->assertSuccess($response);
        $this->assertEquals('open', $response['vote']['status']);
    }

    public function test_vote_settings_are_saved(): void
    {
        $response = $this->callApi('POST', '/api/votes', [
            'title' => 'Configured Vote',
            'visibility' => 'anonymous',
            'visibility_timing' => 'during',
            'collect_name' => true,
            'allow_edit_own' => false,
            'randomize_options' => true,
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('anonymous', $response['vote']['visibility']);
        $this->assertEquals('during', $response['vote']['visibility_timing']);
        $this->assertTrue($response['vote']['collect_name']);
        $this->assertFalse($response['vote']['allow_edit_own']);
        $this->assertTrue($response['vote']['randomize_options']);
    }

    public function test_response_count_is_included(): void
    {
        $vote = $this->createVote(['status' => 'open']);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/admin/{$vote->adminToken}");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('response_count', $response['vote']);
        $this->assertEquals(0, $response['vote']['response_count']);
    }
}
