<?php

namespace App\Models;

use App\Database;

class Report
{
    public ?int $id = null;
    public int $questionId;
    public string $reportType;
    public ?array $config = null;
    public int $position = 0;
    public bool $isPublic = false;
    public ?array $cachedResult = null;
    public ?\DateTime $computedAt = null;
    public ?\DateTime $createdAt = null;

    /**
     * Create a Report instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $report = new self();
        $report->id = (int) $row['id'];
        $report->questionId = (int) $row['question_id'];
        $report->reportType = $row['report_type'];
        $report->config = $row['config'] ? json_decode($row['config'], true) : null;
        $report->position = (int) $row['position'];
        $report->isPublic = (bool) $row['is_public'];
        $report->cachedResult = $row['cached_result'] ? json_decode($row['cached_result'], true) : null;
        $report->computedAt = $row['computed_at'] ? new \DateTime($row['computed_at']) : null;
        $report->createdAt = new \DateTime($row['created_at']);
        return $report;
    }

    /**
     * Find a report by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM reports WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find reports by question ID
     */
    public static function findByQuestionId(int $questionId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM reports WHERE question_id = :question_id ORDER BY position ASC",
            ['question_id' => $questionId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Find all reports for a poll (via its questions)
     */
    public static function findByPollId(int $pollId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT r.* FROM reports r
             INNER JOIN questions q ON r.question_id = q.id
             WHERE q.poll_id = :poll_id
             ORDER BY q.sort_order ASC, r.position ASC",
            ['poll_id' => $pollId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Find public reports for a poll
     */
    public static function findPublicByPollId(int $pollId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT r.* FROM reports r
             INNER JOIN questions q ON r.question_id = q.id
             WHERE q.poll_id = :poll_id AND r.is_public = 1
             ORDER BY q.sort_order ASC, r.position ASC",
            ['poll_id' => $pollId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Create a new report
     */
    public static function create(array $data): self
    {
        $db = Database::getInstance();

        // Get next position for this question
        $maxPosition = $db->fetchColumn(
            "SELECT MAX(position) FROM reports WHERE question_id = :question_id",
            ['question_id' => $data['question_id']]
        );
        $position = $data['position'] ?? (($maxPosition ?? -1) + 1);

        $id = $db->insert('reports', [
            'question_id' => $data['question_id'],
            'report_type' => $data['report_type'],
            'config' => isset($data['config']) ? json_encode($data['config']) : null,
            'position' => $position,
            'is_public' => ($data['is_public'] ?? false) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find((int) $id);
    }

    /**
     * Update the report
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();
        $updateData = [];

        if (array_key_exists('config', $data)) {
            $updateData['config'] = $data['config'] !== null ? json_encode($data['config']) : null;
        }
        if (array_key_exists('position', $data)) {
            $updateData['position'] = (int) $data['position'];
        }
        if (array_key_exists('is_public', $data)) {
            $updateData['is_public'] = $data['is_public'] ? 1 : 0;
        }

        if (!empty($updateData)) {
            $db->update('reports', $updateData, 'id = :id', ['id' => $this->id]);
        }

        return self::find($this->id);
    }

    /**
     * Delete the report
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('reports', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Update cached result
     */
    public function updateCache(array $result): self
    {
        $db = Database::getInstance();
        $db->update('reports', [
            'cached_result' => json_encode($result),
            'computed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $this->id]);

        $this->cachedResult = $result;
        $this->computedAt = new \DateTime();
        return $this;
    }

    /**
     * Invalidate the cache
     */
    public function invalidateCache(): self
    {
        $db = Database::getInstance();
        $db->update('reports', [
            'cached_result' => null,
            'computed_at' => null,
        ], 'id = :id', ['id' => $this->id]);

        $this->cachedResult = null;
        $this->computedAt = null;
        return $this;
    }

    /**
     * Invalidate cache for all reports in a poll
     */
    public static function invalidateCacheForPoll(int $pollId): void
    {
        $db = Database::getInstance();
        $db->query(
            "UPDATE reports SET cached_result = NULL, computed_at = NULL
             WHERE question_id IN (SELECT id FROM questions WHERE poll_id = :poll_id)",
            ['poll_id' => $pollId]
        );
    }

    /**
     * Convert to array for JSON output (admin view)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->questionId,
            'report_type' => $this->reportType,
            'config' => $this->config,
            'position' => $this->position,
            'is_public' => $this->isPublic,
            'cached_result' => $this->cachedResult,
            'computed_at' => $this->computedAt?->format('c'),
            'created_at' => $this->createdAt?->format('c'),
        ];
    }

    /**
     * Convert to array for public view (excludes sensitive info)
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->questionId,
            'report_type' => $this->reportType,
            'config' => $this->config,
            'position' => $this->position,
            'cached_result' => $this->cachedResult,
        ];
    }
}
