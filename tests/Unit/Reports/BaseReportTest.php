<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\Reports\BaseReport;
use App\Services\ReportRegistry;

class BaseReportTest extends TestCase
{
    public function test_get_categories_returns_all_categories(): void
    {
        $categories = BaseReport::getCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('vote_tallies', $categories);
        $this->assertArrayHasKey('single_winner', $categories);
        $this->assertArrayHasKey('multi_winner', $categories);
        $this->assertArrayHasKey('ranking_analysis', $categories);
        $this->assertArrayHasKey('rank_aggregation', $categories);
        $this->assertArrayHasKey('apportionment', $categories);
        $this->assertArrayHasKey('participatory_budgeting', $categories);
        $this->assertArrayHasKey('data_export', $categories);

        // Check display labels
        $this->assertEquals('Vote Tallies', $categories['vote_tallies']);
        $this->assertEquals('Single-Winner', $categories['single_winner']);
        $this->assertEquals('Multi-Winner', $categories['multi_winner']);
        $this->assertEquals('Ranking Analysis', $categories['ranking_analysis']);
        $this->assertEquals('Rank Aggregation', $categories['rank_aggregation']);
        $this->assertEquals('Apportionment', $categories['apportionment']);
        $this->assertEquals('Participatory Budgeting', $categories['participatory_budgeting']);
        $this->assertEquals('Data & Export', $categories['data_export']);
    }

    public function test_all_registered_reports_have_valid_category(): void
    {
        $validCategories = array_keys(BaseReport::getCategories());
        $allTypes = ReportRegistry::all();

        foreach ($allTypes as $type) {
            $this->assertArrayHasKey('category', $type, "Report {$type['type']} missing category");
            $this->assertContains(
                $type['category'],
                $validCategories,
                "Report {$type['type']} has invalid category: {$type['category']}"
            );
        }
    }

    public function test_category_assignments_are_correct(): void
    {
        $expectedCategories = [
            'choice_counts' => 'vote_tallies',
            'approval_winner' => 'vote_tallies',
            'borda_scores' => 'vote_tallies',
            'yna_counts' => 'vote_tallies',
            'voting_rule_winner' => 'single_winner',
            'multi_rule_comparison' => 'single_winner',
            'majority_judgment' => 'single_winner',
            'multiwinner' => 'multi_winner',
            'multiwinner_multi_rule_comparison' => 'multi_winner',
            'pairwise_margins' => 'ranking_analysis',
            'condorcet_winner' => 'ranking_analysis',
            'rank_aggregation' => 'rank_aggregation',
            'multi_swf_comparison' => 'rank_aggregation',
            'apportionment_winner' => 'apportionment',
            'apportionment_multi_rule_comparison' => 'apportionment',
            'pb_winner' => 'participatory_budgeting',
            'response_matrix' => 'data_export',
            'raw_data_export' => 'data_export',
            'text_block' => 'data_export',
        ];

        foreach ($expectedCategories as $type => $expectedCategory) {
            $handler = ReportRegistry::get($type);
            $this->assertNotNull($handler, "Report handler for {$type} not found");
            $this->assertEquals(
                $expectedCategory,
                $handler->getCategory(),
                "Report {$type} has wrong category: expected {$expectedCategory}, got {$handler->getCategory()}"
            );
        }
    }
}
