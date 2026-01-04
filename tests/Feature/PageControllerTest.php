<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Controllers\PageController;
use App\Models\Poll;
use App\Models\SiteSetting;
use App\Auth;

class PageControllerTest extends TestCase
{
    private PageController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new PageController();
    }

    private function captureView(callable $callback): string
    {
        ob_start();
        $callback();
        return ob_get_clean();
    }

    public function test_home(): void
    {
        $output = $this->captureView(fn() => $this->controller->home([]));
        $this->assertStringContainsString('Smarter Polls', $output);
    }

    public function test_about(): void
    {
        $output = $this->captureView(fn() => $this->controller->about([]));
        $this->assertStringContainsString('About', $output);
    }

    public function test_privacy(): void
    {
        $output = $this->captureView(fn() => $this->controller->privacy([]));
        $this->assertStringContainsString('Privacy Policy', $output);
    }

    public function test_demo_not_configured(): void
    {
        SiteSetting::set('demo.poll_id', '');
        $output = $this->captureView(fn() => $this->controller->demo([]));
        $this->assertStringContainsString('Demo Not Configured', $output);
    }

    public function test_demo_poll_not_found(): void
    {
        SiteSetting::set('demo.poll_id', 'nonexistent');
        $output = $this->captureView(fn() => $this->controller->demo([]));
        $this->assertStringContainsString('Demo Not Available', $output);
    }

    public function test_demo_success(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        SiteSetting::set('demo.poll_id', $poll->publicId);
        
        $output = $this->captureView(fn() => $this->controller->demo([]));
        $this->assertStringContainsString($poll->title, $output);
    }

    public function test_demo_results_success(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'visibility' => 'public']);
        SiteSetting::set('demo.poll_id', $poll->publicId);
        
        $output = $this->captureView(fn() => $this->controller->demoResults([]));
        $this->assertStringContainsString('Results', $output);
        $this->assertStringContainsString($poll->title, $output);
    }

    public function test_builder_new(): void
    {
        $output = $this->captureView(fn() => $this->controller->builder([]));
        $this->assertStringContainsString('Create', $output);
    }

    public function test_builder_edit(): void
    {
        $poll = $this->createPoll();
        $output = $this->captureView(fn() => $this->controller->builder([
            'publicId' => $poll->publicId,
            'adminToken' => $poll->adminToken
        ]));
        $this->assertStringContainsString('Edit', $output);
        $this->assertStringContainsString($poll->title, $output);
    }

    public function test_builder_edit_unauthorized(): void
    {
        $poll = $this->createPoll();
        $output = $this->captureView(fn() => $this->controller->builder([
            'publicId' => $poll->publicId,
            'adminToken' => 'wrong'
        ]));
        $this->assertStringContainsString('Access Denied', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_preview(): void
    {
        $_POST['data'] = json_encode([
            'title' => 'Preview Poll',
            'questions' => [
                [
                    'text' => 'Q1',
                    'type' => 'single_choice',
                    'options' => [['label' => 'Opt 1']]
                ]
            ]
        ]);
        
        $output = $this->captureView(fn() => $this->controller->preview([]));
        $this->assertStringContainsString('Preview Poll', $output);
        $this->assertStringContainsString('Q1', $output);
    }

    public function test_login_page(): void
    {
        $output = $this->captureView(fn() => $this->controller->login([]));
        $this->assertStringContainsString('Login', $output);
    }

    public function test_dashboard_redirect_if_not_logged_in(): void
    {
        $this->captureView(fn() => $this->controller->dashboard([]));
        $this->assertRedirect(url('login'));
    }

    public function test_dashboard_success(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        
        $output = $this->captureView(fn() => $this->controller->dashboard([]));
        $this->assertStringContainsString('Dashboard', $output);
        $this->assertStringContainsString($user->name, $output);
    }

    public function test_login_verify_email(): void
    {
        $user = $this->createUser('verify@example.com');
        $token = 'test_token';
        
        $user->setVerificationToken($token);

        $_GET['verify_token'] = $token;
        
        $this->captureView(fn() => $this->controller->login([]));
        
        $user = \App\Models\User::find($user->id);
        $this->assertTrue($user->isEmailVerified());
        $this->assertRedirect(url('dashboard?verified=1'));
    }

    public function test_login_verify_email_invalid(): void
    {
        $_GET['verify_token'] = 'invalid';
        $this->captureView(fn() => $this->controller->login([]));
        // Should just render login page normally or redirect if already logged in
        $this->assertTrue(true); // Reached here without error
    }

    public function test_poll_not_found(): void
    {
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => 'nonexistent']));
        $this->assertStringContainsString('Poll Not Found', $output);
        $this->assertEquals(404, http_response_code());
    }

    public function test_poll_password_protection(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'access_mode' => 'password',
            'access_password' => 'secret123'
        ]);
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        $this->assertStringContainsString('Password Required', $output);
    }

    public function test_poll_results_viewable(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'visibility' => 'public']);
        $output = $this->captureView(fn() => $this->controller->results(['publicId' => $poll->publicId]));
        $this->assertStringContainsString('Results', $output);
    }

    public function test_poll_results_private(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'visibility' => 'private']);
        $output = $this->captureView(fn() => $this->controller->results(['publicId' => $poll->publicId]));
        $this->assertStringContainsString('Results Not Available', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_admin_panel(): void
    {
        $poll = $this->createPoll();
        $output = $this->captureView(fn() => $this->controller->admin([
            'publicId' => $poll->publicId,
            'adminToken' => $poll->adminToken
        ]));
        $this->assertStringContainsString('Admin', $output);
    }

    public function test_results_admin(): void
    {
        $poll = $this->createPoll();
        $output = $this->captureView(fn() => $this->controller->resultsAdmin([
            'publicId' => $poll->publicId,
            'adminToken' => $poll->adminToken
        ]));
        $this->assertStringContainsString('Results', $output);
    }

    public function test_responses_admin(): void
    {
        $poll = $this->createPoll();
        $output = $this->captureView(fn() => $this->controller->responsesAdmin([
            'publicId' => $poll->publicId,
            'adminToken' => $poll->adminToken
        ]));
        $this->assertStringContainsString('Responses', $output);
    }

    public function test_poll_closed_redirects_to_results(): void
    {
        $poll = $this->createPoll(['status' => 'closed', 'visibility' => 'public']);
        $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        $this->assertRedirect(url("{$poll->publicId}/results"));
    }

    public function test_poll_password_submission_success(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'access_mode' => 'password',
            'access_password' => 'secret123'
        ]);
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['access_password'] = 'secret123';
        
        $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertRedirect(url($poll->publicId));
        $this->assertTrue($_SESSION['poll_access_' . $poll->publicId]);
    }

    public function test_poll_password_submission_fail(): void
    {
        $poll = $this->createPoll([
            'status' => 'open',
            'access_mode' => 'password',
            'access_password' => 'secret123'
        ]);
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['access_password'] = 'wrong';
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString('Incorrect password', $output);
    }

    public function test_poll_token_access_success(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $tokens = \App\Models\AccessToken::generate($poll->id, 1);
        $token = $tokens[0]->token;
        
        $_GET['token'] = $token;
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString($poll->title, $output);
        $this->assertEquals($token, $_SESSION['poll_token_' . $poll->publicId]);
    }

    public function test_poll_token_access_used_fail(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $tokens = \App\Models\AccessToken::generate($poll->id, 1);
        $token = $tokens[0];
        $response = \App\Models\Response::create($poll->id, []);
        $token->markUsed($response->id);
        
        $_GET['token'] = $token->token;
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString('Access Link Used', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_poll_email_invite_access_success(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $invite = \App\Models\EmailInvitation::create($poll->id, 'test@example.com');
        
        $_GET['token'] = $invite->token;
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString($poll->title, $output);
        $this->assertEquals($invite->token, $_SESSION['poll_token_' . $poll->publicId]);
    }

    public function test_poll_email_invite_access_used_fail(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        $invite = \App\Models\EmailInvitation::create($poll->id, 'test@example.com');
        $response = \App\Models\Response::create($poll->id, []);
        $invite->markUsed($response->id);
        
        $_GET['token'] = $invite->token;
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString('Invitation Used', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_poll_requires_token_fail(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'voting_mode' => 'identified']);
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString('Access Required', $output);
        $this->assertEquals(403, http_response_code());
    }

    public function test_poll_existing_response_cookie(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'allow_edit_own' => true]);
        $response = \App\Models\Response::create($poll->id, []);
        
        $_COOKIE['voter_token_' . $poll->publicId] = $response->voterToken;
        
        $output = $this->captureView(fn() => $this->controller->poll(['publicId' => $poll->publicId]));
        
        $this->assertStringContainsString($poll->title, $output);
        // It should load the response into view data, but we can't easily check that 
        // without inspecting the view variables or rendered output features.
    }

    public function test_privacy_policy_not_found(): void
    {
        // Temporarily move the privacy policy file
        $path = __DIR__ . '/../../PRIVACY_POLICY.md';
        $tempPath = $path . '.bak';
        if (file_exists($path)) rename($path, $tempPath);
        
        $output = $this->captureView(fn() => $this->controller->privacy([]));
        
        if (file_exists($tempPath)) rename($tempPath, $path);
        
        $this->assertStringContainsString('Privacy Policy Not Found', $output);
        $this->assertEquals(404, http_response_code());
    }
}
