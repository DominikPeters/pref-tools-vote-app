<?php

/**
 * Application Entry Point
 *
 * All requests are routed through this file via .htaccess
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Router;
use App\Controllers\PageController;
use App\Controllers\PollApiController;
use App\Controllers\AuthApiController;
use App\Controllers\SysadminController;
use App\Controllers\SysadminApiController;

// Check if installation is needed
if (needsInstall()) {
    // Redirect to installer
    header('Location: ' . basePath() . '/install.php');
    exit;
}

// Initialize router
$router = new Router();

// Set base path for subfolder deployment
$router->setBasePath(basePath());

// ============================================
// Page Routes (HTML)
// ============================================

$router->get('/', [PageController::class, 'home']);
$router->get('/create', [PageController::class, 'builder']);
$router->get('/login', [PageController::class, 'login']);
$router->get('/dashboard', [PageController::class, 'dashboard']);

// Sysadmin pages
$router->get('/sysadmin', [SysadminController::class, 'dashboard']);
$router->get('/sysadmin/users', [SysadminController::class, 'users']);
$router->get('/sysadmin/polls', [SysadminController::class, 'polls']);
$router->get('/sysadmin/logs', [SysadminController::class, 'logs']);
$router->get('/sysadmin/stats', [SysadminController::class, 'stats']);

// Poll pages - dynamic routes
$router->get('/:publicId', [PageController::class, 'poll']);
$router->post('/:publicId', [PageController::class, 'poll']);
$router->get('/:publicId/results', [PageController::class, 'results']);
$router->get('/:publicId/admin/:adminToken', [PageController::class, 'admin']);
$router->get('/:publicId/admin/:adminToken/edit', [PageController::class, 'builder']);

// ============================================
// API Routes (JSON)
// ============================================

// Authentication
$router->post('/api/auth/register', [AuthApiController::class, 'register']);
$router->post('/api/auth/login', [AuthApiController::class, 'login']);
$router->post('/api/auth/logout', [AuthApiController::class, 'logout']);
$router->get('/api/auth/me', [AuthApiController::class, 'me']);

// Polls
$router->post('/api/polls', [PollApiController::class, 'create']);
$router->get('/api/polls/:publicId', [PollApiController::class, 'show']);
$router->get('/api/polls/:publicId/admin/:adminToken', [PollApiController::class, 'showAdmin']);
$router->put('/api/polls/:publicId/admin/:adminToken', [PollApiController::class, 'update']);
$router->delete('/api/polls/:publicId/admin/:adminToken', [PollApiController::class, 'delete']);
$router->post('/api/polls/:publicId/admin/:adminToken/close', [PollApiController::class, 'close']);
$router->post('/api/polls/:publicId/admin/:adminToken/reopen', [PollApiController::class, 'reopen']);

// Responses (poll responses)
$router->post('/api/polls/:publicId/responses', [PollApiController::class, 'submitResponse']);
$router->get('/api/polls/:publicId/responses', [PollApiController::class, 'listResponses']);
$router->get('/api/polls/:publicId/responses/:responseId', [PollApiController::class, 'getResponse']);
$router->put('/api/polls/:publicId/responses/:responseId', [PollApiController::class, 'updateResponse']);
$router->delete('/api/polls/:publicId/responses/:responseId', [PollApiController::class, 'deleteResponse']);

// Export
$router->get('/api/polls/:publicId/admin/:adminToken/export', [PollApiController::class, 'export']);

// User dashboard
$router->get('/api/user/polls', [AuthApiController::class, 'userPolls']);
$router->get('/api/user/responses', [AuthApiController::class, 'userResponses']);
$router->post('/api/user/claim-poll', [AuthApiController::class, 'claimPoll']);

// Sysadmin API
$router->get('/api/sysadmin/stats', [SysadminApiController::class, 'stats']);
$router->get('/api/sysadmin/users', [SysadminApiController::class, 'listUsers']);
$router->put('/api/sysadmin/users/:userId', [SysadminApiController::class, 'updateUser']);
$router->delete('/api/sysadmin/users/:userId', [SysadminApiController::class, 'deleteUser']);
$router->get('/api/sysadmin/polls', [SysadminApiController::class, 'listPolls']);
$router->delete('/api/sysadmin/polls/:pollId', [SysadminApiController::class, 'deletePoll']);
$router->get('/api/sysadmin/logs', [SysadminApiController::class, 'listLogs']);

// ============================================
// Dispatch Request
// ============================================

$result = $router->dispatch();

// If result is an array, send as JSON
if (is_array($result)) {
    Router::json($result, isset($result['error']) ? (isset($result['status']) ? $result['status'] : 400) : 200);
}
