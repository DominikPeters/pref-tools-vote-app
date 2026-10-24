<?php

namespace Tests\Unit\i18n;

use App\Controllers\LanguageController;
use App\i18n\Languages;
use App\i18n\Translator;
use App\i18n\UiLocale;
use PHPUnit\Framework\TestCase;

class UiLocaleTest extends TestCase
{
    protected function tearDown(): void
    {
        UiLocale::initialize('en');
    }

    public function test_resolves_regional_tags_without_accepting_paths(): void
    {
        $this->assertSame('pt', Languages::resolve('pt-BR'));
        $this->assertSame('fr', Languages::resolve('FR_ch'));
        $this->assertNull(Languages::resolve('../../tests/config'));
        $this->assertNull(Languages::resolve('xx'));
        $this->assertSame('rtl', Languages::getDirection('ar-EG'));
        $this->assertSame('ltr', Languages::getDirection('fr'));
    }

    public function test_negotiates_weighted_browser_languages(): void
    {
        $this->assertSame('fr', UiLocale::negotiate('xx, de;q=0.3, fr-CH;q=0.8, en;q=0'));
        $this->assertSame('de', UiLocale::negotiate('de;q=0.8, fr;q=0.8'));
        $this->assertSame('en', UiLocale::negotiate('fr;q=0, xx'));
        $this->assertSame('en', UiLocale::negotiate('fr;q=banana'));
    }

    public function test_suggests_current_and_browser_languages_followed_by_english(): void
    {
        $this->assertSame(['fr', 'de', 'en'], UiLocale::suggestions('fr-CH, de;q=0.8, xx;q=0.7', 'fr'));
        $this->assertSame(['en', 'fr', 'de'], UiLocale::suggestions('fr, de', 'en'));
        $this->assertSame(['de', 'fr', 'es', 'en'], UiLocale::suggestions('fr, fr-CA, es, it, nl', 'de'));
        $this->assertSame(['en'], UiLocale::suggestions('', 'en'));
        $this->assertSame(['hu', 'en'], UiLocale::suggestions('xx, *;q=0.1', 'hu'));
    }

    public function test_explicit_preference_wins_and_poll_locale_does_not_change_it(): void
    {
        UiLocale::initialize('de', 'fr-FR');
        Translator::setLocale('es');
        $this->assertSame('de', UiLocale::get());
        $this->assertSame('es', Translator::getLocale());
        UiLocale::initialize('../../invalid', 'fr-FR');
        $this->assertSame('fr', UiLocale::get());
    }

    public function test_locale_specific_formatting_does_not_mutate_active_locale(): void
    {
        Translator::setLocale('de');
        $this->assertSame('0 vote', Translator::translateForLocale('fr', 'response_count', ['count' => 0]));
        $this->assertSame('0 votes', Translator::translateForLocale('xx', 'vote_count', ['count' => 0]));
        $this->assertSame('de', Translator::getLocale());
    }

    public function test_language_redirects_stay_in_the_application(): void
    {
        $this->assertSame('/vote/create?x=1', LanguageController::safeReturnPath('/vote/create?x=1', '/vote'));
        foreach (['https://evil.test', '//evil.test', '/\\evil.test', "/vote/\r\nLocation: evil", '/outside', '/vote/%2e%2e/outside', []] as $path) {
            $this->assertSame('/vote/', LanguageController::safeReturnPath($path, '/vote'));
        }
    }
}
