<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\AccessToken;

class TokenApiTest extends TestCase
{
    public function test_list_unauthorized(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/wrong-token/tokens");
        
        $this->assertError($response, 'UNAUTHORIZED');
        $this->assertEquals(403, http_response_code());
    }

    public function test_generate_unauthorized(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/wrong-token/tokens", ['count' => 1]);
        
        $this->assertError($response, 'UNAUTHORIZED');
    }

    public function test_update_unauthorized(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        
        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/wrong-token/tokens/{$token->id}", ['label' => 'New']);
        
        $this->assertError($response, 'UNAUTHORIZED');
    }

    public function test_delete_unauthorized(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        
        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/wrong-token/tokens/{$token->id}");
        
        $this->assertError($response, 'UNAUTHORIZED');
    }

    public function test_list_not_found(): void
    {
        $response = $this->callApi('GET', "/api/polls/nonexistent/admin/token/tokens");
        
        $this->assertError($response, 'UNAUTHORIZED'); // getPollWithAdminAuth returns null which leads to 403
    }

    public function test_generate_with_custom_count_and_prefix(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens", [
            'count' => 2,
            'label_prefix' => ' Test '
        ]);

        $this->assertSuccess($response);
        $this->assertCount(2, $response['tokens']);
        $this->assertEquals('Test 1', $response['tokens'][0]['label']);
        $this->assertEquals('Test 2', $response['tokens'][1]['label']);
    }

    public function test_generate_enforces_limits(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        // Test max limit
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens", [
            'count' => 200
        ]);

        $this->assertSuccess($response);
        $this->assertCount(100, $response['tokens']);

        // Test min limit
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens", [
            'count' => 0
        ]);

        $this->assertSuccess($response);
        $this->assertCount(1, $response['tokens']);
    }

    public function test_update_token_label(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/{$token->id}", [
            'label' => 'Updated Label'
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Updated Label', $response['token']['label']);

        // Verify in DB
        $token = AccessToken::find($token->id);
        $this->assertEquals('Updated Label', $token->label);
    }

    public function test_update_token_not_found(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/999", [
            'label' => 'Updated Label'
        ]);

        $this->assertError($response, 'NOT_FOUND');
    }

    public function test_update_token_wrong_poll(): void
    {
        $poll1 = $this->createPoll(['voting_mode' => 'identified']);
        $poll2 = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll2->id, 1);
        $token = $tokens[0];

        $response = $this->callApi('PUT', "/api/polls/{$poll1->publicId}/admin/{$poll1->adminToken}/tokens/{$token->id}", [
            'label' => 'Updated Label'
        ]);

        $this->assertError($response, 'NOT_FOUND');
    }

    public function test_update_token_no_label(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        $originalLabel = $token->label;

        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/{$token->id}", []);

        $this->assertSuccess($response);
        $this->assertEquals($originalLabel, $response['token']['label']);
    }

    public function test_delete_token_not_found(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/999");

        $this->assertError($response, 'NOT_FOUND');
    }
}
