<?php

namespace App\i18n;

final class Translator
{
    private static array $catalogs = [];
    private static string $locale = 'en';

    public static function setLocale(string $locale): void
    {
        self::$locale = Languages::resolve($locale) ?? Languages::getDefault();
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function translate(string $key, array $params = []): string
    {
        return self::translateForLocale(self::$locale, $key, $params);
    }

    /** Explicit locale calls do not mutate another render's active locale. */
    public static function translateForLocale(string $locale, string $key, array $params = []): string
    {
        $locale = Languages::resolve($locale) ?? Languages::getDefault();
        $messages = self::getCatalog($locale);
        if (!array_key_exists($key, $messages)) {
            $locale = Languages::getDefault();
            $messages = self::getCatalog($locale);
        }
        return isset($messages[$key]) ? MessageFormat::format($messages[$key], $params, $locale) : $key;
    }

    public static function getCatalog(string $locale): array
    {
        $locale = Languages::resolve($locale) ?? Languages::getDefault();
        return self::$catalogs[$locale] ??= json_decode(
            file_get_contents(__DIR__ . '/locales/' . $locale . '.json'),
            true, 512, JSON_THROW_ON_ERROR
        );
    }

    public static function getAllTranslations(): array
    {
        return self::getAllTranslationsForLocale(self::$locale);
    }

    public static function getAllTranslationsForLocale(string $locale): array
    {
        return array_replace(self::getCatalog(Languages::getDefault()), self::getCatalog($locale));
    }

    /** Plain labels for older, cached embed components that predate ICU. */
    public static function getLegacyLabelsForLocale(string $locale): array
    {
        $messages = self::getAllTranslationsForLocale($locale);
        foreach ($messages as $key => $pattern) {
            try {
                $messages[$key] = MessageFormat::format($pattern, [], $locale);
            } catch (\Throwable $error) {
                // Old components only consume labels without arguments.
                $messages[$key] = $pattern;
            }
        }
        return $messages;
    }

    /** Preserve message origins so English fallbacks use English plural rules. */
    public static function configuration(string $locale): array
    {
        $locale = Languages::resolve($locale) ?? Languages::getDefault();
        return [
            'locale' => $locale,
            'direction' => Languages::getDirection($locale),
            'messages' => self::getCatalog($locale),
            'fallback' => $locale === Languages::getDefault() ? [] : self::getCatalog(Languages::getDefault()),
        ];
    }
}
