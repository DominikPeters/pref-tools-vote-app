<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Report;

/**
 * Default ("recommended") reports per question type.
 *
 * These are added automatically when a question is created, so that the
 * results page shows sensible analyses without any configuration. The sets
 * are deliberately small; the full catalog remains available via the
 * "Add Analysis" wizard.
 */
class DefaultReportsService
{
    /**
     * Default report sets, keyed by question type.
     * Each entry: ['report_type' => string, 'config' => ?array]
     * Question types without an entry (free text, section headers) get no reports.
     */
    public const DEFAULTS = [
        'single_choice' => [
            ['report_type' => 'choice_counts', 'config' => null],
        ],
        'approval' => [
            ['report_type' => 'choice_counts', 'config' => null],
            ['report_type' => 'approval_winner', 'config' => null],
        ],
        'ranking' => [
            ['report_type' => 'voting_rule_winner', 'config' => ['rule' => 'schulze']],
            ['report_type' => 'borda_scores', 'config' => null],
        ],
        'ranking_truncated' => [
            ['report_type' => 'voting_rule_winner', 'config' => ['rule' => 'schulze']],
        ],
        'ranking_with_ties' => [
            ['report_type' => 'voting_rule_winner', 'config' => ['rule' => 'schulze']],
        ],
        'grade' => [
            ['report_type' => 'majority_judgment', 'config' => null],
        ],
        'star' => [
            ['report_type' => 'voting_rule_winner', 'config' => ['rule' => 'score_sum']],
            ['report_type' => 'score_distribution', 'config' => null],
        ],
        'yes_no_abstain' => [
            ['report_type' => 'yna_counts', 'config' => null],
        ],
        'participatory_budgeting' => [
            ['report_type' => 'pb_winner', 'config' => null],
        ],
        'distribution' => [
            ['report_type' => 'distribution_aggregation', 'config' => ['rule' => 'mean']],
        ],
        'text_single' => [
            ['report_type' => 'text_responses', 'config' => null],
        ],
        'text_multi' => [
            ['report_type' => 'text_responses', 'config' => null],
        ],
    ];

    /**
     * Get the default report definitions for a question type.
     *
     * @return array<int, array{report_type: string, config: ?array}>
     */
    public static function getDefaultsForQuestionType(string $questionType): array
    {
        return self::DEFAULTS[$questionType] ?? [];
    }

    /**
     * Create the default reports for a question. Skips report types the
     * question already has, so this can also back-fill a partial set.
     *
     * @return Report[] The reports that were created
     */
    public static function addDefaultsForQuestion(Question $question, bool $isPublic = true): array
    {
        $existingTypes = array_map(
            fn(Report $r) => $r->reportType,
            Report::findByQuestionId($question->id)
        );

        $created = [];
        foreach (self::getDefaultsForQuestionType($question->type) as $default) {
            if (in_array($default['report_type'], $existingTypes, true)) {
                continue;
            }
            $created[] = Report::create([
                'question_id' => $question->id,
                'report_type' => $default['report_type'],
                'config' => $default['config'],
                'is_public' => $isPublic,
            ]);
        }
        return $created;
    }

    /**
     * Check whether a question's reports are exactly the untouched default
     * set for the given question type. Order is ignored; visibility is not.
     */
    public static function isDefaultState(Question $question, ?string $questionType = null): bool
    {
        $reports = Report::findByQuestionId($question->id);
        $current = array_map(
            fn(Report $r) => self::signature($r->reportType, $r->config, $r->isPublic),
            $reports
        );
        $expected = array_map(
            fn(array $d) => self::signature(
                $d['report_type'],
                $d['config'],
                $d['is_public'] ?? true
            ),
            self::getDefaultsForQuestionType($questionType ?? $question->type)
        );
        sort($current);
        sort($expected);
        return $current === $expected;
    }

    /**
     * Adjust a question's reports after its type changed:
     * - If the reports were the untouched defaults of the old type,
     *   replace them with the new type's defaults.
     * - Otherwise, delete reports incompatible with the new type; if
     *   nothing survives, fall back to the new type's defaults.
     */
    public static function handleQuestionTypeChange(Question $question, string $oldType): void
    {
        if ($question->type === $oldType) {
            return;
        }

        if (self::isDefaultState($question, $oldType)) {
            foreach (Report::findByQuestionId($question->id) as $report) {
                $report->delete();
            }
            self::addDefaultsForQuestion($question);
            return;
        }

        $remaining = 0;
        $reports = Report::findByQuestionId($question->id);
        $fallbackIsPublic = !array_filter($reports, fn(Report $report) => !$report->isPublic);

        foreach ($reports as $report) {
            $handler = ReportRegistry::get($report->reportType);
            if (!$handler || !in_array($question->type, $handler->getSupportedQuestionTypes(), true)) {
                $report->delete();
            } else {
                // Results computed for the old type are stale
                $report->invalidateCache();
                $remaining++;
            }
        }

        if ($remaining === 0) {
            self::addDefaultsForQuestion($question, $fallbackIsPublic);
        }
    }

    /**
     * Canonical string for comparing a report against a default definition.
     */
    private static function signature(string $reportType, ?array $config, bool $isPublic): string
    {
        if ($config !== null) {
            ksort($config);
        }
        return $reportType . '|' . json_encode($config) . '|' . ($isPublic ? 'public' : 'private');
    }
}
