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
     * Returns: ['sent' => [], 'failed' => [], 'existing' => []]
     */
    public function sendInvitations(Poll $poll, array $emails): array
    {
        $results = ['sent' => [], 'failed' => [], 'existing' => []];

        foreach ($emails as $email) {
            $email = strtolower(trim($email));

            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if (!empty($email)) {
                    $results['failed'][] = ['email' => $email, 'reason' => 'Invalid email format'];
                }
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
     */
    public function resendInvitation(Poll $poll, EmailInvitation $invitation): bool
    {
        $this->sendInvitationEmail($poll, $invitation);
        $invitation->markSent();
        return true;
    }

    /**
     * Send the invitation email
     */
    private function sendInvitationEmail(Poll $poll, EmailInvitation $invitation): void
    {
        $voteUrl = url($poll->publicId . '?token=' . $invitation->token);

        $subject = "You're invited to vote: " . $poll->title;

        $body = $this->renderEmailTemplate('invitation', [
            'poll' => $poll,
            'voteUrl' => $voteUrl,
            'invitation' => $invitation,
        ]);

        $this->mailer->send($invitation->email, $subject, $body, true);
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
