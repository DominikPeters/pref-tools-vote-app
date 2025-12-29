<?php

namespace App\Services;

use App\Models\SiteSetting;

class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Check if Turnstile is configured
     */
    public static function isConfigured(): bool
    {
        $siteKey = SiteSetting::get('api.turnstile_site_key', '');
        $secretKey = SiteSetting::get('api.turnstile_secret_key', '');

        return !empty($siteKey) && !empty($secretKey);
    }

    /**
     * Get the site key for frontend use
     */
    public static function getSiteKey(): string
    {
        return SiteSetting::get('api.turnstile_site_key', '');
    }

    /**
     * Verify a Turnstile token
     *
     * @param string $token The token from the frontend
     * @return bool True if verification succeeded, false otherwise
     */
    public static function verify(string $token): bool
    {
        // If not configured, allow bypass (for development)
        if (!self::isConfigured()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        $secretKey = SiteSetting::get('api.turnstile_secret_key', '');
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';

        $data = [
            'secret' => $secretKey,
            'response' => $token,
        ];

        if (!empty($remoteIp)) {
            $data['remoteip'] = $remoteIp;
        }

        try {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                error_log("Turnstile verification failed: HTTP $httpCode, Error: $error");
                // On network error, we might want to fail open or closed depending on preference
                // Failing closed is safer for security
                return false;
            }

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Turnstile verification failed: Invalid JSON response");
                return false;
            }

            if (!isset($result['success'])) {
                error_log("Turnstile verification failed: Missing success field");
                return false;
            }

            if (!$result['success'] && isset($result['error-codes'])) {
                error_log("Turnstile verification failed: " . implode(', ', $result['error-codes']));
            }

            return $result['success'] === true;

        } catch (\Exception $e) {
            error_log("Turnstile verification exception: " . $e->getMessage());
            return false;
        }
    }
}
