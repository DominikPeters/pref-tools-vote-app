<?php

namespace Tests\Unit\i18n;

use Tests\TestCase;
use App\i18n\Translator;

class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset to default locale before each test
        Translator::setLocale('en');
    }

    public function test_get_locale_returns_default(): void
    {
        $this->assertEquals('en', Translator::getLocale());
    }

    public function test_set_locale_changes_locale(): void
    {
        Translator::setLocale('fr');
        $this->assertEquals('fr', Translator::getLocale());
    }

    public function test_translate_returns_string_for_valid_key(): void
    {
        $result = Translator::translate('submit_vote');
        $this->assertEquals('Submit Vote', $result);
    }

    public function test_translate_returns_key_for_missing_key(): void
    {
        $result = Translator::translate('nonexistent_key');
        $this->assertEquals('nonexistent_key', $result);
    }

    public function test_translate_interpolates_parameters(): void
    {
        // The 'closed_on' key is 'Closed :date'
        $result = Translator::translate('closed_on', ['date' => 'January 15, 2025']);
        $this->assertEquals('Closed January 15, 2025', $result);
    }

    public function test_translate_handles_multiple_parameters(): void
    {
        // The 'rules_count' key is ':count/:total rules'
        $result = Translator::translate('rules_count', ['count' => 3, 'total' => 5]);
        $this->assertEquals('3/5 rules', $result);
    }

    public function test_helper_function_works(): void
    {
        $result = \__('submit_vote');
        $this->assertEquals('Submit Vote', $result);
    }

    public function test_helper_function_with_params(): void
    {
        $result = \__('closed_on', ['date' => 'March 1']);
        $this->assertEquals('Closed March 1', $result);
    }

    public function test_get_all_translations_returns_array(): void
    {
        $translations = Translator::getAllTranslations();

        $this->assertIsArray($translations);
        $this->assertNotEmpty($translations);
        $this->assertArrayHasKey('submit_vote', $translations);
        $this->assertArrayHasKey('result_winner', $translations);
    }

    public function test_get_all_translations_for_locale(): void
    {
        // Get French translations without changing current locale
        $frTranslations = Translator::getAllTranslationsForLocale('fr');

        $this->assertIsArray($frTranslations);
        $this->assertArrayHasKey('submit_vote', $frTranslations);
        $this->assertEquals('Soumettre le vote', $frTranslations['submit_vote']);

        // Current locale should still be English
        $this->assertEquals('en', Translator::getLocale());
    }

    public function test_french_translations_load_correctly(): void
    {
        Translator::setLocale('fr');

        $result = Translator::translate('submit_vote');
        $this->assertEquals('Soumettre le vote', $result);

        $result = Translator::translate('yes');
        $this->assertEquals('Oui', $result);
    }

    public function test_french_rule_translations(): void
    {
        Translator::setLocale('fr');

        // Majority Judgment should have a French translation
        $result = Translator::translate('rule_majority_judgment');
        $this->assertEquals('Jugement Majoritaire', $result);
    }

    public function test_fallback_to_english_for_unknown_locale(): void
    {
        Translator::setLocale('xx'); // Non-existent locale

        // Should fall back to English
        $result = Translator::translate('submit_vote');
        $this->assertEquals('Submit Vote', $result);
    }

    public function test_translate_preserves_unknown_parameters(): void
    {
        // If a parameter placeholder exists but no value is provided,
        // the placeholder should remain
        $result = Translator::translate('closed_on', []); // No 'date' param
        $this->assertEquals('Closed :date', $result);
    }
}
