<?php

/**
 * Application Entry Point
 *
 * All requests are routed through this file via .htaccess
 */

require_once __DIR__ . '/src/bootstrap.php';

use App\Router;
use App\Controllers\PageController;
use App\Controllers\VoteApiController;
use App\Controllers\AuthApiController;

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

// Vote pages - dynamic routes
$router->get('/:publicId', [PageController::class, 'vote']);
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

// Votes
$router->post('/api/votes', [VoteApiController::class, 'create']);
$router->get('/api/votes/:publicId', [VoteApiController::class, 'show']);
$router->get('/api/votes/:publicId/admin/:adminToken', [VoteApiController::class, 'showAdmin']);
$router->put('/api/votes/:publicId/admin/:adminToken', [VoteApiController::class, 'update']);
$router->delete('/api/votes/:publicId/admin/:adminToken', [VoteApiController::class, 'delete']);
$router->post('/api/votes/:publicId/admin/:adminToken/close', [VoteApiController::class, 'close']);
$router->post('/api/votes/:publicId/admin/:adminToken/reopen', [VoteApiController::class, 'reopen']);

// Responses (voter submissions)
$router->post('/api/votes/:publicId/responses', [VoteApiController::class, 'submitResponse']);
$router->get('/api/votes/:publicId/responses', [VoteApiController::class, 'listResponses']);
$router->get('/api/votes/:publicId/responses/:responseId', [VoteApiController::class, 'getResponse']);
$router->put('/api/votes/:publicId/responses/:responseId', [VoteApiController::class, 'updateResponse']);
$router->delete('/api/votes/:publicId/responses/:responseId', [VoteApiController::class, 'deleteResponse']);

// Export
$router->get('/api/votes/:publicId/admin/:adminToken/export', [VoteApiController::class, 'export']);

// User dashboard
$router->get('/api/user/votes', [AuthApiController::class, 'userVotes']);
$router->get('/api/user/responses', [AuthApiController::class, 'userResponses']);
$router->post('/api/user/claim-vote', [AuthApiController::class, 'claimVote']);

// ============================================
// Dispatch Request
// ============================================

$result = $router->dispatch();

// If result is an array, send as JSON
if (is_array($result)) {
    Router::json($result, isset($result['error']) ? (isset($result['status']) ? $result['status'] : 400) : 200);
}
