<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Models\User;
use App\Models\Poll;
use App\Services\LogService;

class SysadminApiController
{
    /**
     * Verify sysadmin access for API requests
     */
    private function requireSysadmin(): ?array
    {
        $user = Auth::getInstance()->user();

        if (!$user) {
            return ['error' => 'Authentication required', 'status' => 401];
        }

        if (!$user->isSysadmin()) {
            return ['error' => 'Sysadmin access required', 'status' => 403];
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
            return ['error' => 'User not found', 'status' => 404];
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Update role if provided
        if (isset($input['role'])) {
            $newRole = $input['role'];

            if (!in_array($newRole, [User::ROLE_USER, User::ROLE_SYSADMIN])) {
                return ['error' => 'Invalid role', 'status' => 400];
            }

            // Prevent removing sysadmin from self
            $currentUser = Auth::getInstance()->user();
            if ($user->id === $currentUser->id && $newRole !== User::ROLE_SYSADMIN) {
                return ['error' => 'Cannot remove sysadmin role from yourself', 'status' => 400];
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
            return ['error' => 'User not found', 'status' => 404];
        }

        // Prevent deleting self
        $currentUser = Auth::getInstance()->user();
        if ($user->id === $currentUser->id) {
            return ['error' => 'Cannot delete yourself', 'status' => 400];
        }

        LogService::getInstance()->log('sysadmin.user.deleted', null, $user->id, null, [
            'email' => $user->email,
            'by_user_id' => $currentUser->id,
        ]);

        $user->delete();

        return ['success' => true];
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
            return ['error' => 'Poll not found', 'status' => 404];
        }

        $currentUser = Auth::getInstance()->user();

        LogService::getInstance()->log('sysadmin.poll.deleted', $poll->id, $currentUser->id, null, [
            'title' => $poll->title,
            'public_id' => $poll->publicId,
        ]);

        $poll->delete();

        return ['success' => true];
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
}
