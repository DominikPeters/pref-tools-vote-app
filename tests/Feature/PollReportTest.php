<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SiteSetting;

class PollReportTest extends TestCase
{
    public function test_reporting_poll_sends_email_to_sysadmin(): void
    {
        // Setup sysadmin email
        SiteSetting::set('notifications.sysadmin_email', 'admin@pref.tools');
        SiteSetting::set('mail.enabled', '1');

        $poll = $this->createPoll(['title' => 'Naughty Poll']);
        $this->clearEmails();

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/report", [
            'reason' => 'spam',
            'note' => 'This poll is clearly spamming products.'
        ]);

        $this->assertSuccess($response);
        $this->assertEquals('Report received', $response['message']);

        // Verify email was sent to sysadmin
        $this->assertEmailSentTo('admin@pref.tools');
        $email = $this->getLastEmailTo('admin@pref.tools');
        
        $this->assertStringContainsString('[Poll Report] Naughty Poll', $email['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString('Spam or misleading content', $email['Content']['Body']);
        $this->assertStringContainsString('This poll is clearly spamming products.', $email['Content']['Body']);
        $this->assertStringContainsString('127.0.0.1', $email['Content']['Body']); // Reporter IP
    }

    public function test_reporting_requires_reason(): void
    {
        $poll = $this->createPoll();
        
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/report", [
            'note' => 'Some note'
        ]);

        $this->assertError($response, 'INVALID_REASON');
    }

    public function test_reporting_with_other_requires_note(): void
    {
        $poll = $this->createPoll();
        
        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/report", [
            'reason' => 'other',
            'note' => '   '
        ]);

        $this->assertError($response, 'NOTE_REQUIRED');
    }

    public function test_report_fails_gracefully_if_mail_not_configured(): void
    {
        SiteSetting::set('mail.enabled', '0');
        $poll = $this->createPoll();
        $this->clearEmails();

        $response = $this->callApi('POST', "/api/polls/{$poll->publicId}/report", [
            'reason' => 'spam',
            'note' => 'Spammy spam'
        ]);

        // Should still return success to the user
        $this->assertSuccess($response);
        $this->assertNoEmailsSent();
    }
}
