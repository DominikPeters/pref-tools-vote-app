<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Question;
use App\Models\Report;
use App\Services\DefaultReportsService;
use App\Services\ReportRegistry;
use App\Services\VotingRulesRegistry;
use App\Services\DistributionRulesRegistry;

class DefaultReportsServiceTest extends TestCase
{
    private function makeQuestion(string $type = 'single_choice'): Question
    {
        $poll = $this->createPoll();
        return $this->createQuestion($poll->id, ['type' => $type]);
    }

    public function test_defaults_are_defined_for_main_question_types(): void
    {
        $ranking = DefaultReportsService::getDefaultsForQuestionType('ranking');
        $types = array_column($ranking, 'report_type');
        $this->assertEquals(['voting_rule_winner', 'borda_scores'], $types);
        $this->assertEquals(['rule' => 'schulze'], $ranking[0]['config']);

        $this->assertNotEmpty(DefaultReportsService::getDefaultsForQuestionType('single_choice'));
        $this->assertNotEmpty(DefaultReportsService::getDefaultsForQuestionType('approval'));
    }

    public function test_text_questions_default_to_text_responses(): void
    {
        foreach (['text_single', 'text_multi'] as $type) {
            $defaults = DefaultReportsService::getDefaultsForQuestionType($type);
            $this->assertEquals(['text_responses'], array_column($defaults, 'report_type'));
        }
    }

    public function test_no_defaults_for_section_headers(): void
    {
        $this->assertSame([], DefaultReportsService::getDefaultsForQuestionType('section_header'));
    }

    /**
     * Every default definition must reference a registered report type that
     * supports its question type, and any configured rule must be valid.
     */
    public function test_all_defaults_are_consistent_with_registries(): void
    {
        foreach (DefaultReportsService::DEFAULTS as $questionType => $defaults) {
            foreach ($defaults as $default) {
                $handler = ReportRegistry::get($default['report_type']);
                $this->assertNotNull($handler, "Unknown report type {$default['report_type']}");
                $this->assertContains(
                    $questionType,
                    $handler->getSupportedQuestionTypes(),
                    "{$default['report_type']} does not support {$questionType}"
                );

                $rule = $default['config']['rule'] ?? null;
                if ($rule !== null && $default['report_type'] === 'voting_rule_winner') {
                    $this->assertArrayHasKey(
                        $rule,
                        VotingRulesRegistry::getRulesForQuestionType($questionType),
                        "Rule {$rule} invalid for {$questionType}"
                    );
                }
                if ($rule !== null && $default['report_type'] === 'distribution_aggregation') {
                    $values = array_column(DistributionRulesRegistry::getRulesAsOptions(), 'value');
                    $this->assertContains($rule, $values);
                }
            }
        }
    }

    public function test_add_defaults_creates_public_reports(): void
    {
        $question = $this->makeQuestion('ranking');
        $created = DefaultReportsService::addDefaultsForQuestion($question);

        $this->assertCount(2, $created);
        $reports = Report::findByQuestionId($question->id);
        $this->assertCount(2, $reports);
        foreach ($reports as $report) {
            $this->assertTrue($report->isPublic);
        }
        $this->assertEquals(['rule' => 'schulze'], $reports[0]->config);
    }

