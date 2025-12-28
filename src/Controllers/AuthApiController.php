<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\Models\Poll;
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
     * GET /api/user/polls
     */
    public function userPolls(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $polls = Poll::findByUserId($this->user()->id);

        return $this->success([
            'polls' => array_map(fn($v) => [
                'public_id' => $v->publicId,
                'title' => $v->title,
                'status' => $v->status,
                'response_count' => $v->getResponseCount(),
                'created_at' => $v->createdAt->format('c'),
            ], $polls),
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
            $poll = Poll::find($response->pollId);
            if ($poll) {
                $result[] = [
                    'poll_public_id' => $poll->publicId,
                    'poll_title' => $poll->title,
                    'created_at' => $response->createdAt->format('c'),
                ];
            }
        }

        return $this->success(['responses' => $result]);
    }

    /**
     * POST /api/user/claim-poll
     */
    public function claimPoll(array $params): array
    {
        $authError = $this->requireAuth();
        if ($authError) {
            return $authError;
        }

        $data = $this->getBody();

        if (empty($data['public_id']) || empty($data['admin_token'])) {
            return $this->error('public_id and admin_token are required', 'MISSING_FIELDS', 400);
        }

        $poll = Poll::findByPublicId($data['public_id']);

        if (!$poll) {
            return $this->error('Poll not found', 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($data['admin_token'])) {
            return $this->error('Invalid admin token', 'INVALID_TOKEN', 403);
        }

        if ($poll->userId !== null) {
            return $this->error('Poll already claimed', 'ALREADY_CLAIMED', 400);
        }

        $poll = $poll->update(['user_id' => $this->user()->id]);

        return $this->success(['poll' => $poll->toAdminArray()]);
    }
}
