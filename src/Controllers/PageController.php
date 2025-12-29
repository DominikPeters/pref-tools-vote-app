<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Poll;

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

        // Check token-based access
        if ($poll->accessMode === 'token') {
            $token = $_GET['token'] ?? null;
            if ($token) {
                $accessToken = \App\Models\AccessToken::findByToken($poll->id, $token);
                if ($accessToken && !$accessToken->usedAt) {
                    // Valid token, store in session
                    $_SESSION['poll_token_' . $poll->publicId] = $token;
                } elseif (!$accessToken) {
                    http_response_code(403);
                    view('error', [
                        'title' => 'Invalid Access Token',
                        'message' => 'The access token is invalid or has expired.',
                    ]);
                    return;
                }
            } elseif (empty($_SESSION['poll_token_' . $poll->publicId])) {
                http_response_code(403);
                view('error', [
                    'title' => 'Access Token Required',
                    'message' => 'This poll requires an access token. Please use the link provided by the poll creator.',
                ]);
                return;
            }
        }

        $poll->loadQuestions();

        // Check for existing response (edit-own-poll)
        $existingResponse = null;
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        if ($voterToken && ($poll->allowEditOwn || $poll->allowEditAny)) {
            $existingResponse = \App\Models\Response::findByVoterToken($poll->id, $voterToken);
            if ($existingResponse) {
                $existingResponse->loadAnswers();
            }
        }

        view('poll', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
            'existingResponse' => $existingResponse,
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
        $canView = false;
        if ($poll->visibility !== 'private') {
            if ($poll->visibilityTiming === 'during' || $poll->status === 'closed') {
                $canView = true;
            }
        }

        if (!$canView) {
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
}
