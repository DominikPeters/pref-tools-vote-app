<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ABCProfileBuilder;
use App\Services\ABCRulesRegistry;

class ABCWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'abc_winner';
    }

    public function getName(): string
    {
        return 'ABC Voting Rule Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the winning committee under a selected multi-winner voting rule';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval'];
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
                    'dynamicOptions' => 'votingRules',
                    'default' => 'equal-shares',
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

        $rule = $config['rule'] ?? 'equal-shares';

        $profile = ABCProfileBuilder::fromApprovalResponses($question, $responses);
        if ($profile->count() === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        // Compute committees
        try {
            $hasExplanation = ABCRulesRegistry::hasExplanation($rule);
            
            if ($hasExplanation) {
                // To get both ties AND an explanation, we need two calls because the library
                // only provides detailed_info for resolute (single-path) executions.
                
                // 1. Get all winners (irresolute)
                $committees = ABCRulesRegistry::compute($rule, $profile, $committeeSize, false);
                
                // 2. Get a detailed path for the explanation (resolute)
                $detailedResults = ABCRulesRegistry::compute($rule, $profile, $committeeSize, true, true);
                
                $explanationHtml = '';
                if (!empty($detailedResults)) {
                    // Generate explanation based on one winning committee's path
                    $explanationHtml = \AbcVoting\Explainer::explain($profile, $detailedResults[0]);
                }
            } else {
                $committees = ABCRulesRegistry::compute($rule, $profile, $committeeSize, false);
                $explanationHtml = null;
            }
        } catch (\Exception $e) {
            return ['error' => 'Error computing result: ' . $e->getMessage()];
        }

        if (empty($committees)) {
            return ['error' => 'No winning committee found.'];
        }

        // Map indices back to option labels
        $optionLabels = ABCProfileBuilder::getOptionLabels($question);
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

        return [
            'rule' => $rule,
            'rule_name' => ABCRulesRegistry::RULES[$rule]['name'] ?? $rule,
            'committee_size' => $committeeSize,
            'committees' => $formattedCommittees,
            'explanation' => $explanationHtml,
            'is_tie' => count($formattedCommittees) > 1,
            'total_responses' => $profile->count(),
        ];
    }
}
