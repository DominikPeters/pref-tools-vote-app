<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class AccessToken
{
    public int $id;
    public int $pollId;
    public string $token;
    public ?string $label = null;
    public ?\DateTime $usedAt = null;
    public ?int $responseId = null;
    public bool $isSecretBallot = false;
    public \DateTime $createdAt;

    /**
     * Create an AccessToken from a database row
     */
    public static function fromRow(array $row): self
    {
        $token = new self();
        $token->id = (int) $row['id'];
        $token->pollId = (int) $row['poll_id'];
        $token->token = $row['token'];
        $token->label = $row['label'];
        $token->usedAt = $row['used_at'] ? new \DateTime($row['used_at']) : null;
        $token->responseId = $row['response_id'] ? (int) $row['response_id'] : null;
        $token->isSecretBallot = (bool) ($row['is_secret_ballot'] ?? false);
        $token->createdAt = new \DateTime($row['created_at']);
        return $token;
    }

    /**
     * Find an access token by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM access_tokens WHERE id = :id', ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find an access token by token string for a specific poll
     */
    public static function findByToken(int $pollId, string $token): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT * FROM access_tokens WHERE poll_id = :poll_id AND token = :token',
            ['poll_id' => $pollId, 'token' => $token]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all tokens for a poll
     */
    public static function findByPollId(int $pollId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT * FROM access_tokens WHERE poll_id = :poll_id ORDER BY created_at DESC',
            ['poll_id' => $pollId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Create new access tokens
     */
    public static function generate(int $pollId, int $count = 1, ?string $labelPrefix = null): array
    {
        $db = Database::getInstance();
        $tokens = [];

        for ($i = 0; $i < $count; $i++) {
            $token = TokenService::generateAccessToken();
            $label = $labelPrefix ? "{$labelPrefix} " . ($i + 1) : null;

            $id = $db->insert('access_tokens', [
                'poll_id' => $pollId,
                'token' => $token,
                'label' => $label,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $tokens[] = self::find($id);
        }

        return $tokens;
    }

    /**
     * Mark the token as used
     * For secret ballot, responseId should be null to avoid linking
     */
    public function markUsed(?int $responseId = null, bool $isSecretBallot = false): self
    {
        $db = Database::getInstance();
        $db->update(
            'access_tokens',
            [
                'used_at' => date('Y-m-d H:i:s'),
                'response_id' => $isSecretBallot ? null : $responseId,
                'is_secret_ballot' => $isSecretBallot ? 1 : 0,
            ],
            'id = :id',
            ['id' => $this->id]
        );

        $this->usedAt = new \DateTime();
        $this->responseId = $isSecretBallot ? null : $responseId;
        $this->isSecretBallot = $isSecretBallot;

        return $this;
    }

    /**
     * Delete the token
     */
    public function delete(): void
    {
        $db = Database::getInstance();
        $db->delete('access_tokens', 'id = :id', ['id' => $this->id]);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'poll_id' => $this->pollId,
            'token' => $this->token,
            'label' => $this->label,
            'used_at' => $this->usedAt?->format('c'),
            'response_id' => $this->responseId,
            'is_secret_ballot' => $this->isSecretBallot,
            'created_at' => $this->createdAt->format('c'),
            'is_used' => $this->usedAt !== null,
        ];
    }
}
