<?php

namespace Tests\Unit\i18n;

use App\i18n\LocaleFormat;
use App\i18n\Translator;
use PHPUnit\Framework\TestCase;

class LocaleFormatTest extends TestCase
{
    public function test_display_dates_use_the_requested_locale_without_changing_it(): void
    {
        Translator::setLocale('de');
        $date = new \DateTimeImmutable('2026-01-02 15:04:00', new \DateTimeZone('Europe/Paris'));
        $english = LocaleFormat::date($date, 'en');
        $french = LocaleFormat::date($date, 'fr');
        $this->assertNotSame($english, $french);
        $this->assertStringContainsString('2026', $french);
        $this->assertNotSame($french, LocaleFormat::dateTime($date, 'fr'));
        $this->assertSame('de', Translator::getLocale());
        $this->assertSame('Europe/Paris', $date->getTimezone()->getName());
        Translator::setLocale('en');
    }
}
