<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Models\Poll;
use App\Services\ProfileBuilder;

class ResponseMatrixReport extends BaseReport
{
    public function getType(): string
    {
        return 'response_matrix';
    }

    public function getName(): string
    {
        return 'Response Matrix';
    }

    public function getDescription(): string
    {
        return 'Doodle-style table showing each voter\'s choices';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval', 'ranking', 'ranking_truncated', 'ranking_with_ties', 'star', 'grade', 'yes_no_abstain'];
    }

    public function getIcon(): string
    {
        return 'table-cells';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'include_user_options',
                    'type' => 'checkbox',
                    'label' => 'Include user-added "Other" options',
                    'required' => false,
                    'default' => true,
                    'dependsOn' => ['field' => 'question.settings.allowOther', 'value' => true],
                ],
            ],
        ];
    }

    /**
     * Compute needs poll context for privacy settings
     * This method receives additional context via the $config parameter
     */
    public function compute(Question $question, array $responses, ?array $config): array
    {
        $question->loadOptions();
        $excludeUserAdded = !($config['include_user_options'] ?? true);
        $labels = ProfileBuilder::getOptionLabels($question, $excludeUserAdded);

        // Get privacy context from config (injected by controller)
        $isAdmin = $config['is_admin'] ?? false;
        $pollVisibility = $config['poll_visibility'] ?? 'private';
        $showNames = $this->shouldShowNames($isAdmin, $pollVisibility);

        // Build option list (columns), optionally excluding user-added options
        $options = [];
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $options[] = [
                'id' => $option->id,
                'label' => $option->label,
            ];
        }

        // Build voter rows
        $rows = [];
        $voterNumber = 0;
        foreach ($responses as $response) {
            $voterNumber++;
            $answer = $this->getAnswerForQuestion($response, $question->id);

            // Determine voter display name
            $voterLabel = "Voter {$voterNumber}";
            if ($showNames && !empty($response->voterName)) {
                $voterLabel = $response->voterName;
            }

            $row = [
                'voter' => $voterLabel,
                'voter_id' => $response->id,
                'cells' => [],
            ];

            // Process based on question type
            $value = $answer ? $answer->getValue() : null;

            foreach ($question->options as $option) {
                $cell = $this->formatCell($question->type, $option->id, $value, $question);
                $row['cells'][] = $cell;
            }

            $rows[] = $row;
        }

        // Determine grades for grade questions (needed for color scaling)
        $grades = null;
        if ($question->type === 'grade') {
            $grades = ProfileBuilder::getGradesForQuestion($question);
        }

        return [
            'question_type' => $question->type,
            'options' => $options,
            'rows' => $rows,
            'grades' => $grades,
            'total_responses' => count($responses),
            'show_names' => $showNames,
        ];
    }

    /**
     * Determine if voter names should be shown based on privacy settings
     */
    private function shouldShowNames(bool $isAdmin, string $pollVisibility): bool
    {
        // Admin always sees names
        if ($isAdmin) {
            return true;
        }

        // For public view, respect poll visibility setting
        return in_array($pollVisibility, ['names_only', 'full']);
    }

    /**
     * Format a cell value based on question type
     */
    private function formatCell(string $questionType, int $optionId, $value, Question $question): array
    {
        if ($value === null) {
            return ['type' => 'empty', 'display' => '', 'value' => null];
        }

        switch ($questionType) {
            case 'approval':
                // value is array of selected option IDs
                $selected = is_array($value) && in_array($optionId, $value);
                return [
                    'type' => 'check',
                    'display' => $selected ? '✓' : '',
                    'value' => $selected,
                    'class' => $selected ? 'cell-yes' : 'cell-empty',
                ];

            case 'ranking':
            case 'ranking_truncated':
                // value is array of option IDs in ranked order
                if (is_array($value)) {
                    $position = array_search($optionId, $value);
                    if ($position !== false) {
                        return [
                            'type' => 'rank',
                            'display' => (string) ($position + 1),
                            'value' => $position + 1,
                            'class' => 'cell-rank',
                        ];
                    }
                }
                return ['type' => 'empty', 'display' => '', 'value' => null, 'class' => 'cell-empty'];

            case 'ranking_with_ties':
                // value is object { optionId: rank, ... }
                if (is_array($value) && isset($value[$optionId])) {
                    $rank = (int) $value[$optionId];
                    return [
                        'type' => 'rank',
                        'display' => (string) $rank,
                        'value' => $rank,
                        'class' => 'cell-rank',
                    ];
                }
                return ['type' => 'empty', 'display' => '', 'value' => null, 'class' => 'cell-empty'];

            case 'star':
                // value is object { optionId: rating, ... }
                if (is_array($value) && isset($value[$optionId])) {
                    $rating = (int) $value[$optionId];
                    $maxStars = $question->settings['starCount'] ?? 5;
                    return [
                        'type' => 'star',
                        'display' => (string) $rating,
                        'value' => $rating,
                        'max' => $maxStars,
                        'class' => 'cell-star',
                    ];
                }
                return ['type' => 'empty', 'display' => '', 'value' => null, 'class' => 'cell-empty'];

            case 'grade':
                // value is object { optionId: gradeString, ... }
                if (is_array($value) && isset($value[$optionId])) {
                    $grade = (string) $value[$optionId];
                    $grades = ProfileBuilder::getGradesForQuestion($question);
                    $gradeIndex = $this->findGradeIndex($grade, $grades);

                    // Abbreviate grade to max 3 chars
                    $abbreviated = $this->abbreviateGrade($grade);

                    return [
                        'type' => 'grade',
                        'display' => $abbreviated,
                        'full_grade' => $grade,
                        'value' => $grade,
                        'grade_index' => $gradeIndex,
                        'total_grades' => count($grades),
                        'class' => 'cell-grade',
                    ];
                }
                return ['type' => 'empty', 'display' => '', 'value' => null, 'class' => 'cell-empty'];

            case 'yes_no_abstain':
                // value is object { optionId: 'yes'|'no'|'abstain', ... }
                if (is_array($value) && isset($value[$optionId])) {
                    $vote = strtolower((string) $value[$optionId]);
                    $displayMap = [
                        'yes' => 'Y', 'y' => 'Y',
                        'no' => 'N', 'n' => 'N',
                        'abstain' => 'A', 'a' => 'A',
                    ];
                    $classMap = [
                        'yes' => 'cell-yes', 'y' => 'cell-yes',
                        'no' => 'cell-no', 'n' => 'cell-no',
                        'abstain' => 'cell-abstain', 'a' => 'cell-abstain',
                    ];
                    return [
                        'type' => 'yna',
                        'display' => $displayMap[$vote] ?? '?',
                        'value' => $vote,
                        'class' => $classMap[$vote] ?? 'cell-empty',
                    ];
                }
                return ['type' => 'empty', 'display' => '', 'value' => null, 'class' => 'cell-empty'];

            default:
                return ['type' => 'unknown', 'display' => '?', 'value' => null];
        }
    }

    /**
     * Find the index of a grade in the grades array (case-insensitive)
     */
    private function findGradeIndex(string $grade, array $grades): int
    {
        $gradeLower = strtolower($grade);
        foreach ($grades as $index => $g) {
            if (strtolower((string) $g) === $gradeLower) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Abbreviate a grade to max 3 characters
     */
    private function abbreviateGrade(string $grade): string
    {
        $grade = trim($grade);

        // Common abbreviations
        $abbreviations = [
            'excellent' => 'Exc',
            'very good' => 'VG',
            'good' => 'Gd',
            'fair' => 'Fr',
            'poor' => 'Pr',
            'reject' => 'Rej',
            'pass' => 'P',
            'fail' => 'F',
        ];

        $lower = strtolower($grade);
        if (isset($abbreviations[$lower])) {
            return $abbreviations[$lower];
        }

        // If already 3 chars or less, return as-is
        if (mb_strlen($grade) <= 3) {
            return $grade;
        }

        // Otherwise truncate to 3 chars
        return mb_substr($grade, 0, 3);
    }

    /**
     * Get answer for a question from a response
     */
    private function getAnswerForQuestion($response, int $questionId): ?\App\Models\Answer
    {
        if (empty($response->answers)) {
            $response->loadAnswers();
        }

        foreach ($response->answers as $answer) {
            if ($answer->questionId === $questionId) {
                return $answer;
            }
        }

        return null;
    }
}
