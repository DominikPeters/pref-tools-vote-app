<?php

namespace App\Services;

use App\Database;

class LogService
{
    private static ?LogService $instance = null;
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
     * Log an action
     */
    public function log(
        string $action,
        ?int $pollId = null,
        ?int $userId = null,
        ?int $responseId = null,
        array $data = []
    ): void {
        $this->db->insert('action_log', [
            'action' => $action,
            'poll_id' => $pollId,
            'user_id' => $userId,
            'response_id' => $responseId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'data' => !empty($data) ? json_encode($data) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get logs for a poll
     */
    public function getLogsForPoll(int $pollId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM action_log WHERE poll_id = :poll_id ORDER BY created_at DESC LIMIT :limit",
            ['poll_id' => $pollId, 'limit' => $limit]
        );
    }

    /**
     * Get logs for a user
     */
    public function getLogsForUser(int $userId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM action_log WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit",
            ['user_id' => $userId, 'limit' => $limit]
        );
    }
}
