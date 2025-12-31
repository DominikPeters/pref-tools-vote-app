<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Poll;
use App\Models\SiteSetting;

class PageController
{
    /**
     * GET / - Landing page
     */
    public function home(array $params): void
    {
        view('home', [
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /demo - Demo poll voting page
     */
    public function demo(array $params): void
    {
        $demoPollId = SiteSetting::get('demo.poll_id');

        if (empty($demoPollId)) {
            view('error', [
                'title' => 'Demo Not Configured',
                'message' => 'No demo poll has been configured. Please check back later or contact the site administrator.',
            ]);
            return;
        }

        $poll = Poll::findByPublicId($demoPollId);

        if (!$poll) {
            view('error', [
                'title' => 'Demo Not Available',
                'message' => 'The demo poll could not be found. It may have been removed.',
            ]);
            return;
        }

        // Reuse the poll method logic with the demo poll
        $params['publicId'] = $demoPollId;
        $this->poll($params);
    }

    /**
     * GET /demo/results - Demo poll results page
     */
    public function demoResults(array $params): void
    {
        $demoPollId = SiteSetting::get('demo.poll_id');

        if (empty($demoPollId)) {
            view('error', [
                'title' => 'Demo Not Configured',
                'message' => 'No demo poll has been configured. Please check back later or contact the site administrator.',
            ]);
            return;
        }

        $poll = Poll::findByPublicId($demoPollId);

        if (!$poll) {
            view('error', [
                'title' => 'Demo Not Available',
                'message' => 'The demo poll could not be found. It may have been removed.',
            ]);
            return;
        }

        // Reuse the results method logic with the demo poll
        $params['publicId'] = $demoPollId;
        $this->results($params);
    }

    /**
     * GET /create - Form builder (new poll)
     * GET /:publicId/admin/:adminToken/edit - Form builder (edit existing)
     */
    public function builder(array $params): void
    {
        $poll = null;
        $adminToken = null;

        // Check if editing an existing poll
        if (!empty($params['publicId']) && !empty($params['adminToken'])) {
            $poll = Poll::findByPublicId($params['publicId']);

            if (!$poll) {
                http_response_code(404);
                view('error', [
                    'title' => 'Poll Not Found',
                    'message' => 'The poll you are looking for does not exist.',
                ]);
                return;
            }

            if (!$poll->verifyAdminToken($params['adminToken'])) {
                http_response_code(403);
                view('error', [
                    'title' => 'Access Denied',
                    'message' => 'Invalid admin token.',
                ]);
                return;
            }

            $poll->loadQuestions();
            $adminToken = $params['adminToken'];
        }

        view('builder', [
            'user' => Auth::getInstance()->user(),
            'poll' => $poll,
            'adminToken' => $adminToken,
        ]);
    }

    /**
     * GET /login - Login/register page
     */
    public function login(array $params): void
    {
        // Redirect if already logged in
        if (Auth::getInstance()->check()) {
            header('Location: ' . url('dashboard'));
            exit;
        }

        view('login', []);
    }

    /**
     * GET /dashboard - User dashboard
     */
    public function dashboard(array $params): void
    {
        $user = Auth::getInstance()->user();

        if (!$user) {
            header('Location: ' . url('login'));
            exit;
        }

        $polls = Poll::findByUserId($user->id);
        $votedPolls = Poll::findVotedByUserId($user->id);

        view('dashboard', [
            'user' => $user,
            'polls' => $polls,
            'votedPolls' => $votedPolls,
        ]);
    }

    /**
     * GET /:publicId - Voter form
     */
    public function poll(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => 'Poll Not Found',
                'message' => 'The poll you are looking for does not exist.',
            ]);
            return;
        }

        // If vote is closed and results are public, redirect to results
        if ($poll->status === 'closed' && $poll->visibility !== 'private') {
            header('Location: ' . url("{$poll->publicId}/results"));
            exit;
        }

        // Check password protection
        if ($poll->accessMode === 'password' && $poll->accessPassword) {
            $sessionKey = 'poll_access_' . $poll->publicId;

            // Handle password submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
                if ($poll->verifyAccessPassword($_POST['access_password'])) {
                    $_SESSION[$sessionKey] = true;
                    header('Location: ' . url($poll->publicId));
                    exit;
                } else {
                    view('password', [
                        'poll' => $poll,
                        'error' => 'Incorrect password. Please try again.',
                    ]);
                    return;
                }
            }

            // Check if already unlocked
            if (empty($_SESSION[$sessionKey])) {
                view('password', [
                    'poll' => $poll,
                    'error' => null,
                ]);
                return;
            }
        }

