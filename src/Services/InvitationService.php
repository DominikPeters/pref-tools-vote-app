<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\EmailInvitation;

class InvitationService
{
    private MailService $mailer;

    public function __construct()
    {
        $this->mailer = new MailService();
    }

    /**
     * Check if mail is configured
     */
    public function isMailConfigured(): bool
    {
        return $this->mailer->isConfigured();
    }

    /**
     * Send invitations to multiple email addresses
     * Returns: ['sent' => [], 'failed' => [], 'existing' => [], 'blocked' => []]
     */
    public function sendInvitations(Poll $poll, array $emails): array
    {
        $results = ['sent' => [], 'failed' => [], 'existing' => [], 'blocked' => []];

        // Normalize emails
        $emails = array_map(fn($e) => strtolower(trim($e)), $emails);
        $emails = array_filter($emails);

        // Check which emails are unsubscribed
        $blockedStatus = UnsubscribeService::checkMultiple($emails);

        foreach ($emails as $email) {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed'][] = ['email' => $email, 'reason' => 'Invalid email format'];
                continue;
            }

            // Check if unsubscribed
            if (!empty($blockedStatus[$email])) {
                $results['blocked'][] = $email;
                continue;
            }

            // Check if already invited
            $existing = EmailInvitation::findByEmail($poll->id, $email);
            if ($existing) {
                $results['existing'][] = $email;
                continue;
            }

            try {
                // Create invitation
                $invitation = EmailInvitation::create($poll->id, $email);

                // Send email
                $this->sendInvitationEmail($poll, $invitation);
                $invitation->markSent();

                $results['sent'][] = $email;
            } catch (\Exception $e) {
                $results['failed'][] = ['email' => $email, 'reason' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Resend invitation to a specific email
     * Returns: ['success' => bool, 'blocked' => bool]
     */
    public function resendInvitation(Poll $poll, EmailInvitation $invitation): array
    {
        // Check if email is unsubscribed
        if (UnsubscribeService::isUnsubscribed($invitation->email)) {
            return ['success' => false, 'blocked' => true];
        }

        $this->sendInvitationEmail($poll, $invitation);
        $invitation->markSent();
        return ['success' => true, 'blocked' => false];
    }

    /**
     * Send the invitation email
     */
    private function sendInvitationEmail(Poll $poll, EmailInvitation $invitation): void
    {
        $voteUrl = url($poll->publicId . '?token=' . $invitation->token);
        $unsubscribeUrl = UnsubscribeService::generateUnsubscribeUrl($invitation->email);
        $oneClickUrl = UnsubscribeService::generateOneClickUrl($invitation->email);

        $subject = "You're invited to vote: " . $poll->title;

        $body = $this->renderEmailTemplate('invitation', [
            'poll' => $poll,
            'voteUrl' => $voteUrl,
            'invitation' => $invitation,
            'unsubscribeUrl' => $unsubscribeUrl,
        ]);

        $this->mailer->send($invitation->email, $subject, $body, true, [
            'one_click_url' => $oneClickUrl,
        ]);
    }

    /**
     * Render an email template
     */
    private function renderEmailTemplate(string $template, array $data): string
    {
        extract($data);
        ob_start();
        include TEMPLATES_PATH . '/emails/' . $template . '.php';
        return ob_get_clean();
    }
}
