<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Controllers\UnsubscribeController;
use App\Services\UnsubscribeService;

class UnsubscribeControllerTest extends TestCase
{
    private UnsubscribeController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new UnsubscribeController();
    }

    private function captureView(callable $callback): string
    {
        ob_start();
        $callback();
        return ob_get_clean();
    }

    public function test_show_page_invalid(): void
    {
        $output = $this->captureView(fn() => $this->controller->showPage([]));
        $this->assertStringContainsString('Invalid Link', $output);
    }

    public function test_show_page_valid(): void
    {
        $email = 'test@example.com';
        $signature = UnsubscribeService::generateSignature($email);
        $_GET['email'] = $email;
        $_GET['sig'] = $signature;

        $output = $this->captureView(fn() => $this->controller->showPage([]));
        $this->assertStringContainsString($email, $output);
        $this->assertStringContainsString('Unsubscribe', $output);
    }

    public function test_handle_api_unsubscribe(): void
    {
        $email = 'test@example.com';
        $signature = UnsubscribeService::generateSignature($email);
        
        $this->setJsonInput([
            'email' => $email,
            'sig' => $signature,
            'action' => 'unsubscribe'
        ]);

        $result = $this->controller->handleApi([]);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_unsubscribed']);
        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));
    }

    public function test_handle_api_resubscribe(): void
    {
        $email = 'test@example.com';
        $signature = UnsubscribeService::generateSignature($email);
        UnsubscribeService::unsubscribe($email);
        
        $this->setJsonInput([
            'email' => $email,
            'sig' => $signature,
            'action' => 'resubscribe'
        ]);

        $result = $this->controller->handleApi([]);
        
        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_unsubscribed']);
        $this->assertFalse(UnsubscribeService::isUnsubscribed($email));
    }

    public function test_handle_api_invalid_signature(): void
    {
        $this->setJsonInput([
            'email' => 'test@example.com',
            'sig' => 'wrong',
            'action' => 'unsubscribe'
        ]);

        $result = $this->controller->handleApi([]);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(403, $result['status']);
    }

    public function test_handle_one_click(): void
    {
        $email = 'oneclick@example.com';
        $signature = UnsubscribeService::generateSignature($email);
        $_GET['email'] = $email;
        $_GET['sig'] = $signature;

        ob_start();
        $this->controller->handleOneClick([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Unsubscribed successfully', $output);
        $this->assertEquals(200, http_response_code());
        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));
    }

    public function test_handle_one_click_invalid(): void
    {
        $_GET['email'] = 'test@example.com';
        $_GET['sig'] = 'wrong';

        ob_start();
        $this->controller->handleOneClick([]);
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringContainsString('Invalid signature', $output);
    }
}
