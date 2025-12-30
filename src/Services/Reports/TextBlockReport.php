<?php

namespace App\Services\Reports;

use App\Models\Question;

class TextBlockReport extends BaseReport
{
    public function getType(): string
    {
        return 'text_block';
    }

    public function getName(): string
    {
        return 'Text Block';
    }

    public function getDescription(): string
    {
        return 'Add custom text or notes to narrate results (supports Markdown)';
    }

    public function getSupportedQuestionTypes(): array
    {
        // Available for all question types
        return [
            'single_choice',
            'approval',
            'ranking',
            'star',
            'grade',
            'yes_no_abstain',
            'text_single',
            'text_multi',
        ];
    }

    public function getIcon(): string
    {
        return 'file-lines';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'content',
                    'type' => 'textarea',
                    'label' => 'Content (Markdown supported)',
                    'required' => true,
                    'placeholder' => 'Enter your text here...\n\nYou can use **bold**, *italic*, and other Markdown formatting.',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $markdown = $config['content'] ?? '';

        if (empty(trim($markdown))) {
            return ['html' => ''];
        }

        // Convert markdown to HTML using Parsedown
        require_once __DIR__ . '/../../../lib/Parsedown/Parsedown.php';
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $html = $parsedown->text($markdown);

        return [
            'html' => $html,
        ];
    }
}
