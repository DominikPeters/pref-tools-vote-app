<?php

namespace App\Services\Reports;

use App\Models\Question;
use App\Services\ProfileBuilder;
use App\Services\SocialWelfareFunctionRegistry;

class RankAggregationReport extends BaseReport
{
    public function getType(): string
    {
        return 'rank_aggregation';
    }

    public function getName(): string
    {
        return __('report_type_rank_aggregation');
    }

    public function getDescription(): string
    {
        return __('report_combines_all_ballots_into_a_consensus_ranking_of_the_options');
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['ranking'];
    }

    public function getIcon(): string
    {
        return 'list-ol';
    }

    public function getCategory(): string
    {
        return 'rank_aggregation';
    }

    public function getIntent(): string
    {
        return 'who_wins';
    }

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'swf',
                    'type' => 'select',
                    'label' => __('report_rank_aggregation_rule'),
                    'required' => true,
                    'options' => [], // Populated dynamically
                    'dynamicOptions' => 'socialWelfareFunctions',
                    'default' => 'kemeny_young',
                ],
            ],
        ];
    }

    public function compute(Question $question, array $responses, ?array $config): array
    {
        $swfKey = $config['swf'] ?? 'kemeny_young';
        $labels = ProfileBuilder::getOptionLabels($question);
        $allSWFs = SocialWelfareFunctionRegistry::getSWFsForQuestionType($question->type);

        // Get the SWF factory from registry
        $swfFactory = SocialWelfareFunctionRegistry::getMethod($swfKey, $question->type);
        if ($swfFactory === null) {
            return $this->errorResult('error_unknown_or_unsupported_social_welfare_function_swfkey', ['swfKey' => $swfKey]);
        }

        // Build ranking profile (complete rankings only for now)
        $profile = ProfileBuilder::fromRankingResponses($question, $responses);

        // Compute rankings (SWFs return an array of Ranking objects)
        $rankingObjects = $swfFactory($profile);

        // Map ranking objects to serializable format
        $question->loadOptions();
        $indexToOptionId = [];
        foreach ($question->options as $index => $option) {
            $indexToOptionId[$index] = $option->id;
        }

        $rankings = [];
        foreach ($rankingObjects as $rankingObj) {
            $indiffList = $rankingObj->toIndiffList();
            $formattedRanking = [];
            foreach ($indiffList as $tier) {
                $formattedTier = [];
                foreach ($tier as $idx) {
                    $optionId = $indexToOptionId[$idx] ?? $idx;
                    $formattedTier[] = [
                        'option_id' => $optionId,
                        'option' => $labels[$optionId] ?? $rankingObj->cmap[$idx] ?? "Option {$idx}",
                    ];
                }
                $formattedRanking[] = $formattedTier;
            }
            $rankings[] = $formattedRanking;
        }

        return [
            'swf' => $swfKey,
            'swf_name' => $allSWFs[$swfKey]['name'] ?? $swfKey,
            'rankings' => $rankings,
            'is_tie' => count($rankings) > 1,
            'total_responses' => count($responses),
        ];
    }
}
