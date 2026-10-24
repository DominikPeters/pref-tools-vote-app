<?php

namespace App\i18n;

/** Request-local interface preference, independent of any poll's language. */
final class UiLocale
{
    public const COOKIE = 'ui_locale';
    private static string $locale = 'en';

    public static function initialize(?string $preference, string $acceptLanguage = ''): void
    {
        self::$locale = Languages::resolve($preference ?? '') ?? self::negotiate($acceptLanguage);
        Translator::setLocale(self::$locale);
    }

    public static function get(): string
    {
        return self::$locale;
    }

    public static function negotiate(string $header): string
    {
        $preferences = [];
        foreach (array_slice(explode(',', substr($header, 0, 4096)), 0, 30) as $index => $part) {
            if (!preg_match('/^\s*([a-zA-Z0-9_*-]+)\s*(?:;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?\s*$/', $part, $match)) {
                continue;
            }
            $weight = isset($match[2]) ? (float)$match[2] : 1.0;
            $locale = $match[1] === '*' ? Languages::getDefault() : Languages::resolve($match[1]);
            if ($weight > 0 && $locale !== null) {
                $preferences[] = ['locale' => $locale, 'weight' => $weight, 'index' => $index];
            }
        }
        usort($preferences, fn($a, $b) => ($b['weight'] <=> $a['weight']) ?: ($a['index'] <=> $b['index']));
        return $preferences[0]['locale'] ?? Languages::getDefault();
    }
}
