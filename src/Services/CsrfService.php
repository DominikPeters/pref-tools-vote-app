<?php

namespace App\Services;

class CsrfService
{
    private static ?CsrfService $instance = null;
    private const SESSION_KEY = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-TOKEN';
    private const FIELD_NAME = 'csrf_token';

    private function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get or generate CSRF token for the current session
     */
    public function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify a token against the session
     */
    public function verify(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        return hash_equals($this->getToken(), $token);
    }

    /**
     * Verify the current request
     */
    public function verifyRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Skip verification for safe methods
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // 1. Check custom header (preferred for AJAX)
        // PHP transforms X-CSRF-TOKEN to HTTP_X_CSRF_TOKEN
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        // 2. Check standard POST data (for traditional forms)
        if ($token === null) {
            $token = $_POST[self::FIELD_NAME] ?? null;
        }

        // 3. Check JSON body (fallback)
        if ($token === null) {
            $json = \App\Router::getJsonBody();
            $token = $json[self::FIELD_NAME] ?? null;
        }

        return $this->verify($token);
    }

    /**
     * Get the HTML meta tag for the CSRF token
     */
    public function getMetaTag(): string
    {
        return '<meta name="csrf-token" content="' . $this->getToken() . '">';
    }

    /**
     * Get an HTML hidden input for the CSRF token
     */
    public function getHiddenInput(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $this->getToken() . '">';
    }
}
