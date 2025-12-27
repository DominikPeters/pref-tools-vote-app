<?php

namespace App\Models;

use App\Database;

class User
{
    public ?int $id = null;
    public string $email;
    public ?string $passwordHash = null;
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
        $user->createdAt = new \DateTime($row['created_at']);
        $user->updatedAt = new \DateTime($row['updated_at']);
        return $user;
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
            'created_at' => $this->createdAt?->format('c'),
        ];
    }
}
