<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;

class InvitationApiTest extends TestCase
{
    public function test_unverified_user_cannot_send_invitations(): void
    {
        // Create an unverified user
        $user = $this->createUser('unverified@example.com', 'password123');
        $this->assertFalse($user->isEmailVerified());
        $this->actingAs($user);

        // Create a poll owned by this user
        $poll = $this->createPoll(['title' => 'Test Poll'], $user->id);

        // Try to send invitations
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'voter@example.com',
        ]);

        $this->assertError($response, 'EMAIL_NOT_VERIFIED');
    }

    public function test_verified_user_can_send_invitations(): void
    {
        // Create a verified user
        $user = $this->createUser('verified@example.com', 'password123');
        $user->markEmailVerified();
        $this->assertTrue($user->isEmailVerified());
        $this->actingAs($user);

        // Create a poll owned by this user
        $poll = $this->createPoll(['title' => 'Test Poll'], $user->id);

        // Try to send invitations - should pass verification check
        // (may fail due to mail not configured, but not due to verification)
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'voter@example.com',
        ]);

        // Either success or mail not configured - not EMAIL_NOT_VERIFIED
        $this->assertTrue(
            ($response['ok'] ?? false) || ($response['code'] ?? '') === 'MAIL_NOT_CONFIGURED',
            'Expected success or MAIL_NOT_CONFIGURED, got: ' . ($response['code'] ?? 'unknown')
        );
    }

    public function test_anonymous_poll_cannot_send_invitations_when_logged_out(): void
    {
        // Create a poll without an owner
        $poll = $this->createPoll(['title' => 'Anonymous Poll']);
        $this->assertNull($poll->userId);

        // Try to send invitations - should fail with AUTH_REQUIRED
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'voter@example.com',
        ]);

        $this->assertError($response, 'AUTH_REQUIRED');
    }

    public function test_unverified_user_cannot_resend_invitation(): void
    {
        // Create an unverified user
        $user = $this->createUser('unverified_resend@example.com', 'password123');
        $this->assertFalse($user->isEmailVerified());
        $this->actingAs($user);

        // Create a poll owned by this user
        $poll = $this->createPoll(['title' => 'Test Poll'], $user->id);

        // Create an invitation directly
        $invitation = \App\Models\EmailInvitation::create($poll->id, 'existing@example.com');

        // Try to resend the invitation
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations/{$invitation->id}/resend");

        $this->assertError($response, 'EMAIL_NOT_VERIFIED');
    }
}
