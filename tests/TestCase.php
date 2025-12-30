<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use App\Database;
use App\Auth;

abstract class TestCase extends BaseTestCase
{
    protected static bool $schemaLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singletons for test isolation
        Database::reset();
        Auth::reset();

        // Initialize fresh database for each test
        $this->initializeDatabase();

        // Clear session
        $_SESSION = [];

        // Clear superglobals
        $_POST = [];
        $_GET = [];
        $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset singletons
        Database::reset();
        Auth::reset();
    }

    /**
     * Initialize the database with schema
     */
    protected function initializeDatabase(): void
    {
        $db = Database::getInstance();

        // Load and run migrations
        $sql = file_get_contents(MIGRATIONS_PATH . '/001_initial_schema.sql');
        $db->runMigration($sql);
    }

    /**
     * Create a test user
     */
    protected function createUser(string $email = 'test@example.com', string $password = 'password123'): \App\Models\User
    {
        $auth = Auth::getInstance();
        return $auth->register($email, $password);
    }

    /**
     * Create a test sysadmin user
     */
    protected function createSysadmin(string $email = 'admin@example.com', string $password = 'password123'): \App\Models\User
    {
        $auth = Auth::getInstance();
        return $auth->register($email, $password, \App\Models\User::ROLE_SYSADMIN);
    }

    /**
     * Log in as a user
     */
    protected function actingAs(\App\Models\User $user): self
    {
        $_SESSION['user_id'] = $user->id;
        Auth::reset(); // Force re-check of session
        return $this;
    }

    /**
     * Create a test poll
     */
    protected function createPoll(array $data = [], ?int $userId = null): \App\Models\Poll
    {
        $defaults = [
            'title' => 'Test Poll',
            'description' => 'A test vote description',
            'status' => 'draft',
        ];

        return \App\Models\Poll::create(array_merge($defaults, $data), $userId);
    }

    /**
     * Create a test question
     */
    protected function createQuestion(int $pollId, array $data = []): \App\Models\Question
    {
        $defaults = [
            'type' => 'single_choice',
            'text' => 'Test Question',
            'required' => true,
            'options' => [
                ['label' => 'Option A'],
                ['label' => 'Option B'],
                ['label' => 'Option C'],
            ],
        ];

        return \App\Models\Question::create($pollId, array_merge($defaults, $data));
    }

    /**
     * Simulate an API request and get JSON response
     */
    protected function callApi(string $method, string $uri, array $data = [], array $params = []): array
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = $params;

        // For POST/PUT, set up the input stream
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $this->setJsonInput($data);
        }

        // Create router and dispatch
        $router = $this->createRouter();

        ob_start();
        $result = $router->dispatch();
        ob_end_clean();

        // Restore input stream
        $this->clearJsonInput();

        return is_array($result) ? $result : [];
    }

    /**
     * Set JSON input for POST/PUT requests
     */
    protected function setJsonInput(array $data): void
    {
        // We'll use a custom stream wrapper for testing
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', TestInputStream::class);
        TestInputStream::$data = json_encode($data);
    }

    /**
     * Clear JSON input
     */
    protected function clearJsonInput(): void
    {
        @stream_wrapper_restore('php');
    }

    /**
     * Create a router with all routes
     */
    protected function createRouter(): \App\Router
    {
        $router = new \App\Router();

        // Auth routes
        $router->post('/api/auth/register', [\App\Controllers\AuthApiController::class, 'register']);
        $router->post('/api/auth/login', [\App\Controllers\AuthApiController::class, 'login']);
        $router->post('/api/auth/logout', [\App\Controllers\AuthApiController::class, 'logout']);
        $router->get('/api/auth/me', [\App\Controllers\AuthApiController::class, 'me']);

        // Poll routes
        $router->post('/api/polls', [\App\Controllers\PollApiController::class, 'create']);
        $router->get('/api/polls/:publicId', [\App\Controllers\PollApiController::class, 'show']);
        $router->get('/api/polls/:publicId/admin/:adminToken', [\App\Controllers\PollApiController::class, 'showAdmin']);
        $router->put('/api/polls/:publicId/admin/:adminToken', [\App\Controllers\PollApiController::class, 'update']);
        $router->delete('/api/polls/:publicId/admin/:adminToken', [\App\Controllers\PollApiController::class, 'delete']);
        $router->post('/api/polls/:publicId/admin/:adminToken/close', [\App\Controllers\PollApiController::class, 'close']);
        $router->post('/api/polls/:publicId/admin/:adminToken/reopen', [\App\Controllers\PollApiController::class, 'reopen']);
        $router->post('/api/polls/:publicId/admin/:adminToken/duplicate', [\App\Controllers\PollApiController::class, 'duplicate']);

        // Response routes
        $router->post('/api/polls/:publicId/responses', [\App\Controllers\PollApiController::class, 'submitResponse']);
        $router->get('/api/polls/:publicId/responses', [\App\Controllers\PollApiController::class, 'listResponses']);
        $router->get('/api/polls/:publicId/responses/:responseId', [\App\Controllers\PollApiController::class, 'getResponse']);
        $router->put('/api/polls/:publicId/responses/:responseId', [\App\Controllers\PollApiController::class, 'updateResponse']);
        $router->delete('/api/polls/:publicId/responses/:responseId', [\App\Controllers\PollApiController::class, 'deleteResponse']);

        // Export
        $router->get('/api/polls/:publicId/admin/:adminToken/export', [\App\Controllers\PollApiController::class, 'export']);

        // Token routes
        $router->get('/api/polls/:publicId/admin/:adminToken/tokens', [\App\Controllers\TokenApiController::class, 'list']);
        $router->post('/api/polls/:publicId/admin/:adminToken/tokens', [\App\Controllers\TokenApiController::class, 'generate']);
        $router->put('/api/polls/:publicId/admin/:adminToken/tokens/:tokenId', [\App\Controllers\TokenApiController::class, 'update']);
        $router->delete('/api/polls/:publicId/admin/:adminToken/tokens/:tokenId', [\App\Controllers\TokenApiController::class, 'delete']);

        // Invitation routes
        $router->get('/api/polls/:publicId/admin/:adminToken/invitations', [\App\Controllers\InvitationApiController::class, 'list']);
        $router->post('/api/polls/:publicId/admin/:adminToken/invitations', [\App\Controllers\InvitationApiController::class, 'send']);
        $router->post('/api/polls/:publicId/admin/:adminToken/invitations/:invitationId/resend', [\App\Controllers\InvitationApiController::class, 'resend']);
        $router->delete('/api/polls/:publicId/admin/:adminToken/invitations/:invitationId', [\App\Controllers\InvitationApiController::class, 'delete']);

        // Report routes
        $router->get('/api/polls/:publicId/reports', [\App\Controllers\ReportApiController::class, 'listPublic']);
        $router->get('/api/polls/:publicId/admin/:adminToken/reports', [\App\Controllers\ReportApiController::class, 'list']);
        $router->get('/api/polls/:publicId/admin/:adminToken/reports/types', [\App\Controllers\ReportApiController::class, 'availableTypes']);
        $router->post('/api/polls/:publicId/admin/:adminToken/reports', [\App\Controllers\ReportApiController::class, 'create']);
        $router->put('/api/polls/:publicId/admin/:adminToken/reports/:reportId', [\App\Controllers\ReportApiController::class, 'update']);
        $router->delete('/api/polls/:publicId/admin/:adminToken/reports/:reportId', [\App\Controllers\ReportApiController::class, 'delete']);
        $router->post('/api/polls/:publicId/admin/:adminToken/reports/reorder', [\App\Controllers\ReportApiController::class, 'reorder']);
        $router->post('/api/polls/:publicId/admin/:adminToken/reports/:reportId/compute', [\App\Controllers\ReportApiController::class, 'recompute']);
        $router->get('/api/polls/:publicId/reports/:reportId/export', [\App\Controllers\ReportApiController::class, 'exportPrefLib']);

        // User dashboard
        $router->get('/api/user/polls', [\App\Controllers\AuthApiController::class, 'userPolls']);
        $router->get('/api/user/responses', [\App\Controllers\AuthApiController::class, 'userResponses']);
        $router->post('/api/user/claim-poll', [\App\Controllers\AuthApiController::class, 'claimPoll']);

        // Unsubscribe routes
        $router->post('/api/unsubscribe', [\App\Controllers\UnsubscribeController::class, 'handleApi']);

        // Sysadmin routes
        $router->get('/api/sysadmin/stats', [\App\Controllers\SysadminApiController::class, 'stats']);
        $router->get('/api/sysadmin/users', [\App\Controllers\SysadminApiController::class, 'listUsers']);
        $router->put('/api/sysadmin/users/:userId', [\App\Controllers\SysadminApiController::class, 'updateUser']);
        $router->delete('/api/sysadmin/users/:userId', [\App\Controllers\SysadminApiController::class, 'deleteUser']);
        $router->get('/api/sysadmin/polls', [\App\Controllers\SysadminApiController::class, 'listPolls']);
        $router->delete('/api/sysadmin/polls/:pollId', [\App\Controllers\SysadminApiController::class, 'deletePoll']);
        $router->get('/api/sysadmin/logs', [\App\Controllers\SysadminApiController::class, 'listLogs']);
        $router->get('/api/sysadmin/settings', [\App\Controllers\SysadminApiController::class, 'getSettings']);
        $router->put('/api/sysadmin/settings', [\App\Controllers\SysadminApiController::class, 'updateSettings']);
        $router->post('/api/sysadmin/settings/test-email', [\App\Controllers\SysadminApiController::class, 'testEmail']);

        return $router;
    }

    /**
     * Assert that a response is successful
     */
    protected function assertSuccess(array $response): void
    {
        $this->assertArrayHasKey('ok', $response, 'Response missing "ok" key: ' . json_encode($response));
        $this->assertTrue($response['ok'], 'Response not successful: ' . json_encode($response));
    }

    /**
     * Assert that a response is an error
     */
    protected function assertError(array $response, ?string $code = null): void
    {
        $this->assertArrayHasKey('error', $response, 'Response missing "error" key');

        if ($code !== null) {
            $this->assertArrayHasKey('code', $response, 'Response missing "code" key');
            $this->assertEquals($code, $response['code']);
        }
    }
}

/**
 * Stream wrapper for simulating php://input
 */
class TestInputStream
{
    public $context;
    public static string $data = '';
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        $result = substr(self::$data, $this->position, $count);
        $this->position += strlen($result);
        return $result;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = strlen(self::$data) + $offset;
                break;
        }
        return true;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }
}
