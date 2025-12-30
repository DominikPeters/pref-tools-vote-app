<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ABCProfileBuilder;
use App\Services\ABCRulesRegistry;

class ABCMultiRuleComparisonReport extends BaseReport
{
    public function getType(): string
    {
        return 'abc_multi_rule_comparison';
    }

    public function getName(): string
    {
        return 'ABC Multi-Rule Comparison';
    }

    public function getDescription(): string
    {
        return 'Compares winning committees under multiple multi-winner voting rules';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval'];
    }

    public function getIcon(): string
    {
        return 'table';
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
                    'dynamicOptions' => 'votingRules',
                ],
                [
                    'name' => 'committee_size',
                    'type' => 'number',
                    'label' => 'Committee Size',
                    'required' => true,
                    'default' => 1,
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
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $question->loadOptions();
        $numOptions = count($question->options);
        
        $committeeSize = (int) ($config['committee_size'] ?? 1);
        if ($committeeSize < 1 || $committeeSize > $numOptions) {
            return [
                'error' => "Invalid committee size: {$committeeSize}. Must be between 1 and {$numOptions}.",
            ];
        }

        $selectedRules = $config['rules'] ?? [];
        if (empty($selectedRules)) {
            // Default rules if none selected
            foreach (ABCRulesRegistry::RULES as $key => $rule) {
                if ($rule['default'] ?? false) {
                    $selectedRules[] = $key;
                }
            }
        }

        $profile = ABCProfileBuilder::fromApprovalResponses($question, $responses);
        if ($profile->count() === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        $optionLabels = ABCProfileBuilder::getOptionLabels($question);
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        $results = [];
        $optionWinCounts = []; // How many rules each option appears in

        foreach ($selectedRules as $ruleKey) {
            try {
                // Use resolute=false to detect ties, but take the first for the comparison table
                $committees = ABCRulesRegistry::compute($ruleKey, $profile, $committeeSize, false);
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
                    $optionWinCounts[$optionId]['rules'][] = ABCRulesRegistry::RULES[$ruleKey]['name'] ?? $ruleKey;
                }

                $results[] = [
                    'rule' => $ruleKey,
                    'rule_name' => ABCRulesRegistry::RULES[$ruleKey]['name'] ?? $ruleKey,
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
            'total_responses' => $profile->count(),
        ];
    }
}
