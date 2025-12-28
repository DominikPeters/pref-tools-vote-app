<?php

/**
 * Application Bootstrap
 *
 * This file initializes the application by:
 * 1. Setting up autoloading
 * 2. Loading configuration
 * 3. Initializing the database connection
 * 4. Starting the session
 */

// Error reporting (will be controlled by config)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('SRC_PATH', BASE_PATH . '/src');
define('CONFIG_PATH', BASE_PATH . '/config');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('DATA_PATH', BASE_PATH . '/data');
define('MIGRATIONS_PATH', BASE_PATH . '/migrations');

// Detect environment (default: production)
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('IS_TEST_ENV', APP_ENV === 'test');

// Simple PSR-4-ish autoloader
spl_autoload_register(function (string $class): void {
    // Only handle App namespace
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    // Convert namespace to path
    $relativePath = str_replace('App\\', '', $class);
    $relativePath = str_replace('\\', '/', $relativePath);
    $file = SRC_PATH . '/' . $relativePath . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Check if config exists (use test config in test environment)
$configFile = IS_TEST_ENV
    ? CONFIG_PATH . '/config.test.php'
    : CONFIG_PATH . '/config.php';
$needsInstall = !file_exists($configFile);

if (!$needsInstall) {
    // Load configuration
    \App\Config::load($configFile);

    // Set timezone
    date_default_timezone_set(\App\Config::get('app.timezone', 'UTC'));

    // Debug mode
    if (\App\Config::get('app.debug', false)) {
        ini_set('display_errors', '1');
    }

    // Start session
    $sessionName = \App\Config::get('session.name', 'poll_session');
    $sessionLifetime = \App\Config::get('session.lifetime', 7200);

    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if sysadmin exists - if not, installation is incomplete
    $db = \App\Database::getInstance();
    $sysadminCount = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM users WHERE role = :role",
        ['role' => 'sysadmin']
    );
    if ($sysadminCount === 0) {
        $needsInstall = true;
    }
}

/**
 * Helper function to render a template
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
 * Escape HTML entities
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the current URL path
 */
function currentPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (($pos = strpos($uri, '?')) !== false) {
        $uri = substr($uri, 0, $pos);
    }
    return $uri;
}

/**
 * Generate a URL
 */
function url(string $path = ''): string
{
    $base = \App\Config::isLoaded() ? \App\Config::get('app.url', '') : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Get the base path for URLs (handles subfolder deployment)
 * Returns empty string for root, or '/subfolder' for subfolder deployment
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
 * Generate an asset URL with base path
 */
function asset(string $path): string
{
    return basePath() . '/' . ltrim($path, '/');
}

/**
 * Check if the app needs installation
 */
function needsInstall(): bool
{
    global $needsInstall;
    return $needsInstall;
}
