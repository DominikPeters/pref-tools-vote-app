<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class Response
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public ?int $id = null;
    public int $pollId;

    public ?string $voterName = null;
    public ?string $voterToken = null;
    public ?int $accessTokenId = null;
    public ?int $userId = null;

    public ?string $ipAddress = null;
    public ?string $userAgent = null;

    public string $status = self::STATUS_ACTIVE;
    public ?\DateTime $withdrawnAt = null;

    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;

    /** @var Answer[] */
    public array $answers = [];

    /**
     * Create a Response instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $response = new self();
        $response->id = (int) $row['id'];
        $response->pollId = (int) $row['poll_id'];
        $response->voterName = $row['voter_name'];
        $response->voterToken = $row['voter_token'];
        $response->accessTokenId = $row['access_token_id'] ? (int) $row['access_token_id'] : null;
        $response->userId = $row['user_id'] ? (int) $row['user_id'] : null;
        $response->ipAddress = $row['ip_address'];
        $response->userAgent = $row['user_agent'];
        $response->status = $row['status'] ?? self::STATUS_ACTIVE;
        $response->withdrawnAt = isset($row['withdrawn_at']) && $row['withdrawn_at']
            ? new \DateTime($row['withdrawn_at'])
            : null;
        $response->createdAt = new \DateTime($row['created_at']);
        $response->updatedAt = new \DateTime($row['updated_at']);
        return $response;
    }

    /**
     * Find a response by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM responses WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find responses by poll ID
     * @param bool $includeWithdrawn Whether to include withdrawn responses (default: false)
     */
    public static function findByPollId(int $pollId, bool $includeWithdrawn = false): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM responses WHERE poll_id = :poll_id";
        if (!$includeWithdrawn) {
            $sql .= " AND status = '" . self::STATUS_ACTIVE . "'";
        }
        $sql .= " ORDER BY created_at ASC";
        $rows = $db->fetchAll($sql, ['poll_id' => $pollId]);
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Count responses by poll ID (active only by default)
     */
    public static function countByPollId(int $pollId, bool $includeWithdrawn = false): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM responses WHERE poll_id = :poll_id";
        if (!$includeWithdrawn) {
            $sql .= " AND status = '" . self::STATUS_ACTIVE . "'";
        }
        $row = $db->fetch($sql, ['poll_id' => $pollId]);
        return (int) $row['count'];
    }

    /**
     * Count withdrawn responses for a poll
     */
    public static function countWithdrawnByPollId(int $pollId): int
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT COUNT(*) as count FROM responses WHERE poll_id = :poll_id AND status = :status",
            ['poll_id' => $pollId, 'status' => self::STATUS_WITHDRAWN]
        );
        return (int) $row['count'];
    }

    /**
     * Find a response by voter token
     */
    public static function findByVoterToken(int $pollId, string $voterToken): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM responses WHERE poll_id = :poll_id AND voter_token = :voter_token",
            ['poll_id' => $pollId, 'voter_token' => $voterToken]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find responses by user ID
     */
    public static function findByUserId(int $userId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM responses WHERE user_id = :user_id ORDER BY created_at DESC",
            ['user_id' => $userId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Find a response by user ID and poll ID
     */
    public static function findByUserIdAndPollId(int $userId, int $pollId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM responses WHERE user_id = :user_id AND poll_id = :poll_id",
            ['user_id' => $userId, 'poll_id' => $pollId]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Create a new response
     * @param bool $isSecretBallot If true, no IP/user_agent is stored for privacy
     */
    public static function create(int $pollId, array $data, bool $isSecretBallot = false): self
    {
        $db = Database::getInstance();

        $now = date('Y-m-d H:i:s');
        $voterToken = TokenService::generateVoterToken();

        // For secret ballot, don't store IP address or user agent for privacy
        $ipAddress = $isSecretBallot ? null : ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $isSecretBallot ? null : (isset($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
            : null);

        $id = $db->insert('responses', [
            'poll_id' => $pollId,
            'voter_name' => $data['voter_name'] ?? null,
            'voter_token' => $voterToken,
            'access_token_id' => $data['access_token_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = self::find((int) $id);

        // Create answers if provided
        if (!empty($data['answers'])) {
            foreach ($data['answers'] as $questionId => $answerData) {
                Answer::create($response->id, (int) $questionId, $answerData);
            }
            $response->loadAnswers();
        }

        return $response;
    }

    /**
     * Update the response
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('voter_name', $data)) {
            $updateData['voter_name'] = $data['voter_name'];
        }

        // Link response to user account if editing while logged in
        if (array_key_exists('user_id', $data) && $data['user_id'] !== null && $this->userId === null) {
            $updateData['user_id'] = $data['user_id'];
        }

        $db->update('responses', $updateData, 'id = :id', ['id' => $this->id]);

        // Update answers if provided
        if (!empty($data['answers'])) {
            // Delete existing answers
            $db->delete('answers', 'response_id = :response_id', ['response_id' => $this->id]);

            // Create new answers
            foreach ($data['answers'] as $questionId => $answerData) {
                Answer::create($this->id, (int) $questionId, $answerData);
            }
        }

        $response = self::find($this->id);
        $response->loadAnswers();

        return $response;
    }

    /**
     * Delete the response
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('responses', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Withdraw the response (soft delete)
     * - Marks status as withdrawn
     * - Deletes all answers (the actual vote content)
     * - Clears voter_name, ip_address, user_agent for privacy
     * - Keeps voter_token, access_token_id, user_id to prevent re-voting
     */
    public function withdraw(): self
    {
        $db = Database::getInstance();

        // Delete all answers
        $db->delete('answers', 'response_id = :response_id', ['response_id' => $this->id]);

        // Update response to withdrawn status and clear personal data
        $now = date('Y-m-d H:i:s');
        $db->update('responses', [
            'status' => self::STATUS_WITHDRAWN,
            'withdrawn_at' => $now,
            'voter_name' => null,
            'ip_address' => null,
            'user_agent' => null,
            'updated_at' => $now,
        ], 'id = :id', ['id' => $this->id]);

        $this->status = self::STATUS_WITHDRAWN;
        $this->withdrawnAt = new \DateTime($now);
        $this->voterName = null;
        $this->ipAddress = null;
        $this->userAgent = null;
        $this->answers = [];

        return $this;
    }

    /**
     * Check if this response has been withdrawn
     */
    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * Check if this response is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Load answers for this response
     */
    public function loadAnswers(): self
    {
        $this->answers = Answer::findByResponseId($this->id);
        return $this;
    }

    /**
     * Check if a voter token matches
     */
    public function verifyVoterToken(string $token): bool
    {
        return $this->voterToken && hash_equals($this->voterToken, $token);
    }

    /**
     * Convert to array for JSON output
     */
    public function toArray(bool $includeVoterInfo = true): array
    {
        $data = [
            'id' => $this->id,
            'status' => $this->status,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
            'withdrawn_at' => $this->withdrawnAt?->format('c'),
            'answers' => [],
        ];

        if ($includeVoterInfo) {
            $data['voter_name'] = $this->voterName;
        }

        foreach ($this->answers as $answer) {
            $data['answers'][$answer->questionId] = $answer->getValue();
        }

        return $data;
    }
}
