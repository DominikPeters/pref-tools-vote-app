<?php

namespace App\Services;

use App\Models\SiteSetting;

// Include PHPMailer classes
require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $smtpEncryption;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->smtpHost = SiteSetting::get('mail.smtp_host', '');
        $this->smtpPort = SiteSetting::getInt('mail.smtp_port', 587);
        $this->smtpUsername = SiteSetting::get('mail.smtp_username', '');
        $this->smtpPassword = SiteSetting::get('mail.smtp_password', '');
        $this->smtpEncryption = SiteSetting::get('mail.smtp_encryption', 'tls');
        $this->fromAddress = SiteSetting::get('mail.from_address', '');
        $this->fromName = SiteSetting::get('mail.from_name', 'Pref.Tools Vote');
    }

    /**
     * Check if mail is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->smtpHost) && !empty($this->fromAddress);
    }

    /**
     * Send an email using PHPMailer
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body
     * @param bool $isHtml Whether the body is HTML
     * @param array $options Optional settings:
     *                       - unsubscribe_url: URL for List-Unsubscribe header
     *                       - one_click_url: URL for RFC 8058 one-click unsubscribe
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = false, array $options = []): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            if (!empty($this->smtpHost)) {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->Port = $this->smtpPort;

                // Authentication
                if (!empty($this->smtpUsername) && !empty($this->smtpPassword)) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $this->smtpUsername;
                    $mail->Password = $this->smtpPassword;
                }

                // Encryption
                if ($this->smtpEncryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($this->smtpEncryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
            }

            // Recipients
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);

            // Reply-To (optional)
            if (!empty($options['reply_to'])) {
                $replyToName = $options['reply_to_name'] ?? '';
                $mail->addReplyTo($options['reply_to'], $replyToName);
            }

            // Add List-Unsubscribe headers (RFC 8058)
            if (!empty($options['one_click_url'])) {
                // RFC 8058: List-Unsubscribe-Post enables one-click unsubscribe in email clients
                $mail->addCustomHeader('List-Unsubscribe', '<' . $options['one_click_url'] . '>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            } elseif (!empty($options['unsubscribe_url'])) {
                // Fallback: just the List-Unsubscribe header without one-click
                $mail->addCustomHeader('List-Unsubscribe', '<' . $options['unsubscribe_url'] . '>');
            }

            // Content
            $mail->CharSet = 'UTF-8';
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Plain text alternative for HTML emails
            if ($isHtml) {
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            // Log the error
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            throw new \Exception("Failed to send email: " . $mail->ErrorInfo);
        }
    }

    /**
     * Send email to multiple recipients
     */
    public function sendToMany(array $recipients, string $subject, string $body, bool $isHtml = false): array
    {
        $results = ['sent' => [], 'failed' => []];

        foreach ($recipients as $to) {
            try {
                $this->send($to, $subject, $body, $isHtml);
                $results['sent'][] = $to;
            } catch (\Exception $e) {
                $results['failed'][] = ['email' => $to, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
