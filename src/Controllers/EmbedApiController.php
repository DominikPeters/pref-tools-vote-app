<?php

namespace App\Controllers;

use App\Models\Poll;
use App\Models\Response;
use App\Models\Answer;
use App\Models\Report;
use App\Services\LogService;
use App\i18n\Translator;

class EmbedApiController extends ApiController
{
    /**
     * Set CORS headers for embed endpoints
     */
    private function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400');

        // Remove X-Frame-Options to allow embedding
        header_remove('X-Frame-Options');
    }

    /**
     * Handle OPTIONS preflight requests
     */
    public function preflight(): array
    {
        $this->setCorsHeaders();
        http_response_code(204);
        return [];
    }

    /**
     * GET /api/embed/:publicId/:embedToken - Get poll for embedding
     */
    public function show(array $params): array
    {
        $this->setCorsHeaders();

        $poll = Poll::findByEmbedToken($params['publicId'], $params['embedToken']);

        if (!$poll) {
            return $this->error($this->t('error_poll_not_found_or_invalid_embed_token'), 'NOT_FOUND', 404);
        }

        $this->messageLocale = $poll->locale;

        if (!$poll->canBeEmbedded()) {
            $reason = $this->t('error_this_poll_cannot_be_embedded');
            if ($poll->votingMode !== 'open') {
                $reason = $this->t('error_only_polls_with_open_voting_mode_can_be_embedded');
            } elseif ($poll->status !== 'open') {
                $reason = $this->t('error_this_poll_is_not_currently_open_for_voting');
            } elseif (!$poll->allowEmbedding) {
                $reason = $this->t('error_embedding_is_not_enabled_for_this_poll');
            }
            return $this->error($reason, 'NOT_EMBEDDABLE', 403);
        }

        $poll->loadQuestions();

        // Get translations for poll locale
        $translations = Translator::getLegacyLabelsForLocale($poll->locale);

        return $this->success([
            'poll' => $poll->toEmbedArray(),
            'translations' => $translations,
            'i18n' => Translator::configuration($poll->locale),
            'locale' => $poll->locale,
            'site_url' => url($poll->publicId),
            'results_url' => $poll->areResultsViewable() ? url("{$poll->publicId}/results") : null,
        ]);
    }

    /**
     * POST /api/embed/:publicId/:embedToken/responses - Submit response from embed
     */
    public function submitResponse(array $params): array
    {
        $this->setCorsHeaders();

        $poll = Poll::findByEmbedToken($params['publicId'], $params['embedToken']);

        if (!$poll) {
            return $this->error($this->t('error_poll_not_found_or_invalid_embed_token'), 'NOT_FOUND', 404);
        }

        $this->messageLocale = $poll->locale;

        if (!$poll->canBeEmbedded()) {
            return $this->error($this->t('error_this_poll_cannot_be_embedded'), 'NOT_EMBEDDABLE', 403);
        }

        if ($poll->status !== 'open') {
            return $this->error($this->t('error_this_poll_is_not_accepting_responses'), 'POLL_CLOSED', 400);
        }

        $data = $this->getBody() ?? [];

        // Log the embed submission source
        $origin = $_SERVER['HTTP_ORIGIN'] ?? 'unknown';
        LogService::getInstance()->log('embed.response', $poll->id, null, null, [
            'origin' => $origin,
        ]);

        // Process "Other" answers if present
        $poll->loadQuestions();
        if (!empty($data['answers'])) {
            $data['answers'] = $this->processOtherAnswers($poll, $data['answers']);
        }

        try {
            // Create response - embed always uses open mode (anonymous)
            $response = Response::create($poll->id, [
                'voter_name' => $poll->collectName ? ($data['voter_name'] ?? null) : null,
                'answers' => $data['answers'] ?? [],
            ], false);

            // Lock voting mode on first response
            $poll->lockMode();

            // Invalidate report caches
            Report::invalidateCacheForPoll($poll->id);

            return $this->success([
                'thank_you_message' => $poll->thankYouMessage ? markdown($poll->thankYouMessage) : null,
                'results_viewable' => $poll->areResultsViewable(),
                'site_url' => url($poll->publicId),
                'results_url' => $poll->areResultsViewable() ? url("{$poll->publicId}/results") : null,
            ]);
        } catch (\Exception $e) {
            return $this->error($this->t('error_failed_to_submit_response_detail', ['detail' => $e->getMessage()]), 'SUBMIT_FAILED', 500);
        }
    }

    /**
     * POST /api/polls/:publicId/admin/:adminToken/embed-token - Generate embed token
     */
    public function generateToken(array $params): array
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            return $this->error($this->t('error_poll_not_found'), 'NOT_FOUND', 404);
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            return $this->error($this->t('error_invalid_admin_token'), 'INVALID_TOKEN', 403);
        }

        if ($poll->votingMode !== 'open') {
            return $this->error($this->t('error_embedding_is_only_available_for_polls_with_open_voting_mode'), 'INVALID_MODE', 400);
        }

        $embedToken = $poll->getOrCreateEmbedToken();

        return $this->success([
            'embed_token' => $embedToken,
            'embed_url' => url("{$poll->publicId}/embed/{$embedToken}"),
        ]);
    }

    /**
     * Process "Other" answers - convert { other: "text" } to real option IDs
     * Similar to PollApiController::processOtherAnswers
     */
    private function processOtherAnswers(Poll $poll, array $answers): array
    {
        $questionMap = [];
        foreach ($poll->questions as $q) {
            $questionMap[$q->id] = $q;
        }

        foreach ($answers as $questionId => $answer) {
            if (!isset($questionMap[$questionId])) {
                continue;
            }

            $question = $questionMap[$questionId];

            // Handle single choice with "other"
            if (is_array($answer) && isset($answer['other'])) {
                $otherOption = $question->findOrCreateOtherOption($answer['other']);
                if ($otherOption) {
                    $answers[$questionId] = $otherOption->id;
                }
            }

            // Handle approval with "other" in array
            if (is_array($answer) && !isset($answer['other'])) {
                foreach ($answer as $i => $item) {
                    if (is_array($item) && isset($item['other'])) {
                        $otherOption = $question->findOrCreateOtherOption($item['other']);
                        if ($otherOption) {
                            $answers[$questionId][$i] = $otherOption->id;
                        }
                    }
                }
            }
        }

        return $answers;
    }
}
