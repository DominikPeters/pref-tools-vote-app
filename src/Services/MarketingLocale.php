<?php

namespace App\Services;

use App\Config;
use App\i18n\Languages;
use App\i18n\UiLocale;

/** Exact language URLs for public marketing pages, independent of poll routes. */
final class MarketingLocale
{
    /** Literal routes only: regional aliases and arbitrary IDs never become locale routes. */
    public static function routes(): array
    {
        $routes = [];
        foreach (array_keys(Languages::getAll()) as $locale) {
            $routes['/' . $locale] = 'home';
            $routes['/' . $locale . '/about'] = 'about';
        }
        return $routes;
    }

    /** @return array{page: string, locale: ?string}|null */
    public static function context(?string $requestUri = null, ?string $basePath = null): ?array
    {
        $requestUri ??= $_SERVER['REQUEST_URI'] ?? '/';
        if (!str_starts_with($requestUri, '/') || str_starts_with($requestUri, '//') || preg_match('/[\x00-\x20\\\\]/', $requestUri)) return null;
        $url = parse_url($requestUri);
        if ($url === false || isset($url['host']) || isset($url['scheme'])) return null;
        $path = $url['path'] ?? '/';
        $basePath = rtrim($basePath ?? basePath(), '/');
        if ($basePath !== '') {
            if ($path !== $basePath && !str_starts_with($path, $basePath . '/')) return null;
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = rtrim($path, '/') ?: '/';
        if ($path === '/') return ['page' => 'home', 'locale' => null];
        if ($path === '/about') return ['page' => 'about', 'locale' => null];
        $parts = explode('/', trim($path, '/'));
        if (!Languages::isValid($parts[0])) return null;
        if (count($parts) === 1) return ['page' => 'home', 'locale' => $parts[0]];
        if (count($parts) === 2 && $parts[1] === 'about') return ['page' => 'about', 'locale' => $parts[0]];
        return null;
    }

    public static function path(string $page = 'home', ?string $locale = null, ?string $basePath = null): string
    {
        $locale ??= UiLocale::get();
        if (!Languages::isValid($locale)) throw new \InvalidArgumentException('Unsupported marketing locale');
        if (!in_array($page, ['home', 'about'], true)) throw new \InvalidArgumentException('Unknown marketing page');
        return rtrim($basePath ?? basePath(), '/') . '/' . $locale . ($page === 'home' ? '/' : '/about');
    }

    /** URL locale wins only for this request; visiting it never changes an account preference. */
    public static function activate(string $locale, ?callable $writeCookie = null): void
    {
        if (!Languages::isValid($locale)) throw new \InvalidArgumentException('Unsupported marketing locale');
        UiLocale::initialize($locale);
        ($writeCookie ?? 'setcookie')(UiLocale::COOKIE, $locale, [
            'expires' => time() + 365 * 86400,
            'path' => rtrim(basePath(), '/') . '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Absolute SEO links are based on the configured origin, never request Host headers. */
    public static function metadata(string $page, ?string $locale = null): array
    {
        $locale ??= UiLocale::get();
        $origin = rtrim((string) Config::get('app.url', ''), '/');
        $alternates = [];
        foreach (array_keys(Languages::getAll()) as $code) {
            $alternates[$code] = $origin . self::path($page, $code, '');
        }
        $alternates['x-default'] = $origin . ($page === 'home' ? '/' : '/about');
        return [
            'canonical' => $origin . self::path($page, $locale, ''),
            'alternates' => $alternates,
            'og_locale' => self::openGraphLocale($locale),
        ];
    }

    private static function openGraphLocale(string $locale): string
    {
        return match ($locale) {
            'bn' => 'bn_BD', 'de' => 'de_DE', 'en' => 'en_US', 'es' => 'es_ES',
            'fr' => 'fr_FR', 'hi' => 'hi_IN', 'hr' => 'hr_HR', 'hu' => 'hu_HU',
            'it' => 'it_IT', 'nl' => 'nl_NL', 'pl' => 'pl_PL', 'pt' => 'pt_BR',
            'ro' => 'ro_RO', 'ru' => 'ru_RU',
            default => str_replace('-', '_', $locale),
        };
    }

    /** Reserve literal page/API namespaces when a custom short public-ID length is used. */
    public static function isReservedPublicId(string $id): bool
    {
        return Languages::isValid($id) || in_array($id, [
            'create', 'login', 'dashboard', 'language', 'privacy', 'terms', 'legal', 'about',
            'sysadmin', 'unsubscribe', 'preview', 'demo', 'api',
        ], true);
    }
}
