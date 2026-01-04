<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\Models\Poll;
use App\Services\LogService;

class SysadminController
{
    /**
     * Verify sysadmin access
     */
    private function requireSysadmin(): ?User
    {
        $user = Auth::getInstance()->user();

        if (!$user) {
            header('Location: ' . url('login'));
            if (!defined('PHPUNIT_RUNNING')) {
                exit;
            }
            return null;
        }

        if (!$user->isSysadmin()) {
            http_response_code(403);
            view('error', [
                'title' => 'Access Denied',
                'message' => 'You do not have permission to access the sysadmin area.',
            ]);
            if (!defined('PHPUNIT_RUNNING')) {
                exit;
            }
            return null;
        }

        return $user;
    }

    /**
     * GET /sysadmin - Dashboard overview
     */
    public function dashboard(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/dashboard', [
            'user' => $user,
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * GET /sysadmin/users - User administration
     */
    public function users(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/users', [
            'user' => $user,
        ]);
    }

    /**
     * GET /sysadmin/polls - Poll administration
     */
    public function polls(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/polls', [
            'user' => $user,
        ]);
    }

    /**
     * GET /sysadmin/logs - Action log viewer
     */
    public function logs(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/logs', [
            'user' => $user,
        ]);
    }

    /**
     * GET /sysadmin/stats - Statistics
     */
    public function stats(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/stats', [
            'user' => $user,
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * GET /sysadmin/config - Site configuration
     */
    public function config(array $params): void
    {
        $user = $this->requireSysadmin();

        view('sysadmin/config', [
            'user' => $user,
        ]);
    }

    /**
     * Get system statistics
     */
    private function getStats(): array
    {
        $db = \App\Database::getInstance();

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
}
