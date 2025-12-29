<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\AccessToken;
use App\Models\Response;

class VotingModeTest extends TestCase
{
    // ==========================================================================
    // Voting Mode Creation
    // ==========================================================================

    public function test_can_create_poll_with_open_mode(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Open Poll',
            'voting_mode' => 'open',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('open', $response['poll']['voting_mode']);
    }

    public function test_can_create_poll_with_identified_mode(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Identified Poll',
            'voting_mode' => 'identified',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('identified', $response['poll']['voting_mode']);
    }

    public function test_can_create_poll_with_secret_ballot_mode(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Secret Ballot Poll',
            'voting_mode' => 'secret_ballot',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('secret_ballot', $response['poll']['voting_mode']);
    }

    public function test_poll_defaults_to_open_mode(): void
    {
        $response = $this->callApi('POST', '/api/polls', [
            'title' => 'Default Mode Poll',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('open', $response['poll']['voting_mode']);
    }

    // ==========================================================================
    // Mode Locking
    // ==========================================================================

    public function test_mode_is_locked_after_first_response(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $question = $this->createQuestion($poll->id);

        // Submit a response
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Try to change voting mode
        $response = $this->callApi('PUT', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}", [
            'voting_mode' => 'identified',
        ]);

        $this->assertError($response, 'MODE_LOCKED');
    }

    public function test_mode_locked_at_is_set_after_first_response(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $question = $this->createQuestion($poll->id);

        // Submit a response
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Check the poll
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}");

        $this->assertSuccess($response);
        $this->assertNotNull($response['poll']['mode_locked_at']);
    }

    // ==========================================================================
    // Token API
    // ==========================================================================

    public function test_can_generate_tokens(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens", [
            'count' => 5,
        ]);

        $this->assertSuccess($response);
        $this->assertCount(5, $response['tokens']);
        $this->assertArrayHasKey('token', $response['tokens'][0]);
        $this->assertArrayHasKey('url', $response['tokens'][0]);
    }

    public function test_can_generate_tokens_with_label_prefix(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens", [
            'count' => 3,
            'label_prefix' => 'Voter',
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Voter 1', $response['tokens'][0]['label']);
        $this->assertEquals('Voter 2', $response['tokens'][1]['label']);
        $this->assertEquals('Voter 3', $response['tokens'][2]['label']);
    }

    public function test_can_list_tokens(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        AccessToken::generate($poll->id, 3);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens");

        $this->assertSuccess($response);
        $this->assertCount(3, $response['tokens']);
    }

    public function test_can_delete_unused_token(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];

        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/{$token->id}");

        $this->assertSuccess($response);

        // Verify deleted
        $listResponse = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens");
        $this->assertCount(0, $listResponse['tokens']);
    }

    public function test_cannot_delete_used_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];

        // Use the token by submitting a response
        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $token->token]);

        // Try to delete it
        $response = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/tokens/{$token->id}");

        $this->assertError($response, 'TOKEN_USED');
    }

    // ==========================================================================
    // Identified Mode Voting
    // ==========================================================================

    public function test_identified_poll_requires_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $question = $this->createQuestion($poll->id);

        // Try to vote without token
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->assertError($response, 'ACCESS_DENIED');
    }

    public function test_identified_poll_accepts_valid_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);
    }

    public function test_identified_poll_links_response_to_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $token->token]);

        // Check that token is linked
        $token = AccessToken::find($token->id);
        $this->assertNotNull($token->usedAt);
        $this->assertNotNull($token->responseId);
    }

    public function test_cannot_use_same_token_twice(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        // First vote
        $response1 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);
        $this->assertSuccess($response1);

        // Second vote with same token
        $response2 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[1]->id],
        ], ['token' => $tokens[0]->token]);
        $this->assertError($response2, 'ACCESS_DENIED');
    }

    // ==========================================================================
    // Secret Ballot Mode
    // ==========================================================================

    public function test_secret_ballot_requires_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->assertError($response, 'ACCESS_DENIED');
    }

    public function test_secret_ballot_does_not_link_response_to_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $token->token]);

        // Token should be marked used but NOT linked to response
        $token = AccessToken::find($token->id);
        $this->assertNotNull($token->usedAt);
        $this->assertNull($token->responseId); // Not linked for secret ballot
        $this->assertTrue($token->isSecretBallot);
    }

    public function test_secret_ballot_ignores_voter_name(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot', 'collect_name' => true]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'voter_name' => 'Should Be Ignored',
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);
        $this->assertNull($response['response']['voter_name']);
    }

    public function test_secret_ballot_prevents_editing(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $createResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $responseId = $createResponse['response']['id'];

        // Try to edit (need admin token for identified response)
        $editResponse = $this->callApi('PUT', "/api/polls/{$poll->publicId}/responses/{$responseId}", [
            'answers' => [$question->id => $question->options[1]->id],
        ], ['admin_token' => $poll->adminToken]);

        $this->assertError($editResponse, 'EDIT_NOT_ALLOWED');
    }

    public function test_secret_ballot_prevents_deleting(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $createResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $responseId = $createResponse['response']['id'];

        // Try to delete (need admin token)
        $deleteResponse = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/responses/{$responseId}", [], ['admin_token' => $poll->adminToken]);

        $this->assertError($deleteResponse, 'DELETE_NOT_ALLOWED');
    }

    // ==========================================================================
    // Open Mode (Cookie-based)
    // ==========================================================================

    public function test_open_mode_allows_voting_without_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->assertSuccess($response);
    }
}
