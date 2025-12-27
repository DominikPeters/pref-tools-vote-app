<?php

/**
 * Test Configuration
 *
 * Uses in-memory SQLite for fast, isolated tests.
 */

return [
    'database' => [
        'driver' => 'sqlite',
        'sqlite_path' => ':memory:', // In-memory database for tests
    ],
    'app' => [
        'name' => 'Pref.Tools Vote (Test)',
        'url' => 'http://localhost',
        'debug' => true,
        'timezone' => 'UTC',
    ],
    'session' => [
        'name' => 'vote_test_session',
        'lifetime' => 7200,
    ],
    'security' => [
        'public_id_length' => 8,
        'admin_token_length' => 32,
        'voter_token_length' => 32,
        'access_token_length' => 16,
    ],
    'mail' => [
        'enabled' => false,
    ],
];
