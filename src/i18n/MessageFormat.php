<?php

namespace App\i18n;

/** ICU MessageFormat, shared with intl-messageformat in the browser. */
final class MessageFormat
{
    private static array $formatters = [];

    public static function format(string $pattern, array $params = [], string $locale = 'en'): string
    {
        if (!class_exists(\MessageFormatter::class)) {
            throw new \RuntimeException('Internationalization requires the PHP intl extension.');
        }
        $cacheKey = $locale . "\0" . $pattern;
        $formatter = self::$formatters[$cacheKey] ??= new \MessageFormatter($locale, $pattern);
        foreach ($params as &$value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            } elseif (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new \InvalidArgumentException('Message parameters must be scalar values.');
            }
        }
        unset($value);
        $result = $formatter->format($params);
        if ($result === false) {
            throw new \InvalidArgumentException('Cannot format message: ' . $formatter->getErrorMessage());
        }
        return $result;
    }
}
