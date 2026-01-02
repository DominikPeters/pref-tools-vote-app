<?php

namespace Tests;

trait MailAssertions
{
    protected string $mailhogBaseUrl = 'http://127.0.0.1:8025';

    /**
     * Clear all messages from MailHog
     */
    protected function clearEmails(): void
    {
        $ch = curl_init($this->mailhogBaseUrl . '/api/v1/messages');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

    /**
     * Get all messages from MailHog
     */
    protected function getEmails(): array
    {
        $content = file_get_contents($this->mailhogBaseUrl . '/api/v2/messages');
        $data = json_decode($content, true);
        return $data['items'] ?? [];
    }

    /**
     * Assert that an email was sent to a specific recipient
     */
    protected function assertEmailSentTo(string $to, ?string $subject = null): void
    {
        $emails = $this->getEmails();
        $found = false;

        foreach ($emails as $email) {
            $recipients = $email['Content']['Headers']['To'] ?? [];
            $recipientFound = false;
            foreach ($recipients as $recipient) {
                if (strpos($recipient, $to) !== false) {
                    $recipientFound = true;
                    break;
                }
            }

            if ($recipientFound) {
                if ($subject === null || ($email['Content']['Headers']['Subject'][0] ?? '') === $subject) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Email to {$to}" . ($subject ? " with subject '{$subject}'" : "") . " was not found in MailHog.");
    }

    /**
     * Assert that no emails were sent
     */
    protected function assertNoEmailsSent(): void
    {
        $emails = $this->getEmails();
        $this->assertCount(0, $emails, "Expected no emails to be sent, but found " . count($emails));
    }

    /**
     * Get the last email sent to a specific recipient
     */
    protected function getLastEmailTo(string $to): ?array
    {
        $emails = $this->getEmails();
        
        foreach ($emails as $email) {
            $recipients = $email['Content']['Headers']['To'] ?? [];
            foreach ($recipients as $recipient) {
                if (strpos($recipient, $to) !== false) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Extract a link from an email body by pattern
     */
    protected function extractLinkFromEmail(array $email, string $pattern): ?string
    {
        $body = $email['Content']['Body'];
        if (preg_match($pattern, $body, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
