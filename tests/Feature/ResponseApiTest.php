<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Vote;
use App\Models\Question;
use App\Models\Response;

class ResponseApiTest extends TestCase
{
    private Vote $vote;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a vote with a question for response tests
        $this->vote = $this->createVote(['status' => 'open']);
        $this->question = $this->createQuestion($this->vote->id);
    }

    public function test_can_submit_response(): void
    {
        $response = $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [
                $this->question->id => $this->question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('voter_token', $response);
    }

    public function test_cannot_submit_to_closed_vote(): void
    {
        // Close the vote
        $this->vote->close();

        $response = $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [
                $this->question->id => $this->question->options[0]->id,
            ],
        ]);

        $this->assertError($response, 'VOTE_NOT_OPEN');
    }

    public function test_cannot_submit_to_draft_vote(): void
    {
        $draftVote = $this->createVote(['status' => 'draft']);
        $question = $this->createQuestion($draftVote->id);

        $response = $this->callApi('POST', "/api/votes/{$draftVote->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertError($response, 'VOTE_NOT_OPEN');
    }

    public function test_response_includes_voter_name_when_collected(): void
    {
        $vote = $this->createVote(['status' => 'open', 'collect_name' => true]);
        $question = $this->createQuestion($vote->id);

        $response = $this->callApi('POST', "/api/votes/{$vote->publicId}/responses", [
            'voter_name' => 'Alice Smith',
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Alice Smith', $response['response']['voter_name']);
    }

    public function test_can_submit_approval_answer(): void
    {
        $question = $this->createQuestion($this->vote->id, [
            'type' => 'approval',
            'text' => 'Select all that apply',
        ]);

        $selectedIds = [
            $question->options[0]->id,
            $question->options[2]->id,
        ];

        $response = $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [
                $question->id => $selectedIds,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($selectedIds, $response['response']['answers'][$question->id]);
    }

    public function test_can_submit_ranking_answer(): void
    {
        $question = $this->createQuestion($this->vote->id, [
            'type' => 'ranking',
            'text' => 'Rank your preferences',
        ]);

        $ranking = [
            $question->options[2]->id,
            $question->options[0]->id,
            $question->options[1]->id,
        ];

        $response = $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [
                $question->id => $ranking,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($ranking, $response['response']['answers'][$question->id]);
    }

    public function test_can_submit_text_answer(): void
    {
        $question = $this->createQuestion($this->vote->id, [
            'type' => 'text_single',
            'text' => 'What is your name?',
            'options' => [], // Text questions don't have options
        ]);

        $response = $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [
                $question->id => 'John Doe',
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('John Doe', $response['response']['answers'][$question->id]);
    }

    public function test_can_list_responses_when_visible(): void
    {
        // Create vote with visible responses
        $vote = $this->createVote([
            'status' => 'closed',
            'visibility' => 'anonymous',
            'visibility_timing' => 'after_close',
        ]);
        $question = $this->createQuestion($vote->id);

        // Submit a response directly
        Response::create($vote->id, [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/responses");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('responses', $response);
        $this->assertCount(1, $response['responses']);
    }

    public function test_cannot_list_responses_when_private(): void
    {
        $vote = $this->createVote([
            'status' => 'closed',
            'visibility' => 'private',
        ]);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/responses");

        $this->assertError($response, 'NOT_VISIBLE');
    }

    public function test_cannot_list_responses_before_close_if_after_close_timing(): void
    {
        $vote = $this->createVote([
            'status' => 'open',
            'visibility' => 'anonymous',
            'visibility_timing' => 'after_close',
        ]);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/responses");

        $this->assertError($response, 'NOT_VISIBLE');
    }

    public function test_can_list_responses_during_voting_if_during_timing(): void
    {
        $vote = $this->createVote([
            'status' => 'open',
            'visibility' => 'anonymous',
            'visibility_timing' => 'during',
        ]);
        $question = $this->createQuestion($vote->id);

        // Submit a response
        Response::create($vote->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/responses");

        $this->assertSuccess($response);
        $this->assertCount(1, $response['responses']);
    }

    public function test_admin_can_always_see_responses(): void
    {
        $vote = $this->createVote([
            'status' => 'open',
            'visibility' => 'private',
        ]);
        $question = $this->createQuestion($vote->id);

        Response::create($vote->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Use admin token in query string
        $_GET['admin_token'] = $vote->adminToken;

        $response = $this->callApi('GET', "/api/votes/{$vote->publicId}/responses");

        $this->assertSuccess($response);
        $this->assertCount(1, $response['responses']);
    }

    public function test_response_count_updates_after_submission(): void
    {
        // Check initial count
        $initial = $this->callApi('GET', "/api/votes/{$this->vote->publicId}/admin/{$this->vote->adminToken}");
        $this->assertEquals(0, $initial['vote']['response_count']);

        // Submit response
        $this->callApi('POST', "/api/votes/{$this->vote->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);

        // Check updated count
        $updated = $this->callApi('GET', "/api/votes/{$this->vote->publicId}/admin/{$this->vote->adminToken}");
        $this->assertEquals(1, $updated['vote']['response_count']);
    }
}
