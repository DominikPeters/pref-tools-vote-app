<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Poll;

class RouterTest extends TestCase
{
    /**
     * Test that CSRF protection is active for state-changing API requests
     */
    public function test_api_csrf_protection(): void
    {
        $poll = $this->createPoll();
        
        // We'll use a special flag to tell callApi NOT to include the token
        $_SERVER['SKIP_TEST_CSRF'] = true;
        
        $result = $this->callApi('POST', "/api/polls/{$poll->publicId}/responses", [
            'answers' => [1 => 1]
        ]);
        
        unset($_SERVER['SKIP_TEST_CSRF']);

        $this->assertEquals(403, http_response_code());
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('CSRF_ERROR', $result['code']);
    }

    /**
     * Test that GET requests are exempt from CSRF
     */
    public function test_get_requests_exempt_from_csrf(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        
        // GET request should work even without token
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        
        $result = $this->callApi('GET', "/api/polls/{$poll->publicId}");
        
        $this->assertSuccess($result);
    }

    /**
     * Test that specific routes are exempted from CSRF
     */
    public function test_csrf_exempt_routes(): void
    {
        // Unsubscribe one-click should be exempt as it's triggered from emails
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/unsubscribe/one-click";
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        
        $router = $this->createRouter();
        
        ob_start();
        $router->dispatch();
        ob_end_clean();

        // Should NOT be 403. Might be 400 or something else due to missing params, 
        // but definitely not CSRF_ERROR.
        $this->assertNotEquals(403, http_response_code());
    }
}
