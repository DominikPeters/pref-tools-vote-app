<?php

namespace App\Models;

use App\Database;

class SiteSetting
{
    public string $key;
    public ?string $value;
    public ?\DateTime $updatedAt;

    /**
     * Keys that should be masked in API responses
     */
    public const MASKED_KEYS = [
        'mail.smtp_password',
        'api.openai_key',
        'api.turnstile_secret_key',
    ];

    /**
     * All available setting keys with their defaults
     */
    public const DEFAULTS = [
        // Email/SMTP settings
        'mail.enabled' => '0',
        'mail.from_address' => '',
        'mail.from_name' => '',
        'mail.smtp_host' => '',
        'mail.smtp_port' => '587',
        'mail.smtp_username' => '',
        'mail.smtp_password' => '',
        'mail.smtp_encryption' => 'tls',

        // API keys
        'api.openai_key' => '',
        'api.turnstile_site_key' => '',
        'api.turnstile_secret_key' => '',

        // Content moderation
        'moderation.enabled' => '0',
        'moderation.fail_open' => '1',
        'moderation.threshold.sexual' => '0.8',
        'moderation.threshold.sexual_minors' => '0.01',
        'moderation.threshold.harassment' => '0.7',
        'moderation.threshold.harassment_threatening' => '0.5',
        'moderation.threshold.hate' => '0.7',
        'moderation.threshold.hate_threatening' => '0.5',
        'moderation.threshold.illicit' => '0.8',
        'moderation.threshold.illicit_violent' => '0.5',
        'moderation.threshold.self_harm' => '0.7',
        'moderation.threshold.self_harm_intent' => '0.5',
        'moderation.threshold.self_harm_instructions' => '0.3',
        'moderation.threshold.violence' => '0.8',
        'moderation.threshold.violence_graphic' => '0.6',

        // Notifications
        'notifications.sysadmin_email' => '',

        // Site branding
        'site.name' => 'Pref.Tools Vote',
        'site.logo_url' => '',
        'site.footer_text' => '',

        // Site access
        'site.registration_enabled' => '1',
        'site.maintenance_mode' => '0',

        // Session
        'session.lifetime' => '120',
    ];

    /**
     * Create a SiteSetting instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $setting = new self();
        $setting->key = $row['key'];
        $setting->value = $row['value'];
        $setting->updatedAt = new \DateTime($row['updated_at']);
        return $setting;
    }

    /**
     * Get a single setting value
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT value FROM site_settings WHERE `key` = :key",
            ['key' => $key]
        );

        if ($row !== null) {
            return $row['value'];
        }

        // Check for default
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Get a boolean setting value
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return $value === '1' || $value === 'true' || $value === 'yes';
    }

    /**
     * Get an integer setting value
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) $value;
    }

    /**
     * Set a single setting value
     */
    public static function set(string $key, ?string $value): void
    {
        $db = Database::getInstance();

        // Check if key exists
        $existing = $db->fetch(
            "SELECT `key` FROM site_settings WHERE `key` = :key",
            ['key' => $key]
        );

        if ($existing) {
            $db->query(
                "UPDATE site_settings SET value = :value, updated_at = :updated_at WHERE `key` = :key",
                [
                    'key' => $key,
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            $db->query(
                "INSERT INTO site_settings (`key`, value, updated_at) VALUES (:key, :value, :updated_at)",
                [
                    'key' => $key,
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Set multiple settings at once
     */
    public static function setMany(array $settings): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                self::set($key, $value);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Get all settings as an associative array
     */
    public static function all(): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT `key`, value FROM site_settings");

        // Start with defaults
        $settings = self::DEFAULTS;

        // Override with database values
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    /**
     * Get all settings with masked sensitive values for API output
     */
    public static function allMasked(): array
    {
        $settings = self::all();

        foreach (self::MASKED_KEYS as $key) {
            if (isset($settings[$key]) && !empty($settings[$key])) {
                $settings[$key] = self::maskValue($settings[$key]);
            }
        }

        return $settings;
    }

    /**
     * Check if a key is set and non-empty
     */
    public static function isSet(string $key): bool
    {
        $value = self::get($key);
        return $value !== null && $value !== '';
    }

    /**
     * Check if a masked key has a value (without revealing it)
     */
    public static function hasSecret(string $key): bool
    {
        if (!in_array($key, self::MASKED_KEYS, true)) {
            return false;
        }
        return self::isSet($key);
    }

    /**
     * Delete a setting
     */
    public static function delete(string $key): bool
    {
        $db = Database::getInstance();
        return $db->delete('site_settings', '`key` = :key', ['key' => $key]) > 0;
    }

    /**
     * Mask a sensitive value, showing only last 4 characters
     */
    public static function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return '••••••••';
        }
        return '••••••••' . substr($value, -4);
    }

    /**
     * Check if a value looks like a masked placeholder (not a real update)
     */
    public static function isMaskedValue(string $value): bool
    {
        return preg_match('/^•+/', $value) === 1;
    }
}
