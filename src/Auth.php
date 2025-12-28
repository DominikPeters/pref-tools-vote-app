<?php

namespace App;

use App\Models\User;

class Auth
{
    private static ?Auth $instance = null;
    private ?User $user = null;
    private bool $checked = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Attempt to authenticate a user with email and password
     */
    public function attempt(string $email, string $password): ?User
    {
        $db = Database::getInstance();

        $row = $db->fetch(
            "SELECT * FROM users WHERE email = :email",
            ['email' => strtolower(trim($email))]
        );

        if (!$row) {
            return null;
        }

        if (!password_verify($password, $row['password_hash'])) {
            return null;
        }

        $user = User::fromRow($row);
        $this->login($user);

        return $user;
    }

    /**
     * Log in a user (set session)
     */
    public function login(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
        $this->user = $user;
        $this->checked = true;
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->user = null;
        $this->checked = true;
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?User
    {
        if (!$this->checked) {
            $this->checkSession();
        }
        return $this->user;
    }

    /**
     * Check if a user is authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the current user's ID
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user?->id;
    }

    /**
     * Check session for existing login
     */
    private function checkSession(): void
    {
        $this->checked = true;

        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $_SESSION['user_id']]
        );

        if ($row) {
            $this->user = User::fromRow($row);
        } else {
            // Invalid session, clear it
            unset($_SESSION['user_id']);
        }
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, string $role = User::ROLE_USER): User
    {
        $email = strtolower(trim($email));

        $db = Database::getInstance();

        // Check if email already exists
        $existing = $db->fetch(
            "SELECT id FROM users WHERE email = :email",
            ['email' => $email]
        );

        if ($existing) {
            throw new \RuntimeException('Email already registered');
        }

        // Create user
        $id = $db->insert('users', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $user = new User();
        $user->id = (int)$id;
        $user->email = $email;
        $user->role = $role;
        $user->createdAt = new \DateTime();
        $user->updatedAt = new \DateTime();

        return $user;
    }

    /**
     * Hash a password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify a password against a hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

/**
 * Helper function to get auth instance
 */
function auth(): Auth
{
    return Auth::getInstance();
}
