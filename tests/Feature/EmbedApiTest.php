<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;

class EmbedApiTest extends TestCase
{
    // ==========================================
    // Embed Token Generation
    // ==========================================

    public function test_can_generate_embed_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/embed-token");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('embed_token', $response);
        $this->assertArrayHasKey('embed_url', $response);
        $this->assertNotEmpty($response['embed_token']);
    }

    public function test_embed_token_is_reused_on_second_request(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);

        $response1 = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/embed-token");
        $response2 = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/embed-token");

        $this->assertSuccess($response1);
        $this->assertSuccess($response2);
        $this->assertEquals($response1['embed_token'], $response2['embed_token']);
    }

    public function test_embed_token_requires_valid_admin_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/invalid-token/embed-token");

        $this->assertError($response, 'INVALID_TOKEN');
    }

    public function test_embed_token_requires_open_voting_mode(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/embed-token");

        $this->assertError($response, 'INVALID_MODE');
    }

    // ==========================================
    // Get Poll for Embedding
    // ==========================================

    public function test_can_get_poll_via_embed_api(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $this->createQuestion($poll->id, ['text' => 'Test Question']);

        $response = $this->callApi('GET', "/api/embed/{$poll->publicId}/{$embedToken}");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('poll', $response);
        $this->assertArrayHasKey('translations', $response);
        $this->assertArrayHasKey('site_url', $response);
        $this->assertEquals($poll->title, $response['poll']['title']);
        $this->assertCount(1, $response['poll']['questions']);
    }

    public function test_embed_api_returns_404_for_invalid_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);

        $response = $this->callApi('GET', "/api/embed/{$poll->publicId}/invalid-token");

        $this->assertError($response, 'NOT_FOUND');
    }

    public function test_embed_api_requires_embedding_enabled(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        // allow_embedding is false by default
        $embedToken = $poll->getOrCreateEmbedToken();

        $response = $this->callApi('GET', "/api/embed/{$poll->publicId}/{$embedToken}");

        $this->assertError($response, 'NOT_EMBEDDABLE');
    }

    public function test_embed_api_requires_open_status(): void
    {
        $poll = $this->createPoll(['status' => 'closed', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $response = $this->callApi('GET', "/api/embed/{$poll->publicId}/{$embedToken}");

        $this->assertError($response, 'NOT_EMBEDDABLE');
    }

    public function test_embed_api_requires_open_voting_mode(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $response = $this->callApi('GET', "/api/embed/{$poll->publicId}/{$embedToken}");

        $this->assertError($response, 'NOT_EMBEDDABLE');
    }

    // ==========================================
    // Submit Response via Embed
    // ==========================================

    public function test_can_submit_response_via_embed_api(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $question = $this->createQuestion($poll->id);
        $optionId = $question->options[0]->id;

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'answers' => [
                $question->id => $optionId,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('site_url', $response);
        $this->assertEquals(1, $poll->getResponseCount());
    }

    public function test_embed_response_includes_thank_you_message(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',
            'thank_you_message' => '## Thanks for voting!',
        ]);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('thank_you_message', $response);
        $this->assertStringContainsString('Thanks for voting', $response['thank_you_message']);
    }

    public function test_embed_response_includes_results_url_when_public(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',
            'visibility' => 'full', // Public results
        ]);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertTrue($response['results_viewable']);
        $this->assertNotNull($response['results_url']);
    }

    public function test_embed_response_no_results_url_when_private(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',
            'visibility' => 'private', // Private results
        ]);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertFalse($response['results_viewable']);
        $this->assertNull($response['results_url']);
    }

    public function test_embed_response_fails_for_closed_poll(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        // Close the poll
        $poll->close();

        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertError($response, 'NOT_EMBEDDABLE');
    }

    public function test_embed_response_collects_voter_name_when_enabled(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',
            'collect_name' => true,
        ]);
        $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/embed/{$poll->publicId}/{$embedToken}/responses", [
            'voter_name' => 'John Doe',
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);

        // Verify the response was saved with the name
        $responses = \App\Models\Response::findByPollId($poll->id);
        $this->assertCount(1, $responses);
        $this->assertEquals('John Doe', $responses[0]->voterName);
    }

    // ==========================================
    // Poll Model Methods
    // ==========================================

    public function test_poll_can_be_embedded_returns_true_when_all_conditions_met(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll = $poll->update(['allow_embedding' => true]);

        $this->assertTrue($poll->canBeEmbedded());
    }

    public function test_poll_can_be_embedded_returns_false_when_embedding_disabled(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        // allow_embedding defaults to false

        $this->assertFalse($poll->canBeEmbedded());
    }

    public function test_poll_can_be_embedded_returns_false_for_identified_mode(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $poll->update(['allow_embedding' => true]);

        $this->assertFalse($poll->canBeEmbedded());
    }

    public function test_poll_can_be_embedded_returns_false_for_secret_ballot(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $poll->update(['allow_embedding' => true]);

        $this->assertFalse($poll->canBeEmbedded());
    }

    public function test_poll_can_be_embedded_returns_false_for_closed_poll(): void
    {
        $poll = $this->createPoll(['status' => 'closed', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);

        $this->assertFalse($poll->canBeEmbedded());
    }

    public function test_poll_can_be_embedded_returns_false_for_draft_poll(): void
    {
        $poll = $this->createPoll(['status' => 'draft', 'voting_mode' => 'open']);
        $poll->update(['allow_embedding' => true]);

        $this->assertFalse($poll->canBeEmbedded());
    }

    public function test_find_by_embed_token_returns_poll(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $embedToken = $poll->getOrCreateEmbedToken();

        $found = Poll::findByEmbedToken($poll->publicId, $embedToken);

        $this->assertNotNull($found);
        $this->assertEquals($poll->id, $found->id);
    }

    public function test_find_by_embed_token_returns_null_for_wrong_token(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll->getOrCreateEmbedToken();

        $found = Poll::findByEmbedToken($poll->publicId, 'wrong-token');

        $this->assertNull($found);
    }

    public function test_find_by_embed_token_returns_null_for_wrong_public_id(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $embedToken = $poll->getOrCreateEmbedToken();

        $found = Poll::findByEmbedToken('wrong-public-id', $embedToken);

        $this->assertNull($found);
    }

    public function test_to_admin_array_includes_embedding_fields(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'open']);
        $poll = $poll->update(['allow_embedding' => true]);
        $embedToken = $poll->getOrCreateEmbedToken();

        // Reload to get updated embed token
        $poll = Poll::find($poll->id);
        $data = $poll->toAdminArray();

        $this->assertArrayHasKey('allow_embedding', $data);
        $this->assertArrayHasKey('embed_token', $data);
        $this->assertArrayHasKey('can_be_embedded', $data);
        $this->assertTrue($data['allow_embedding']);
        $this->assertEquals($embedToken, $data['embed_token']);
        $this->assertTrue($data['can_be_embedded']);
    }

    public function test_to_embed_array_returns_minimal_data(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',
            'visibility' => 'full',
        ]);
        $this->createQuestion($poll->id);
        $poll->loadQuestions();

        $data = $poll->toEmbedArray();

        $this->assertArrayHasKey('public_id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('questions', $data);
        $this->assertArrayHasKey('results_viewable', $data);
        // Should NOT include admin-only fields
        $this->assertArrayNotHasKey('admin_token', $data);
        $this->assertArrayNotHasKey('user_id', $data);
    }
}
