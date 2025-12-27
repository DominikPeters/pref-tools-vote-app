<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Vote;

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
     * GET /create - Form builder (new vote)
     * GET /:publicId/admin/:adminToken/edit - Form builder (edit existing)
     */
    public function builder(array $params): void
    {
        $vote = null;
        $adminToken = null;

        // Check if editing an existing vote
        if (!empty($params['publicId']) && !empty($params['adminToken'])) {
            $vote = Vote::findByPublicId($params['publicId']);

            if (!$vote) {
                http_response_code(404);
                view('error', [
                    'title' => 'Vote Not Found',
                    'message' => 'The vote you are looking for does not exist.',
                ]);
                return;
            }

            if (!$vote->verifyAdminToken($params['adminToken'])) {
                http_response_code(403);
                view('error', [
                    'title' => 'Access Denied',
                    'message' => 'Invalid admin token.',
                ]);
                return;
            }

            $vote->loadQuestions();
            $adminToken = $params['adminToken'];
        }

        view('builder', [
            'user' => Auth::getInstance()->user(),
            'vote' => $vote,
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

        $votes = Vote::findByUserId($user->id);

        view('dashboard', [
            'user' => $user,
            'votes' => $votes,
        ]);
    }

    /**
     * GET /:publicId - Voter form
     */
    public function vote(array $params): void
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            http_response_code(404);
            view('error', [
                'title' => 'Vote Not Found',
                'message' => 'The vote you are looking for does not exist.',
            ]);
            return;
        }

        // If vote is closed and results are public, redirect to results
        if ($vote->status === 'closed' && $vote->visibility !== 'private') {
            header('Location: ' . url("{$vote->publicId}/results"));
            exit;
        }

        // Check password protection
        if ($vote->accessMode === 'password' && $vote->accessPassword) {
            $sessionKey = 'vote_access_' . $vote->publicId;

            // Handle password submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
                if ($vote->verifyAccessPassword($_POST['access_password'])) {
                    $_SESSION[$sessionKey] = true;
                    header('Location: ' . url($vote->publicId));
                    exit;
                } else {
                    view('password', [
                        'vote' => $vote,
                        'error' => 'Incorrect password. Please try again.',
                    ]);
                    return;
                }
            }

            // Check if already unlocked
            if (empty($_SESSION[$sessionKey])) {
                view('password', [
                    'vote' => $vote,
                    'error' => null,
                ]);
                return;
            }
        }

        // Check token-based access
        if ($vote->accessMode === 'token') {
            $token = $_GET['token'] ?? null;
            if ($token) {
                $accessToken = \App\Models\AccessToken::findByToken($vote->id, $token);
                if ($accessToken && !$accessToken->usedAt) {
                    // Valid token, store in session
                    $_SESSION['vote_token_' . $vote->publicId] = $token;
                } elseif (!$accessToken) {
                    http_response_code(403);
                    view('error', [
                        'title' => 'Invalid Access Token',
                        'message' => 'The access token is invalid or has expired.',
                    ]);
                    return;
                }
            } elseif (empty($_SESSION['vote_token_' . $vote->publicId])) {
                http_response_code(403);
                view('error', [
                    'title' => 'Access Token Required',
                    'message' => 'This vote requires an access token. Please use the link provided by the vote creator.',
                ]);
                return;
            }
        }

        $vote->loadQuestions();

        // Check for existing response (edit-own-vote)
        $existingResponse = null;
        $voterToken = $_COOKIE['voter_token_' . $vote->publicId] ?? null;
        if ($voterToken && ($vote->allowEditOwn || $vote->allowEditAny)) {
            $existingResponse = \App\Models\Response::findByVoterToken($vote->id, $voterToken);
            if ($existingResponse) {
                $existingResponse->loadAnswers();
            }
        }

        view('vote', [
            'vote' => $vote,
            'user' => Auth::getInstance()->user(),
            'existingResponse' => $existingResponse,
        ]);
    }

    /**
     * GET /:publicId/results - Results page
     */
    public function results(array $params): void
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            http_response_code(404);
            view('error', [
                'title' => 'Vote Not Found',
                'message' => 'The vote you are looking for does not exist.',
            ]);
            return;
        }

        // Check if results are visible
        $canView = false;
        if ($vote->visibility !== 'private') {
            if ($vote->visibilityTiming === 'during' || $vote->status === 'closed') {
                $canView = true;
            }
        }

        if (!$canView) {
            http_response_code(403);
            view('error', [
                'title' => 'Results Not Available',
                'message' => 'The results for this vote are not yet available.',
            ]);
            return;
        }

        $vote->loadQuestions();

        view('results', [
            'vote' => $vote,
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken - Admin panel
     */
    public function admin(array $params): void
    {
        $vote = Vote::findByPublicId($params['publicId']);

        if (!$vote) {
            http_response_code(404);
            view('error', [
                'title' => 'Vote Not Found',
                'message' => 'The vote you are looking for does not exist.',
            ]);
            return;
        }

        if (!$vote->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => 'Access Denied',
                'message' => 'Invalid admin token.',
            ]);
            return;
        }

        $vote->loadQuestions();

        view('admin', [
            'vote' => $vote,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }
}