    public function test_add_defaults_skips_existing_types(): void
    {
        $question = $this->makeQuestion('ranking');
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'voting_rule_winner',
            'config' => ['rule' => 'irv'],
        ]);

        $created = DefaultReportsService::addDefaultsForQuestion($question);

        // Only borda_scores is added; the existing winner report is untouched
        $this->assertCount(1, $created);
        $this->assertEquals('borda_scores', $created[0]->reportType);
        $reports = Report::findByQuestionId($question->id);
        $this->assertCount(2, $reports);
        $this->assertEquals(['rule' => 'irv'], $reports[0]->config);
    }

    public function test_is_default_state(): void
    {
        $question = $this->makeQuestion('ranking');
        $this->assertFalse(DefaultReportsService::isDefaultState($question));

        DefaultReportsService::addDefaultsForQuestion($question);
        $this->assertTrue(DefaultReportsService::isDefaultState($question));

        // Changing a config makes the state non-default
        $reports = Report::findByQuestionId($question->id);
        $reports[0]->update(['config' => ['rule' => 'irv']]);
        $this->assertFalse(DefaultReportsService::isDefaultState($question));

        // Restoring it makes the state default again
        $reports[0]->update(['config' => ['rule' => 'schulze']]);
        $this->assertTrue(DefaultReportsService::isDefaultState($question));

        // An extra report makes the state non-default
        $extra = Report::create([
            'question_id' => $question->id,
            'report_type' => 'pairwise_margins',
        ]);
        $this->assertFalse(DefaultReportsService::isDefaultState($question));
        $extra->delete();

        // Order does not affect default-ness
        $reports = Report::findByQuestionId($question->id);
        $reports[0]->update(['position' => 5]);
        $this->assertTrue(DefaultReportsService::isDefaultState($question));

        // Visibility changes are customization and must be preserved
        $reports[0]->update(['is_public' => false]);
        $this->assertFalse(DefaultReportsService::isDefaultState($question));
    }

    public function test_type_change_replaces_untouched_defaults(): void
    {
        $question = $this->makeQuestion('ranking');
        DefaultReportsService::addDefaultsForQuestion($question);

        $question = $question->update(['type' => 'approval']);
        DefaultReportsService::handleQuestionTypeChange($question, 'ranking');

        $types = array_map(fn($r) => $r->reportType, Report::findByQuestionId($question->id));
        sort($types);
        $this->assertEquals(['approval_winner', 'choice_counts'], $types);
    }

    public function test_type_change_keeps_customized_compatible_reports(): void
    {
        $question = $this->makeQuestion('ranking');
        // Customized setup: a response matrix (also supported by approval)
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'response_matrix',
        ]);
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'pairwise_margins',
        ]);

        $question = $question->update(['type' => 'approval']);
        DefaultReportsService::handleQuestionTypeChange($question, 'ranking');

        // pairwise_margins is incompatible with approval and gets deleted;
        // response_matrix survives, so no defaults are added
        $reports = Report::findByQuestionId($question->id);
        $this->assertCount(1, $reports);
        $this->assertEquals('response_matrix', $reports[0]->reportType);
    }

    public function test_type_change_falls_back_to_defaults_when_nothing_survives(): void
    {
        $question = $this->makeQuestion('ranking');
        // Customized setup where nothing is compatible with approval
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'pairwise_margins',
        ]);

        $question = $question->update(['type' => 'approval']);
        DefaultReportsService::handleQuestionTypeChange($question, 'ranking');

        $types = array_map(fn($r) => $r->reportType, Report::findByQuestionId($question->id));
        sort($types);
        $this->assertEquals(['approval_winner', 'choice_counts'], $types);
    }

    public function test_type_change_fallback_preserves_private_visibility(): void
    {
        $question = $this->makeQuestion('ranking');
        DefaultReportsService::addDefaultsForQuestion($question);

        foreach (Report::findByQuestionId($question->id) as $report) {
            $report->update(['is_public' => false]);
        }

        $question = $question->update(['type' => 'approval']);
        DefaultReportsService::handleQuestionTypeChange($question, 'ranking');

        $reports = Report::findByQuestionId($question->id);
        $this->assertCount(2, $reports);
        foreach ($reports as $report) {
            $this->assertFalse($report->isPublic);
        }
    }

    public function test_no_change_when_type_stays_the_same(): void
    {
        $question = $this->makeQuestion('ranking');
        Report::create([
            'question_id' => $question->id,
            'report_type' => 'pairwise_margins',
        ]);

        DefaultReportsService::handleQuestionTypeChange($question, 'ranking');

        $reports = Report::findByQuestionId($question->id);
        $this->assertCount(1, $reports);
        $this->assertEquals('pairwise_margins', $reports[0]->reportType);
    }
}
