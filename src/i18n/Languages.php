<?php

namespace App\i18n;

/**
 * Registry of available languages for the application.
 *
 * To add a new language:
 * 1. Register its native name and direction in languages.json
 * 2. Create an ICU message catalog: src/i18n/locales/{code}.json
 */
class Languages
{
    /** @var array<string, array{name: string, direction: string}>|null */
    private static ?array $metadata = null;

    public static function getMetadata(): array
    {
        return self::$metadata ??= json_decode(file_get_contents(__DIR__ . '/languages.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Get all available languages.
     *
     * @return array<string, string> Locale code => native name
     */
    public static function getAll(): array
    {
        return array_map(fn($language) => $language['name'], self::getMetadata());
    }

    /**
     * Check if a language code is valid/available.
     */
    public static function isValid(string $code): bool
    {
        return isset(self::getMetadata()[$code]);
    }

    /** Resolve a browser/BCP 47 tag to an available catalog, without guessing. */
    public static function resolve(string $tag): ?string
    {
        if (!preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})*$/i', $tag)) return null;
        $tag = strtolower(str_replace('_', '-', $tag));
        foreach (array_keys(self::getMetadata()) as $code) {
            if (strtolower($code) === $tag) return $code;
        }
        $language = explode('-', $tag)[0];
        return isset(self::getMetadata()[$language]) ? $language : null;
    }

    /** Also works for RTL tags before their catalogs are added. */
    public static function getDirection(string $locale): string
    {
        if (isset(self::getMetadata()[$locale])) return self::getMetadata()[$locale]['direction'];
        $language = strtolower(preg_split('/[-_]/', $locale)[0]);
        return in_array($language, ['ar', 'fa', 'he', 'ur', 'ps', 'dv', 'yi'], true) ? 'rtl' : 'ltr';
    }

    /**
     * Get the native name for a language code.
     */
    public static function getName(string $code): ?string
    {
        return self::getMetadata()[$code]['name'] ?? null;
    }

    /**
     * Get the default language code.
     */
    public static function getDefault(): string
    {
        return 'en';
    }
}
