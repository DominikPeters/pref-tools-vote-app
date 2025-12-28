<?php

namespace App\Models;

use App\Database;

class User
{
    public const ROLE_USER = 'user';
    public const ROLE_SYSADMIN = 'sysadmin';

    public ?int $id = null;
    public string $email;
    public ?string $passwordHash = null;
    public string $role = self::ROLE_USER;
    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;

    /**
     * Create a User instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->email = $row['email'];
        $user->passwordHash = $row['password_hash'];
        $user->role = $row['role'] ?? self::ROLE_USER;
        $user->createdAt = new \DateTime($row['created_at']);
        $user->updatedAt = new \DateTime($row['updated_at']);
        return $user;
    }

    /**
     * Check if user is a sysadmin
     */
    public function isSysadmin(): bool
    {
        return $this->role === self::ROLE_SYSADMIN;
    }

    /**
     * Find a user by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find a user by email
     */
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM users WHERE email = :email",
            ['email' => strtolower(trim($email))]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Convert to array for JSON output (excluding sensitive data)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->createdAt?->format('c'),
        ];
    }

    /**
     * Get all users (for sysadmin)
     */
    public static function all(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Count total users
     */
    public static function count(): int
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM users");
    }

    /**
     * Update user role
     */
    public function updateRole(string $role): void
    {
        $db = Database::getInstance();
        $db->update('users', ['role' => $role], 'id = :id', ['id' => $this->id]);
        $this->role = $role;
    }

    /**
     * Delete user
     */
    public function delete(): void
    {
        $db = Database::getInstance();
        $db->delete('users', 'id = :id', ['id' => $this->id]);
    }
}
