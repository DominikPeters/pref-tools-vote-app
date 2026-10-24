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
        return __('report_type_multiwinner');
    }

    public function getDescription(): string
    {
        return __('report_elects_a_committee_of_several_winners_using_a_multi_winner_voting_rule');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['approval', 'ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'trophy';
    }

    public function getCategory(): string
    {
        return 'multi_winner';
    }

    public function getIntent(): string
    {
        return 'multiple_winners';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => __('voting_rule'),
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'multiwinnerRules',
                    'default' => 'equal-shares',
                ],
                [
                    'name' => 'committee_size',
                    'type' => 'number',
                    'label' => __('committee_size'),
                    'required' => true,
                    'default' => 2,
                    'min' => 1,
                    'dynamicMax' => 'numOptions',
                ],
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
        $question->loadOptions();
        $excludeUserAdded = !($config['include_user_options'] ?? true);

        // Count options (excluding user-added if needed)
        $numOptions = 0;
        foreach ($question->options as $option) {
            if ($excludeUserAdded && ($option->features['isUserAdded'] ?? false)) {
                continue;
            }
            $numOptions++;
        }

        $committeeSize = (int) ($config['committee_size'] ?? 1);
        if ($committeeSize < 1 || $committeeSize > $numOptions) {
            return $this->errorResult('error_invalid_committee_size_committeesize_must_be_between_1_and_numoptions', ['committeeSize' => $committeeSize, 'numOptions' => $numOptions]);
        }

        $rule = $config['rule'] ?? ($question->type === 'approval' ? 'equal-shares' : 'stv_meek');

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
            return $this->errorResult('error_no_valid_responses_for_this_question');
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
            return $this->errorResult('error_error_computing_result_detail', ['detail' => $e->getMessage()]);
        }

        if (empty($committees)) {
            return $this->errorResult('error_no_winning_committee_found');
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

        $ruleName = MultiwinnerRulesRegistry::getRules('approval')[$rule]['name']
            ?? MultiwinnerRulesRegistry::getRules('ranking')[$rule]['name']
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