        // Check for token-based access in URL and store in session if valid
        $token = $_GET['token'] ?? null;
        if ($token) {
            $accessToken = \App\Models\AccessToken::findByToken($poll->id, $token);
            if ($accessToken && !$accessToken->usedAt) {
                $_SESSION['poll_token_' . $poll->publicId] = $token;
            } elseif ($accessToken && $accessToken->usedAt) {
                // If token is already used, we can show an error immediately
                http_response_code(403);
                view('error', [
                    'title' => 'Access Link Used',
                    'message' => 'This access link has already been used.',
                ]);
                return;
            }
            // Check email invitations too
            $emailInvite = \App\Models\EmailInvitation::findByToken($poll->id, $token);
            if ($emailInvite && !$emailInvite->usedAt) {
                $_SESSION['poll_token_' . $poll->publicId] = $token;
            } elseif ($emailInvite && $emailInvite->usedAt) {
                http_response_code(403);
                view('error', [
                    'title' => 'Invitation Used',
                    'message' => 'This invitation link has already been used.',
                ]);
                return;
            }
        }

        // Enforce token-based access if required by access mode
        if ($poll->accessMode === 'token') {
            if (empty($_SESSION['poll_token_' . $poll->publicId])) {
                http_response_code(403);
                view('error', [
                    'title' => 'Access Token Required',
                    'message' => 'This poll requires an access token. Please use the link provided by the poll creator.',
                ]);
                return;
            }
        }

        $poll->loadQuestions();

        // Check for existing response
        $existingResponse = null;
        $hasVoted = false;
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        if ($voterToken) {
            $existingResponse = \App\Models\Response::findByVoterToken($poll->id, $voterToken);
            if ($existingResponse) {
                $hasVoted = true;
                // Only load answers if they can actually edit
                if ($poll->allowEditOwn || $poll->allowEditAny) {
                    $existingResponse->loadAnswers();
                } else {
                    $existingResponse = null; // Don't pass response if not editing
                }
            }
        }

        view('poll', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
            'existingResponse' => $existingResponse,
            'hasVoted' => $hasVoted,
        ]);
    }

    /**
     * GET /:publicId/results - Results page
     */
    public function results(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => 'Poll Not Found',
                'message' => 'The poll you are looking for does not exist.',
            ]);
            return;
        }

        // Check if results are visible
        if ($poll->visibility === 'private') {
            http_response_code(403);
            view('error', [
                'title' => 'Results Not Available',
                'message' => 'The results for this poll are not yet available.',
            ]);
            return;
        }

        $poll->loadQuestions();

        view('results', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken - Admin panel
     */
    public function admin(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => 'Poll Not Found',
                'message' => 'The poll you are looking for does not exist.',
            ]);
            return;
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => 'Access Denied',
                'message' => 'Invalid admin token.',
            ]);
            return;
        }

        $poll->loadQuestions();

        view('admin', [
            'poll' => $poll,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken/results - Admin results page
     */
    public function resultsAdmin(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => 'Poll Not Found',
                'message' => 'The poll you are looking for does not exist.',
            ]);
            return;
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => 'Access Denied',
                'message' => 'Invalid admin token.',
            ]);
            return;
        }

        $poll->loadQuestions();

        view('results-admin', [
            'poll' => $poll,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /privacy - Privacy policy page
     */
    public function privacy(array $params): void
    {
        $filePath = __DIR__ . '/../../PRIVACY_POLICY.md';

        if (!file_exists($filePath)) {
            http_response_code(404);
            view('error', [
                'title' => 'Privacy Policy Not Found',
                'message' => 'The privacy policy document is not available.',
            ]);
            return;
        }

        $markdown = file_get_contents($filePath);

        require_once __DIR__ . '/../../lib/Parsedown/Parsedown.php';
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $parsedown->setBreaksEnabled(true);
        $html = $parsedown->text($markdown);

        view('privacy', [
            'user' => Auth::getInstance()->user(),
            'content' => $html,
        ]);
    }
}
