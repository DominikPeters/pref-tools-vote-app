<?php

namespace App\Models;

use App\Database;

class EmailDeliverability
{
    public string $email;
    public ?\DateTime $unsubscribedAt = null;
    public ?array $data = null;
    public \DateTime $createdAt;
    public \DateTime $updatedAt;

    /**
     * Create an EmailDeliverability from a database row
     */
    public static function fromRow(array $row): self
    {
        $record = new self();
        $record->email = $row['email'];
        $record->unsubscribedAt = $row['unsubscribed_at'] ? new \DateTime($row['unsubscribed_at']) : null;
        $record->data = $row['data'] ? json_decode($row['data'], true) : null;
        $record->createdAt = new \DateTime($row['created_at']);
        $record->updatedAt = new \DateTime($row['updated_at']);
        return $record;
    }

    /**
     * Find a record by email
     */
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT * FROM email_deliverability WHERE email = :email',
            ['email' => strtolower(trim($email))]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find or create a record by email
     */
    public static function findOrCreate(string $email): self
    {
        $email = strtolower(trim($email));
        $existing = self::findByEmail($email);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $db->query(
            'INSERT INTO email_deliverability (email, created_at, updated_at) VALUES (:email, :created_at, :updated_at)',
            ['email' => $email, 'created_at' => $now, 'updated_at' => $now]
        );

        return self::findByEmail($email);
    }

    /**
     * Check if an email is unsubscribed
     */
    public static function isUnsubscribed(string $email): bool
    {
        $record = self::findByEmail($email);
        return $record !== null && $record->unsubscribedAt !== null;
    }

    /**
     * Unsubscribe an email
     */
    public static function unsubscribe(string $email): self
    {
        $record = self::findOrCreate($email);

        if ($record->unsubscribedAt === null) {
            $db = Database::getInstance();
            $now = date('Y-m-d H:i:s');
            $db->query(
                'UPDATE email_deliverability SET unsubscribed_at = :unsubscribed_at, updated_at = :updated_at WHERE email = :email',
                ['email' => $record->email, 'unsubscribed_at' => $now, 'updated_at' => $now]
            );
            $record->unsubscribedAt = new \DateTime($now);
            $record->updatedAt = new \DateTime($now);
        }

        return $record;
    }

    /**
     * Resubscribe an email (undo unsubscribe)
     */
    public static function resubscribe(string $email): self
    {
        $record = self::findOrCreate($email);

        if ($record->unsubscribedAt !== null) {
            $db = Database::getInstance();
            $now = date('Y-m-d H:i:s');
            $db->query(
                'UPDATE email_deliverability SET unsubscribed_at = NULL, updated_at = :updated_at WHERE email = :email',
                ['email' => $record->email, 'updated_at' => $now]
            );
            $record->unsubscribedAt = null;
            $record->updatedAt = new \DateTime($now);
        }

        return $record;
    }

    /**
     * Check multiple emails for unsubscribe status
     * Returns array of email => bool (true if unsubscribed)
     */
    public static function checkMultiple(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $emails = array_map(fn($e) => strtolower(trim($e)), $emails);
        $placeholders = implode(',', array_fill(0, count($emails), '?'));

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT email, unsubscribed_at FROM email_deliverability WHERE email IN ($placeholders) AND unsubscribed_at IS NOT NULL",
            array_values($emails)
        );

        $unsubscribed = [];
        foreach ($rows as $row) {
            $unsubscribed[$row['email']] = true;
        }

        $result = [];
        foreach ($emails as $email) {
            $result[$email] = isset($unsubscribed[$email]);
        }

        return $result;
    }

    /**
     * Update arbitrary data in the JSON column
     */
    public function updateData(array $newData): self
    {
        $this->data = array_merge($this->data ?? [], $newData);

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $db->query(
            'UPDATE email_deliverability SET data = :data, updated_at = :updated_at WHERE email = :email',
            ['email' => $this->email, 'data' => json_encode($this->data), 'updated_at' => $now]
        );
        $this->updatedAt = new \DateTime($now);

        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'unsubscribed_at' => $this->unsubscribedAt?->format('c'),
            'is_unsubscribed' => $this->unsubscribedAt !== null,
            'data' => $this->data,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
