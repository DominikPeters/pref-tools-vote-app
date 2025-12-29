<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\SiteSetting;

class SiteSettingTest extends TestCase
{
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $value = SiteSetting::get('nonexistent.key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testGetReturnsDefaultFromConstants(): void
    {
        // Should return the default defined in DEFAULTS constant
        $value = SiteSetting::get('site.name');
        $this->assertEquals('Pref.Tools Vote', $value);
    }

    public function testSetAndGet(): void
    {
        SiteSetting::set('test.key', 'test_value');
        $value = SiteSetting::get('test.key');
        $this->assertEquals('test_value', $value);
    }

    public function testSetOverwritesExisting(): void
    {
        SiteSetting::set('test.key', 'first_value');
        SiteSetting::set('test.key', 'second_value');
        $value = SiteSetting::get('test.key');
        $this->assertEquals('second_value', $value);
    }

    public function testGetBoolReturnsTrue(): void
    {
        SiteSetting::set('test.bool', '1');
        $this->assertTrue(SiteSetting::getBool('test.bool'));

        SiteSetting::set('test.bool', 'true');
        $this->assertTrue(SiteSetting::getBool('test.bool'));

        SiteSetting::set('test.bool', 'yes');
        $this->assertTrue(SiteSetting::getBool('test.bool'));
    }

    public function testGetBoolReturnsFalse(): void
    {
        SiteSetting::set('test.bool', '0');
        $this->assertFalse(SiteSetting::getBool('test.bool'));

        SiteSetting::set('test.bool', 'false');
        $this->assertFalse(SiteSetting::getBool('test.bool'));

        SiteSetting::set('test.bool', '');
        $this->assertFalse(SiteSetting::getBool('test.bool'));
    }

    public function testGetBoolDefaultValue(): void
    {
        $this->assertFalse(SiteSetting::getBool('nonexistent.bool', false));
        $this->assertTrue(SiteSetting::getBool('nonexistent.bool', true));
    }

    public function testGetIntReturnsInteger(): void
    {
        SiteSetting::set('test.int', '42');
        $this->assertSame(42, SiteSetting::getInt('test.int'));
    }

    public function testGetIntDefaultValue(): void
    {
        $this->assertSame(100, SiteSetting::getInt('nonexistent.int', 100));
    }

    public function testSetMany(): void
    {
        SiteSetting::setMany([
            'multi.key1' => 'value1',
            'multi.key2' => 'value2',
            'multi.key3' => 'value3',
        ]);

        $this->assertEquals('value1', SiteSetting::get('multi.key1'));
        $this->assertEquals('value2', SiteSetting::get('multi.key2'));
        $this->assertEquals('value3', SiteSetting::get('multi.key3'));
    }

    public function testAll(): void
    {
        SiteSetting::set('custom.setting', 'custom_value');
        $all = SiteSetting::all();

        // Should include defaults
        $this->assertArrayHasKey('site.name', $all);
        $this->assertArrayHasKey('mail.enabled', $all);

        // Should include custom setting
        $this->assertArrayHasKey('custom.setting', $all);
        $this->assertEquals('custom_value', $all['custom.setting']);
    }

    public function testAllMaskedHidesSensitiveValues(): void
    {
        SiteSetting::set('mail.smtp_password', 'secret_password_123');
        SiteSetting::set('api.openai_key', 'sk-1234567890abcdef');

        $masked = SiteSetting::allMasked();

        // Should be masked
        $this->assertEquals('••••••••_123', $masked['mail.smtp_password']);
        $this->assertEquals('••••••••cdef', $masked['api.openai_key']);

        // Non-sensitive should be visible
        $this->assertEquals('Pref.Tools Vote', $masked['site.name']);
    }

    public function testIsSet(): void
    {
        $this->assertFalse(SiteSetting::isSet('unset.key'));

        SiteSetting::set('set.key', 'value');
        $this->assertTrue(SiteSetting::isSet('set.key'));

        SiteSetting::set('empty.key', '');
        $this->assertFalse(SiteSetting::isSet('empty.key'));
    }

    public function testDelete(): void
    {
        SiteSetting::set('deletable.key', 'value');
        $this->assertEquals('value', SiteSetting::get('deletable.key'));

        $result = SiteSetting::delete('deletable.key');
        $this->assertTrue($result);

        // Should now return default
        $this->assertNull(SiteSetting::get('deletable.key'));
    }

    public function testMaskValue(): void
    {
        // Normal length string
        $this->assertEquals('••••••••efgh', SiteSetting::maskValue('abcdefgh'));

        // Short string
        $this->assertEquals('••••••••', SiteSetting::maskValue('abc'));

        // Long string
        $this->assertEquals('••••••••cdef', SiteSetting::maskValue('1234567890abcdef'));
    }

    public function testIsMaskedValue(): void
    {
        $this->assertTrue(SiteSetting::isMaskedValue('••••••••1234'));
        $this->assertTrue(SiteSetting::isMaskedValue('••••'));
        $this->assertFalse(SiteSetting::isMaskedValue('plaintext'));
        $this->assertFalse(SiteSetting::isMaskedValue(''));
    }

    public function testHasSecret(): void
    {
        // Test for a masked key that has a value
        SiteSetting::set('mail.smtp_password', 'secret');
        $this->assertTrue(SiteSetting::hasSecret('mail.smtp_password'));

        // Test for a masked key that doesn't have a value
        SiteSetting::delete('api.openai_key');
        $this->assertFalse(SiteSetting::hasSecret('api.openai_key'));

        // Test for a non-masked key
        SiteSetting::set('site.name', 'Test');
        $this->assertFalse(SiteSetting::hasSecret('site.name'));
    }
}
