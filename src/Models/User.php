<?php

namespace App\Models;

use App\Database;

class User
{
    public const ROLE_USER = 'user';
    public const ROLE_SYSADMIN = 'sysadmin';

    public ?int $id = null;
    public string $email;
    public string $name;
    public ?string $passwordHash = null;
    public string $role = self::ROLE_USER;
    public ?\DateTime $emailVerifiedAt = null;
    public ?string $emailVerificationToken = null;
    public ?\DateTime $emailVerificationExpires = null;
    public ?string $passwordResetToken = null;
    public ?\DateTime $passwordResetExpires = null;
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
        $user->name = $row['name'];
        $user->passwordHash = $row['password_hash'];
        $user->role = $row['role'] ?? self::ROLE_USER;
        $user->emailVerifiedAt = !empty($row['email_verified_at']) ? new \DateTime($row['email_verified_at']) : null;
        $user->emailVerificationToken = $row['email_verification_token'] ?? null;
        $user->emailVerificationExpires = !empty($row['email_verification_expires']) ? new \DateTime($row['email_verification_expires']) : null;
        $user->passwordResetToken = $row['password_reset_token'] ?? null;
        $user->passwordResetExpires = !empty($row['password_reset_expires']) ? new \DateTime($row['password_reset_expires']) : null;
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
     * Check if user's email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
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
            'name' => $this->name,
            'role' => $this->role,
            'email_verified' => $this->isEmailVerified(),
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

    /**
     * Set email verification token (24-hour expiry)
     */
    public function setVerificationToken(string $token): void
    {
        $db = Database::getInstance();
        $expires = new \DateTime('+24 hours');
        $db->update('users', [
            'email_verification_token' => $token,
            'email_verification_expires' => $expires->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $this->id]);
        $this->emailVerificationToken = $token;
        $this->emailVerificationExpires = $expires;
    }

    /**
     * Clear email verification token
     */
    public function clearVerificationToken(): void
    {
        $db = Database::getInstance();
        $db->update('users', [
            'email_verification_token' => null,
            'email_verification_expires' => null,
        ], 'id = :id', ['id' => $this->id]);
        $this->emailVerificationToken = null;
        $this->emailVerificationExpires = null;
    }

    /**
     * Mark email as verified
     */
    public function markEmailVerified(): void
    {
        $db = Database::getInstance();
        $now = new \DateTime();
        $db->update('users', [
            'email_verified_at' => $now->format('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'email_verification_expires' => null,
        ], 'id = :id', ['id' => $this->id]);
        $this->emailVerifiedAt = $now;
        $this->emailVerificationToken = null;
        $this->emailVerificationExpires = null;
    }

    /**
     * Set password reset token (1-hour expiry)
     */
    public function setPasswordResetToken(string $token): void
    {
        $db = Database::getInstance();
        $expires = new \DateTime('+1 hour');
        $db->update('users', [
            'password_reset_token' => $token,
            'password_reset_expires' => $expires->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $this->id]);
        $this->passwordResetToken = $token;
        $this->passwordResetExpires = $expires;
    }

    /**
     * Clear password reset token
     */
    public function clearPasswordResetToken(): void
    {
        $db = Database::getInstance();
        $db->update('users', [
            'password_reset_token' => null,
            'password_reset_expires' => null,
        ], 'id = :id', ['id' => $this->id]);
        $this->passwordResetToken = null;
        $this->passwordResetExpires = null;
    }

    /**
     * Update user's password
     */
    public function updatePassword(string $hashedPassword): void
    {
        $db = Database::getInstance();
        $db->update('users', [
            'password_hash' => $hashedPassword,
            'password_reset_token' => null,
            'password_reset_expires' => null,
        ], 'id = :id', ['id' => $this->id]);
        $this->passwordHash = $hashedPassword;
        $this->passwordResetToken = null;
        $this->passwordResetExpires = null;
    }

    /**
     * Update user name
     */
    public function updateName(string $name): void
    {
        $db = Database::getInstance();
        $db->update('users', [
            'name' => $name,
            'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $this->id]);
        $this->name = $name;
        $this->updatedAt = new \DateTime();
    }

    /**
     * Find user by verification token (checks expiry)
     */
    public static function findByVerificationToken(string $token): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM users WHERE email_verification_token = :token AND email_verification_expires > :now",
            ['token' => $token, 'now' => (new \DateTime())->format('Y-m-d H:i:s')]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find user by password reset token (checks expiry)
     */
    public static function findByPasswordResetToken(string $token): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM users WHERE password_reset_token = :token AND password_reset_expires > :now",
            ['token' => $token, 'now' => (new \DateTime())->format('Y-m-d H:i:s')]
        );
        return $row ? self::fromRow($row) : null;
    }
}
