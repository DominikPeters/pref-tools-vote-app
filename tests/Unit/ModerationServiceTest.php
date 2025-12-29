<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ModerationService;
use App\Models\SiteSetting;

class ModerationServiceTest extends TestCase
{
    public function test_build_content_string_includes_poll_title(): void
    {
        $data = ['title' => 'My Test Poll'];
        $content = ModerationService::buildContentString($data);

        $this->assertStringContainsString('# My Test Poll', $content);
    }

    public function test_build_content_string_includes_description(): void
    {
        $data = [
            'title' => 'Poll',
            'description' => 'This is the poll description',
        ];
        $content = ModerationService::buildContentString($data);

        $this->assertStringContainsString('This is the poll description', $content);
    }

    public function test_build_content_string_includes_questions(): void
    {
        $data = [
            'title' => 'Poll',
            'questions' => [
                [
                    'text' => 'What is your favorite color?',
                    'description' => 'Choose wisely',
                ],
            ],
        ];
        $content = ModerationService::buildContentString($data);

        $this->assertStringContainsString('What is your favorite color?', $content);
        $this->assertStringContainsString('Choose wisely', $content);
    }

    public function test_build_content_string_includes_options(): void
    {
        $data = [
            'title' => 'Poll',
            'questions' => [
                [
                    'text' => 'Pick one',
                    'options' => [
                        ['label' => 'Option A', 'description' => 'First option'],
                        ['label' => 'Option B'],
                    ],
                ],
            ],
        ];
        $content = ModerationService::buildContentString($data);

        $this->assertStringContainsString('- Option A', $content);
        $this->assertStringContainsString('- Option B', $content);
        $this->assertStringContainsString('First option', $content);
    }

    public function test_build_content_string_handles_empty_data(): void
    {
        $content = ModerationService::buildContentString([]);
        $this->assertEquals('', $content);
    }

    public function test_is_configured_returns_false_when_no_api_key(): void
    {
        // No API key set by default in test database
        $this->assertFalse(ModerationService::isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        SiteSetting::set('api.openai_key', 'sk-test-key-12345');

        $this->assertTrue(ModerationService::isConfigured());
    }

    public function test_is_enabled_returns_false_when_not_configured(): void
    {
        // No API key, so should be disabled
        $this->assertFalse(ModerationService::isEnabled());
    }

    public function test_is_enabled_returns_false_when_configured_but_not_enabled(): void
    {
        SiteSetting::set('api.openai_key', 'sk-test-key');
        SiteSetting::set('moderation.enabled', '0');

        $this->assertFalse(ModerationService::isEnabled());
    }

    public function test_is_enabled_returns_true_when_configured_and_enabled(): void
    {
        SiteSetting::set('api.openai_key', 'sk-test-key');
        SiteSetting::set('moderation.enabled', '1');

        $this->assertTrue(ModerationService::isEnabled());
    }

    public function test_moderate_skips_when_not_enabled(): void
    {
        $result = ModerationService::moderate('test content');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['flagged']);
        $this->assertTrue($result['skipped']);
        $this->assertEquals('Moderation not enabled', $result['reason']);
    }

    public function test_moderate_skips_empty_content(): void
    {
        SiteSetting::set('api.openai_key', 'sk-test-key');
        SiteSetting::set('moderation.enabled', '1');

        $result = ModerationService::moderate('   ');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['flagged']);
        $this->assertTrue($result['skipped']);
        $this->assertEquals('Empty content', $result['reason']);
    }

    public function test_get_thresholds_returns_defaults(): void
    {
        $thresholds = ModerationService::getThresholds();

        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('sexual', $thresholds);
        $this->assertArrayHasKey('harassment', $thresholds);
        $this->assertArrayHasKey('violence', $thresholds);
        $this->assertEquals(0.8, $thresholds['sexual']);
        $this->assertEquals(0.01, $thresholds['sexual/minors']);
    }

    public function test_get_thresholds_uses_custom_values(): void
    {
        SiteSetting::set('moderation.threshold.sexual', '0.5');
        SiteSetting::set('moderation.threshold.harassment', '0.3');

        $thresholds = ModerationService::getThresholds();

        $this->assertEquals(0.5, $thresholds['sexual']);
        $this->assertEquals(0.3, $thresholds['harassment']);
    }

    public function test_get_flagged_message_with_categories(): void
    {
        $result = [
            'flagged_categories' => [
                'harassment' => ['score' => 0.9, 'threshold' => 0.7],
                'hate' => ['score' => 0.8, 'threshold' => 0.7],
            ],
        ];

        $message = ModerationService::getFlaggedMessage($result);

        $this->assertStringContainsString('harassment', $message);
        $this->assertStringContainsString('hate', $message);
        $this->assertStringContainsString('revise', $message);
    }

    public function test_get_flagged_message_without_categories(): void
    {
        $result = ['flagged_categories' => []];

        $message = ModerationService::getFlaggedMessage($result);

        $this->assertStringContainsString('flagged', $message);
    }

    public function test_categories_constant_contains_all_categories(): void
    {
        $categories = ModerationService::CATEGORIES;

        $this->assertContains('sexual', $categories);
        $this->assertContains('sexual/minors', $categories);
        $this->assertContains('harassment', $categories);
        $this->assertContains('harassment/threatening', $categories);
        $this->assertContains('hate', $categories);
        $this->assertContains('hate/threatening', $categories);
        $this->assertContains('violence', $categories);
        $this->assertContains('violence/graphic', $categories);
        $this->assertContains('self-harm', $categories);
    }
}
