<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class AccessToken
{
    public int $id;
    public int $voteId;
    public string $token;
    public ?string $label = null;
    public ?\DateTime $usedAt = null;
    public ?int $responseId = null;
    public \DateTime $createdAt;

    /**
     * Create an AccessToken from a database row
     */
    public static function fromRow(array $row): self
    {
        $token = new self();
        $token->id = (int) $row['id'];
        $token->voteId = (int) $row['vote_id'];
        $token->token = $row['token'];
        $token->label = $row['label'];
        $token->usedAt = $row['used_at'] ? new \DateTime($row['used_at']) : null;
        $token->responseId = $row['response_id'] ? (int) $row['response_id'] : null;
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
     * Find an access token by token string for a specific vote
     */
    public static function findByToken(int $voteId, string $token): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT * FROM access_tokens WHERE vote_id = :vote_id AND token = :token',
            ['vote_id' => $voteId, 'token' => $token]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all tokens for a vote
     */
    public static function findByVoteId(int $voteId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT * FROM access_tokens WHERE vote_id = :vote_id ORDER BY created_at DESC',
            ['vote_id' => $voteId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Create new access tokens
     */
    public static function generate(int $voteId, int $count = 1, ?string $labelPrefix = null): array
    {
        $db = Database::getInstance();
        $tokens = [];

        for ($i = 0; $i < $count; $i++) {
            $token = TokenService::generateAccessToken();
            $label = $labelPrefix ? "{$labelPrefix} " . ($i + 1) : null;

            $id = $db->insert('access_tokens', [
                'vote_id' => $voteId,
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
     */
    public function markUsed(?int $responseId = null): self
    {
        $db = Database::getInstance();
        $db->update(
            'access_tokens',
            [
                'used_at' => date('Y-m-d H:i:s'),
                'response_id' => $responseId,
            ],
            'id = :id',
            ['id' => $this->id]
        );

        $this->usedAt = new \DateTime();
        $this->responseId = $responseId;

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
            'vote_id' => $this->voteId,
            'token' => $this->token,
            'label' => $this->label,
            'used_at' => $this->usedAt?->format('c'),
            'response_id' => $this->responseId,
            'created_at' => $this->createdAt->format('c'),
            'is_used' => $this->usedAt !== null,
        ];
    }
}
