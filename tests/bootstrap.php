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
