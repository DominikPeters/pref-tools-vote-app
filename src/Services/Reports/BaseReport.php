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
            'vote_tallies' => 'Vote Tallies',
            'single_winner' => 'Single-Winner',
            'multi_winner' => 'Multi-Winner',
            'ranking_analysis' => 'Ranking Analysis',
            'rank_aggregation' => 'Rank Aggregation',
            'apportionment' => 'Apportionment',
            'participatory_budgeting' => 'Participatory Budgeting',
            'distribution_aggregation' => 'Distribution Aggregation',
            'data_export' => 'Data & Export',
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
     * Get metadata for the report type catalog
     */
    public function getMetadata(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'category' => $this->getCategory(),
            'supported_question_types' => $this->getSupportedQuestionTypes(),
            'has_config' => $this->hasConfig(),
            'requires_config' => $this->requiresConfig(),
            'config_schema' => $this->getConfigSchema(),
        ];
    }
}
