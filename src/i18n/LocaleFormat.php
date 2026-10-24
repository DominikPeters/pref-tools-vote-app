<?php

namespace App\i18n;

/** Locale-aware display values; persisted timestamps keep their ISO format. */
final class LocaleFormat
{
    public static function date(\DateTimeInterface $date, ?string $locale = null): string
    {
        return self::format($date, $locale, \IntlDateFormatter::NONE);
    }

    public static function dateTime(\DateTimeInterface $date, ?string $locale = null): string
    {
        return self::format($date, $locale, \IntlDateFormatter::SHORT);
    }

    private static function format(\DateTimeInterface $date, ?string $locale, int $timeStyle): string
    {
        $locale = Languages::resolve($locale ?? Translator::getLocale()) ?? Languages::getDefault();
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, $timeStyle, $date->getTimezone());
        return $formatter->format($date);
    }
}
