<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SiteSetting;

class ModerationTest extends TestCase
{
    public function test_poll_creation_succeeds_when_moderation_disabled(): void
    {
        // Moderation not enabled by default
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Normal Poll',
            'description' => 'Just a test poll',
            'questions' => [
                [
                    'type' => 'single_choice',
                    'text' => 'Pick one',
                    'options' => [
                        ['label' => 'Option A'],
                        ['label' => 'Option B'],
                    ],
                ],
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('poll', $response);
        $this->assertEquals('Normal Poll', $response['poll']['title']);
    }

    public function test_poll_creation_succeeds_when_no_api_key(): void
    {
        // Enable moderation but no API key
        SiteSetting::set('moderation.enabled', '1');

        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Test Poll Without API Key',
        ]);

        // Should succeed because no API key means moderation is skipped
        $this->assertSuccess($response);
    }

    public function test_poll_update_succeeds_when_moderation_disabled(): void
    {
        $poll = $this->createPoll(['title' => 'Original Title']);

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}", [
            'title' => 'Updated Title',
            'description' => 'New description',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Updated Title', $response['poll']['title']);
    }

    public function test_poll_update_skips_moderation_for_non_content_changes(): void
    {
        // Even if moderation were enabled, changing status shouldn't trigger it
        $poll = $this->createPoll();

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}", [
            'status' => 'open',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('open', $response['poll']['status']);
    }
}
