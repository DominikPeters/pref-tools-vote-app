<?php

namespace App\Services;

use App\i18n\Languages;

/** Resolve generated, content-hashed screenshots without mixing image languages. */
final class ScreenshotAssets
{
    private static ?array $manifest = null;

    public static function forLocale(string $name, string $locale, string $basePath = ''): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name)) {
            throw new \InvalidArgumentException('Invalid screenshot name.');
        }
        $locale = Languages::resolve($locale) ?? 'en';
        $manifestPath = BASE_PATH . '/screenshots/manifest.json';
        if (self::$manifest === null) {
            // FTP may expose a partial manifest while a release uploads it.
            $contents = @file_get_contents($manifestPath);
            try {
                $decoded = is_string($contents) ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : [];
                self::$manifest = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                self::$manifest = [];
            }
        }
        if (self::$manifest !== []) {
            $resolved = self::resolve(self::$manifest, $name, $locale, $basePath);
            if ($resolved !== null) return $resolved;
        }

        // Original assets keep the home page usable during the first generation.
        $result = ['locale' => 'en'];
        foreach (['light' => '', 'dark' => '-dark'] as $theme => $suffix) {
            $path = 'screenshots/' . $name . $suffix . '.png';
            $absolutePath = BASE_PATH . '/' . $path;
            $size = is_file($absolutePath) ? getimagesize($absolutePath) : false;
            if ($size === false) throw new \RuntimeException('Screenshot asset is unavailable: ' . $name);
            $result[$theme] = [
                'url' => rtrim($basePath, '/') . '/' . $path,
                'width' => $size[0],
                'height' => $size[1],
                'fingerprint' => hash_file('sha256', $absolutePath),
            ];
        }
        return $result;
    }

    /** Validate a complete light/dark pair; fall back as a pair to avoid mixed locales. */
    public static function resolve(array $manifest, string $name, string $locale, string $basePath = ''): ?array
    {
        if (($manifest['version'] ?? null) !== 1) return null;
        foreach (array_unique([$locale, 'en']) as $candidate) {
            $pair = $manifest['locales'][$candidate][$name] ?? null;
            if (!is_array($pair)) continue;
            $result = ['locale' => $candidate];
            foreach (['light', 'dark'] as $theme) {
                $entry = $pair[$theme] ?? null;
                if (!is_array($entry)
                    || !is_string($entry['path'] ?? null)
                    || !preg_match('~^screenshots/localized/[a-z]{2}/[a-z][a-z0-9_]*-(?:light|dark)-[a-f0-9]{12}\.webp$~D', $entry['path'])
                    || !is_int($entry['width'] ?? null) || $entry['width'] < 1
                    || !is_int($entry['height'] ?? null) || $entry['height'] < 1
                    || !is_string($entry['fingerprint'] ?? null)
                    || !preg_match('/^[a-f0-9]{64}$/D', $entry['fingerprint'])) {
                    continue 2;
                }
                $expectedPath = 'screenshots/localized/' . $candidate . '/' . $name . '-' . $theme
                    . '-' . substr($entry['fingerprint'], 0, 12) . '.webp';
                if ($entry['path'] !== $expectedPath) continue 2;
                $result[$theme] = [
                    'url' => rtrim($basePath, '/') . '/' . $entry['path'],
                    'width' => $entry['width'],
                    'height' => $entry['height'],
                    'fingerprint' => $entry['fingerprint'],
                ];
            }
            return $result;
        }
        return null;
    }
}
