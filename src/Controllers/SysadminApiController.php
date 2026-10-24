<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Models\User;
use App\Models\Poll;
use App\Models\SiteSetting;
use App\Services\LogService;
use App\Services\MailService;

class SysadminApiController
{
    /**
     * Verify sysadmin access for API requests
     */
    private function requireSysadmin(): ?array
    {
        $user = Auth::getInstance()->user();

        if (!$user) {
            return ['error' => __('error_authentication_required'), 'status' => 401];
        }

        if (!$user->isSysadmin()) {
            return ['error' => __('error_sysadmin_access_required'), 'status' => 403];
        }

        return null;
    }

    /**
     * GET /api/sysadmin/stats - Get system statistics
     */
    public function stats(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $db = Database::getInstance();

        return [
            'users' => [
                'total' => User::count(),
                'sysadmins' => (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM users WHERE role = :role",
                    ['role' => User::ROLE_SYSADMIN]
                ),
            ],
            'polls' => [
                'total' => Poll::count(),
                'draft' => Poll::countByStatus('draft'),
                'open' => Poll::countByStatus('open'),
                'closed' => Poll::countByStatus('closed'),
            ],
            'responses' => [
                'total' => (int) $db->fetchColumn("SELECT COUNT(*) FROM responses"),
            ],
            'logs' => [
                'total' => LogService::getInstance()->countLogs(),
            ],
        ];
    }

