<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ABCProfileBuilder;
use App\Services\ProfileBuilder;
use App\Services\MultiwinnerRulesRegistry;

class MultiwinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'multiwinner';
    }

    public function getName(): string
    {
        return 'Multi-Winner Voting Rule Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the winning committee under a selected multi-winner voting rule (ABC for approval, STV for rankings)';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval', 'ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'trophy';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => 'Voting Rule',
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'multiwinnerRules',
                    'default' => 'equal-shares',
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

        $rule = $config['rule'] ?? ($question->type === 'approval' ? 'equal-shares' : 'stv_meek');

        // Build appropriate profile
        if ($question->type === 'approval') {
            $profile = ABCProfileBuilder::fromApprovalResponses($question, $responses);
            $optionLabels = ABCProfileBuilder::getOptionLabels($question);
        } else {
            $profile = ProfileBuilder::fromRankingResponses($question, $responses);
            $optionLabels = ProfileBuilder::getOptionLabels($question);
        }

        $numVoters = ($profile instanceof \AbcVoting\Profile) ? $profile->count() : $profile->numVoters;
        if ($numVoters === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        // Compute committees
        try {
            $hasExplanation = MultiwinnerRulesRegistry::hasExplanation($rule);
            
            if ($hasExplanation) {
                // To get both ties AND an explanation, we need two calls because the library
                // only provides detailed_info for resolute (single-path) executions.
                
                // 1. Get all winners (irresolute)
                $committees = MultiwinnerRulesRegistry::compute($rule, $profile, $committeeSize, false);
                
                // 2. Get a detailed path for the explanation (resolute)
                $detailedResults = MultiwinnerRulesRegistry::compute($rule, $profile, $committeeSize, true, true);
                
                $explanationHtml = '';
                if (!empty($detailedResults)) {
                    // Generate explanation based on one winning committee's path
                    $explanationHtml = \AbcVoting\Explainer::explain($profile, $detailedResults[0]);
                }
            } else {
                $committees = MultiwinnerRulesRegistry::compute($rule, $profile, $committeeSize, false);
                $explanationHtml = null;
            }
        } catch (\Exception $e) {
            return ['error' => 'Error computing result: ' . $e->getMessage()];
        }

        if (empty($committees)) {
            return ['error' => 'No winning committee found.'];
        }

        // Map indices back to option labels
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        $formattedCommittees = [];
        foreach ($committees as $committee) {
            $members = [];
            foreach ($committee as $candIdx) {
                $optionId = $indexToOptionId[$candIdx];
                $members[] = [
                    'option_id' => $optionId,
                    'option' => $optionLabels[$optionId] ?? "Option {$candIdx}",
                ];
            }
            $formattedCommittees[] = $members;
        }

        $ruleName = MultiwinnerRulesRegistry::ABC_RULES[$rule]['name'] 
            ?? MultiwinnerRulesRegistry::PREF_MULTIWINNER_RULES[$rule]['name'] 
            ?? $rule;

        return [
            'rule' => $rule,
            'rule_name' => $ruleName,
            'committee_size' => $committeeSize,
            'committees' => $formattedCommittees,
            'explanation' => $explanationHtml,
            'is_tie' => count($formattedCommittees) > 1,
            'total_responses' => $numVoters,
        ];
    }
}