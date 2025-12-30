<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class EmailInvitation
{
    public int $id;
    public int $pollId;
    public string $email;
    public string $token;
    public ?\DateTime $sentAt = null;
    public ?\DateTime $clickedAt = null;
    public ?\DateTime $usedAt = null;
    public ?int $responseId = null;
    public bool $isSecretBallot = false;
    public \DateTime $createdAt;

    /**
     * Create an EmailInvitation from a database row
     */
    public static function fromRow(array $row): self
    {
        $invitation = new self();
        $invitation->id = (int) $row['id'];
        $invitation->pollId = (int) $row['poll_id'];
        $invitation->email = $row['email'];
        $invitation->token = $row['token'];
        $invitation->sentAt = $row['sent_at'] ? new \DateTime($row['sent_at']) : null;
        $invitation->clickedAt = $row['clicked_at'] ?? null ? new \DateTime($row['clicked_at']) : null;
        $invitation->usedAt = $row['used_at'] ? new \DateTime($row['used_at']) : null;
        $invitation->responseId = $row['response_id'] ? (int) $row['response_id'] : null;
        $invitation->isSecretBallot = (bool) ($row['is_secret_ballot'] ?? false);
        $invitation->createdAt = new \DateTime($row['created_at']);
        return $invitation;
    }

    /**
     * Find an invitation by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM email_invitations WHERE id = :id', ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find an invitation by token for a specific poll
     */
    public static function findByToken(int $pollId, string $token): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT * FROM email_invitations WHERE poll_id = :poll_id AND token = :token',
            ['poll_id' => $pollId, 'token' => $token]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find an invitation by email for a specific poll
     */
    public static function findByEmail(int $pollId, string $email): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT * FROM email_invitations WHERE poll_id = :poll_id AND email = :email',
            ['poll_id' => $pollId, 'email' => strtolower(trim($email))]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all invitations for a poll
     */
    public static function findByPollId(int $pollId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT * FROM email_invitations WHERE poll_id = :poll_id ORDER BY created_at DESC',
            ['poll_id' => $pollId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Create a new email invitation
     */
    public static function create(int $pollId, string $email): self
    {
        $db = Database::getInstance();
        $token = TokenService::generateAccessToken();

        $id = $db->insert('email_invitations', [
            'poll_id' => $pollId,
            'email' => strtolower(trim($email)),
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find($id);
    }

    /**
     * Mark as sent
     */
    public function markSent(): self
    {
        $db = Database::getInstance();
        $db->update(
            'email_invitations',
            ['sent_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $this->id]
        );

        $this->sentAt = new \DateTime();
        return $this;
    }

    /**
     * Mark as clicked (when the invitation link is visited)
     */
    public function markClicked(): self
    {
        if ($this->clickedAt !== null) {
            return $this; // Already marked
        }

        $db = Database::getInstance();
        $db->update(
            'email_invitations',
            ['clicked_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $this->id]
        );

        $this->clickedAt = new \DateTime();
        return $this;
    }

    /**
     * Mark as used
     * For secret ballot: no timestamp (to prevent correlation), no responseId linking
     */
    public function markUsed(?int $responseId = null, bool $isSecretBallot = false): self
    {
        $db = Database::getInstance();

        // For secret ballot, don't store timestamp to prevent timing correlation attacks
        $updateData = [
            'response_id' => $isSecretBallot ? null : $responseId,
            'is_secret_ballot' => $isSecretBallot ? 1 : 0,
        ];

        if (!$isSecretBallot) {
            $updateData['used_at'] = date('Y-m-d H:i:s');
            $this->usedAt = new \DateTime();
        }

        $db->update('email_invitations', $updateData, 'id = :id', ['id' => $this->id]);

        $this->responseId = $isSecretBallot ? null : $responseId;
        $this->isSecretBallot = $isSecretBallot;

        return $this;
    }

    /**
     * Delete the invitation
     */
    public function delete(): void
    {
        $db = Database::getInstance();
        $db->delete('email_invitations', 'id = :id', ['id' => $this->id]);
    }

    /**
     * Check if this invitation has been used
     */
    public function isUsed(): bool
    {
        return $this->usedAt !== null || $this->isSecretBallot;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'poll_id' => $this->pollId,
            'email' => $this->email,
            'token' => $this->token,
            'sent_at' => $this->sentAt?->format('c'),
            'clicked_at' => $this->clickedAt?->format('c'),
            'used_at' => $this->usedAt?->format('c'),
            'response_id' => $this->responseId,
            'is_secret_ballot' => $this->isSecretBallot,
            'created_at' => $this->createdAt->format('c'),
            'is_sent' => $this->sentAt !== null,
            'is_clicked' => $this->clickedAt !== null,
            'is_used' => $this->isUsed(),
        ];
    }
}
