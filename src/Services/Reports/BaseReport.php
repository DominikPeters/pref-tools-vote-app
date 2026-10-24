<?php

namespace App\Services\Reports;

use App\Models\Question;

abstract class BaseReport
{
    /**
     * Get the report type identifier
     */
    abstract public function getType(): string;

    /**
     * Get the human-readable name
     */
    abstract public function getName(): string;

    /**
     * Get the description
     */
    abstract public function getDescription(): string;

    /**
     * Get the question types this report supports
     * @return string[]
     */
    abstract public function getSupportedQuestionTypes(): array;

    /**
     * Compute the report data
     *
     * @param Question $question The question to analyze
     * @param array $responses Array of Response objects with loaded answers
     * @param array|null $config Report-specific configuration
     * @return array The computed result data for frontend rendering
     */
    abstract public function compute(Question $question, array $responses, ?array $config): array;

    /**
     * Get the icon identifier for the report
     */
    public function getIcon(): string
    {
        return 'chart-bar';
    }

    /**
     * Get the category identifier for grouping in the UI
     */
    public function getCategory(): string
    {
        return 'data_export'; // Default category
    }

    /**
     * Get all available categories with their display labels and order
     */
    public static function getCategories(): array
    {
        return [
            'vote_tallies' => __('report_vote_tallies'),
            'single_winner' => __('report_single_winner'),
            'multi_winner' => __('report_multi_winner'),
            'ranking_analysis' => __('report_ranking_analysis'),
            'rank_aggregation' => __('report_type_rank_aggregation'),
            'apportionment' => __('report_apportionment'),
            'participatory_budgeting' => __('type_participatory_budgeting'),
            'distribution_aggregation' => __('report_type_distribution_aggregation'),
            'data_export' => __('report_data_export'),
        ];
    }

    /**
     * Get the intent identifier for the "What do you want to show?" wizard
     */
    public function getIntent(): string
    {
        return 'how_voted';
    }

    /**
     * Get all wizard intents with display labels and descriptions, in order
     */
    public static function getIntents(): array
    {
        return [
            'who_wins' => [
                'label' => __('report_who_wins'),
                'description' => __('report_determine_the_winning_option_using_a_voting_method'),
            ],
            'how_voted' => [
                'label' => __('report_how_did_people_vote'),
                'description' => __('report_tallies_tables_and_visualizations_of_the_votes'),
            ],
            'multiple_winners' => [
                'label' => __('report_multiple_winners'),
                'description' => __('report_elect_a_committee_or_divide_seats_among_the_options'),
            ],
            'compare_methods' => [
                'label' => __('report_compare_voting_methods'),
                'description' => __('report_see_how_the_outcome_differs_across_voting_rules'),
            ],
            'export' => [
                'label' => __('report_export_the_data'),
                'description' => __('report_download_the_raw_ballots_for_external_analysis'),
            ],
            'text' => [
                'label' => __('report_add_explanatory_text'),
                'description' => __('report_write_notes_shown_alongside_the_results'),
            ],
        ];
    }

    /**
     * Get the configuration schema for the UI
     * Returns null if no configuration is needed
     *
     * @return array|null Schema format: { fields: [{ name, type, label, required?, options?, default? }] }
     */
    public function getConfigSchema(): ?array
    {
        return null;
    }

    /**
     * Check if this report type has any configuration options
     */
    public function hasConfig(): bool
    {
        $schema = $this->getConfigSchema();
        return $schema !== null && !empty($schema['fields']);
    }

    /**
     * Check if this report type requires configuration before creation
     * (i.e., has any required fields that must be set)
     */
    public function requiresConfig(): bool
    {
        $schema = $this->getConfigSchema();
        if ($schema === null || empty($schema['fields'])) {
            return false;
        }

        foreach ($schema['fields'] as $field) {
            if (!empty($field['required'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep error identities and parameters in the shared cache for later translation.
     */
    protected function errorResult(string $key, array $params = []): array
    {
        return [
            'error' => \App\i18n\Translator::translateForLocale('en', $key, $params),
            'error_key' => $key,
            'error_params' => $params,
        ];
    }

    /** Get metadata for the report type catalog. */
    public function getMetadata(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'category' => $this->getCategory(),
            'intent' => $this->getIntent(),
            'supported_question_types' => $this->getSupportedQuestionTypes(),
            'has_config' => $this->hasConfig(),
            'requires_config' => $this->requiresConfig(),
            'config_schema' => $this->getConfigSchema(),
        ];
    }
}
