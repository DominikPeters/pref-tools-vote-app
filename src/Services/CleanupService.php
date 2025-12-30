<?php

namespace App\Services;

use App\Database;
use App\Models\SiteSetting;

/**
 * Service for cleaning up old data (GDPR compliance)
 * - Anonymizes IP addresses and user agents in action_log after retention period
 * - Anonymizes IP addresses in responses (non-secret ballot) after retention period
 */
class CleanupService
{
    private static ?CleanupService $instance = null;
    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (used in tests)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Check if cleanup should run (10% probability, then 24h check)
     * Called from index.php on each request
     */
    public static function maybeRun(): void
    {
        // 10% probability check first
        if (random_int(1, 100) > 10) {
            return;
        }

        // Check if 24 hours have passed since last cleanup
        $lastCleanup = SiteSetting::get('privacy.last_cleanup', '');
        if (!empty($lastCleanup)) {
            $lastTime = strtotime($lastCleanup);
            $now = time();
            if ($now - $lastTime < 86400) { // 24 hours in seconds
                return;
            }
        }

        // Run cleanup
        self::getInstance()->runCleanup();
    }

    /**
     * Run the cleanup process
     */
    public function runCleanup(): int
    {
        $retentionDays = SiteSetting::getInt('privacy.retention_days', 90);
        if ($retentionDays <= 0) {
            // Retention disabled
            return 0;
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        $totalCleaned = 0;

        // Anonymize old action log entries
        $totalCleaned += $this->cleanActionLog($cutoffDate);

        // Anonymize old response data (IP, user agent)
        $totalCleaned += $this->cleanResponses($cutoffDate);

        // Update last cleanup time
        SiteSetting::set('privacy.last_cleanup', date('Y-m-d H:i:s'));

        // Log the cleanup
        if ($totalCleaned > 0) {
            LogService::getInstance()->log('system.data_cleanup', null, null, null, [
                'records_cleaned' => $totalCleaned,
                'retention_days' => $retentionDays,
            ]);
        }

        return $totalCleaned;
    }

    /**
     * Anonymize old action log entries
     */
    private function cleanActionLog(string $cutoffDate): int
    {
        // Count records to be cleaned
        $countRow = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM action_log
             WHERE created_at < :cutoff AND ip_address IS NOT NULL",
            ['cutoff' => $cutoffDate]
        );
        $count = (int) ($countRow['cnt'] ?? 0);

        if ($count === 0) {
            return 0;
        }

        // Anonymize IP addresses (set to NULL or anonymize)
        $this->db->query(
            "UPDATE action_log
             SET ip_address = NULL
             WHERE created_at < :cutoff AND ip_address IS NOT NULL",
            ['cutoff' => $cutoffDate]
        );

        return $count;
    }

    /**
     * Anonymize old response data
     */
    private function cleanResponses(string $cutoffDate): int
    {
        // Count records to be cleaned
        $countRow = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM responses
             WHERE created_at < :cutoff
             AND (ip_address IS NOT NULL OR user_agent IS NOT NULL)",
            ['cutoff' => $cutoffDate]
        );
        $count = (int) ($countRow['cnt'] ?? 0);

        if ($count === 0) {
            return 0;
        }

        // Anonymize IP addresses and user agents
        $this->db->query(
            "UPDATE responses
             SET ip_address = NULL, user_agent = NULL
             WHERE created_at < :cutoff
             AND (ip_address IS NOT NULL OR user_agent IS NOT NULL)",
            ['cutoff' => $cutoffDate]
        );

        return $count;
    }

    /**
     * Get cleanup statistics
     */
    public function getStats(): array
    {
        $retentionDays = SiteSetting::getInt('privacy.retention_days', 90);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        // Count records that would be cleaned
        $actionLogCount = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM action_log
             WHERE created_at < :cutoff AND ip_address IS NOT NULL",
            ['cutoff' => $cutoffDate]
        );

        $responseCount = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM responses
             WHERE created_at < :cutoff
             AND (ip_address IS NOT NULL OR user_agent IS NOT NULL)",
            ['cutoff' => $cutoffDate]
        );

        return [
            'retention_days' => $retentionDays,
            'last_cleanup' => SiteSetting::get('privacy.last_cleanup', 'Never'),
            'pending_action_logs' => (int) ($actionLogCount['cnt'] ?? 0),
            'pending_responses' => (int) ($responseCount['cnt'] ?? 0),
        ];
    }
}
