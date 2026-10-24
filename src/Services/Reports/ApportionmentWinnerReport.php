<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ApportionmentProfileBuilder;
use App\Services\ApportionmentRulesRegistry;
use App\Services\LocalizedApportionmentExplanation;

class ApportionmentWinnerReport extends BaseReport
{
    public function getType(): string
    {
        return 'apportionment_winner';
    }

    public function getName(): string
    {
        return __('report_type_apportionment_winner');
    }

    public function getDescription(): string
    {
        return __('report_divides_a_number_of_seats_among_the_options_in_proportion_to_their_votes');
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
                    'label' => __('report_apportionment_method'),
                    'required' => true,
                    'options' => [],
                    'dynamicOptions' => 'apportionmentRules',
                    'default' => 'hamilton',
                ],
                [
                    'name' => 'seats',
                    'type' => 'number',
                    'label' => __('total_seats'),
                    'required' => true,
                    'default' => 10,
                    'min' => 1,
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
        $seats = (int) ($config['seats'] ?? 10);
        $rule = $config['rule'] ?? 'hamilton';
        $excludeUserAdded = !($config['include_user_options'] ?? true);

        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, $responses, $seats, $excludeUserAdded);
        if (array_sum($instance->votes) === 0) {
            return $this->errorResult('error_no_valid_responses_for_this_question');
        }

        try {
            $result = ApportionmentRulesRegistry::compute($rule, $instance, true);
            $explanationData = [
                'votes' => $instance->votes,
                'seats' => $instance->seats,
                'party_names' => $instance->partyNames,
                'detailed_info' => $result['detailed_info'] ?? [],
            ];
            $explanationHtml = \App\i18n\Translator::withLocale('en', fn() => LocalizedApportionmentExplanation::render($explanationData));
        } catch (\Exception $e) {
            return $this->errorResult('error_error_computing_result_detail', ['detail' => $e->getMessage()]);
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
            'rule_name' => ApportionmentRulesRegistry::getRules()[$rule]['name'] ?? $rule,
            'seats' => $seats,
            'allocation' => $allocation,
            'explanation' => $explanationHtml,
            // Cache calculation facts so each viewer can render the explanation in their locale.
            'explanation_data' => $explanationData,
            'ties' => $result['ties'] ?? false,
            'total_votes' => array_sum($instance->votes),
        ];
    }
}
