<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\AccessToken;

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
}