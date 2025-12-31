<?php

/**
 * PHPUnit Test Bootstrap
 *
 * Sets up the test environment with an in-memory SQLite database.
 */

// Define paths
define('BASE_PATH', dirname(__DIR__));
define('SRC_PATH', BASE_PATH . '/src');
define('CONFIG_PATH', BASE_PATH . '/config');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('DATA_PATH', BASE_PATH . '/data');
define('MIGRATIONS_PATH', BASE_PATH . '/migrations');

// Autoloader for App namespace
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relativePath = str_replace('App\\', '', $class);
        $relativePath = str_replace('\\', '/', $relativePath);
        $file = SRC_PATH . '/' . $relativePath . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }

    // Autoloader for Tests namespace
    if (str_starts_with($class, 'Tests\\')) {
        $relativePath = str_replace('Tests\\', '', $class);
        $relativePath = str_replace('\\', '/', $relativePath);
        $file = __DIR__ . '/' . $relativePath . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Load test configuration
\App\Config::load(__DIR__ . '/config.php');

// Set timezone
date_default_timezone_set('UTC');

// Start session (mock)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper function to escape HTML
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper function to generate a URL
 */
function url(string $path = ''): string
{
    $base = \App\Config::isLoaded() ? \App\Config::get('app.url', '') : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Helper function to get base path
 */
function basePath(): string
{
    if (!\App\Config::isLoaded()) {
        return '';
    }
    $url = \App\Config::get('app.url', '');
    $parsed = parse_url($url);
    return rtrim($parsed['path'] ?? '', '/');
}

/**
 * Render a template to a string
 */
function render(string $template, array $data = []): string
{
    extract($data);
    ob_start();
    require TEMPLATES_PATH . '/' . $template . '.php';
    return ob_get_clean();
}

/**
 * Helper function to render a template and echo it
 */
function view(string $template, array $data = []): void
{
    echo render($template, $data);
}

/**
 * Get the current URL path
 */
function currentPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    return $path ?: '/';
}

/**
 * Helper function to get asset URL with cache busting
 */
function asset(string $path): string
{
    $base = basePath();
    $fullPath = BASE_PATH . '/assets/' . ltrim($path, '/');
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return $base . '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Render markdown to HTML safely
 */
function markdown(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }

    static $parsedown = null;
    if ($parsedown === null) {
        require_once BASE_PATH . '/lib/Parsedown/Parsedown.php';
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $parsedown->setBreaksEnabled(true);
    }

    return $parsedown->text($text);
}
