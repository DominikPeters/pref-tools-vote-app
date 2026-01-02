<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;
use App\Models\Question;
use App\Models\SiteSetting;

class ResponseNotificationTest extends TestCase
{
    public function test_sends_notification_to_owner_on_response(): void
    {
        // 1. Setup owner
        $owner = $this->createUser('owner@example.com', 'password123', 'Poll Owner');
        $owner->markEmailVerified();
        
        // 2. Setup poll with notifications enabled
        $poll = $this->createPoll([
            'title' => 'Notify Me Poll',
            'status' => 'open',
            'notify_on_response' => true,
            'collect_name' => true
        ], $owner->id);
        $question = $this->createQuestion($poll->id);

        $this->clearEmails();

        // 3. Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'voter_name' => 'Bob Voter',
            'answers' => [
                $question->id => $question->options[0]->id
            ]
        ]);

        $this->assertSuccess($response);

        // 4. Verify email notification
        $this->assertEmailSentTo('owner@example.com', 'New response to "Notify Me Poll" from Bob Voter');
        
        $email = $this->getLastEmailTo('owner@example.com');
        $this->assertStringContainsString('Notify Me Poll', $email['Content']['Body']);
        $this->assertStringContainsString('Bob Voter', $email['Content']['Body']);
        $this->assertStringContainsString($poll->adminToken, $email['Content']['Body']); // Link should contain admin token
    }

    public function test_does_not_send_notification_if_disabled(): void
    {
        $owner = $this->createUser('owner@example.com');
        $owner->markEmailVerified();
        
        $poll = $this->createPoll([
            'status' => 'open',
            'notify_on_response' => false
        ], $owner->id);
        $question = $this->createQuestion($poll->id);

        $this->clearEmails();

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id]
        ]);

        $this->assertNoEmailsSent();
    }

    public function test_does_not_send_notification_if_owner_unverified(): void
    {
        $owner = $this->createUser('unverified@example.com');
        // NOT marking verified
        
        $poll = $this->createPoll([
            'status' => 'open',
            'notify_on_response' => true
        ], $owner->id);
        $question = $this->createQuestion($poll->id);

        $this->clearEmails();

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id]
        ]);

        $this->assertNoEmailsSent();
    }

    public function test_does_not_send_notification_for_anonymous_poll(): void
    {
        // Poll with no owner
        $poll = $this->createPoll([
            'status' => 'open',
            'notify_on_response' => true
        ], null);
        $question = $this->createQuestion($poll->id);

        $this->clearEmails();

        $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [$question->id => $question->options[0]->id]
        ]);

        $this->assertNoEmailsSent();
    }
}
