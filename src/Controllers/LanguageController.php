<?php

namespace App\Controllers;

use App\i18n\Languages;
use App\i18n\UiLocale;

final class LanguageController
{
    /** POST /language, protected by the application's CSRF middleware. */
    public function update(array $params): void
    {
        $value = $_POST['locale'] ?? null;
        $locale = is_string($value) ? Languages::resolve($value) : null;
        if ($locale === null) {
            http_response_code(400);
            view('error', ['title' => __('backend_unsupported_language'), 'message' => __('backend_choose_an_available_interface_language')]);
            return;
        }
        \App\Auth::getInstance()->user()?->updatePreferredLocale($locale);
        $returnTo = self::returnPathForLocale($_POST['return_to'] ?? '', $locale, basePath());
        setcookie(UiLocale::COOKIE, $locale, [
            'expires' => time() + 365 * 86400,
            'path' => rtrim(basePath(), '/') . '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: ' . $returnTo, true, 303);
    }

    public static function safeReturnPath(mixed $path, string $basePath = ''): string
    {
        $basePath = rtrim($basePath, '/');
        $fallback = rtrim($basePath, '/') . '/';
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\x00-\x20\\\\]/', $path)) {
            return $fallback;
        }
        $url = parse_url($path);
        if ($url === false || isset($url['host']) || isset($url['scheme'])) return $fallback;
        $decodedPath = rawurldecode($url['path'] ?? '');
        if (preg_match('/[\x00-\x20\\\\]/', $decodedPath) || str_starts_with($decodedPath, '//') || in_array('..', explode('/', $decodedPath), true)) return $fallback;
        if ($basePath !== '' && $decodedPath !== $basePath && !str_starts_with($decodedPath, $basePath . '/')) return $fallback;
        return $path;
    }

    /** Keep marketing language changes on the equivalent page; app and poll paths stay intact. */
    public static function returnPathForLocale(mixed $path, string $locale, string $basePath = ''): string
    {
        $safe = self::safeReturnPath($path, $basePath);
        if (!Languages::isValid($locale)) return $safe;
        $context = \App\Services\MarketingLocale::context($safe, $basePath);
        if ($context === null) return $safe;
        $url = parse_url($safe);
        return \App\Services\MarketingLocale::path($context['page'], $locale, $basePath)
            . (isset($url['query']) ? '?' . $url['query'] : '')
            . (isset($url['fragment']) ? '#' . $url['fragment'] : '');
    }
}
