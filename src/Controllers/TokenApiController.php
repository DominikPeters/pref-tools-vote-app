<?php

namespace App\Controllers;

use App\Models\Poll;
use App\Models\AccessToken;

class TokenApiController extends ApiController
{
    /**
     * Get poll with admin authentication
     */
    private function getPollWithAdminAuth(array $params): ?Poll
    {
        $poll = Poll::findByPublicId($params['publicId'] ?? '');
        if (!$poll) {
            return null;
        }

        if (!$poll->verifyAdminToken($params['adminToken'] ?? '')) {
            return null;
        }

        return $poll;
    }

    /**
     * List all access tokens for a poll
     * GET /api/polls/:publicId/admin/:adminToken/tokens
     */
    public function list(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        $tokens = AccessToken::findByPollId($poll->id);

        return $this->success([
            'tokens' => array_map(function ($token) use ($poll) {
                $arr = $token->toArray();
                $arr['url'] = url($poll->publicId . '?token=' . $token->token);
                return $arr;
            }, $tokens),
        ]);
    }

    /**
     * Generate new access tokens
     * POST /api/polls/:publicId/admin/:adminToken/tokens
     */
    public function generate(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        $data = $this->getBody() ?? [];
        $count = min(max((int) ($data['count'] ?? 1), 1), 100); // 1-100 tokens
        $labelPrefix = isset($data['label_prefix']) && trim($data['label_prefix']) !== ''
            ? trim($data['label_prefix'])
            : null;

        $tokens = AccessToken::generate($poll->id, $count, $labelPrefix);

        return $this->success([
            'tokens' => array_map(function ($token) use ($poll) {
                $arr = $token->toArray();
                $arr['url'] = url($poll->publicId . '?token=' . $token->token);
                return $arr;
            }, $tokens),
        ]);
    }

    /**
     * Update a token's label
     * PUT /api/polls/:publicId/admin/:adminToken/tokens/:tokenId
     */
    public function update(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        $token = AccessToken::find((int) ($params['tokenId'] ?? 0));
        if (!$token || $token->pollId !== $poll->id) {
            return $this->error('Token not found', 'NOT_FOUND', 404);
        }

        $data = $this->getBody() ?? [];

        if (isset($data['label'])) {
            $db = \App\Database::getInstance();
            $db->update(
                'access_tokens',
                ['label' => $data['label']],
                'id = :id',
                ['id' => $token->id]
            );
            $token->label = $data['label'];
        }

        $arr = $token->toArray();
        $arr['url'] = url($poll->publicId . '?token=' . $token->token);

        return $this->success(['token' => $arr]);
    }

    /**
     * Delete an access token
     * DELETE /api/polls/:publicId/admin/:adminToken/tokens/:tokenId
     */
    public function delete(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        $token = AccessToken::find((int) ($params['tokenId'] ?? 0));
        if (!$token || $token->pollId !== $poll->id) {
            return $this->error('Token not found', 'NOT_FOUND', 404);
        }

        if ($token->usedAt) {
            return $this->error('Cannot delete a used token', 'TOKEN_USED', 400);
        }

        $token->delete();

        return $this->success();
    }
}
