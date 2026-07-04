<?php

namespace App\Services\Reports;

use App\Models\Question;

class TextResponsesReport extends BaseReport
{
    public function getType(): string
    {
        return 'text_responses';
    }

    public function getName(): string
    {
        return 'Text Answers';
    }

    public function getDescription(): string
    {
        return 'Lists all text answers given to this question';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['text_single', 'text_multi'];
    }

    public function getIcon(): string
    {
        return 'file-lines';
    }

    public function getCategory(): string
    {
        return 'vote_tallies';
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $answers = [];
        foreach ($responses as $response) {
            foreach ($response->answers as $answer) {
                if ($answer->questionId !== $question->id) {
                    continue;
                }
                $value = $answer->getValue();
                if (is_string($value) && trim($value) !== '') {
                    $answers[] = $value;
                }
            }
        }

        return [
            'answers' => $answers,
            'total_responses' => count($answers),
        ];
    }
}
