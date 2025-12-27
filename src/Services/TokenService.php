<?php

namespace App\Services;

use App\Config;

class TokenService
{
    /**
     * Generate a secure random token
     */
    public static function generate(int $length = 32): string
    {
        $bytes = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Generate a public ID for votes (shorter, URL-friendly)
     */
    public static function generatePublicId(): string
    {
        $length = Config::get('security.public_id_length', 8);
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Generate an admin token
     */
    public static function generateAdminToken(): string
    {
        $length = Config::get('security.admin_token_length', 32);
        return self::generate($length);
    }

    /**
     * Generate a voter token (for tracking "edit own vote")
     */
    public static function generateVoterToken(): string
    {
        $length = Config::get('security.voter_token_length', 32);
        return self::generate($length);
    }

    /**
     * Generate an access token (for one-time access)
     */
    public static function generateAccessToken(): string
    {
        $length = Config::get('security.access_token_length', 16);
        return self::generate($length);
    }
}
