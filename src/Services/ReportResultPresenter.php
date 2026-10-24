<?php

namespace App\Services;

use App\i18n\Translator;

/** Project language-dependent labels without mutating shared computed results. */
final class ReportResultPresenter
{
    public static function localize(?array $result, string $type, ?string $locale = null, bool $showNames = true): ?array
    {
        if ($result === null) return null;
        return Translator::withLocale($locale ?? Translator::getLocale(), static function () use ($result, $type, $showNames) {
            $rules = match ($type) {
                'voting_rule_winner', 'multi_rule_comparison' => VotingRulesRegistry::getRulesForQuestionType('ranking') + VotingRulesRegistry::getRulesForQuestionType('grade'),
                'multiwinner', 'multiwinner_multi_rule_comparison' => MultiwinnerRulesRegistry::getRules('approval') + MultiwinnerRulesRegistry::getRules('ranking'),
                'apportionment_winner', 'apportionment_multi_rule_comparison' => ApportionmentRulesRegistry::getRules(),
                'distribution_aggregation', 'distribution_multi_rule_comparison' => DistributionRulesRegistry::getRules(),
                'pb_winner' => PBRulesRegistry::getRules(),
                default => [],
            };
            $swfs = in_array($type, ['rank_aggregation', 'multi_swf_comparison'], true)
                ? SocialWelfareFunctionRegistry::getSWFsForQuestionType('ranking') : [];
            $present = static function (array $node) use (&$present, $rules, $swfs): array {
                foreach ($node as $key => $value) if (is_array($value)) $node[$key] = $present($value);
                if (isset($node['rule_name'], $node['rule'], $rules[$node['rule']])) {
                    $node['rule_name'] = $rules[$node['rule']]['name'];
                }
                if (isset($node['swf_name'], $node['swf'], $swfs[$node['swf']])) {
                    $node['swf_name'] = $swfs[$node['swf']]['name'];
                }
                if (isset($node['error_key'])) {
                    $node['error'] = Translator::translate($node['error_key'], $node['error_params'] ?? []);
                    unset($node['error_key'], $node['error_params']);
                }
                if (isset($node['message_key'])) {
                    $node['message'] = Translator::translate($node['message_key'], $node['message_params'] ?? []);
                    unset($node['message_key'], $node['message_params']);
                }
                if (isset($node['rule_ids'])) {
                    $node['rules'] = array_map(static fn($id) => $rules[$id]['name'] ?? $id, $node['rule_ids']);
                    unset($node['rule_ids']);
                }
                if (isset($node['comparison_key'])) {
                    $node['comparison'] = Translator::translate($node['comparison_key'], $node['comparison_params'] ?? []);
                    unset($node['comparison_key'], $node['comparison_params'], $node['comparison_counts']);
                }
                return $node;
            };
            $result = $present($result);
            if ($type === 'response_matrix' && isset($result['rows'])) {
                $cachedNames = $result['show_names'] ?? false;
                foreach ($result['rows'] as $index => &$row) {
                    if (!$showNames || !($row['has_voter_name'] ?? $cachedNames)) {
                        $row['voter'] = Translator::translate('report_voter_number', ['number' => $row['voter_number'] ?? $index + 1]);
                    }
                    unset($row['has_voter_name'], $row['voter_number']);
                }
                unset($row);
                $result['show_names'] = $showNames;
            }
            if (isset($result['explanation_data'])) {
                $result['explanation'] = LocalizedApportionmentExplanation::render($result['explanation_data']);
                unset($result['explanation_data']);
            }
            unset($result['_i18n_version']);
            return $result;
        });
    }
}
