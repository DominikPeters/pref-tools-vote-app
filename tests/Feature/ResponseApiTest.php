<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class ResponseApiTest extends TestCase
{
    private Poll $poll;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a poll with a question for response tests
        $this->poll = $this->createPoll(['status' => 'open']);
        $this->question = $this->createQuestion($this->poll->id);
    }

    public function test_can_submit_response(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [
                $this->question->id => $this->question->options[0]->id,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('voter_token', $response);
    }

    public function test_cannot_submit_to_closed_poll(): void
    {
        // Close the poll
        $this->poll->close();

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [
                $this->question->id => $this->question->options[0]->id,
            ],
        ]);

        $this->assertError($response, 'POLL_NOT_OPEN');
    }

    public function test_cannot_submit_to_draft_poll(): void
    {
        $draftPoll = $this->createPoll(['status' => 'draft']);
        $question = $this->createQuestion($draftPoll->id);

        $response = $this->callApi('POST', "/api/polls/{$draftPoll->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $this->assertError($response, 'POLL_NOT_OPEN');
    }

    public function test_response_includes_voter_name_when_collected(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'collect_name' => true]);
        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
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
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'text' => 'Select all that apply',
        ]);

        $selectedIds = [
            $question->options[0]->id,
            $question->options[2]->id,
        ];

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [
                $question->id => $selectedIds,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($selectedIds, $response['response']['answers'][$question->id]);
    }

    public function test_can_submit_ranking_answer(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'text' => 'Rank your preferences',
        ]);

        $ranking = [
            $question->options[2]->id,
            $question->options[0]->id,
            $question->options[1]->id,
        ];

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [
                $question->id => $ranking,
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals($ranking, $response['response']['answers'][$question->id]);
    }

    public function test_can_submit_text_answer(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'text_single',
            'text' => 'What is your name?',
            'options' => [], // Text questions don't have options
        ]);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [
                $question->id => 'John Doe',
            ],
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('John Doe', $response['response']['answers'][$question->id]);
    }

    public function test_can_list_responses_when_visible(): void
    {
        // Create poll with visible responses
        $poll = $this->createPoll([
            'status' => 'open',
            'visibility' => 'anonymous',
        ]);
        $question = $this->createQuestion($poll->id);

        // Submit a response directly
        Response::create($poll->id, [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/responses");

        $this->assertSuccess($response);
        $this->assertArrayHasKey('responses', $response);
        $this->assertCount(1, $response['responses']);
    }

    public function test_cannot_list_responses_when_private(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'visibility' => 'private',
        ]);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/responses");

        $this->assertError($response, 'NOT_VISIBLE');
    }

    public function test_admin_can_always_see_responses(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'visibility' => 'private',
        ]);
        $question = $this->createQuestion($poll->id);

        Response::create($poll->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/responses", [], [
            'admin_token' => $poll->adminToken
        ]);

        $this->assertSuccess($response);
        $this->assertCount(1, $response['responses']);
    }

    public function test_response_count_updates_after_submission(): void
    {
        // Check initial count
        $initial = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}");
        $this->assertEquals(0, $initial['poll']['response_count']);

        // Submit response
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);

        // Check updated count
        $updated = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/admin/{$this->poll->adminToken}");
        $this->assertEquals(1, $updated['poll']['response_count']);
    }

    public function test_can_get_single_response(): void
    {
        $response = Response::create($this->poll->id, [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);

        $apiResponse = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/responses/{$response->id}");

        $this->assertSuccess($apiResponse);
        $this->assertEquals($response->id, $apiResponse['response']['id']);
    }

    public function test_can_update_own_response(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'allow_edit_own' => true]);
        $question = $this->createQuestion($poll->id);

        $response = Response::create($poll->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        // Mock voter token cookie
        $_COOKIE['voter_token_' . $poll->publicId] = $response->voterToken;

        $apiResponse = $this->callApi('PUT', "/api/polls/{$poll->publicId}/responses/{$response->id}", [
            'answers' => [$question->id => $question->options[1]->id],
        ]);

        $this->assertSuccess($apiResponse);
        $this->assertEquals($question->options[1]->id, $apiResponse['response']['answers'][$question->id]);
    }

    public function test_cannot_update_own_response_if_disabled(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'allow_edit_own' => false]);
        $question = $this->createQuestion($poll->id);

        $response = Response::create($poll->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $_COOKIE['voter_token_' . $poll->publicId] = $response->voterToken;

        $apiResponse = $this->callApi('PUT', "/api/polls/{$poll->publicId}/responses/{$response->id}", [
            'answers' => [$question->id => $question->options[1]->id],
        ]);

        $this->assertError($apiResponse, 'EDIT_NOT_ALLOWED');
    }

    public function test_can_delete_own_response(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'allow_edit_own' => true]);
        $question = $this->createQuestion($poll->id);

        $response = Response::create($poll->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $_COOKIE['voter_token_' . $poll->publicId] = $response->voterToken;

        $apiResponse = $this->callApi('DELETE', "/api/polls/{$poll->publicId}/responses/{$response->id}");

        $this->assertSuccess($apiResponse);
        $this->assertNull(Response::find($response->id));
    }

    // =========================================================================
    // "Other" Option Tests
    // =========================================================================

    public function test_can_submit_single_choice_other_answer(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite food',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger']],
        ]);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => ['other' => 'Sushi'],
            ],
        ]);

        $this->assertSuccess($response);

        // Verify a user-added option was created
        $answeredOptionId = $response['response']['answers'][$question->id];
        $this->assertIsInt($answeredOptionId);

        $option = \App\Models\Option::find($answeredOptionId);
        $this->assertEquals('Other: Sushi', $option->label);
        $this->assertTrue($option->features['isUserAdded'] ?? false);
    }

    public function test_can_submit_approval_other_answer(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'approval',
            'text' => 'Select foods you like',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger']],
        ]);

        $pizzaId = $question->options[0]->id;

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => [$pizzaId, ['other' => 'Tacos']],
            ],
        ]);

        $this->assertSuccess($response);

        $answers = $response['response']['answers'][$question->id];
        $this->assertCount(2, $answers);
        $this->assertContains($pizzaId, $answers);

        // Find the "Other" option ID
        $otherOptionId = array_values(array_diff($answers, [$pizzaId]))[0];
        $option = \App\Models\Option::find($otherOptionId);
        $this->assertEquals('Other: Tacos', $option->label);
        $this->assertTrue($option->features['isUserAdded'] ?? false);
    }

    public function test_other_answers_are_grouped_by_text(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite food',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger']],
        ]);

        // First voter submits "Sushi"
        $response1 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => ['other' => 'Sushi'],
            ],
        ]);

        // Second voter also submits "Sushi"
        $response2 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => ['other' => 'Sushi'],
            ],
        ]);

        $this->assertSuccess($response1);
        $this->assertSuccess($response2);

        // Both should have voted for the same option
        $optionId1 = $response1['response']['answers'][$question->id];
        $optionId2 = $response2['response']['answers'][$question->id];
        $this->assertEquals($optionId1, $optionId2);
    }

    public function test_other_answer_ignored_if_allow_other_disabled(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite color',
            'settings' => ['allowOther' => false],  // Disabled
            'options' => [['label' => 'Red'], ['label' => 'Blue']],
        ]);

        // Try to submit an "other" answer
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => ['other' => 'Green'],
            ],
        ]);

        $this->assertSuccess($response);

        // The answer should be stored as the raw object (not converted)
        // since allowOther is disabled, processOtherAnswers won't convert it
        $answer = $response['response']['answers'][$question->id];
        $this->assertIsArray($answer);
        $this->assertEquals('Green', $answer['other']);
    }

    public function test_empty_other_text_treated_as_no_answer(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite food',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger']],
        ]);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => ['other' => '   '],  // Whitespace only
            ],
        ]);

        $this->assertSuccess($response);

        // Should be treated as no answer
        $this->assertNull($response['response']['answers'][$question->id] ?? null);
    }
}
