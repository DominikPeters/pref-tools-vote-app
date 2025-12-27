<?php

namespace App\Models;

use App\Database;

class Answer
{
    public ?int $id = null;
    public int $responseId;
    public int $questionId;

    public ?string $valueText = null;
    public ?int $valueChoice = null;
    public ?array $valueJson = null;

    public ?\DateTime $createdAt = null;

    /**
     * Create an Answer instance from a database row
     */
    public static function fromRow(array $row): self
    {
        $answer = new self();
        $answer->id = (int) $row['id'];
        $answer->responseId = (int) $row['response_id'];
        $answer->questionId = (int) $row['question_id'];
        $answer->valueText = $row['value_text'];
        $answer->valueChoice = $row['value_choice'] !== null ? (int) $row['value_choice'] : null;
        $answer->valueJson = $row['value_json'] ? json_decode($row['value_json'], true) : null;
        $answer->createdAt = new \DateTime($row['created_at']);
        return $answer;
    }

    /**
     * Find an answer by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM answers WHERE id = :id", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find answers by response ID
     */
    public static function findByResponseId(int $responseId): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT * FROM answers WHERE response_id = :response_id",
            ['response_id' => $responseId]
        );
        return array_map(fn($row) => self::fromRow($row), $rows);
    }

    /**
     * Create a new answer
     */
    public static function create(int $responseId, int $questionId, mixed $value): self
    {
        $db = Database::getInstance();

        $data = [
            'response_id' => $responseId,
            'question_id' => $questionId,
            'value_text' => null,
            'value_choice' => null,
            'value_json' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Determine how to store the value based on type
        if (is_string($value)) {
            $data['value_text'] = $value;
        } elseif (is_int($value)) {
            $data['value_choice'] = $value;
        } elseif (is_array($value)) {
            $data['value_json'] = json_encode($value);
        }

        $id = $db->insert('answers', $data);

        return self::find((int) $id);
    }

    /**
     * Get the value in its native format
     */
    public function getValue(): mixed
    {
        if ($this->valueText !== null) {
            return $this->valueText;
        }
        if ($this->valueChoice !== null) {
            return $this->valueChoice;
        }
        if ($this->valueJson !== null) {
            return $this->valueJson;
        }
        return null;
    }

    /**
     * Delete the answer
     */
    public function delete(): bool
    {
        $db = Database::getInstance();
        return $db->delete('answers', 'id = :id', ['id' => $this->id]) > 0;
    }

    /**
     * Convert to array for JSON output
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->questionId,
            'value' => $this->getValue(),
        ];
    }
}
