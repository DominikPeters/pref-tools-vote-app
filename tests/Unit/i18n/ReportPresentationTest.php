<?php

namespace Tests\Unit\i18n;

use App\i18n\Translator;
use App\Services\ReportResultPresenter;
use PHPUnit\Framework\TestCase;

class ReportPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        Translator::setLocale('en');
    }

    public function test_cached_labels_and_errors_follow_the_viewer_without_changing_data(): void
    {
        Translator::setLocale('de');
        $cached = [
            '_i18n_version' => 1,
            'results' => [['rule' => 'irv', 'rule_name' => 'Stale label', 'winners' => [['option' => 'Instant Runoff (IRV)', 'option_id' => 7]]]],
            'summary' => [['option' => 'User-provided option', 'count' => 21, 'rules' => ['Stale label'], 'rule_ids' => ['irv']]],
        ];
        $french = ReportResultPresenter::localize($cached, 'multi_rule_comparison', 'fr');
        $expected = Translator::translateForLocale('fr', 'rule_instant_runoff_irv');
        $this->assertSame($expected, $french['results'][0]['rule_name']);
        $this->assertSame([$expected], $french['summary'][0]['rules']);
        $this->assertSame('Instant Runoff (IRV)', $french['results'][0]['winners'][0]['option']);
        $this->assertSame(21, $french['summary'][0]['count']);
        $this->assertSame('Stale label', $cached['results'][0]['rule_name']);
        $this->assertArrayNotHasKey('_i18n_version', $french);
        $this->assertSame('de', Translator::getLocale());
    }

    public function test_public_projection_redacts_even_a_legacy_admin_matrix_cache(): void
    {
        $cached = ['show_names' => true, 'rows' => [['voter' => 'Private Person', 'voter_id' => 12, 'cells' => [['selected' => true]]]]];
        $anonymous = ReportResultPresenter::localize($cached, 'response_matrix', 'fr', false);
        $this->assertFalse($anonymous['show_names']);
        $this->assertSame(Translator::translateForLocale('fr', 'report_voter_number', ['number' => 1]), $anonymous['rows'][0]['voter']);
        $this->assertStringNotContainsString('Private Person', json_encode($anonymous));
        $this->assertSame('Private Person', ReportResultPresenter::localize($cached, 'response_matrix', 'en', true)['rows'][0]['voter']);
        $this->assertSame($cached['rows'][0]['cells'], $anonymous['rows'][0]['cells']);
    }

    public function test_error_and_message_descriptors_are_formatted_at_display_time(): void
    {
        $error = ReportResultPresenter::localize([
            'error' => 'Old English error', 'error_key' => 'error_no_valid_responses_for_this_question', 'error_params' => [],
        ], 'multiwinner', 'fr');
        $this->assertSame(Translator::translateForLocale('fr', 'error_no_valid_responses_for_this_question'), $error['error']);
        $this->assertArrayNotHasKey('error_key', $error);
        try {
            Translator::withLocale('fr', static function () { throw new \RuntimeException('scope'); });
        } catch (\RuntimeException $error) {
            $this->assertSame('scope', $error->getMessage());
        }
        $this->assertSame('en', Translator::getLocale());
    }

    public function test_cached_calculation_facts_render_localized_escaped_explanations(): void
    {
        $facts = [
            'votes' => [100, 50], 'seats' => 3, 'party_names' => ['<script>Party</script>', 'B'],
            'detailed_info' => [
                'method' => 'hamilton', 'representatives' => [2, 1], 'lower_quotas' => [2, 1],
                'quotas' => [2.0, 1.0], 'remainders' => [0.0, 0.0], 'at_cutoff' => [], 'ties' => false,
            ],
        ];
        $cached = ['explanation' => 'Stale English HTML', 'explanation_data' => $facts];
        $french = ReportResultPresenter::localize($cached, 'apportionment_winner', 'fr');
        $english = ReportResultPresenter::localize($cached, 'apportionment_winner', 'en');
        $this->assertStringContainsString('2,00', $french['explanation']);
        $this->assertStringContainsString('2.00', $english['explanation']);
        $this->assertStringNotContainsString('<script>', $french['explanation']);
        $this->assertStringContainsString('&lt;script&gt;', $french['explanation']);
        $this->assertArrayNotHasKey('explanation_data', $french);
        $this->assertSame($facts, $cached['explanation_data']);
        $this->assertSame('en', Translator::getLocale());
    }
}
