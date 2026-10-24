<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;
use App\Services\VotingRulesRegistry;

class VotingRuleWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'voting_rule_winner';
    }

    public function getName(): string
    {
        return __('report_type_voting_rule_winner');
    }

    public function getDescription(): string
    {
        return __('report_the_winning_option_according_to_a_voting_rule_of_your_choice');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties', 'grade', 'star'];
    }

    public function getIcon(): string
    {
        return 'trophy';
    }

    public function getCategory(): string
    {
        return 'single_winner';
    }

    public function getIntent(): string
    {
        return 'who_wins';
    }

    public function getConfigSchema(): ?array
    {
        // Options populated dynamically based on question type in frontend
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => __('voting_rule'),
                    'required' => true,
                    'options' => [], // Populated dynamically
                    'dynamicOptions' => 'votingRules',
                    'default' => 'schulze',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $rule = $config['rule'] ?? 'schulze';
        $labels = ProfileBuilder::getOptionLabels($question);
        $allRules = VotingRulesRegistry::getRulesForQuestionType($question->type);

        // Get the voting method from registry
        $method = VotingRulesRegistry::getMethod($rule, $question->type);
        if ($method === null) {
            return $this->errorResult('error_unknown_or_unsupported_voting_rule_rule', ['rule' => $rule]);
        }

        // Build appropriate profile
        $profileType = VotingRulesRegistry::getProfileType($question->type);
        if ($profileType === 'ranking') {
            $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        } elseif ($profileType === 'grade') {
            $profile = ProfileBuilder::fromGradeResponses($question, $responses);
        } else {
            return $this->errorResult('error_unsupported_question_type');
        }

        // No votes yet: voting methods cannot run on an empty profile
        if ($profile->numVoters === 0) {
            return [
                'rule' => $rule,
                'rule_name' => $allRules[$rule]['name'] ?? $rule,
                'winners' => [],
                'is_tie' => false,
                'total_responses' => 0,
            ];
        }

        // Compute winners
        $winnerIndices = $method($profile);

        // Map indices back to option IDs
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        $winners = [];
        foreach ($winnerIndices as $idx) {
            $optionId = $indexToOptionId[$idx] ?? $idx;
            $winners[] = [
                'option_id' => $optionId,
                'option' => $labels[$optionId] ?? $profile->cmap[$idx] ?? "Option {$idx}",
            ];
        }

        return [
            'rule' => $rule,
            'rule_name' => $allRules[$rule]['name'] ?? $rule,
            'winners' => $winners,
            'is_tie' => count($winners) > 1,
            'total_responses' => count($responses),
        ];
    }
}
