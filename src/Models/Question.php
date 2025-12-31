<?php

namespace App\Models;

use App\Database;

class Question
{
    public ?int $id = null;
    public int $pollId;
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
        'section_header',
    ];

    /**
     * Create a Question instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $question = new self();
        $question->id = (int) $row['id'];
        $question->pollId = (int) $row['poll_id'];
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
     * Find questions by poll ID
     */
    public static function findByPollId(int $pollId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM questions WHERE poll_id = :poll_id ORDER BY sort_order ASC",
            ['poll_id' => $pollId]
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
    public static function create(int $pollId, array $data): self
    {
        $db = Database::getInstance();

        $type = $data['type'] ?? 'single_choice';
        $settings = $data['settings'] ?? null;
        $optionCount = count($data['options'] ?? []);

        // Validate type and settings
        $errors = self::validateTypeSettings($type, $settings, $optionCount);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        // Get next sort order
        $maxOrder = $db->fetchColumn(
            "SELECT MAX(sort_order) FROM questions WHERE poll_id = :poll_id",
            ['poll_id' => $pollId]
        );
        $sortOrder = $data['sort_order'] ?? (($maxOrder ?? -1) + 1);

        $id = $db->insert('questions', [
            'poll_id' => $pollId,
            'sort_order' => $sortOrder,
            'type' => $type,
            'text' => $data['text'] ?? '',
            'description' => $data['description'] ?? null,
            'required' => ($data['required'] ?? true) ? 1 : 0,
            'settings' => $settings !== null ? json_encode($settings) : null,
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

        // Determine effective type and settings after update
        $type = $data['type'] ?? $this->type;
        $settings = array_key_exists('settings', $data) ? $data['settings'] : $this->settings;

        // Get option count (load if needed)
        if (empty($this->options)) {
            $this->loadOptions();
        }
        $optionCount = count($this->options);

        // Validate type and settings
        $errors = self::validateTypeSettings($type, $settings, $optionCount);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

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

        if (array_key_exists('settings', $data)) {
            $updateData['settings'] = $data['settings'] !== null ? json_encode($data['settings']) : null;
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
     * Validate question type and settings
     *
     * @param string $type Question type
     * @param array|null $settings Type-specific settings
     * @param int $optionCount Number of options for this question
     * @return array Array of error messages (empty if valid)
     */
    public static function validateTypeSettings(string $type, ?array $settings, int $optionCount = 0): array
    {
        $errors = [];

        // Validate type is known
        if (!in_array($type, self::TYPES)) {
            $errors[] = "Unknown question type: {$type}";
            return $errors;
        }

        if ($settings === null) {
            return $errors;
        }

        switch ($type) {
            case 'approval':
                $min = $settings['min'] ?? 0;
                $max = $settings['max'] ?? null;

                if (!is_int($min) && !is_numeric($min)) {
                    $errors[] = "Approval min must be a number";
                } else {
                    $min = (int) $min;
                    if ($min < 0) {
                        $errors[] = "Approval min cannot be negative";
                    }
                    if ($optionCount > 0 && $min > $optionCount) {
                        $errors[] = "Approval min ({$min}) cannot exceed number of options ({$optionCount})";
                    }
                }

                if ($max !== null) {
                    if (!is_int($max) && !is_numeric($max)) {
                        $errors[] = "Approval max must be a number";
                    } else {
                        $max = (int) $max;
                        if ($max < 1) {
                            $errors[] = "Approval max must be at least 1";
                        }
                        if ($optionCount > 0 && $max > $optionCount) {
                            // This is okay - we treat it as "all"
                        }
                        if (is_numeric($settings['min'] ?? 0) && $max < (int)($settings['min'] ?? 0)) {
                            $errors[] = "Approval max ({$max}) cannot be less than min ({$min})";
                        }
                    }
                }
                break;

            case 'star':
                $starCount = $settings['starCount'] ?? 5;
                if (!is_int($starCount) && !is_numeric($starCount)) {
                    $errors[] = "Star count must be a number";
                } else {
                    $starCount = (int) $starCount;
                    if ($starCount < 2 || $starCount > 10) {
                        $errors[] = "Star count must be between 2 and 10";
                    }
                }
                break;

            case 'grade':
                if (isset($settings['grades'])) {
                    if (!is_array($settings['grades'])) {
                        $errors[] = "Grades must be an array";
                    } elseif (count($settings['grades']) < 1) {
                        $errors[] = "At least one grade is required";
                    } else {
                        foreach ($settings['grades'] as $grade) {
                            if (!is_string($grade) || trim($grade) === '') {
                                $errors[] = "Each grade must be a non-empty string";
                                break;
                            }
                        }
                    }
                }
                break;

            case 'yes_no_abstain':
                if (isset($settings['allowAbstain']) && !is_bool($settings['allowAbstain'])) {
                    $errors[] = "allowAbstain must be a boolean";
                }
                break;
        }

        return $errors;
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
            'description_html' => $this->description ? markdown($this->description) : null,
            'required' => $this->required,
            'settings' => $this->settings,
            'options' => array_map(fn($o) => $o->toArray(), $this->options),
        ];
    }
}
