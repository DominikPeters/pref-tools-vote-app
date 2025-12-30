<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Models\AccessToken;
use App\Models\EmailInvitation;
use App\Database;

class SecretBallotTest extends TestCase
{
    public function test_secret_ballot_requires_token_even_if_access_mode_is_link(): void
    {
        // Create a secret ballot poll with access_mode 'link' (default)
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
            'access_mode' => 'link',
        ]);
        $question = $this->createQuestion($poll->id);

        // Generate an access token
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0]->token;

        // Try to submit response WITHOUT token - should fail
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);
        $this->assertError($response, 'ACCESS_DENIED');
        $this->assertEquals('Valid access token required to vote in this poll', $response['error']);

        // Try to submit response WITH token in GET - should succeed
        // This simulates what the JS should do or what the backend should pick up from session
        $responseWithToken = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ], ['token' => $token]);
        $this->assertSuccess($responseWithToken);
    }

    public function test_secret_ballot_fails_if_token_not_in_session_and_not_in_get(): void
    {
        // This test simulates the case where the token was in the URL of the page,
        // but not saved to the session because access_mode was 'link'.

        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
            'access_mode' => 'link',
        ]);
        $question = $this->createQuestion($poll->id);

        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0]->token;

        // In a real scenario:
        // 1. User visits /:publicId?token=XYZ
        // 2. PageController::poll is called.
        // We simulate this by calling a mock of what PageController would do.
        
        // Let's see if we can simulate the session being set or not.
        $_GET['token'] = $token;
        
        // This is what PageController::poll does currently:
        if ($poll->accessMode === 'token') {
            $t = $_GET['token'] ?? null;
            if ($t) {
                $accessToken = AccessToken::findByToken($poll->id, $t);
                if ($accessToken && !$accessToken->usedAt) {
                    $_SESSION['poll_token_' . $poll->publicId] = $t;
                }
            }
        }
        
        // Since access_mode is 'link', the session variable is NOT set.
        $this->assertArrayNotHasKey('poll_token_' . $poll->publicId, $_SESSION);

        // 3. User submits vote (POST /api/...)
        // Reset $_GET for the POST request
        unset($_GET['token']);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id,
            ],
        ]);

        // It should FAIL if it's not in the session
        $this->assertError($response, 'ACCESS_DENIED');
        $this->assertEquals('Valid access token required to vote in this poll', $response['error']);
    }

    public function test_secret_ballot_does_not_store_ip_address(): void
    {
        // Set up fake IP and user agent
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Browser 1.0';

        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
        ]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);

        // Check the database directly - IP and user agent should be NULL
        $db = Database::getInstance();
        $row = $db->fetch('SELECT ip_address, user_agent FROM responses WHERE id = :id', [
            'id' => $response['response']['id'],
        ]);

        $this->assertNull($row['ip_address'], 'Secret ballot should not store IP address');
        $this->assertNull($row['user_agent'], 'Secret ballot should not store user agent');
    }

    public function test_non_secret_ballot_stores_ip_address(): void
    {
        // Set up fake IP and user agent
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Browser 1.0';

        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'open',  // Not secret ballot
        ]);
        $question = $this->createQuestion($poll->id);

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->assertSuccess($response);

        // Check the database directly - IP and user agent should be stored
        $db = Database::getInstance();
        $row = $db->fetch('SELECT ip_address, user_agent FROM responses WHERE id = :id', [
            'id' => $response['response']['id'],
        ]);

        $this->assertEquals('192.168.1.100', $row['ip_address']);
        $this->assertEquals('Test Browser 1.0', $row['user_agent']);
    }

    public function test_secret_ballot_token_has_no_used_at_timestamp(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
        ]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);
        $tokenId = $tokens[0]->id;

        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);

        // Reload the token
        $token = AccessToken::find($tokenId);

        // Token should be marked as used via is_secret_ballot but NOT have a timestamp
        $this->assertTrue($token->isSecretBallot, 'Token should be marked as secret ballot');
        $this->assertNull($token->usedAt, 'Secret ballot token should not have used_at timestamp');
        $this->assertTrue($token->isUsed(), 'Token should still report as used');
        $this->assertNull($token->responseId, 'Secret ballot token should not link to response');
    }

    public function test_secret_ballot_email_invitation_has_no_used_at_timestamp(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
        ]);
        $question = $this->createQuestion($poll->id);
        $invitation = EmailInvitation::create($poll->id, 'voter@example.com');

        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $invitation->token]);

        $this->assertSuccess($response);

        // Reload the invitation
        $invitation = EmailInvitation::find($invitation->id);

        // Invitation should be marked as used via is_secret_ballot but NOT have a timestamp
        $this->assertTrue($invitation->isSecretBallot, 'Invitation should be marked as secret ballot');
        $this->assertNull($invitation->usedAt, 'Secret ballot invitation should not have used_at timestamp');
        $this->assertTrue($invitation->isUsed(), 'Invitation should still report as used');
        $this->assertNull($invitation->responseId, 'Secret ballot invitation should not link to response');
    }

    public function test_secret_ballot_token_cannot_be_reused(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
        ]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        // First submission should succeed
        $response1 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);
        $this->assertSuccess($response1);

        // Second submission with same token should fail
        $response2 = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[1]->id],
        ], ['token' => $tokens[0]->token]);
        $this->assertError($response2, 'ACCESS_DENIED');
        $this->assertEquals('This access link has already been used', $response2['error']);
    }

    public function test_secret_ballot_does_not_log_response_submission(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'secret_ballot',
        ]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);

        // Count existing action logs
        $db = Database::getInstance();
        $beforeCount = $db->fetch('SELECT COUNT(*) as count FROM action_log')['count'];

        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);

        // Check that no response.submitted log was created
        $afterCount = $db->fetch('SELECT COUNT(*) as count FROM action_log')['count'];
        $responseLog = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'response.submitted' AND poll_id = :poll_id",
            ['poll_id' => $poll->id]
        );

        $this->assertNull($responseLog, 'Secret ballot should not log response.submitted');
    }

    public function test_non_secret_ballot_token_has_used_at_timestamp(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'voting_mode' => 'identified',  // Not secret ballot
        ]);
        $question = $this->createQuestion($poll->id);
        $tokens = AccessToken::generate($poll->id, 1);
        $tokenId = $tokens[0]->id;

        // Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id],
        ], ['token' => $tokens[0]->token]);

        $this->assertSuccess($response);

        // Reload the token
        $token = AccessToken::find($tokenId);

        // Token should have a timestamp and link to response
        $this->assertFalse($token->isSecretBallot, 'Non-secret token should not be marked as secret ballot');
        $this->assertNotNull($token->usedAt, 'Non-secret token should have used_at timestamp');
        $this->assertNotNull($token->responseId, 'Non-secret token should link to response');
    }
}