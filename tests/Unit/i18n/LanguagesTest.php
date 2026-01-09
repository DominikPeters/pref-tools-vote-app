<?php

namespace Tests\Unit\i18n;

use Tests\TestCase;
use App\i18n\Languages;

class LanguagesTest extends TestCase
{
    public function test_get_all_returns_available_languages(): void
    {
        $languages = Languages::getAll();

        $this->assertIsArray($languages);
        $this->assertArrayHasKey('en', $languages);
        $this->assertArrayHasKey('fr', $languages);
    }

    public function test_get_all_returns_native_names(): void
    {
        $languages = Languages::getAll();

        $this->assertEquals('English', $languages['en']);
        $this->assertEquals('Français', $languages['fr']);
    }

    public function test_is_valid_returns_true_for_valid_codes(): void
    {
        $this->assertTrue(Languages::isValid('en'));
        $this->assertTrue(Languages::isValid('fr'));
    }

    public function test_is_valid_returns_false_for_invalid_codes(): void
    {
        $this->assertFalse(Languages::isValid('xx'));
        $this->assertFalse(Languages::isValid(''));
        $this->assertFalse(Languages::isValid('english'));
        $this->assertFalse(Languages::isValid('EN')); // Case-sensitive
    }

    public function test_get_name_returns_native_name(): void
    {
        $this->assertEquals('English', Languages::getName('en'));
        $this->assertEquals('Français', Languages::getName('fr'));
    }

    public function test_get_name_returns_null_for_invalid_code(): void
    {
        $this->assertNull(Languages::getName('xx'));
        $this->assertNull(Languages::getName(''));
    }

    public function test_get_default_returns_english(): void
    {
        $this->assertEquals('en', Languages::getDefault());
    }

    public function test_all_languages_have_translation_files(): void
    {
        $languages = Languages::getAll();
        $i18nDir = __DIR__ . '/../../../src/i18n/';

        foreach (array_keys($languages) as $code) {
            $filePath = $i18nDir . $code . '.php';
            $this->assertFileExists(
                $filePath,
                "Translation file missing for language: {$code}"
            );
        }
    }

    public function test_translation_files_return_arrays(): void
    {
        $languages = Languages::getAll();
        $i18nDir = __DIR__ . '/../../../src/i18n/';

        foreach (array_keys($languages) as $code) {
            $translations = require $i18nDir . $code . '.php';
            $this->assertIsArray(
                $translations,
                "Translation file for {$code} should return an array"
            );
            $this->assertNotEmpty(
                $translations,
                "Translation file for {$code} should not be empty"
            );
        }
    }
}
