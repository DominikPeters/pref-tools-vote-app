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
                    $this->addError($field, 'The :field field is required.');
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'The :field field must be a valid email address.');
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) < $min) {
                    $this->addError($field, "The :field field must be at least {$min} characters.");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "The :field field must be at least {$min}.");
                } elseif (is_array($value) && count($value) < $min) {
                    $this->addError($field, "The :field field must have at least {$min} items.");
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) > $max) {
                    $this->addError($field, "The :field field must be at most {$max} characters.");
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, "The :field field must be at most {$max}.");
                } elseif (is_array($value) && count($value) > $max) {
                    $this->addError($field, "The :field field must have at most {$max} items.");
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, $params)) {
                    $this->addError($field, 'The selected :field is invalid.');
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, 'The :field field must be a number.');
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'The :field field must be an integer.');
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->addError($field, 'The :field field must be true or false.');
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, 'The :field field must be an array.');
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'The :field field must be a valid URL.');
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value !== null && $value !== '' && !preg_match($pattern, $value)) {
                    $this->addError($field, 'The :field field format is invalid.');
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                $confirmValue = $this->data[$confirmField] ?? null;
                if ($value !== $confirmValue) {
                    $this->addError($field, 'The :field confirmation does not match.');
                }
                break;
        }
    }

    /**
     * Add an error message
     */
    private function addError(string $field, string $message): void
    {
        $humanField = str_replace('_', ' ', $field);
        $message = str_replace(':field', $humanField, $message);

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
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
