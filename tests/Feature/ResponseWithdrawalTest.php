<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Models\AccessToken;
use App\Database;

class ResponseWithdrawalTest extends TestCase
{
    private Poll $poll;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open', 'allow_edit_own' => false]);
        $this->question = $this->createQuestion($this->poll->id);
    }

    public function test_can_withdraw_response_with_voter_token(): void
    {
        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $this->assertSuccess($response);
        $responseId = $response['response']['id'];
        $voterToken = $response['voter_token'];

        // Set the voter token cookie
        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;

        // Withdraw the response
        $withdrawResponse = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertSuccess($withdrawResponse);
        $this->assertEquals('withdrawn', $withdrawResponse['response']['status']);
        $this->assertNotNull($withdrawResponse['response']['withdrawn_at']);
    }

    public function test_can_withdraw_response_as_logged_in_user(): void
    {
        $user = $this->createUser('voter@test.com', 'password123', 'Voter');
        $this->actingAs($user);

        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $this->assertSuccess($response);
        $responseId = $response['response']['id'];

        // Clear the cookie to prove we're using user auth
        unset($_COOKIE['voter_token_' . $this->poll->publicId]);

        // Withdraw the response
        $withdrawResponse = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertSuccess($withdrawResponse);
        $this->assertEquals('withdrawn', $withdrawResponse['response']['status']);
    }

    public function test_withdrawal_deletes_answers(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $responseId = $response['response']['id'];
        $voterToken = $response['voter_token'];

        // Verify answers exist
        $db = Database::getInstance();
        $answerCount = $db->fetch(
            'SELECT COUNT(*) as count FROM answers WHERE response_id = :id',
            ['id' => $responseId]
        )['count'];
        $this->assertGreaterThan(0, $answerCount);

        // Withdraw
        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");

        // Verify answers are deleted
        $answerCount = $db->fetch(
            'SELECT COUNT(*) as count FROM answers WHERE response_id = :id',
            ['id' => $responseId]
        )['count'];
        $this->assertEquals(0, $answerCount);
    }

    public function test_withdrawal_clears_personal_data(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Browser';

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'voter_name' => 'Test Voter',
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $responseId = $response['response']['id'];
        $voterToken = $response['voter_token'];

        // Withdraw
        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");

        // Check database directly
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM responses WHERE id = :id', ['id' => $responseId]);

        $this->assertNull($row['voter_name']);
        $this->assertNull($row['ip_address']);
        $this->assertNull($row['user_agent']);
        $this->assertEquals('withdrawn', $row['status']);
        // But voter_token should still be there to prevent re-voting
        $this->assertNotNull($row['voter_token']);
    }

    public function test_withdrawal_prevents_re_voting(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $voterToken = $response['voter_token'];

        // Withdraw
        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$response['response']['id']}/withdraw");

        // Try to vote again with same cookie
        $newResponse = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[1]->id],
        ]);

        // The existing response (withdrawn) should be found by voter token
        // Since allow_edit_own is false, this should fail
        $this->assertError($newResponse, 'ALREADY_SUBMITTED');
    }

    public function test_cannot_withdraw_secret_ballot(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'secret_ballot']);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $withdrawResponse = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses/{$response['response']['id']}/withdraw");
        $this->assertError($withdrawResponse, 'WITHDRAW_NOT_ALLOWED');
    }

    public function test_cannot_withdraw_others_response(): void
    {
        // User A submits
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $responseId = $response['response']['id'];

        // User B tries to withdraw (no cookie, not logged in as owner)
        unset($_COOKIE['voter_token_' . $this->poll->publicId]);
        $withdrawResponse = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertError($withdrawResponse, 'WITHDRAW_NOT_ALLOWED');
    }

    public function test_cannot_withdraw_already_withdrawn(): void
    {
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $responseId = $response['response']['id'];
        $voterToken = $response['voter_token'];

        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;

        // First withdrawal succeeds
        $withdraw1 = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertSuccess($withdraw1);

        // Second withdrawal fails
        $withdraw2 = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertError($withdraw2, 'ALREADY_WITHDRAWN');
    }

    public function test_withdrawal_works_even_if_edit_disabled(): void
    {
        // Poll explicitly has allow_edit_own = false (set in setUp)
        $this->assertFalse($this->poll->allowEditOwn);

        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $responseId = $response['response']['id'];
        $voterToken = $response['voter_token'];

        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;

        // Withdrawal should still work
        $withdrawResponse = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$responseId}/withdraw");
        $this->assertSuccess($withdrawResponse);
    }

    public function test_withdrawn_responses_not_in_results(): void
    {
        // Submit two responses
        $response1 = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $voterToken1 = $response1['voter_token'];

        unset($_COOKIE['voter_token_' . $this->poll->publicId]);

        $response2 = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[1]->id],
        ]);

        // Withdraw first response
        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken1;
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$response1['response']['id']}/withdraw");

        // Make poll visible
        $this->poll->update(['visibility' => 'anonymous', 'status' => 'closed']);

        // List responses - should only show the active one
        $listResponse = $this->callApi('GET', "/api/polls/{$this->poll->publicId}/responses");
        $this->assertSuccess($listResponse);
        $this->assertCount(1, $listResponse['responses']);
        $this->assertEquals($response2['response']['id'], $listResponse['responses'][0]['id']);
    }

    public function test_response_counts_include_withdrawn_info(): void
    {
        // Submit and withdraw a response
        $response = $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses", [
            'answers' => [$this->question->id => $this->question->options[0]->id],
        ]);
        $voterToken = $response['voter_token'];

        $_COOKIE['voter_token_' . $this->poll->publicId] = $voterToken;
        $this->callApi('POST', "/api/polls/{$this->poll->publicId}/responses/{$response['response']['id']}/withdraw");

        // Check counts
        $activeCount = Response::countByPollId($this->poll->id);
        $withdrawnCount = Response::countWithdrawnByPollId($this->poll->id);
        $totalCount = Response::countByPollId($this->poll->id, true);

        $this->assertEquals(0, $activeCount);
        $this->assertEquals(1, $withdrawnCount);
        $this->assertEquals(1, $totalCount);
    }
}
