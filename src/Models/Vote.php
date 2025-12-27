<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class Vote
{
    public ?int $id = null;
    public string $publicId;
    public string $adminToken;
    public ?int $userId = null;

    public string $title = 'Untitled Vote';
    public ?string $description = null;

    public string $status = 'draft'; // draft, open, closed
    public string $visibility = 'private'; // private, anonymous, names_only, full
    public string $visibilityTiming = 'after_close'; // during, after_close
    public bool $collectName = false;
    public ?string $nameVisibility = null;
    public bool $allowEditOwn = true;
    public bool $allowEditAny = false;
    public bool $randomizeOptions = false;

    public string $accessMode = 'link'; // link, password, token, email, login
    public ?string $accessPassword = null;

    public string $locale = 'en';

    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;
    public ?\DateTime $closedAt = null;

    /** @var Question[] */
    public array $questions = [];

    /**
     * Create a Vote instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $vote = new self();
        $vote->id = (int) $row['id'];
        $vote->publicId = $row['public_id'];
        $vote->adminToken = $row['admin_token'];
        $vote->userId = $row['user_id'] ? (int) $row['user_id'] : null;

        $vote->title = $row['title'];
        $vote->description = $row['description'];

        $vote->status = $row['status'];
        $vote->visibility = $row['visibility'];
        $vote->visibilityTiming = $row['visibility_timing'];
        $vote->collectName = (bool) $row['collect_name'];
        $vote->nameVisibility = $row['name_visibility'];
        $vote->allowEditOwn = (bool) $row['allow_edit_own'];
        $vote->allowEditAny = (bool) $row['allow_edit_any'];
        $vote->randomizeOptions = (bool) $row['randomize_options'];

        $vote->accessMode = $row['access_mode'];
        $vote->accessPassword = $row['access_password'];

        $vote->locale = $row['locale'];

        $vote->createdAt = new \DateTime($row['created_at']);
        $vote->updatedAt = new \DateTime($row['updated_at']);
        $vote->closedAt = $row['closed_at'] ? new \DateTime($row['closed_at']) : null;

        return $vote;
    }

    /**
     * Find a vote by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM votes WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find a vote by public ID
     */
    public static function findByPublicId(string $publicId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM votes WHERE public_id = :public_id",
            ['public_id' => $publicId]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find votes by user ID
     */
    public static function findByUserId(int $userId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM votes WHERE user_id = :user_id ORDER BY created_at DESC",
            ['user_id' => $userId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Create a new vote
     */
    public static function create(array $data, ?int $userId = null): self
    {
        $db = Database::getInstance();

        $publicId = TokenService::generatePublicId();
        $adminToken = TokenService::generateAdminToken();

        // Ensure unique public ID
        while ($db->fetch("SELECT id FROM votes WHERE public_id = :id", ['id' => $publicId])) {
            $publicId = TokenService::generatePublicId();
        }

        $now = date('Y-m-d H:i:s');

        $id = $db->insert('votes', [
            'public_id' => $publicId,
            'admin_token' => $adminToken,
            'user_id' => $userId,
            'title' => $data['title'] ?? 'Untitled Vote',
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'private',
            'visibility_timing' => $data['visibility_timing'] ?? 'after_close',
            'collect_name' => ($data['collect_name'] ?? false) ? 1 : 0,
            'name_visibility' => $data['name_visibility'] ?? null,
            'allow_edit_own' => ($data['allow_edit_own'] ?? true) ? 1 : 0,
            'allow_edit_any' => ($data['allow_edit_any'] ?? false) ? 1 : 0,
            'randomize_options' => ($data['randomize_options'] ?? false) ? 1 : 0,
            'access_mode' => $data['access_mode'] ?? 'link',
            'access_password' => isset($data['access_password'])
                ? password_hash($data['access_password'], PASSWORD_DEFAULT)
                : null,
            'locale' => $data['locale'] ?? 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return self::find((int) $id);
    }

    /**
     * Update the vote
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowedFields = [
            'title', 'description', 'status', 'visibility', 'visibility_timing',
            'collect_name', 'name_visibility', 'allow_edit_own', 'allow_edit_any',
            'randomize_options', 'access_mode', 'locale'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                // Convert booleans to integers
                if (in_array($field, ['collect_name', 'allow_edit_own', 'allow_edit_any', 'randomize_options'])) {
                    $value = $value ? 1 : 0;
                }
                $updateData[$field] = $value;
            }
        }

        // Handle password separately
        if (isset($data['access_password'])) {
            $updateData['access_password'] = password_hash($data['access_password'], PASSWORD_DEFAULT);
        }

        $db->update('votes', $updateData, 'id = :id', ['id' => $this->id]);

        return self::find($this->id);
    }

    /**
     * Delete the vote
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('votes', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Close the vote
     */
    public function close(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'votes',
            ['status' => 'closed', 'closed_at' => $now, 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Reopen the vote
     */
    public function reopen(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'votes',
            ['status' => 'open', 'closed_at' => null, 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Publish the vote (change from draft to open)
     */
    public function publish(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'votes',
            ['status' => 'open', 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Load questions for this vote
     */
    public function loadQuestions(): self
    {
        $this->questions = Question::findByVoteId($this->id);
        return $this;
    }

    /**
     * Check if the admin token is valid
     */
    public function verifyAdminToken(string $token): bool
    {
        return hash_equals($this->adminToken, $token);
    }

    /**
     * Check if access password is valid
     */
    public function verifyAccessPassword(string $password): bool
    {
        if (!$this->accessPassword) {
            return true;
        }
        return password_verify($password, $this->accessPassword);
    }

    /**
     * Get response count
     */
    public function getResponseCount(): int
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM responses WHERE vote_id = :vote_id",
            ['vote_id' => $this->id]
        );
    }

    /**
     * Convert to array for public JSON output (voter-facing)
     */
    public function toPublicArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'visibility_timing' => $this->visibilityTiming,
            'collect_name' => $this->collectName,
            'allow_edit_own' => $this->allowEditOwn,
            'allow_edit_any' => $this->allowEditAny,
            'randomize_options' => $this->randomizeOptions,
            'access_mode' => $this->accessMode,
            'requires_password' => $this->accessMode === 'password',
            'locale' => $this->locale,
            'questions' => array_map(fn($q) => $q->toArray(), $this->questions),
        ];
    }

    /**
     * Convert to array for admin JSON output
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->publicId,
            'admin_token' => $this->adminToken,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'visibility_timing' => $this->visibilityTiming,
            'collect_name' => $this->collectName,
            'name_visibility' => $this->nameVisibility,
            'allow_edit_own' => $this->allowEditOwn,
            'allow_edit_any' => $this->allowEditAny,
            'randomize_options' => $this->randomizeOptions,
            'access_mode' => $this->accessMode,
            'locale' => $this->locale,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
            'closed_at' => $this->closedAt?->format('c'),
            'response_count' => $this->getResponseCount(),
            'questions' => array_map(fn($q) => $q->toArray(), $this->questions),
        ];
    }
}
