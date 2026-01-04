<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ABCProfileBuilder;
use App\Services\ProfileBuilder;
use App\Services\MultiwinnerRulesRegistry;

class MultiwinnerMultiRuleComparisonReport extends BaseReport
{
    public function getType(): string
    {
        return 'multiwinner_multi_rule_comparison';
    }

    public function getName(): string
    {
        return 'Multi-Winner Multi-Rule Comparison';
    }

    public function getDescription(): string
    {
        return 'Compares winning committees under multiple multi-winner voting rules';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval', 'ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'table';
    }

    public function getCategory(): string
    {
        return 'multi_winner';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rules',
                    'type' => 'checkboxes',
                    'label' => 'Voting Rules to Compare',
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'multiwinnerRules',
                ],
                [
                    'name' => 'committee_size',
                    'type' => 'number',
                    'label' => 'Committee Size',
                    'required' => true,
                    'default' => 2,
                    'min' => 1,
                    'dynamicMax' => 'numOptions',
                ],
                [
                    'name' => 'show_summary',
                    'type' => 'checkbox',
                    'label' => 'Show frequency summary',
                    'required' => true,
                    'default' => true,
                ],
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

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $question->loadOptions();
        $excludeUserAdded = !($config['include_user_options'] ?? true);

        // Count options (excluding user-added if needed) and build index map
        $numOptions = 0;
        $indexToOptionId = [];
        $filteredOptions = [];
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $indexToOptionId[$numOptions] = $option->id;
            $filteredOptions[] = $option;
            $numOptions++;
        }

        $committeeSize = (int) ($config['committee_size'] ?? 1);
        if ($committeeSize < 1 || $committeeSize > $numOptions) {
            return [
                'error' => "Invalid committee size: {$committeeSize}. Must be between 1 and {$numOptions}.",
            ];
        }

        $selectedRules = $config['rules'] ?? [];
        $allRulesMetadata = MultiwinnerRulesRegistry::getRules($question->type === 'approval' ? 'approval' : 'ranking');

        if (empty($selectedRules)) {
            // Default rules if none selected
            foreach ($allRulesMetadata as $key => $rule) {
                if ($rule['default'] ?? false) {
                    $selectedRules[] = $key;
                }
            }
        }

        // Build appropriate profile
        if ($question->type === 'approval') {
            $profile = ABCProfileBuilder::fromApprovalResponses($question, $responses, $excludeUserAdded);
            $optionLabels = ABCProfileBuilder::getOptionLabels($question, $excludeUserAdded);
        } else {
            $profile = ProfileBuilder::fromRankingResponses($question, $responses);
            $optionLabels = ProfileBuilder::getOptionLabels($question);
        }

        $numVoters = ($profile instanceof \AbcVoting\Profile) ? $profile->count() : $profile->numVoters;
        if ($numVoters === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        $results = [];
        $optionWinCounts = []; // How many rules each option appears in

        foreach ($selectedRules as $ruleKey) {
            try {
                // Use resolute=false to detect ties, but take the first for the comparison table
                $committees = MultiwinnerRulesRegistry::compute($ruleKey, $profile, $committeeSize, false);
                if (empty($committees)) continue;

                $isTie = count($committees) > 1;
                $committee = $committees[0];
                $members = [];
                foreach ($committee as $candIdx) {
                    $optionId = $indexToOptionId[$candIdx];
                    $members[] = [
                        'option_id' => $optionId,
                        'option' => $optionLabels[$optionId] ?? "Option {$candIdx}",
                    ];

                    if (!isset($optionWinCounts[$optionId])) {
                        $optionWinCounts[$optionId] = [
                            'option_id' => $optionId,
                            'option' => $optionLabels[$optionId] ?? "Option {$candIdx}",
                            'count' => 0,
                            'rules' => [],
                        ];
                    }
                    $optionWinCounts[$optionId]['count']++;
                    $ruleName = $allRulesMetadata[$ruleKey]['name'] ?? $ruleKey;
                    $optionWinCounts[$optionId]['rules'][] = $ruleName;
                }

                $results[] = [
                    'rule' => $ruleKey,
                    'rule_name' => $allRulesMetadata[$ruleKey]['name'] ?? $ruleKey,
                    'committee' => $members,
                    'is_tie' => $isTie,
                ];
            } catch (\Exception $e) {
                // Skip failed rules in comparison
                continue;
            }
        }

        // Sort summary by frequency
        usort($optionWinCounts, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'results' => $results,
            'summary' => array_values($optionWinCounts),
            'committee_size' => $committeeSize,
            'total_rules' => count($results),
            'total_responses' => $numVoters,
        ];
    }
}