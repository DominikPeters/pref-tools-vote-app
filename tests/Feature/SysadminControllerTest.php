<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Controllers\SysadminController;
use App\Models\User;

class SysadminControllerTest extends TestCase
{
    private SysadminController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SysadminController();
    }

    private function captureView(callable $callback): string
    {
        ob_start();
        $callback();
        return ob_get_clean();
    }

    public function test_dashboard_access_denied_for_regular_user(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        
        $output = $this->captureView(fn() => $this->controller->dashboard([]));
        $this->assertStringContainsString('Access Denied', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_dashboard_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->dashboard([]));
        $this->assertStringContainsString('Dashboard', $output);
    }

    public function test_users_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->users([]));
        $this->assertStringContainsString('Users', $output);
    }

    public function test_polls_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->polls([]));
        $this->assertStringContainsString('Polls', $output);
    }

    public function test_logs_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->logs([]));
        $this->assertStringContainsString('Logs', $output);
    }

    public function test_stats_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->stats([]));
        $this->assertStringContainsString('Statistics', $output);
    }

    public function test_config_success(): void
    {
        $admin = $this->createSysadmin();
        $this->actingAs($admin);
        
        $output = $this->captureView(fn() => $this->controller->config([]));
        $this->assertStringContainsString('Settings', $output);
    }
}
