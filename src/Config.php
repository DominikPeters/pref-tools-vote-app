<?php

namespace App;

class Config
{
    private static ?array $config = null;

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }
        self::$config = require $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            throw new \RuntimeException("Configuration not loaded. Call Config::load() first.");
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function all(): array
    {
        if (self::$config === null) {
            throw new \RuntimeException("Configuration not loaded. Call Config::load() first.");
        }
        return self::$config;
    }

    public static function isLoaded(): bool
    {
        return self::$config !== null;
    }
}
