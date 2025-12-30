<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\EmailDeliverability;

class UnsubscribeService
{
    private const SECRET_KEY = 'security.unsubscribe_secret';
    private const SIGNATURE_ALGO = 'sha256';

    /**
     * Get or generate the secret key for HMAC signing
     */
    private static function getSecretKey(): string
    {
        $secret = SiteSetting::get(self::SECRET_KEY);

        if (empty($secret)) {
            // Generate a new secret key
            $secret = bin2hex(random_bytes(32));
            SiteSetting::set(self::SECRET_KEY, $secret);
        }

        return $secret;
    }

    /**
     * Generate HMAC signature for an email
     */
    public static function generateSignature(string $email): string
    {
        $email = strtolower(trim($email));
        return hash_hmac(self::SIGNATURE_ALGO, $email, self::getSecretKey());
    }

    /**
     * Verify HMAC signature for an email
     */
    public static function verifySignature(string $email, string $signature): bool
    {
        $email = strtolower(trim($email));
        $expected = hash_hmac(self::SIGNATURE_ALGO, $email, self::getSecretKey());
        return hash_equals($expected, $signature);
    }

    /**
     * Generate an unsubscribe URL for an email
     */
    public static function generateUnsubscribeUrl(string $email): string
    {
        $email = strtolower(trim($email));
        $signature = self::generateSignature($email);
        return url('unsubscribe?' . http_build_query([
            'email' => $email,
            'sig' => $signature,
        ]));
    }

    /**
     * Generate a resubscribe URL for an email
     */
    public static function generateResubscribeUrl(string $email): string
    {
        $email = strtolower(trim($email));
        $signature = self::generateSignature($email);
        return url('unsubscribe?' . http_build_query([
            'email' => $email,
            'sig' => $signature,
            'action' => 'resubscribe',
        ]));
    }

    /**
     * Generate the one-click unsubscribe URL for RFC 8058
     * This URL accepts POST requests with List-Unsubscribe=One-Click body
     */
    public static function generateOneClickUrl(string $email): string
    {
        $email = strtolower(trim($email));
        $signature = self::generateSignature($email);
        return url('unsubscribe/one-click?' . http_build_query([
            'email' => $email,
            'sig' => $signature,
        ]));
    }

    /**
     * Unsubscribe an email address
     */
    public static function unsubscribe(string $email): EmailDeliverability
    {
        return EmailDeliverability::unsubscribe($email);
    }

    /**
     * Resubscribe an email address
     */
    public static function resubscribe(string $email): EmailDeliverability
    {
        return EmailDeliverability::resubscribe($email);
    }

    /**
     * Check if an email is unsubscribed
     */
    public static function isUnsubscribed(string $email): bool
    {
        return EmailDeliverability::isUnsubscribed($email);
    }

    /**
     * Check multiple emails for unsubscribe status
     * Returns array of email => bool (true if blocked)
     */
    public static function checkMultiple(array $emails): array
    {
        return EmailDeliverability::checkMultiple($emails);
    }
}
