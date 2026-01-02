<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Auth;
use App\Controllers\PageController;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VerificationFlowTest extends TestCase
{
    /**
     * Test the PHP-driven verification flow in PageController
     */
    public function test_php_verification_redirects_to_dashboard(): void
    {
        $user = $this->createUser('verify-php@example.com', 'password123');
        $token = 'test-verification-token-123';
        $user->setVerificationToken($token);

        $this->assertFalse($user->isEmailVerified());

        // Set up the environment for PageController
        $_GET['verify_token'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        // Use output buffering to catch any view output (though it shouldn't produce any on redirect)
        ob_start();
        $controller = new PageController();
        $controller->login([]);
        ob_end_clean();

        // 1. Verify side effects in database
        $updatedUser = User::find($user->id);
        $this->assertTrue($updatedUser->isEmailVerified(), 'User should be marked as verified in DB');
        
        // 2. Verify user is logged in
        $this->assertEquals($user->id, Auth::getInstance()->id(), 'User should be logged in');

        // 3. Verify redirect header was set
        $headers = xdebug_get_headers(); // xdebug_get_headers is more reliable in CLI if available
        if (empty($headers)) {
            $headers = headers_list();
        }

        $found = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0 && strpos($header, 'dashboard?verified=1') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should have sent a Location header to dashboard?verified=1. Found: ' . implode(', ', $headers));
    }
}
