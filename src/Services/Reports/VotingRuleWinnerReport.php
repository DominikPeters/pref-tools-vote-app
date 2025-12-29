<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;
use PrefVoting\ScoringMethods;
use PrefVoting\C1Methods;
use PrefVoting\MarginBasedMethods;
use PrefVoting\IterativeMethods;

class VotingRuleWinnerReport extends BaseReport
{
    // Available voting rules
    public const RULES = [
        'plurality' => 'Plurality',
        'borda' => 'Borda Count',
        'copeland' => 'Copeland',
        'schulze' => 'Schulze (Beat Path)',
        'ranked_pairs' => 'Ranked Pairs',
        'irv' => 'Instant Runoff (IRV)',
    ];

    public function getType(): string
    {
        return 'voting_rule_winner';
    }

    public function getName(): string
    {
        return 'Voting Rule Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the winner under a selected voting rule';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking', 'ranking_truncated', 'ranking_with_ties'];
    }

    public function getIcon(): string
    {
        return 'trophy';
    }

    public function getConfigSchema(): ?array
    {
        $options = [];
        foreach (self::RULES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => 'Voting Rule',
                    'required' => true,
                    'options' => $options,
                    'default' => 'schulze',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $rule = $config['rule'] ?? 'schulze';
        $profile = ProfileBuilder::fromRankingResponses($question, $responses);
        $labels = ProfileBuilder::getOptionLabels($question);

        // Get the voting method
        $method = $this->getVotingMethod($rule);
        if ($method === null) {
            return [
                'error' => "Unknown voting rule: {$rule}",
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
            'rule_name' => self::RULES[$rule] ?? $rule,
            'winners' => $winners,
            'is_tie' => count($winners) > 1,
            'total_responses' => count($responses),
        ];
    }

    private function getVotingMethod(string $rule): ?callable
    {
        return match ($rule) {
            'plurality' => ScoringMethods::plurality(),
            'borda' => ScoringMethods::borda(),
            'copeland' => C1Methods::copeland(),
            'schulze' => MarginBasedMethods::beatPath(),
            'ranked_pairs' => MarginBasedMethods::rankedPairs(),
            'irv' => IterativeMethods::instantRunoff(),
            default => null,
        };
    }
}
