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

    public function test_invitation_email_includes_inviter_name(): void
    {
        // Create a verified user
        $user = $this->createUser('alice@example.com', 'password123', 'Alice Inviter');
        $user->markEmailVerified();
        $this->actingAs($user);

        // Create a poll owned by Alice
        $poll = $this->createPoll(['title' => 'Important Vote'], $user->id);

        $this->clearEmails();

        // Send invitation
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'bob@example.com',
        ]);

        $this->assertSuccess($response);

        // Verify email in MailHog
        $this->assertEmailSentTo('bob@example.com');
        $email = $this->getLastEmailTo('bob@example.com');

        $this->assertStringContainsString('Alice Inviter invited you to vote', $email['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString('Alice Inviter Invited You to Vote', $email['Content']['Body']);

        // Verify Reply-To
        $this->assertStringContainsString('alice@example.com', $email['Content']['Headers']['Reply-To'][0]);
    }

    public function test_invitation_email_is_logged(): void
    {
        // Create a verified user
        $user = $this->createUser('logger@example.com', 'password123', 'Logger User');
        $user->markEmailVerified();
        $this->actingAs($user);

        // Create a poll owned by this user
        $poll = $this->createPoll(['title' => 'Logged Poll'], $user->id);

        $this->clearEmails();

        // Send invitation
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/invitations", [
            'emails' => 'invited@example.com',
        ]);

        $this->assertSuccess($response);
        $this->assertEmailSentTo('invited@example.com');

        // Verify email send was logged
        $db = \App\Database::getInstance();
        $log = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'email.invitation_sent' AND poll_id = :poll_id",
            ['poll_id' => $poll->id]
        );

        $this->assertNotNull($log, 'Invitation email send should be logged');
        $this->assertEquals('email.invitation_sent', $log['action']);
        $this->assertEquals($poll->id, $log['poll_id']);
        $this->assertEquals($user->id, $log['user_id']);
    }
}
