<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Poll;
use App\Models\Question;
use App\Models\SiteSetting;

class ResponseNotificationTest extends TestCase
{
    public function test_secret_ballot_sends_no_notification_or_ballot_linked_log_even_when_enabled(): void
    {
        $owner = $this->createUser('secret-owner@example.com');
        $owner->markEmailVerified();
        $poll = $this->createPoll([
            'status' => 'open', 'voting_mode' => 'secret_ballot', 'notify_on_response' => true,
        ], $owner->id);
        $question = $this->createQuestion($poll->id);
        $token = \App\Models\AccessToken::generate($poll->id, 1)[0];
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';
        $this->clearEmails();

        $result = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'access_token' => $token->token,
            'answers' => [$question->id => $question->options[0]->id],
        ]);

        $this->assertSuccess($result);
        $this->assertNoEmailsSent();
        $this->assertSame(0, (int) \App\Database::getInstance()->fetchColumn(
            'SELECT COUNT(*) FROM action_log WHERE poll_id = :poll_id', ['poll_id' => $poll->id]
        ));
    }

    public function test_notification_uses_the_recipients_language_even_when_the_voter_uses_another_language(): void
    {
        $owner = $this->createUser('french-owner@example.com', 'password123', 'Owner');
        $owner->markEmailVerified();
        $owner->updatePreferredLocale('fr');
        $poll = $this->createPoll([
            'title' => 'Recipient language', 'status' => 'open', 'locale' => 'de',
            'notify_on_response' => true, 'collect_name' => true,
        ], $owner->id);
        $question = $this->createQuestion($poll->id);
        \App\i18n\UiLocale::initialize('de');
        $this->clearEmails();
        $result = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'voter_name' => 'Besucher', 'answers' => [$question->id => $question->options[0]->id],
        ]);
        $this->assertSuccess($result);
        $this->assertEmailSentTo($owner->email);
        $email = $this->getLastEmailTo($owner->email);
        $this->assertSame(
            \App\i18n\Translator::translateForLocale('fr', 'email_new_response_to_title_from_name', ['title' => $poll->title, 'name' => 'Besucher']),
            // mb_decode_mimeheader only decodes Q-encoded spaces correctly from PHP 8.3.
            iconv_mime_decode($email['Content']['Headers']['Subject'][0], ICONV_MIME_DECODE_STRICT, 'UTF-8')
        );
        $this->assertStringContainsString('lang="fr" dir="ltr"', $email['Content']['Body']);
        $this->assertSame('de', \App\i18n\Translator::getLocale());
    }

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

    public function test_response_notification_email_is_logged(): void
    {
        // 1. Setup owner
        $owner = $this->createUser('logowner@example.com', 'password123', 'Log Owner');
        $owner->markEmailVerified();

        // 2. Setup poll with notifications enabled
        $poll = $this->createPoll([
            'title' => 'Log Test Poll',
            'status' => 'open',
            'notify_on_response' => true,
        ], $owner->id);
        $question = $this->createQuestion($poll->id);

        $this->clearEmails();

        // 3. Submit a response
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [
                $question->id => $question->options[0]->id
            ]
        ]);

        $this->assertSuccess($response);
        $this->assertEmailSentTo('logowner@example.com');

        // 4. Verify email send was logged
        $db = \App\Database::getInstance();
        $log = $db->fetch(
            "SELECT * FROM action_log WHERE action = 'email.response_notification_sent' AND poll_id = :poll_id",
            ['poll_id' => $poll->id]
        );

        $this->assertNotNull($log, 'Response notification email send should be logged');
        $this->assertEquals('email.response_notification_sent', $log['action']);
        $this->assertEquals($poll->id, $log['poll_id']);
        $this->assertEquals($owner->id, $log['user_id']);
        $this->assertEquals($response['response']['id'], $log['response_id']);
    }
}
