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
        return __('report_type_multi_rule_comparison');
    }

    public function getDescription(): string
    {
        return __('report_do_different_voting_rules_agree_on_the_winner_compare_them_side_by_side');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties', 'grade', 'star'];
    }

    public function getIcon(): string
    {
        return 'table';
    }

    public function getCategory(): string
    {
        return 'single_winner';
    }

    public function getIntent(): string
    {
        return 'compare_methods';
    }

    public function getConfigSchema(): ?array
    {
        // Rules are rendered as a list of checkboxes, populated dynamically based on question type
        return [
            'fields' => [
                [
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => __('report_voting_rules_to_compare'),
                    'required' => true,
                    'options' => [], // Populated dynamically based on question type
                    'dynamicOptions' => 'votingRules', // Signal to frontend to fetch dynamically
                ],
                [
                    'name' => 'show_summary',
                    'type' => 'checkbox',
                    'label' => __('report_show_win_count_summary'),
                    'required' => true,
                    'default' => true,
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
            return $this->errorResult('error_unsupported_question_type');
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
                        'rule_ids' => [],
                    ];
                }
                $winCounts[$optionId]['count']++;
                $winCounts[$optionId]['rules'][] = $result['rule_name'];
                $winCounts[$optionId]['rule_ids'][] = $result['rule'];
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
