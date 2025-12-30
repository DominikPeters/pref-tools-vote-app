<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;
use App\Models\EmailInvitation;

class InvitationOwnershipTest extends TestCase
{
    public function test_logged_out_user_cannot_list_invitations(): void
    {
        $poll = $this->createPoll(['title' => 'Anonymous Poll']);
        
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations");
        
        $this->assertError($response, 'AUTH_REQUIRED');
    }

    public function test_logged_out_user_cannot_send_invitations(): void
    {
        $poll = $this->createPoll(['title' => 'Anonymous Poll']);
        
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'test@example.com'
        ]);
        
        $this->assertError($response, 'AUTH_REQUIRED');
    }

    public function test_user_cannot_manage_invitations_for_unlinked_poll(): void
    {
        $user = $this->createUser('user@example.com');
        $this->actingAs($user);
        
        $poll = $this->createPoll(['title' => 'Unlinked Poll']);
        
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations");
        
        $this->assertError($response, 'POLL_NOT_LINKED');
    }

    public function test_user_cannot_manage_invitations_for_others_poll(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        
        $poll = $this->createPoll(['title' => 'Owned Poll'], $owner->id);
        
        $this->actingAs($other);
        
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations");
        
        $this->assertError($response, 'NOT_OWNER');
    }

    public function test_owner_can_manage_invitations(): void
    {
        $user = $this->createUser('owner_ok@example.com');
        $user->markEmailVerified();
        $this->actingAs($user);
        
        $poll = $this->createPoll(['title' => 'My Poll'], $user->id);
        
        $response = $this->callApi('GET', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations");
        
        $this->assertTrue($response['ok']);
        $this->assertArrayHasKey('invitations', $response);
    }
}
