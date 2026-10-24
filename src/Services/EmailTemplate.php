<?php

namespace App\Services;

use App\i18n\Languages;
use App\i18n\Translator;

/** Email-local rendering context; never changes the active web interface locale. */
final class EmailTemplate
{
    public static function render(string $template, string $locale, array $data = []): string
    {
        if (!in_array($template, ['invitation', 'verification', 'password-reset', 'response-notification', 'report'], true)) {
            throw new \InvalidArgumentException('Unknown email template');
        }
        extract($data, EXTR_SKIP);
        $emailLocale = Languages::resolve($locale) ?? Languages::getDefault();
        $emailDirection = Languages::getDirection($emailLocale);
        $emailT = static fn(string $key, array $params = []): string => Translator::translateForLocale($emailLocale, $key, $params);
        ob_start();
        try {
            include TEMPLATES_PATH . '/emails/' . $template . '.php';
            return ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    public static function formatDate(\DateTimeInterface $date, string $locale): string
    {
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $date->getTimezone());
        return $formatter->format($date) ?: $date->format('Y-m-d H:i T');
    }
}
