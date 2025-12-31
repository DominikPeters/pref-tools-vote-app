<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\SiteSetting;
use App\Controllers\PageController;

class DemoPollTest extends TestCase
{
    /**
     * Test that /demo shows error when no demo poll is configured
     */
    public function test_demo_shows_error_when_not_configured(): void
    {
        // Ensure no demo poll is set
        SiteSetting::set('demo.poll_id', '');

        $controller = new PageController();

        ob_start();
        $controller->demo([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Not Configured', $output);
    }

    /**
     * Test that /demo shows error when configured poll doesn't exist
     */
    public function test_demo_shows_error_when_poll_not_found(): void
    {
        // Set a non-existent poll ID
        SiteSetting::set('demo.poll_id', 'nonexistent123');

        $controller = new PageController();

        ob_start();
        $controller->demo([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Not Available', $output);
    }

    /**
     * Test that /demo renders poll when properly configured
     */
    public function test_demo_renders_poll_when_configured(): void
    {
        // Create a poll and set it as demo
        $poll = $this->createPoll([
            'title' => 'Demo Test Poll',
            'status' => 'open',
        ]);
        $this->createQuestion($poll->id);

        SiteSetting::set('demo.poll_id', $poll->publicId);

        $controller = new PageController();

        ob_start();
        $controller->demo([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Test Poll', $output);
    }

    /**
     * Test that /demo/results shows error when no demo poll is configured
     */
    public function test_demo_results_shows_error_when_not_configured(): void
    {
        SiteSetting::set('demo.poll_id', '');

        $controller = new PageController();

        ob_start();
        $controller->demoResults([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Not Configured', $output);
    }

    /**
     * Test that /demo/results shows error when configured poll doesn't exist
     */
    public function test_demo_results_shows_error_when_poll_not_found(): void
    {
        SiteSetting::set('demo.poll_id', 'nonexistent123');

        $controller = new PageController();

        ob_start();
        $controller->demoResults([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Not Available', $output);
    }

    /**
     * Test that /demo/results renders results when properly configured
     */
    public function test_demo_results_renders_when_configured(): void
    {
        // Create a poll with public visibility and set it as demo
        $poll = $this->createPoll([
            'title' => 'Demo Results Poll',
            'status' => 'open',
            'visibility' => 'anonymous', // Results visible
        ]);
        $this->createQuestion($poll->id);

        SiteSetting::set('demo.poll_id', $poll->publicId);

        $controller = new PageController();

        ob_start();
        $controller->demoResults([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Demo Results Poll', $output);
    }

    /**
     * Test that demo poll setting can be updated via SiteSetting
     */
    public function test_demo_poll_setting_can_be_updated(): void
    {
        $poll1 = $this->createPoll(['title' => 'Poll 1']);
        $poll2 = $this->createPoll(['title' => 'Poll 2']);

        SiteSetting::set('demo.poll_id', $poll1->publicId);
        $this->assertEquals($poll1->publicId, SiteSetting::get('demo.poll_id'));

        SiteSetting::set('demo.poll_id', $poll2->publicId);
        $this->assertEquals($poll2->publicId, SiteSetting::get('demo.poll_id'));
    }

    /**
     * Test that demo poll default is empty string
     */
    public function test_demo_poll_default_is_empty(): void
    {
        // Fresh database should have empty demo.poll_id
        $this->assertEquals('', SiteSetting::get('demo.poll_id'));
    }
}
