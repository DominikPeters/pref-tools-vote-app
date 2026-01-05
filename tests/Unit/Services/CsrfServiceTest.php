<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CsrfService;
use App\Router;

class CsrfServiceTest extends TestCase
{
    private CsrfService $csrf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrf = CsrfService::getInstance();
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1'
        ];
        Router::reset();
    }

    public function test_get_token_generates_and_persists_token(): void
    {
        $token = $this->csrf->getToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals($token, $_SESSION['csrf_token']);
        $this->assertEquals($token, $this->csrf->getToken());
    }

    public function test_verify_returns_true_for_valid_token(): void
    {
        $token = $this->csrf->getToken();
        $this->assertTrue($this->csrf->verify($token));
    }

    public function test_verify_returns_false_for_invalid_token(): void
    {
        $this->csrf->getToken();
        $this->assertFalse($this->csrf->verify('wrong-token'));
        $this->assertFalse($this->csrf->verify(null));
    }

    public function test_verify_request_skips_safe_methods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue($this->csrf->verifyRequest());

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->assertTrue($this->csrf->verifyRequest());

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->assertTrue($this->csrf->verifyRequest());
    }

    public function test_verify_request_checks_header(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = $this->csrf->getToken();
        
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->assertTrue($this->csrf->verifyRequest());

        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid';
        $this->assertFalse($this->csrf->verifyRequest());
    }

    public function test_verify_request_checks_post_data(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = $this->csrf->getToken();
        
        $_POST['csrf_token'] = $token;
        $this->assertTrue($this->csrf->verifyRequest());

        $_POST['csrf_token'] = 'invalid';
        $this->assertFalse($this->csrf->verifyRequest());
    }

    public function test_verify_request_checks_json_body(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = $this->csrf->getToken();
        
        // Mock JSON input
        $this->setJsonInput(['csrf_token' => $token]);
        Router::reset(); // Ensure router reads the new input
        
        $this->assertTrue($this->csrf->verifyRequest());

        $this->setJsonInput(['csrf_token' => 'invalid']);
        Router::reset();
        $this->assertFalse($this->csrf->verifyRequest());
    }

    public function test_get_meta_tag(): void
    {
        $token = $this->csrf->getToken();
        $tag = $this->csrf->getMetaTag();
        
        $this->assertStringContainsString('name="csrf-token"', $tag);
        $this->assertStringContainsString('content="' . $token . '"', $tag);
    }

    public function test_get_hidden_input(): void
    {
        $token = $this->csrf->getToken();
        $input = $this->csrf->getHiddenInput();
        
        $this->assertStringContainsString('type="hidden"', $input);
        $this->assertStringContainsString('name="csrf_token"', $input);
        $this->assertStringContainsString('value="' . $token . '"', $input);
    }
}
