<?php

namespace App\Models;

use App\Database;

class Option
{
    public ?int $id = null;
    public int $questionId;
    public int $sortOrder = 0;

    public string $label;
    public ?string $description = null;
    public ?array $features = null;

    public ?\DateTime $createdAt = null;

    /**
     * Create an Option instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $option = new self();
        $option->id = (int) $row['id'];
        $option->questionId = (int) $row['question_id'];
        $option->sortOrder = (int) $row['sort_order'];
        $option->label = $row['label'];
        $option->description = $row['description'];
        $option->features = isset($row['features']) ? json_decode($row['features'], true) : null;
        $option->createdAt = new \DateTime($row['created_at']);
        return $option;
    }

    /**
     * Find an option by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM options WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find options by question ID
     */
    public static function findByQuestionId(int $questionId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM options WHERE question_id = :question_id ORDER BY sort_order ASC",
            ['question_id' => $questionId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Create a new option
     */
    public static function create(int $questionId, array $data): self
    {
        $db = Database::getInstance();

        // Get next sort order if not provided
        if (!isset($data['sort_order'])) {
            $maxOrder = $db->fetchColumn(
                "SELECT MAX(sort_order) FROM options WHERE question_id = :question_id",
                ['question_id' => $questionId]
            );
            $data['sort_order'] = ($maxOrder ?? -1) + 1;
        }

        $id = $db->insert('options', [
            'question_id' => $questionId,
            'sort_order' => $data['sort_order'],
            'label' => $data['label'] ?? '',
            'description' => $data['description'] ?? null,
            'features' => isset($data['features']) ? json_encode($data['features']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find((int) $id);
    }

    /**
     * Update the option
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();

        $updateData = [];

        if (array_key_exists('sort_order', $data)) {
            $updateData['sort_order'] = $data['sort_order'];
        }
        if (array_key_exists('label', $data)) {
            $updateData['label'] = $data['label'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (array_key_exists('features', $data)) {
            $updateData['features'] = $data['features'] !== null ? json_encode($data['features']) : null;
        }

        if (!empty($updateData)) {
            $db->update('options', $updateData, 'id = :id', ['id' => $this->id]);
        }

        return self::find($this->id);
    }

    /**
     * Delete the option
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('options', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Convert to array for JSON output
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'sort_order' => $this->sortOrder,
            'label' => $this->label,
            'description' => $this->description,
            'description_html' => $this->description ? markdown($this->description) : null,
        ];

        if ($this->features !== null) {
            $data['features'] = $this->features;
        }

        return $data;
    }
}
