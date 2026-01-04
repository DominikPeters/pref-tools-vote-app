<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ApportionmentProfileBuilder;
use App\Services\ApportionmentRulesRegistry;
use Apportionment\Explainer;

class ApportionmentWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'apportionment_winner';
    }

    public function getName(): string
    {
        return 'Apportionment Rule Winner';
    }

    public function getDescription(): string
    {
        return 'Shows the seat allocation under a selected apportionment method';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['single_choice'];
    }

    public function getIcon(): string
    {
        return 'calculator';
    }

    public function getCategory(): string
    {
        return 'apportionment';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'rule',
                    'type' => 'select',
                    'label' => 'Apportionment Method',
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'apportionmentRules',
                    'default' => 'hamilton',
                ],
                [
                    'name' => 'seats',
                    'type' => 'number',
                    'label' => 'Total Seats',
                    'required' => true,
                    'default' => 10,
                    'min' => 1,
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
        $seats = (int) ($config['seats'] ?? 10);
        $rule = $config['rule'] ?? 'hamilton';
        $excludeUserAdded = !($config['include_user_options'] ?? true);

        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, $responses, $seats, $excludeUserAdded);
        if (array_sum($instance->votes) === 0) {
            return ['error' => 'No valid responses for this question.'];
        }

        try {
            $result = ApportionmentRulesRegistry::compute($rule, $instance, true);
            $explanationHtml = Explainer::explain($instance, $result);
        } catch (\Exception $e) {
            return ['error' => 'Error computing result: ' . $e->getMessage()];
        }

        $allocation = [];
        foreach ($instance->votes as $i => $votes) {
            $allocation[] = [
                'option' => $instance->partyNames[$i],
                'votes' => $votes,
                'seats' => $result['representatives'][$i],
            ];
        }

        return [
            'rule' => $rule,
            'rule_name' => ApportionmentRulesRegistry::RULES[$rule]['name'] ?? $rule,
            'seats' => $seats,
            'allocation' => $allocation,
            'explanation' => $explanationHtml,
            'ties' => $result['ties'] ?? false,
            'total_votes' => array_sum($instance->votes),
        ];
    }
}
