<?php

namespace App\i18n;

/**
 * Registry of available languages for the application.
 *
 * To add a new language:
 * 1. Add it to the AVAILABLE array below
 * 2. Create a translation file: src/i18n/{code}.php
 */
class Languages
{
    /**
     * Available languages with their native names.
     * Key is the locale code, value is the native name.
     */
    private const AVAILABLE = [
        'en' => 'English',
        'fr' => 'Français',
    ];

    /**
     * Get all available languages.
     *
     * @return array<string, string> Locale code => native name
     */
    public static function getAll(): array
    {
        return self::AVAILABLE;
    }

    /**
     * Check if a language code is valid/available.
     */
    public static function isValid(string $code): bool
    {
        return isset(self::AVAILABLE[$code]);
    }

    /**
     * Get the native name for a language code.
     */
    public static function getName(string $code): ?string
    {
        return self::AVAILABLE[$code] ?? null;
    }

    /**
     * Get the default language code.
     */
    public static function getDefault(): string
    {
        return 'en';
    }
}
