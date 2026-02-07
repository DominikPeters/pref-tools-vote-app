<?php

return [
    // Database configuration
    'database' => [
        // 'driver' => 'sqlite' or 'mysql'
        'driver' => 'sqlite',

        // SQLite settings
        'sqlite_path' => __DIR__ . '/../data/poll.db',

        // MySQL settings (used when driver is 'mysql')
        'mysql_host' => 'localhost',
        'mysql_port' => 3306,
        'mysql_database' => 'poll_app',
        'mysql_username' => 'root',
        'mysql_password' => '',
        'mysql_charset' => 'utf8mb4',

        // Automatically run pending migrations on startup
        'auto_migrate' => true,
    ],

    // Application settings
    'app' => [
        'name' => 'Pref.Tools Vote',
        'url' => 'http://localhost',
        'debug' => true,
        'timezone' => 'UTC',
    ],

    // Session settings
    'session' => [
        'name' => 'poll_session',
        'lifetime' => 7200, // 2 hours
    ],

    // Security
    'security' => [
        // Length of random tokens
        'public_id_length' => 8,
        'admin_token_length' => 32,
        'voter_token_length' => 32,
        'access_token_length' => 16,
    ],

    // Mail settings (for future email invitations)
    'mail' => [
        'enabled' => false,
        'from_address' => 'noreply@example.com',
        'from_name' => 'Pref.Tools Vote',
        // SMTP settings
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
    ],
];
