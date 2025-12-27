<?php

namespace App\Models;

use App\Database;

class Question
{
    public ?int $id = null;
    public int $voteId;
    public int $sortOrder = 0;

    public string $type; // text_single, text_multi, single_choice, approval, ranking, etc.
    public string $text;
    public ?string $description = null;
    public bool $required = true;

    public ?array $settings = null;
    public ?string $visibility = null;

    public ?\DateTime $createdAt = null;

    /** @var Option[] */
    public array $options = [];

    // Valid question types
    public const TYPES = [
        'text_single',
        'text_multi',
        'single_choice',
        'approval',
        'ranking',
        'ranking_truncated',
        'ranking_with_ties',
        'utility',
        'star',
        'grade',
        'yes_no_abstain',
    ];

    /**
     * Create a Question instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $question = new self();
        $question->id = (int) $row['id'];
        $question->voteId = (int) $row['vote_id'];
        $question->sortOrder = (int) $row['sort_order'];
        $question->type = $row['type'];
        $question->text = $row['text'];
        $question->description = $row['description'];
        $question->required = (bool) $row['required'];
        $question->settings = $row['settings'] ? json_decode($row['settings'], true) : null;
        $question->visibility = $row['visibility'];
        $question->createdAt = new \DateTime($row['created_at']);
        return $question;
    }

    /**
     * Find a question by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM questions WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find questions by vote ID
     */
    public static function findByVoteId(int $voteId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM questions WHERE vote_id = :vote_id ORDER BY sort_order ASC",
            ['vote_id' => $voteId]
        );

        $questions = array_map(fn($row) => self::fromRow($row), $rows);

        // Load options for each question
        foreach ($questions as $question) {
            $question->loadOptions();
        }

        return $questions;
    }

    /**
     * Create a new question
     */
    public static function create(int $voteId, array $data): self
    {
        $db = Database::getInstance();

        // Get next sort order
        $maxOrder = $db->fetchColumn(
            "SELECT MAX(sort_order) FROM questions WHERE vote_id = :vote_id",
            ['vote_id' => $voteId]
        );
        $sortOrder = $data['sort_order'] ?? (($maxOrder ?? -1) + 1);

        $id = $db->insert('questions', [
            'vote_id' => $voteId,
            'sort_order' => $sortOrder,
            'type' => $data['type'],
            'text' => $data['text'] ?? '',
            'description' => $data['description'] ?? null,
            'required' => ($data['required'] ?? true) ? 1 : 0,
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'visibility' => $data['visibility'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $question = self::find((int) $id);

        // Create options if provided
        if (!empty($data['options'])) {
            foreach ($data['options'] as $index => $optionData) {
                Option::create($question->id, [
                    'label' => $optionData['label'] ?? '',
                    'description' => $optionData['description'] ?? null,
                    'sort_order' => $optionData['sort_order'] ?? $index,
                ]);
            }
            $question->loadOptions();
        }

        return $question;
    }

    /**
     * Update the question
     */
    public function update(array $data): self
    {
        $db = Database::getInstance();

        $updateData = [];

        $allowedFields = ['sort_order', 'type', 'text', 'description', 'required', 'visibility'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($field === 'required') {
                    $value = $value ? 1 : 0;
                }
                $updateData[$field] = $value;
            }
        }

        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }

        if (!empty($updateData)) {
            $db->update('questions', $updateData, 'id = :id', ['id' => $this->id]);
        }

        return self::find($this->id);
    }

    /**
     * Delete the question
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('questions', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Load options for this question
     */
    public function loadOptions(): self
    {
        $this->options = Option::findByQuestionId($this->id);
        return $this;
    }

    /**
     * Check if this question type requires options
     */
    public function requiresOptions(): bool
    {
        return in_array($this->type, [
            'single_choice', 'approval', 'ranking', 'ranking_truncated',
            'ranking_with_ties', 'utility', 'star', 'grade', 'yes_no_abstain'
        ]);
    }

    /**
     * Convert to array for JSON output
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sortOrder,
            'type' => $this->type,
            'text' => $this->text,
            'description' => $this->description,
            'required' => $this->required,
            'settings' => $this->settings,
            'options' => array_map(fn($o) => $o->toArray(), $this->options),
        ];
    }
}
