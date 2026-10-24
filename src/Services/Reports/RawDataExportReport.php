<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\PrefLibExporter;

class RawDataExportReport extends BaseReport
{
    public function getType(): string
    {
        return 'raw_data_export';
    }

    public function getName(): string
    {
        return __('report_type_raw_data_export');
    }

    public function getDescription(): string
    {
        return __('report_download_the_raw_ballots_preflib_pabulib_format_for_analysis_in_external_tools');
    }

    public function getSupportedQuestionTypes(): array
    {
        // All question types supported by PrefLibExporter or PabulibExporter
        return [
            'single_choice',
            'ranking',
            'ranking_truncated',
            'ranking_with_ties',
            'approval',
            'yes_no_abstain',
            'grade',
            'star',
            'participatory_budgeting',
        ];
    }

    public function getIcon(): string
    {
        return 'file-export';
    }

    public function getIntent(): string
    {
        return 'export';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'include_user_options',
                    'type' => 'checkbox',
                    'label' => __('report_include_user_added_other_options'),
                    'required' => false,
                    'default' => true,
                    'dependsOn' => ['field' => 'question.settings.allowOther', 'value' => true],
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        // Check if the question type is supported
        if ($question->type === 'participatory_budgeting') {
            $dataType = 'pb';
        } elseif (PrefLibExporter::isSupported($question)) {
            $dataType = PrefLibExporter::getDataType($question);
        } else {
            return [
                'supported' => false,
                'error' => __('error_this_question_type_is_not_supported_for_raw_data_export'),
                'error_key' => 'error_this_question_type_is_not_supported_for_raw_data_export',
                'error_params' => [],
            ];
        }

        $fileName = 'export.' . $dataType;

        // Return only metadata - actual data is fetched on-demand via separate API
        // to avoid storing large exports in the database cache
        return [
            'supported' => true,
            'data_type' => strtoupper($dataType),
            'file_name' => $fileName,
            'total_responses' => count($responses),
        ];
    }
}