    /**
     * GET /api/sysadmin/users - List all users
     */
    public function listUsers(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = (int) ($_GET['offset'] ?? 0);

        $users = User::all($limit, $offset);

        return [
            'users' => array_map(fn($u) => $u->toArray(), $users),
            'total' => User::count(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * PUT /api/sysadmin/users/:userId - Update user (role)
     */
    public function updateUser(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $userId = (int) $params['userId'];
        $user = User::find($userId);

        if (!$user) {
            return ['error' => __('error_user_not_found'), 'status' => 404];
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Update role if provided
        if (isset($input['role'])) {
            $newRole = $input['role'];

            if (!in_array($newRole, [User::ROLE_USER, User::ROLE_SYSADMIN])) {
                return ['error' => __('error_invalid_role'), 'status' => 400];
            }

            // Prevent removing sysadmin from self
            $currentUser = Auth::getInstance()->user();
            if ($user->id === $currentUser->id && $newRole !== User::ROLE_SYSADMIN) {
                return ['error' => __('error_cannot_remove_sysadmin_role_from_yourself'), 'status' => 400];
            }

            $user->updateRole($newRole);

            LogService::getInstance()->log('sysadmin.user.role_updated', null, $user->id, null, [
                'new_role' => $newRole,
                'by_user_id' => $currentUser->id,
            ]);
        }

        return ['user' => $user->toArray()];
    }

    /**
     * DELETE /api/sysadmin/users/:userId - Delete user
     */
    public function deleteUser(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $userId = (int) $params['userId'];
        $user = User::find($userId);

        if (!$user) {
            return ['error' => __('error_user_not_found'), 'status' => 404];
        }

        // Prevent deleting self
        $currentUser = Auth::getInstance()->user();
        if ($user->id === $currentUser->id) {
            return ['error' => __('error_cannot_delete_yourself'), 'status' => 400];
        }

        LogService::getInstance()->log('sysadmin.user.deleted', null, $user->id, null, [
            'email' => $user->email,
            'by_user_id' => $currentUser->id,
        ]);

        $user->delete();

        return ['ok' => true, 'success' => true];
    }

    /**
     * GET /api/sysadmin/polls - List all polls
     */
    public function listPolls(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = (int) ($_GET['offset'] ?? 0);

        $polls = Poll::all($limit, $offset);

        return [
            'polls' => array_map(fn($p) => $p->toSysadminArray(), $polls),
            'total' => Poll::count(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * DELETE /api/sysadmin/polls/:pollId - Delete poll
     */
    public function deletePoll(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $pollId = (int) $params['pollId'];
        $poll = Poll::find($pollId);

        if (!$poll) {
            return ['error' => __('error_poll_not_found'), 'status' => 404];
        }

        $currentUser = Auth::getInstance()->user();

        LogService::getInstance()->log('sysadmin.poll.deleted', $poll->id, $currentUser->id, null, [
            'title' => $poll->title,
            'public_id' => $poll->publicId,
        ]);

        $poll->delete();

        return ['ok' => true, 'success' => true];
    }

    /**
     * GET /api/sysadmin/logs - List action logs
     */
    public function listLogs(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = (int) ($_GET['offset'] ?? 0);

        $logService = LogService::getInstance();
        $logs = $logService->getAllLogs($limit, $offset);

        // Enrich logs with user email if available
        $userCache = [];
        foreach ($logs as &$log) {
            if ($log['user_id'] && !isset($userCache[$log['user_id']])) {
                $user = User::find($log['user_id']);
                $userCache[$log['user_id']] = $user ? $user->email : null;
            }
            $log['user_email'] = $log['user_id'] ? ($userCache[$log['user_id']] ?? null) : null;
            $log['data'] = $log['data'] ? json_decode($log['data'], true) : null;
        }

        return [
            'logs' => $logs,
            'total' => $logService->countLogs(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * GET /api/sysadmin/settings - Get all site settings
     */
    public function getSettings(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        return [
            'settings' => SiteSetting::allMasked(),
        ];
    }

    /**
     * PUT /api/sysadmin/settings - Update site settings
     */
    public function updateSettings(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!isset($input['settings']) || !is_array($input['settings'])) {
            return ['error' => __('error_settings_object_required'), 'status' => 400];
        }

        $validKeys = array_keys(SiteSetting::DEFAULTS);
        $updates = [];
        $changedKeys = [];

        foreach ($input['settings'] as $key => $value) {
            // Only accept known setting keys
            if (!in_array($key, $validKeys, true)) {
                continue;
            }

            // Skip masked values (user didn't change them)
            if (is_string($value) && SiteSetting::isMaskedValue($value)) {
                continue;
            }

            // Convert booleans to string '1' or '0'
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $updates[$key] = $value;
            $changedKeys[] = $key;
        }

        if (!empty($updates)) {
            SiteSetting::setMany($updates);

            // Log the change (don't include actual values for sensitive keys)
            $logData = ['keys' => $changedKeys];
            $currentUser = Auth::getInstance()->user();
            LogService::getInstance()->log('sysadmin.settings.updated', null, $currentUser->id, null, $logData);
        }

        return [
            'settings' => SiteSetting::allMasked(),
            'updated' => count($updates),
        ];
    }

    /**
     * GET /api/sysadmin/stats/history - Get time-series statistics
     */
    public function statsHistory(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        $days = $_GET['days'] ?? '30';
        $db = Database::getInstance();

        // Determine date range
        if ($days === 'all') {
            // Find the earliest date across all relevant tables
            $earliestPoll = $db->fetchColumn("SELECT MIN(DATE(created_at)) FROM polls");
            $earliestResponse = $db->fetchColumn("SELECT MIN(DATE(created_at)) FROM responses");
            $earliestLog = $db->fetchColumn("SELECT MIN(DATE(created_at)) FROM action_log");

            $dates = array_filter([$earliestPoll, $earliestResponse, $earliestLog]);
            $startDate = $dates ? min($dates) : date('Y-m-d');
        } else {
            $daysInt = max(1, min(365, (int) $days));
            $startDate = date('Y-m-d', strtotime("-{$daysInt} days"));
        }
        $endDate = date('Y-m-d');

        // Generate all dates in range (to include zeros)
        $allDates = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        while ($current <= $end) {
            $allDates[$current->format('Y-m-d')] = [
                'date' => $current->format('Y-m-d'),
                'polls' => 0,
                'responses' => 0,
                'emails' => [
                    'verification' => 0,
                    'password_reset' => 0,
                    'invitation' => 0,
                    'response_notification' => 0,
                    'other' => 0,
                ],
            ];
            $current->modify('+1 day');
        }

        // Query polls created per day
        $pollCounts = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM polls
             WHERE DATE(created_at) >= :start AND DATE(created_at) <= :end
             GROUP BY DATE(created_at)",
            ['start' => $startDate, 'end' => $endDate]
        );
        foreach ($pollCounts as $row) {
            if (isset($allDates[$row['date']])) {
                $allDates[$row['date']]['polls'] = (int) $row['count'];
            }
        }

        // Query responses created per day
        $responseCounts = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM responses
             WHERE DATE(created_at) >= :start AND DATE(created_at) <= :end
             GROUP BY DATE(created_at)",
            ['start' => $startDate, 'end' => $endDate]
        );
        foreach ($responseCounts as $row) {
            if (isset($allDates[$row['date']])) {
                $allDates[$row['date']]['responses'] = (int) $row['count'];
            }
        }

        // Query emails sent per day by type (from action_log)
        // Map action names to email types
        $actionToType = [
            'email.verification_sent' => 'verification',
            'user.verification_resent' => 'verification',
            'user.password_reset_requested' => 'password_reset',
            'email.invitation_sent' => 'invitation',
            'email.response_notification_sent' => 'response_notification',
            'poll.reported' => 'other',
            'sysadmin.email.test_sent' => 'other',
        ];

        $emailActions = array_keys($actionToType);
        $placeholders = implode(',', array_fill(0, count($emailActions), '?'));
        $emailParams = array_merge($emailActions, [$startDate, $endDate]);

        $emailCounts = $db->fetchAll(
            "SELECT DATE(created_at) as date, action, COUNT(*) as count
             FROM action_log
             WHERE action IN ({$placeholders})
               AND DATE(created_at) >= ? AND DATE(created_at) <= ?
             GROUP BY DATE(created_at), action",
            $emailParams
        );
        foreach ($emailCounts as $row) {
            if (isset($allDates[$row['date']])) {
                $type = $actionToType[$row['action']] ?? 'other';
                $allDates[$row['date']]['emails'][$type] += (int) $row['count'];
            }
        }

        return [
            'history' => array_values($allDates),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * POST /api/sysadmin/settings/test-email - Send a test email
     */
    public function testEmail(array $params): array
    {
        if ($error = $this->requireSysadmin()) {
            return $error;
        }

        // Check if mail is enabled
        if (!SiteSetting::getBool('mail.enabled')) {
            return ['error' => __('error_email_is_not_enabled_in_settings'), 'status' => 400];
        }

        // Get sysadmin email
        $toEmail = SiteSetting::get('notifications.sysadmin_email');
        if (empty($toEmail)) {
            return ['error' => __('error_sysadmin_notification_email_is_not_configured'), 'status' => 400];
        }

        // Get current user for logging
        $currentUser = Auth::getInstance()->user();

        try {
            $mailService = new MailService();
            $siteName = SiteSetting::get('site.name', 'Pref.Tools Vote');

            // A registered recipient uses their own preference; otherwise use the source language.
            $emailLocale = User::findByEmail($toEmail)?->getPreferredLocale() ?? \App\i18n\Languages::getDefault();
            $result = $mailService->send(
                $toEmail,
                \App\i18n\Translator::translateForLocale($emailLocale, 'email_sitename_test_email', ['siteName' => $siteName]),
                \App\i18n\Translator::translateForLocale($emailLocale, 'email_this_is_a_test_email_from_sitename_if_you_received_this_email_your_smtp_configuration_is_working_cor', ['siteName' => $siteName, 'timestamp' => \App\Services\EmailTemplate::formatDate(new \DateTime(), $emailLocale)])
            );

            if ($result) {
                LogService::getInstance()->log('sysadmin.email.test_sent', null, $currentUser->id, null, [
                    'to' => $toEmail,
                ]);
                return ['ok' => true, 'success' => true, 'message' => __('backend_test_email_sent_to_toemail', ['toEmail' => $toEmail])];
            } else {
                return ['error' => __('error_failed_to_send_test_email_check_your_smtp_settings'), 'status' => 500];
            }
        } catch (\Exception $e) {
            LogService::getInstance()->log('sysadmin.email.test_failed', null, $currentUser->id, null, [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);
            return ['error' => __('error_email_error_detail', ['detail' => $e->getMessage()]), 'status' => 500];
        }
    }
}
