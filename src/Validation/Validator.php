<?php

namespace App\Validation;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate fields against rules
     */
    public function validate(array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $fieldRules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single rule to a field
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, 'validation_required');
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'validation_email');
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value, 'UTF-8') < $min) {
                    $this->addError($field, 'validation_min_characters', ['count' => $min]);
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, 'validation_min_number', ['count' => $min]);
                } elseif (is_array($value) && count($value) < $min) {
                    $this->addError($field, 'validation_min_items', ['count' => $min]);
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value, 'UTF-8') > $max) {
                    $this->addError($field, 'validation_max_characters', ['count' => $max]);
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, 'validation_max_number', ['count' => $max]);
                } elseif (is_array($value) && count($value) > $max) {
                    $this->addError($field, 'validation_max_items', ['count' => $max]);
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, $params)) {
                    $this->addError($field, 'validation_selected_invalid');
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, 'validation_numeric');
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'validation_integer');
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->addError($field, 'validation_boolean');
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, 'validation_array');
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'validation_url');
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value !== null && $value !== '' && !preg_match($pattern, $value)) {
                    $this->addError($field, 'validation_format');
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                $confirmValue = $this->data[$confirmField] ?? null;
                if ($value !== $confirmValue) {
                    $this->addError($field, 'validation_confirmed');
                }
                break;
        }
    }

    /**
     * Add an error message
     */
    private function addError(string $field, string $key, array $params = []): void
    {
        $message = __($key, ['field' => self::fieldLabel($field)] + $params);
        $this->errors[$field][] = $message;
    }

    /** Translate known API field labels; custom field names remain readable. */
    public static function fieldLabel(string $field, ?string $locale = null): string
    {
        $translate = static fn(string $key): string => \App\i18n\Translator::translateForLocale(
            $locale ?? \App\i18n\Translator::getLocale(), $key
        );
        return match ($field) {
            'email' => $translate('validation_field_email'),
            'name' => $translate('validation_field_name'),
            'password' => $translate('validation_field_password'),
            'password_confirmation' => $translate('validation_field_password_confirmation'),
            'current_password' => $translate('validation_field_current_password'),
            'new_password' => $translate('validation_field_new_password'),
            'new_password_confirmation' => $translate('validation_field_new_password_confirmation'),
            'title' => $translate('validation_field_title'),
            'questions' => $translate('validation_field_questions'),
            'public_id' => $translate('validation_field_poll_id'),
            'admin_token' => $translate('validation_field_admin_token'),
            'token' => $translate('validation_field_token'),
            'voter_name' => $translate('validation_field_voter_name'),
            'answers' => $translate('validation_field_answers'),
            'role' => $translate('validation_field_role'),
            'status' => $translate('validation_field_status'),
            'locale' => $translate('validation_field_language'),
            'reason' => $translate('validation_field_reason'),
            'note' => $translate('validation_field_note'),
            'emails' => $translate('validation_field_email_addresses'),
            'confirmation' => $translate('validation_field_confirmation'),
            'poll_action' => $translate('validation_field_poll_action'),
            'polls' => $translate('validation_field_polls'),
            'response_action' => $translate('validation_field_response_action'),
            'responses' => $translate('validation_field_responses'),
            'username' => $translate('validation_field_username'),
            'first_name' => $translate('validation_field_first_name'),
            'age' => $translate('validation_field_age'),
            'tags' => $translate('validation_field_tags'),
            'url' => $translate('validation_field_url'),
            'website' => $translate('validation_field_website'),
            'items' => $translate('validation_field_items'),
            'score' => $translate('validation_field_score'),
            'count' => $translate('validation_field_count'),
            'settings' => $translate('validation_field_settings'),
            default => str_replace('_', ' ', $field),
        };
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get validated data (only fields that had rules)
     */
    public function validated(): array
    {
        return $this->data;
    }

    /**
     * Static factory method
     */
    public static function make(array $data, array $rules): self
    {
        $validator = new self($data);
        $validator->validate($rules);
        return $validator;
    }
}
