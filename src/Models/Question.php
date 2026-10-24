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
        'participatory_budgeting',
        'distribution',
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
                    'features' => $optionData['features'] ?? null,
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
            'single_choice', 'approval', 'participatory_budgeting', 'distribution', 'ranking', 'ranking_truncated',
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
            $errors[] = __('unknown_question_type', ['type' => $type]);
            return $errors;
        }

        if ($settings === null) {
            return $errors;
        }

        switch ($type) {
            case 'single_choice':
                // allowOther is optional boolean
                if (isset($settings['allowOther']) && !is_bool($settings['allowOther'])) {
                    $errors[] = __('validation_allowother_must_be_a_boolean');
                }
                break;

            case 'approval':
                $min = $settings['min'] ?? 0;
                $max = $settings['max'] ?? null;

                if (!is_int($min) && !is_numeric($min)) {
                    $errors[] = __('validation_approval_min_must_be_a_number');
                } else {
                    $min = (int) $min;
                    if ($min < 0) {
                        $errors[] = __('validation_approval_min_cannot_be_negative');
                    }
                    if ($optionCount > 0 && $min > $optionCount) {
                        $errors[] = __('validation_approval_min_min_cannot_exceed_number_of_options_optioncount', ['min' => $min, 'optionCount' => $optionCount]);
                    }
                }

                if ($max !== null) {
                    if (!is_int($max) && !is_numeric($max)) {
                        $errors[] = __('validation_approval_max_must_be_a_number');
                    } else {
                        $max = (int) $max;
                        if ($max < 1) {
                            $errors[] = __('validation_approval_max_must_be_at_least_1');
                        }
                        if ($optionCount > 0 && $max > $optionCount) {
                            // This is okay - we treat it as "all"
                        }
                        if (is_numeric($settings['min'] ?? 0) && $max < (int)($settings['min'] ?? 0)) {
                            $errors[] = __('validation_approval_max_max_cannot_be_less_than_min_min', ['max' => $max, 'min' => $min]);
                        }
                    }
                }

                // allowOther is optional boolean
                if (isset($settings['allowOther']) && !is_bool($settings['allowOther'])) {
                    $errors[] = __('validation_allowother_must_be_a_boolean');
                }
                break;

            case 'participatory_budgeting':
                $min = $settings['min'] ?? 0;
                $max = $settings['max'] ?? null;

                if (!is_int($min) && !is_numeric($min)) {
                    $errors[] = __('validation_participatory_budgeting_min_must_be_a_number');
                } else {
                    $min = (int) $min;
                    if ($min < 0) {
                        $errors[] = __('validation_participatory_budgeting_min_cannot_be_negative');
                    }
                    if ($optionCount > 0 && $min > $optionCount) {
                        $errors[] = __('validation_participatory_budgeting_min_min_cannot_exceed_number_of_options_optioncount', ['min' => $min, 'optionCount' => $optionCount]);
                    }
                }

                if ($max !== null) {
                    if (!is_int($max) && !is_numeric($max)) {
                        $errors[] = __('validation_participatory_budgeting_max_must_be_a_number');
                    } else {
                        $max = (int) $max;
                        if ($max < 1) {
                            $errors[] = __('validation_participatory_budgeting_max_must_be_at_least_1');
                        }
                        if ($optionCount > 0 && $max > $optionCount) {
                            // This is okay - we treat it as "all"
                        }
                        if (is_numeric($settings['min'] ?? 0) && $max < (int)($settings['min'] ?? 0)) {
                            $errors[] = __('validation_participatory_budgeting_max_max_cannot_be_less_than_min_min', ['max' => $max, 'min' => $min]);
                        }
                    }
                }
                // Currency is optional string, no validation needed
                break;

            case 'star':
                $starCount = $settings['starCount'] ?? 5;
                if (!is_int($starCount) && !is_numeric($starCount)) {
                    $errors[] = __('validation_star_count_must_be_a_number');
                } else {
                    $starCount = (int) $starCount;
                    if ($starCount < 2 || $starCount > 10) {
                        $errors[] = __('validation_star_count_must_be_between_2_and_10');
                    }
                }
                break;

            case 'grade':
                if (isset($settings['grades'])) {
                    if (!is_array($settings['grades'])) {
                        $errors[] = __('validation_grades_must_be_an_array');
                    } elseif (count($settings['grades']) < 1) {
                        $errors[] = __('validation_at_least_one_grade_is_required');
                    } else {
                        foreach ($settings['grades'] as $grade) {
                            if (!is_string($grade) || trim($grade) === '') {
                                $errors[] = __('validation_each_grade_must_be_a_non_empty_string');
                                break;
                            }
                        }
                    }
                }
                break;

            case 'yes_no_abstain':
                if (isset($settings['allowAbstain']) && !is_bool($settings['allowAbstain'])) {
                    $errors[] = __('validation_allowabstain_must_be_a_boolean');
                }
                break;

            case 'distribution':
                $budget = $settings['budget'] ?? 100;
                if (!is_int($budget) && !is_numeric($budget)) {
                    $errors[] = __('validation_distribution_budget_must_be_a_number');
                } else {
                    $budget = (int) $budget;
                    if ($budget < 1) {
                        $errors[] = __('validation_distribution_budget_must_be_at_least_1');
                    }
                }

                $maxPerOption = $settings['maxPerOption'] ?? null;
                if ($maxPerOption !== null) {
                    if (!is_int($maxPerOption) && !is_numeric($maxPerOption)) {
                        $errors[] = __('validation_distribution_maxperoption_must_be_a_number');
                    } else {
                        $maxPerOption = (int) $maxPerOption;
                        if ($maxPerOption < 1) {
                            $errors[] = __('validation_distribution_maxperoption_must_be_at_least_1');
                        }
                        if (is_numeric($settings['budget'] ?? 100) && $maxPerOption > (int)($settings['budget'] ?? 100)) {
                            // This is okay - we treat it as "all" (no limit)
                        }
                    }
                }

                $minOptions = $settings['minOptions'] ?? 1;
                if (!is_int($minOptions) && !is_numeric($minOptions)) {
                    $errors[] = __('validation_distribution_minoptions_must_be_a_number');
                } else {
                    $minOptions = (int) $minOptions;
                    if ($minOptions < 1) {
                        $errors[] = __('validation_distribution_minoptions_must_be_at_least_1');
                    }
                    if ($optionCount > 0 && $minOptions > $optionCount) {
                        $errors[] = __('validation_distribution_minoptions_minoptions_cannot_exceed_number_of_options_optioncount', ['minOptions' => $minOptions, 'optionCount' => $optionCount]);
                    }
                }

                if (isset($settings['requireAll']) && !is_bool($settings['requireAll'])) {
                    $errors[] = __('validation_distribution_requireall_must_be_a_boolean');
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
