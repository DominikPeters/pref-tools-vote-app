<?php

namespace App\Controllers;

use App\Router;
use App\Auth;

abstract class ApiController
{
    /** Voter endpoints may select the poll locale without changing the UI renderer. */
    protected ?string $messageLocale = null;

    protected function t(string $key, array $params = []): string
    {
        return \App\i18n\Translator::translateForLocale(
            $this->messageLocale ?? \App\i18n\Translator::getLocale(), $key, $params
        );
    }

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
            return $this->error($this->t('error_authentication_required'), 'AUTH_REQUIRED', 401);
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
            $label = \App\Validation\Validator::fieldLabel($field, $this->messageLocale);

            if (str_contains($rule, 'required') && ($value === null || $value === '')) {
                $errors[$field] = $this->t('validation_the_field_field_is_required', ['field' => $label]);
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (str_contains($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = $this->t('validation_the_field_field_must_be_a_valid_email', ['field' => $label]);
            }

            if (str_contains($rule, 'min:')) {
                preg_match('/min:(\d+)/', $rule, $matches);
                $min = (int) $matches[1];
                if (mb_strlen($value, 'UTF-8') < $min) {
                    $errors[$field] = $this->t('validation_the_field_field_must_be_at_least_count_plural_one_character_other_characters', ['field' => $label, 'count' => $min]);
                }
            }

            if (str_contains($rule, 'max:')) {
                preg_match('/max:(\d+)/', $rule, $matches);
                $max = (int) $matches[1];
                if (mb_strlen($value, 'UTF-8') > $max) {
                    $errors[$field] = $this->t('validation_the_field_field_must_be_at_most_count_plural_one_character_other_characters', ['field' => $label, 'count' => $max]);
                }
            }
        }

        if (!empty($errors)) {
            return $this->error($this->t('error_validation_failed'), 'VALIDATION_ERROR', 422) + ['errors' => $errors];
        }

        return null;
    }
}
