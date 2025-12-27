<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private string $driver;

    private function __construct()
    {
        $this->driver = Config::get('database.driver', 'sqlite');

        if ($this->driver === 'sqlite') {
            $this->connectSqlite();
        } else {
            $this->connectMysql();
        }
    }

    private function connectSqlite(): void
    {
        $path = Config::get('database.sqlite_path');
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO("sqlite:{$path}");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable foreign keys for SQLite
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function connectMysql(): void
    {
        $host = Config::get('database.mysql_host', 'localhost');
        $port = Config::get('database.mysql_port', 3306);
        $database = Config::get('database.mysql_database');
        $username = Config::get('database.mysql_username');
        $password = Config::get('database.mysql_password');
        $charset = Config::get('database.mysql_charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * Execute a query and return the statement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute a query and return all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a query and return a single row
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Execute a query and return a single value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    /**
     * Insert a row and return the last insert ID
     */
    public function insert(string $table, array $data): int|string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows and return the number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($c) => "{$c} = :set_{$c}", array_keys($data));

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        // Prefix data keys with 'set_' to avoid collision with where params
        $prefixedData = [];
        foreach ($data as $key => $value) {
            $prefixedData["set_{$key}"] = $value;
        }

        $stmt = $this->query($sql, array_merge($prefixedData, $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete rows and return the number of affected rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $table): bool
    {
        if ($this->isSqlite()) {
            $result = $this->fetch(
                "SELECT name FROM sqlite_master WHERE type='table' AND name = :table",
                ['table' => $table]
            );
        } else {
            $result = $this->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table",
                ['db' => Config::get('database.mysql_database'), 'table' => $table]
            );
        }
        return $result !== null;
    }

    /**
     * Run a migration SQL file
     */
    public function runMigration(string $sql): void
    {
        // Adjust SQL for driver differences
        if ($this->isSqlite()) {
            // SQLite ignores AUTO_INCREMENT, but we still need to remove it for cleaner parsing
            $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);
            // SQLite uses INTEGER PRIMARY KEY for autoincrement, not INT
            $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY', $sql);
        }

        // Execute statements one by one
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s)
        );

        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /**
     * Reset the singleton (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
