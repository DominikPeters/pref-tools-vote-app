<?php

namespace App\Services;

use App\Models\SiteSetting;

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
     * Send an email
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = false): bool
    {
        // If SMTP is configured, use SMTP
        if (!empty($this->smtpHost)) {
            return $this->sendViaSMTP($to, $subject, $body, $isHtml);
        }

        // Fall back to PHP's mail() function
        return $this->sendViaMail($to, $subject, $body, $isHtml);
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSMTP(string $to, string $subject, string $body, bool $isHtml): bool
    {
        $socket = null;

        try {
            // Connect to SMTP server
            $protocol = '';
            if ($this->smtpEncryption === 'ssl') {
                $protocol = 'ssl://';
            }

            $socket = @fsockopen(
                $protocol . $this->smtpHost,
                $this->smtpPort,
                $errno,
                $errstr,
                30
            );

            if (!$socket) {
                throw new \Exception("Could not connect to SMTP server: $errstr ($errno)");
            }

            stream_set_timeout($socket, 30);

            // Read greeting
            $this->readResponse($socket, 220);

            // Send EHLO
            $this->sendCommand($socket, "EHLO " . gethostname());
            $this->readResponse($socket, 250);

            // STARTTLS if using TLS
            if ($this->smtpEncryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                $this->readResponse($socket, 220);

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \Exception("Failed to enable TLS encryption");
                }

                // Re-send EHLO after STARTTLS
                $this->sendCommand($socket, "EHLO " . gethostname());
                $this->readResponse($socket, 250);
            }

            // Authenticate if credentials provided
            if (!empty($this->smtpUsername) && !empty($this->smtpPassword)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->readResponse($socket, 334);

                $this->sendCommand($socket, base64_encode($this->smtpUsername));
                $this->readResponse($socket, 334);

                $this->sendCommand($socket, base64_encode($this->smtpPassword));
                $this->readResponse($socket, 235);
            }

            // Send email
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromAddress}>");
            $this->readResponse($socket, 250);

            $this->sendCommand($socket, "RCPT TO:<{$to}>");
            $this->readResponse($socket, 250);

            $this->sendCommand($socket, "DATA");
            $this->readResponse($socket, 354);

            // Build message
            $contentType = $isHtml ? 'text/html' : 'text/plain';
            $fromHeader = !empty($this->fromName)
                ? "=?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromAddress}>"
                : $this->fromAddress;

            $message = "From: {$fromHeader}\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n";
            $message .= "Date: " . date('r') . "\r\n";
            $message .= "Message-ID: <" . uniqid() . "@" . gethostname() . ">\r\n";
            $message .= "\r\n";
            $message .= $body;
            $message .= "\r\n.";

            $this->sendCommand($socket, $message);
            $this->readResponse($socket, 250);

            // Quit
            $this->sendCommand($socket, "QUIT");
            @fclose($socket);

            return true;

        } catch (\Exception $e) {
            if ($socket) {
                @fclose($socket);
            }
            throw $e;
        }
    }

    /**
     * Send command to SMTP server
     */
    private function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * Read response from SMTP server
     */
    private function readResponse($socket, int $expectedCode): string
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new \Exception("Failed to read from SMTP server");
            }
            $response .= $line;

            // Check if this is the last line (no continuation)
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception("SMTP error: Expected $expectedCode, got $code. Response: $response");
        }

        return $response;
    }

    /**
     * Send email via PHP's mail() function (fallback)
     */
    private function sendViaMail(string $to, string $subject, string $body, bool $isHtml): bool
    {
        $contentType = $isHtml ? 'text/html' : 'text/plain';
        $fromHeader = !empty($this->fromName)
            ? "=?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromAddress}>"
            : $this->fromAddress;

        $headers = [
            "From: {$fromHeader}",
            "Reply-To: {$this->fromAddress}",
            "MIME-Version: 1.0",
            "Content-Type: {$contentType}; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
        ];

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }
}
