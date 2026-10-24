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
        return __('report_type_text_block');
    }

    public function getDescription(): string
    {
        return __('report_add_custom_text_or_notes_to_narrate_results_supports_markdown');
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

    public function getIntent(): string
    {
        return 'text';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'content',
                    'type' => 'textarea',
                    'label' => __('report_content_markdown_supported'),
                    'required' => true,
                    'placeholder' => __('report_enter_your_text_here_n_nyou_can_use_bold_italic_and_other_markdown_formatting'),
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
