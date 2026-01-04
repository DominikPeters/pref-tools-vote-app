<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AccessControlService;
use App\Models\Poll;
use App\Models\AccessToken;
use App\Models\EmailInvitation;
use App\Models\Response;
use App\Auth;

class AccessControlServiceTest extends TestCase
{
    private AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccessControlService();
    }

    public function test_validate_access_open_mode(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'open']);
        $result = $this->service->validateAccess($poll);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['identity']);
        $this->assertNull($result['error']);
    }

    public function test_validate_access_password_protected_fail(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'open', 'access_mode' => 'password']);
        $result = $this->service->validateAccess($poll);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('Password required to access this poll', $result['error']);
    }

    public function test_validate_access_password_protected_success(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'open', 'access_mode' => 'password']);
        
        $_SESSION['poll_access_' . $poll->publicId] = true;
        
        $result = $this->service->validateAccess($poll);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['error']);
    }

    public function test_validate_access_token_success(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1, 'Voter');
        $token = $tokens[0];

        $result = $this->service->validateAccess($poll, $token->token);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('token', $result['identity']['type']);
        $this->assertEquals($token->id, $result['identity']['token_id']);
        $this->assertEquals('Voter 1', $result['identity']['label']);
    }

    public function test_validate_access_token_already_used(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        
        $response = Response::create($poll->id, []);
        $token->markUsed($response->id);

        $result = $this->service->validateAccess($poll, $token->token);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('This access link has already been used', $result['error']);
    }

    public function test_validate_access_email_invitation_success(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $invite = EmailInvitation::create($poll->id, 'test@example.com');

        $result = $this->service->validateAccess($poll, $invite->token);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('email', $result['identity']['type']);
        $this->assertEquals($invite->id, $result['identity']['invitation_id']);
        $this->assertEquals('test@example.com', $result['identity']['email']);
        
        // Check if markClicked was called
        $invite = EmailInvitation::find($invite->id);
        $this->assertNotNull($invite->clickedAt);
    }

    public function test_validate_access_email_invitation_already_used(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $invite = EmailInvitation::create($poll->id, 'test@example.com');
        
        $response = Response::create($poll->id, []);
        $invite->markUsed($response->id);

        $result = $this->service->validateAccess($poll, $invite->token);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('This invitation link has already been used', $result['error']);
    }

    public function test_validate_access_invalid_token(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        
        $result = $this->service->validateAccess($poll, 'invalid-token');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('Invalid access token', $result['error']);
    }

    public function test_validate_access_login_required_not_logged_in(): void
    {
        $poll = $this->createPoll([
            'voting_mode' => 'identified',
            'access_methods' => ['login']
        ]);

        $result = $this->service->validateAccess($poll);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('Valid access token required to vote in this poll', $result['error']);
    }

    public function test_validate_access_login_required_logged_in(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $poll = $this->createPoll([
            'voting_mode' => 'identified',
            'access_methods' => ['login']
        ]);

        $result = $this->service->validateAccess($poll);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('login', $result['identity']['type']);
        $this->assertEquals($user->id, $result['identity']['user_id']);
    }

    public function test_validate_access_login_already_voted_secret_ballot(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $poll = $this->createPoll([
            'voting_mode' => 'secret_ballot',
            'access_methods' => ['login'],
            'status' => 'open'
        ]);
        
        Response::create($poll->id, [
            'user_id' => $user->id
        ]);

        $result = $this->service->validateAccess($poll);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('You have already voted in this poll', $result['error']);
    }

    public function test_validate_access_login_already_voted_identified(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $poll = $this->createPoll([
            'voting_mode' => 'identified',
            'access_methods' => ['login'],
            'status' => 'open'
        ]);
        
        $response = Response::create($poll->id, [
            'user_id' => $user->id
        ]);

        $result = $this->service->validateAccess($poll);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('login', $result['identity']['type']);
        $this->assertEquals($response->id, $result['identity']['existing_response_id']);
    }

    public function test_mark_access_used_token(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $tokens = AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        
        $response = Response::create($poll->id, []);

        $identity = [
            'type' => 'token',
            'token_id' => $token->id
        ];

        $this->service->markAccessUsed($poll, $identity, $response->id);

        $token = AccessToken::find($token->id);
        $this->assertNotNull($token->usedAt);
        $this->assertEquals($response->id, $token->responseId);
    }

    public function test_mark_access_used_email(): void
    {
        $poll = $this->createPoll(['voting_mode' => 'identified']);
        $invite = EmailInvitation::create($poll->id, 'test@example.com');
        
        $response = Response::create($poll->id, []);

        $identity = [
            'type' => 'email',
            'invitation_id' => $invite->id
        ];

        $this->service->markAccessUsed($poll, $identity, $response->id);

        $invite = EmailInvitation::find($invite->id);
        $this->assertNotNull($invite->usedAt);
        $this->assertEquals($response->id, $invite->responseId);
    }
}
