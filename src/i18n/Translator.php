<?php

namespace App\i18n;

class Translator
{
    private static ?array $strings = null;
    private static string $locale = 'en';

    /**
     * Set the current locale
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        self::$strings = null; // Force reload
    }

    /**
     * Get current locale
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Translate a string
     */
    public static function translate(string $key, array $params = []): string
    {
        if (self::$strings === null) {
            self::loadStrings();
        }

        $text = self::$strings[$key] ?? $key;

        if (!empty($params)) {
            foreach ($params as $name => $value) {
                $text = str_replace(":{$name}", $value, $text);
            }
        }

        return $text;
    }

    /**
     * Load translation strings for current locale
     */
    private static function loadStrings(): void
    {
        $file = __DIR__ . '/' . self::$locale . '.php';

        if (file_exists($file)) {
            self::$strings = require $file;
        } else {
            // Fallback to English
            self::$strings = require __DIR__ . '/en.php';
        }
    }

    /**
     * Get all translations for the current locale.
     * Used to embed translations in templates for JavaScript.
     *
     * @return array<string, string>
     */
    public static function getAllTranslations(): array
    {
        if (self::$strings === null) {
            self::loadStrings();
        }

        return self::$strings;
    }

    /**
     * Get all translations for a specific locale.
     *
     * @param string $locale The locale code (e.g., 'en', 'fr')
     * @return array<string, string>
     */
    public static function getAllTranslationsForLocale(string $locale): array
    {
        $currentLocale = self::$locale;
        self::setLocale($locale);
        $translations = self::getAllTranslations();
        self::setLocale($currentLocale);

        return $translations;
    }
}
