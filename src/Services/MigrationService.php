<?php

namespace App\Services;

use App\Database;

class MigrationService
{
    private Database $db;
    private string $migrationsPath;

    public function __construct(?string $migrationsPath = null)
    {
        $this->db = Database::getInstance();
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . '/migrations';
    }

    /**
     * Run all pending migrations
     *
     * @return array List of migrations that were run
     */
    public function runPending(): array
    {
        $this->ensureMigrationsTable();

        $pending = $this->getPendingMigrations();

        // If this is an existing system where the initial schema was already applied manually,
        // mark it as completed to avoid trying to run it again.
        if (in_array('001_initial_schema.sql', $pending) && $this->db->tableExists('users')) {
            $this->db->insert('migrations', [
                'migration' => '001_initial_schema.sql',
                'ran_at' => date('Y-m-d H:i:s'),
            ]);
            // Refresh pending list
            $pending = $this->getPendingMigrations();
        }

        $ran = [];

        foreach ($pending as $migration) {
            $this->runMigration($migration);
            $ran[] = $migration;
        }

        return $ran;
    }

    /**
     * Get list of pending migrations
     */
    public function getPendingMigrations(): array
    {
        $this->ensureMigrationsTable();

        $all = $this->getAllMigrationFiles();
        $completed = $this->getCompletedMigrations();

        return array_values(array_diff($all, $completed));
    }

    /**
     * Get list of completed migrations
     */
    public function getCompletedMigrations(): array
    {
        $rows = $this->db->fetchAll("SELECT migration FROM migrations ORDER BY id ASC");
        return array_column($rows, 'migration');
    }

    /**
     * Get all migration files from the migrations directory
     */
    public function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.sql');
        $migrations = array_map('basename', $files);
        sort($migrations); // Ensure alphabetical order (001_, 002_, etc.)

        return $migrations;
    }

    /**
     * Run a specific migration
     */
    public function runMigration(string $filename): void
    {
        $path = $this->migrationsPath . '/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$filename}");
        }

        $sql = file_get_contents($path);
        $this->db->runMigration($sql);

        // Record the migration
        $this->db->insert('migrations', [
            'migration' => $filename,
            'ran_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ensure the migrations tracking table exists
     */
    public function ensureMigrationsTable(): void
    {
        if ($this->db->tableExists('migrations')) {
            return;
        }

        $sql = $this->db->isSqlite()
            ? "CREATE TABLE migrations (
                id INTEGER PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL
            )"
            : "CREATE TABLE migrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL
            )";

        $this->db->getPdo()->exec($sql);
    }

    /**
     * Check if any migrations are pending
     */
    public function hasPending(): bool
    {
        return count($this->getPendingMigrations()) > 0;
    }

    /**
     * Get migration status
     */
    public function getStatus(): array
    {
        $this->ensureMigrationsTable();

        return [
            'all' => $this->getAllMigrationFiles(),
            'completed' => $this->getCompletedMigrations(),
            'pending' => $this->getPendingMigrations(),
        ];
    }
}
