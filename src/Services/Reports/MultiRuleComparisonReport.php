<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;
use App\Services\VotingRulesRegistry;

class MultiRuleComparisonReport extends BaseReport
{
    public function getType(): string
    {
        return 'multi_rule_comparison';
    }

    public function getName(): string
    {
        return 'Multi-Rule Comparison';
    }

    public function getDescription(): string
    {
        return 'Shows winners under multiple voting rules in a comparison table';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties', 'grade', 'star'];
    }

    public function getIcon(): string
    {
        return 'table';
    }

    public function getConfigSchema(): ?array
    {
        // Rules are rendered as a list of checkboxes, populated dynamically based on question type
        return [
            'fields' => [
                [
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => 'Voting Rules to Compare',
                    'required' => true,
                    'options' => [], // Populated dynamically based on question type
                    'dynamicOptions' => 'votingRules', // Signal to frontend to fetch dynamically
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $selectedRules = $config['rules'] ?? VotingRulesRegistry::getDefaultRules($question->type);
        $labels = ProfileBuilder::getOptionLabels($question);

        // Determine profile type and build appropriate profile
        $profileType = VotingRulesRegistry::getProfileType($question->type);
        $allRules = VotingRulesRegistry::getRulesForQuestionType($question->type);

        if ($profileType === 'ranking') {
            $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        } elseif ($profileType === 'grade') {
            $profile = ProfileBuilder::fromGradeResponses($question, $responses);
        } else {
            return ['error' => 'Unsupported question type'];
        }

        // Map index back to option ID
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        // Compute winners for each selected rule
        $results = [];
        foreach ($selectedRules as $ruleKey) {
            if (!isset($allRules[$ruleKey])) {
                continue;
            }

            $method = VotingRulesRegistry::getMethod($ruleKey, $question->type);
            if ($method === null) {
                continue;
            }

            $winnerIndices = $method($profile);
            $winners = [];
            foreach ($winnerIndices as $idx) {
                $optionId = $indexToOptionId[$idx] ?? $idx;
                $winners[] = [
                    'option_id' => $optionId,
                    'option' => $labels[$optionId] ?? $profile->cmap[$idx] ?? "Option {$idx}",
                ];
            }

            $results[] = [
                'rule' => $ruleKey,
                'rule_name' => $allRules[$ruleKey]['name'],
                'winners' => $winners,
                'is_tie' => count($winners) > 1,
            ];
        }

        // Build a summary: which options won under how many rules
        $winCounts = [];
        foreach ($results as $result) {
            foreach ($result['winners'] as $winner) {
                $optionId = $winner['option_id'];
                if (!isset($winCounts[$optionId])) {
                    $winCounts[$optionId] = [
                        'option_id' => $optionId,
                        'option' => $winner['option'],
                        'count' => 0,
                        'rules' => [],
                    ];
                }
                $winCounts[$optionId]['count']++;
                $winCounts[$optionId]['rules'][] = $result['rule_name'];
            }
        }

        // Sort by win count descending
        usort($winCounts, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'results' => $results,
            'summary' => array_values($winCounts),
            'total_rules' => count($results),
            'total_responses' => count($responses),
        ];
    }
}
