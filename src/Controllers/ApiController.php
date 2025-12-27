<?php

namespace App\Controllers;

use App\Router;
use App\Auth;

abstract class ApiController
{
    /**
     * Get JSON body from request
     */
    protected function getBody(): ?array
    {
        return Router::getJsonBody();
    }

    /**
     * Send JSON response
     */
    protected function json(mixed $data, int $status = 200): array
    {
        http_response_code($status);
        return $data;
    }

    /**
     * Send success response
     */
    protected function success(array $data = []): array
    {
        return array_merge(['ok' => true], $data);
    }

    /**
     * Send error response
     */
    protected function error(string $message, string $code, int $status = 400): array
    {
        http_response_code($status);
        return ['error' => $message, 'code' => $code];
    }

    /**
     * Get the current authenticated user
     */
    protected function user(): ?\App\Models\User
    {
        return Auth::getInstance()->user();
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return Auth::getInstance()->check();
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): ?array
    {
        if (!$this->isAuthenticated()) {
            return $this->error('Authentication required', 'AUTH_REQUIRED', 401);
        }
        return null;
    }

    /**
     * Validate required fields
     */
    protected function validate(array $data, array $rules): ?array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            if (str_contains($rule, 'required') && ($value === null || $value === '')) {
                $errors[$field] = "The {$field} field is required";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (str_contains($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "The {$field} field must be a valid email";
            }

            if (str_contains($rule, 'min:')) {
                preg_match('/min:(\d+)/', $rule, $matches);
                $min = (int) $matches[1];
                if (strlen($value) < $min) {
                    $errors[$field] = "The {$field} field must be at least {$min} characters";
                }
            }

            if (str_contains($rule, 'max:')) {
                preg_match('/max:(\d+)/', $rule, $matches);
                $max = (int) $matches[1];
                if (strlen($value) > $max) {
                    $errors[$field] = "The {$field} field must be at most {$max} characters";
                }
            }
        }

        if (!empty($errors)) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422) + ['errors' => $errors];
        }

        return null;
    }
}
