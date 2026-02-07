<?php

/**
 * Migration Runner CLI
 *
 * Usage:
 *   php migrate.php           # Run all pending migrations
 *   php migrate.php status    # Show migration status
 *   php migrate.php pending   # List pending migrations
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Services\MigrationService;

// Check if config exists
if (!file_exists(__DIR__ . '/config/config.php')) {
    echo "Error: config/config.php not found. Run install.php first.\n";
    exit(1);
}

$command = $argv[1] ?? 'run';
$migrationService = new MigrationService();

switch ($command) {
    case 'run':
        $pending = $migrationService->getPendingMigrations();

        if (empty($pending)) {
            echo "No pending migrations.\n";
            exit(0);
        }

        echo "Running " . count($pending) . " migration(s)...\n\n";

        try {
            $ran = $migrationService->runPending();
            foreach ($ran as $migration) {
                echo "  ✓ {$migration}\n";
            }
            echo "\nDone.\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'status':
        $status = $migrationService->getStatus();

        echo "Migration Status\n";
        echo "================\n\n";

        if (empty($status['all'])) {
            echo "No migrations found.\n";
            exit(0);
        }

        foreach ($status['all'] as $migration) {
            $isCompleted = in_array($migration, $status['completed']);
            $symbol = $isCompleted ? '✓' : '○';
            $label = $isCompleted ? 'ran' : 'pending';
            echo "  {$symbol} {$migration} ({$label})\n";
        }

        echo "\n";
        echo "Completed: " . count($status['completed']) . "\n";
        echo "Pending: " . count($status['pending']) . "\n";
        break;

    case 'pending':
        $pending = $migrationService->getPendingMigrations();

        if (empty($pending)) {
            echo "No pending migrations.\n";
            exit(0);
        }

        echo "Pending migrations:\n";
        foreach ($pending as $migration) {
            echo "  - {$migration}\n";
        }
        break;

    default:
        echo "Unknown command: {$command}\n";
        echo "Usage: php migrate.php [run|status|pending]\n";
        exit(1);
}
