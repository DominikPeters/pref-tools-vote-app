<?php

namespace App\Models;

use App\Database;
use App\Services\TokenService;

class Poll
{
    public ?int $id = null;
    public string $publicId;
    public string $adminToken;
    public ?int $userId = null;

    public string $title = 'Untitled Poll';
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

    public string $votingMode = 'open'; // open, identified, secret_ballot
    public ?array $accessMethods = null; // ['link'], ['token', 'email'], etc.
    public ?\DateTime $modeLockedAt = null;

    public string $locale = 'en';

    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;
    public ?\DateTime $closedAt = null;

    /** @var Question[] */
    public array $questions = [];

    /**
     * Create a Poll instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $poll = new self();
        $poll->id = (int) $row['id'];
        $poll->publicId = $row['public_id'];
        $poll->adminToken = $row['admin_token'];
        $poll->userId = $row['user_id'] ? (int) $row['user_id'] : null;

        $poll->title = $row['title'];
        $poll->description = $row['description'];

        $poll->status = $row['status'];
        $poll->visibility = $row['visibility'];
        $poll->visibilityTiming = $row['visibility_timing'];
        $poll->collectName = (bool) $row['collect_name'];
        $poll->nameVisibility = $row['name_visibility'];
        $poll->allowEditOwn = (bool) $row['allow_edit_own'];
        $poll->allowEditAny = (bool) $row['allow_edit_any'];
        $poll->randomizeOptions = (bool) $row['randomize_options'];

        $poll->accessMode = $row['access_mode'];
        $poll->accessPassword = $row['access_password'];

        $poll->votingMode = $row['voting_mode'] ?? 'open';
        $poll->accessMethods = isset($row['access_methods']) ? json_decode($row['access_methods'], true) : null;
        $poll->modeLockedAt = isset($row['mode_locked_at']) && $row['mode_locked_at']
            ? new \DateTime($row['mode_locked_at'])
            : null;

        $poll->locale = $row['locale'];

        $poll->createdAt = new \DateTime($row['created_at']);
        $poll->updatedAt = new \DateTime($row['updated_at']);
        $poll->closedAt = $row['closed_at'] ? new \DateTime($row['closed_at']) : null;

        return $poll;
    }

    /**
     * Find a poll by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM polls WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find a poll by public ID
     */
    public static function findByPublicId(string $publicId): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM polls WHERE public_id = :public_id",
            ['public_id' => $publicId]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find polls by user ID
     */
    public static function findByUserId(int $userId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM polls WHERE user_id = :user_id ORDER BY created_at DESC",
            ['user_id' => $userId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Find polls where a user has voted (has a response with user_id)
     */
    public static function findVotedByUserId(int $userId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT DISTINCT p.* FROM polls p
             INNER JOIN responses r ON r.poll_id = p.id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC",
            ['user_id' => $userId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Get all polls (for sysadmin)
     */
    public static function all(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM polls ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Count total polls
     */
    public static function count(): int
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM polls");
    }

    /**
     * Count polls by status
     */
    public static function countByStatus(string $status): int
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM polls WHERE status = :status",
            ['status' => $status]
        );
    }

    /**
     * Create a new poll
     */
    public static function create(array $data, ?int $userId = null): self
    {
        $db = Database::getInstance();

        $publicId = TokenService::generatePublicId();
        $adminToken = TokenService::generateAdminToken();

        // Ensure unique public ID
        while ($db->fetch("SELECT id FROM polls WHERE public_id = :id", ['id' => $publicId])) {
            $publicId = TokenService::generatePublicId();
        }

        $now = date('Y-m-d H:i:s');

        $id = $db->insert('polls', [
            'public_id' => $publicId,
            'admin_token' => $adminToken,
            'user_id' => $userId,
            'title' => $data['title'] ?? 'Untitled Poll',
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
            'voting_mode' => $data['voting_mode'] ?? 'open',
            'access_methods' => isset($data['access_methods'])
                ? json_encode($data['access_methods'])
                : null,
            'locale' => $data['locale'] ?? 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return self::find((int) $id);
    }

    /**
     * Update the poll
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowedFields = [
            'user_id', 'title', 'description', 'status', 'visibility', 'visibility_timing',
            'collect_name', 'name_visibility', 'allow_edit_own', 'allow_edit_any',
            'randomize_options', 'access_mode', 'voting_mode', 'access_methods', 'locale'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                // Convert booleans to integers
                if (in_array($field, ['collect_name', 'allow_edit_own', 'allow_edit_any', 'randomize_options'])) {
                    $value = $value ? 1 : 0;
                }
                // JSON encode arrays
                if ($field === 'access_methods' && is_array($value)) {
                    $value = json_encode($value);
                }
                $updateData[$field] = $value;
            }
        }

        // Handle password separately
        if (isset($data['access_password'])) {
            $updateData['access_password'] = password_hash($data['access_password'], PASSWORD_DEFAULT);
        }

        $db->update('polls', $updateData, 'id = :id', ['id' => $this->id]);

        return self::find($this->id);
    }

    /**
     * Delete the poll
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('polls', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Close the poll
     */
    public function close(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'polls',
            ['status' => 'closed', 'closed_at' => $now, 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Reopen the poll
     */
    public function reopen(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'polls',
            ['status' => 'open', 'closed_at' => null, 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Publish the poll (change from draft to open)
     */
    public function publish(): self
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'polls',
            ['status' => 'open', 'updated_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        return self::find($this->id);
    }

    /**
     * Load questions for this poll
     */
    public function loadQuestions(): self
    {
        $this->questions = Question::findByPollId($this->id);
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
            "SELECT COUNT(*) FROM responses WHERE poll_id = :poll_id",
            ['poll_id' => $this->id]
        );
    }

    /**
     * Check if results are publicly viewable
     */
    public function areResultsViewable(): bool
    {
        if ($this->visibility === 'private') {
            return false;
        }
        if ($this->visibilityTiming === 'during') {
            return true;
        }
        return $this->status === 'closed';
    }

    /**
     * Check if voting is currently available
     */
    public function isVotingOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the voting mode is locked (cannot be changed)
     */
    public function isModeLocked(): bool
    {
        return $this->modeLockedAt !== null || $this->getResponseCount() > 0;
    }

    /**
     * Lock the voting mode (called on first response)
     */
    public function lockMode(): void
    {
        if ($this->modeLockedAt !== null) {
            return;
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->update(
            'polls',
            ['mode_locked_at' => $now],
            'id = :id',
            ['id' => $this->id]
        );

        $this->modeLockedAt = new \DateTime($now);
    }

    /**
     * Check if this poll can collect voter names
     */
    public function canCollectName(): bool
    {
        return $this->votingMode !== 'secret_ballot';
    }

    /**
     * Check if responses can be edited in this poll
     */
    public function canEditResponse(): bool
    {
        if ($this->votingMode === 'secret_ballot') {
            return false;
        }
        return $this->allowEditOwn || $this->allowEditAny;
    }

    /**
     * Check if this poll requires voter identity (token/email/login)
     */
    public function requiresIdentity(): bool
    {
        return in_array($this->votingMode, ['identified', 'secret_ballot']);
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
            'collect_name' => $this->canCollectName() && $this->collectName,
            'allow_edit_own' => $this->canEditResponse() && $this->allowEditOwn,
            'allow_edit_any' => $this->canEditResponse() && $this->allowEditAny,
            'randomize_options' => $this->randomizeOptions,
            'access_mode' => $this->accessMode,
            'voting_mode' => $this->votingMode,
            'results_viewable' => $this->areResultsViewable(),
            'requires_password' => $this->accessMode === 'password',
            'requires_identity' => $this->requiresIdentity(),
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
            'voting_mode' => $this->votingMode,
            'access_methods' => $this->accessMethods,
            'mode_locked' => $this->isModeLocked(),
            'mode_locked_at' => $this->modeLockedAt?->format('c'),
            'locale' => $this->locale,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
            'closed_at' => $this->closedAt?->format('c'),
            'response_count' => $this->getResponseCount(),
            'questions' => array_map(fn($q) => $q->toArray(), $this->questions),
        ];
    }

    /**
     * Convert to array for sysadmin JSON output (no admin_token, includes owner info)
     */
    public function toSysadminArray(): array
    {
        $owner = $this->userId ? User::find($this->userId) : null;

        return [
            'id' => $this->id,
            'public_id' => $this->publicId,
            'user_id' => $this->userId,
            'owner_email' => $owner?->email,
            'title' => $this->title,
            'status' => $this->status,
            'access_mode' => $this->accessMode,
            'voting_mode' => $this->votingMode,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
            'response_count' => $this->getResponseCount(),
        ];
    }
}
