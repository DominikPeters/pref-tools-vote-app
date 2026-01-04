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
        return 'Rank Aggregation';
    }

    public function getDescription(): string
    {
        return 'Aggregates votes into one or more rankings using a Rank Aggregation Rule';
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

    public function getConfigSchema(): ?array
    {
        return [
            'fields' => [
                [
                    'name' => 'swf',
                    'type' => 'select',
                    'label' => 'Rank Aggregation Rule',
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
            return [
                'error' => "Unknown or unsupported Social Welfare Function: {$swfKey}",
            ];
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
