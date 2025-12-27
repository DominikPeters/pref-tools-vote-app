<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\Models\Vote;
use App\Models\Response;
use App\Services\LogService;

class AuthApiController extends ApiController
{
    /**
     * POST /api/auth/register
     */
    public function register(array $params): array
    {
        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validation) {
            return $validation;
        }

        try {
            $auth = Auth::getInstance();
            $user = $auth->register($data['email'], $data['password']);
            $auth->login($user);

            LogService::getInstance()->log('user.registered', null, $user->id);

            return $this->success(['user' => $user->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'REGISTRATION_FAILED', 400);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(array $params): array
    {
        $data = $this->getBody();

        $validation = $this->validate($data ?? [], [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validation) {
            return $validation;
        }

        $auth = Auth::getInstance();
        $user = $auth->attempt($data['email'], $data['password']);

        if (!$user) {
            return $this->error('Invalid credentials', 'INVALID_CREDENTIALS', 401);
        }

        LogService::getInstance()->log('user.login', null, $user->id);

        return $this->success(['user' => $user->toArray()]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(array $params): array
    {
        $auth = Auth::getInstance();
        $userId = $auth->id();

        $auth->logout();

        if ($userId) {
            LogService::getInstance()->log('user.logout', null, $userId);
        }

        return $this->success();
    }

    /**
     * GET /api/auth/me
     */
    public function me(array $params): array
    {
        $user = $this->user();

        if (!$user) {
            return $this->error('Not authenticated', 'NOT_AUTHENTICATED', 401);
        }

        return $this->success(['user' => $user->toArray()]);
    }

    /**
     * GET /api/user/votes
     */
    public function userVotes(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $votes = Vote::findByUserId($this->user()->id);

        return $this->success([
            'votes' => array_map(fn($v) => [
                'public_id' => $v->publicId,
                'title' => $v->title,
                'status' => $v->status,
                'response_count' => $v->getResponseCount(),
                'created_at' => $v->createdAt->format('c'),
            ], $votes),
        ]);
    }

    /**
     * GET /api/user/responses
     */
    public function userResponses(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $responses = Response::findByUserId($this->user()->id);

        $result = [];
        foreach ($responses as $response) {
            $vote = Vote::find($response->voteId);
            if ($vote) {
                $result[] = [
                    'vote_public_id' => $vote->publicId,
                    'vote_title' => $vote->title,
                    'created_at' => $response->createdAt->format('c'),
                ];
            }
        }

        return $this->success(['responses' => $result]);
    }

    /**
     * POST /api/user/claim-vote
     */
    public function claimVote(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        if (empty($data['public_id']) || empty($data['admin_token'])) {
            return $this->error('public_id and admin_token are required', 'MISSING_FIELDS', 400);
        }

        $vote = Vote::findByPublicId($data['public_id']);

        if (!$vote) {
            return $this->error('Vote not found', 'NOT_FOUND', 404);
        }

        if (!$vote->verifyAdminToken($data['admin_token'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        if ($vote->userId !== null) {
            return $this->error('Vote already claimed', 'ALREADY_CLAIMED', 400);
        }

        $vote = $vote->update(['user_id' => $this->user()->id]);

        return $this->success(['vote' => $vote->toAdminArray()]);
    }
}
