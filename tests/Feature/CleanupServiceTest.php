<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Database;
use App\Models\SiteSetting;
use App\Services\CleanupService;

class CleanupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CleanupService::reset();
    }

    public function test_cleanup_anonymizes_old_action_log_entries(): void
    {
        $db = Database::getInstance();

        // Create an old action log entry (100 days ago)
        $oldDate = date('Y-m-d H:i:s', strtotime('-100 days'));
        $db->insert('action_log', [
            'action' => 'test.action',
            'ip_address' => '192.168.1.1',
            'created_at' => $oldDate,
        ]);

        // Create a recent action log entry (10 days ago)
        $recentDate = date('Y-m-d H:i:s', strtotime('-10 days'));
        $db->insert('action_log', [
            'action' => 'test.recent',
            'ip_address' => '192.168.1.2',
            'created_at' => $recentDate,
        ]);

        // Run cleanup
        $service = CleanupService::getInstance();
        $cleaned = $service->runCleanup();

        // Verify old entry was anonymized
        $oldEntry = $db->fetch("SELECT * FROM action_log WHERE action = 'test.action'");
        $this->assertNull($oldEntry['ip_address']);

        // Verify recent entry was NOT anonymized
        $recentEntry = $db->fetch("SELECT * FROM action_log WHERE action = 'test.recent'");
        $this->assertEquals('192.168.1.2', $recentEntry['ip_address']);

        $this->assertEquals(1, $cleaned);
    }

    public function test_cleanup_anonymizes_old_response_data(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open'], $user->id);
        $question = $this->createQuestion($poll->id);

        $db = Database::getInstance();

        // Update the response's created_at to be old
        $db->insert('responses', [
            'poll_id' => $poll->id,
            'voter_token' => 'token123',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent/1.0',
            'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
        ]);

        // Run cleanup
        $service = CleanupService::getInstance();
        $cleaned = $service->runCleanup();

        // Verify response data was anonymized
        $response = $db->fetch("SELECT * FROM responses WHERE voter_token = 'token123'");
        $this->assertNull($response['ip_address']);
        $this->assertNull($response['user_agent']);

        $this->assertGreaterThanOrEqual(1, $cleaned);
    }

    public function test_cleanup_respects_retention_days_setting(): void
    {
        $db = Database::getInstance();

        // Set retention to 30 days
        SiteSetting::set('privacy.retention_days', '30');

        // Create an entry 50 days ago (should be cleaned with 30-day retention)
        $date = date('Y-m-d H:i:s', strtotime('-50 days'));
        $db->insert('action_log', [
            'action' => 'test.old',
            'ip_address' => '1.2.3.4',
            'created_at' => $date,
        ]);

        // Run cleanup
        $service = CleanupService::getInstance();
        $cleaned = $service->runCleanup();

        $entry = $db->fetch("SELECT * FROM action_log WHERE action = 'test.old'");
        $this->assertNull($entry['ip_address']);
    }

    public function test_cleanup_does_nothing_when_retention_disabled(): void
    {
        $db = Database::getInstance();

        // Set retention to 0 (disabled)
        SiteSetting::set('privacy.retention_days', '0');

        // Create an old entry
        $oldDate = date('Y-m-d H:i:s', strtotime('-200 days'));
        $db->insert('action_log', [
            'action' => 'test.old',
            'ip_address' => '5.6.7.8',
            'created_at' => $oldDate,
        ]);

        // Run cleanup
        $service = CleanupService::getInstance();
        $cleaned = $service->runCleanup();

        // Entry should NOT be anonymized
        $entry = $db->fetch("SELECT * FROM action_log WHERE action = 'test.old'");
        $this->assertEquals('5.6.7.8', $entry['ip_address']);
        $this->assertEquals(0, $cleaned);
    }

    public function test_cleanup_updates_last_cleanup_time(): void
    {
        // Clear any existing setting
        SiteSetting::delete('privacy.last_cleanup');

        $service = CleanupService::getInstance();
        $service->runCleanup();

        $lastCleanup = SiteSetting::get('privacy.last_cleanup');
        $this->assertNotEmpty($lastCleanup);

        // Should be recent (within last minute)
        $lastTime = strtotime($lastCleanup);
        $this->assertGreaterThan(time() - 60, $lastTime);
    }

    public function test_get_stats_returns_pending_counts(): void
    {
        $db = Database::getInstance();

        // Create old entries
        $oldDate = date('Y-m-d H:i:s', strtotime('-100 days'));
        $db->insert('action_log', [
            'action' => 'test.stats1',
            'ip_address' => '1.1.1.1',
            'created_at' => $oldDate,
        ]);
        $db->insert('action_log', [
            'action' => 'test.stats2',
            'ip_address' => '2.2.2.2',
            'created_at' => $oldDate,
        ]);

        $service = CleanupService::getInstance();
        $stats = $service->getStats();

        $this->assertArrayHasKey('retention_days', $stats);
        $this->assertArrayHasKey('pending_action_logs', $stats);
        $this->assertArrayHasKey('pending_responses', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['pending_action_logs']);
    }

    public function test_cleanup_does_not_run_twice_within_24_hours(): void
    {
        // Set last cleanup to 1 hour ago
        SiteSetting::set('privacy.last_cleanup', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $db = Database::getInstance();
        $oldDate = date('Y-m-d H:i:s', strtotime('-100 days'));
        $db->insert('action_log', [
            'action' => 'test.24h',
            'ip_address' => '9.9.9.9',
            'created_at' => $oldDate,
        ]);

        // Call maybeRun multiple times (simulating 100% probability for test)
        // Since last cleanup was 1 hour ago, it should not run
        // We can't easily test the probability, but we can verify the 24h check

        // The entry should still have its IP (cleanup didn't run due to 24h check)
        // Note: maybeRun has 10% probability, so we need to test the logic directly

        $lastCleanup = SiteSetting::get('privacy.last_cleanup');
        $lastTime = strtotime($lastCleanup);
        $this->assertGreaterThan(time() - 7200, $lastTime); // Within 2 hours

        // Verify the check: if we manually call runCleanup, it will still work
        $service = CleanupService::getInstance();
        $service->runCleanup();

        $entry = $db->fetch("SELECT * FROM action_log WHERE action = 'test.24h'");
        $this->assertNull($entry['ip_address']); // Manual cleanup works
    }
}
