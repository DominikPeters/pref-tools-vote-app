<?php

namespace App\Services;

use App\Models\SiteSetting;

class ModerationService
{
    private const API_URL = 'https://api.openai.com/v1/moderations';
    private const MODEL = 'omni-moderation-latest';
    private const TIMEOUT = 2; // seconds

    /**
     * All moderation categories from OpenAI API
     */
    public const CATEGORIES = [
        'sexual',
        'sexual/minors',
        'harassment',
        'harassment/threatening',
        'hate',
        'hate/threatening',
        'illicit',
        'illicit/violent',
        'self-harm',
        'self-harm/intent',
        'self-harm/instructions',
        'violence',
        'violence/graphic',
    ];

    /**
     * Default thresholds (0-1 scale, higher = more permissive)
     */
    public const DEFAULT_THRESHOLDS = [
        'sexual' => 0.8,
        'sexual/minors' => 0.01,
        'harassment' => 0.7,
        'harassment/threatening' => 0.5,
        'hate' => 0.7,
        'hate/threatening' => 0.5,
        'illicit' => 0.8,
        'illicit/violent' => 0.5,
        'self-harm' => 0.7,
        'self-harm/intent' => 0.5,
        'self-harm/instructions' => 0.3,
        'violence' => 0.8,
        'violence/graphic' => 0.6,
    ];

    /**
     * Check if moderation is configured (API key present)
     */
    public static function isConfigured(): bool
    {
        $apiKey = SiteSetting::get('api.openai_key', '');
        return !empty($apiKey);
    }

    /**
     * Check if moderation is enabled
     */
    public static function isEnabled(): bool
    {
        return self::isConfigured() &&
               SiteSetting::getBool('moderation.enabled', false);
    }

    /**
     * Build markdown string from poll data for moderation
     */
    public static function buildContentString(array $pollData): string
    {
        $parts = [];

        if (!empty($pollData['title'])) {
            $parts[] = '# ' . $pollData['title'];
        }
        if (!empty($pollData['description'])) {
            $parts[] = $pollData['description'];
        }

        if (!empty($pollData['questions'])) {
            foreach ($pollData['questions'] as $qIndex => $question) {
                if (!empty($question['text'])) {
                    $parts[] = '## Question ' . ($qIndex + 1) . ': ' . $question['text'];
                }
                if (!empty($question['description'])) {
                    $parts[] = $question['description'];
                }

                if (!empty($question['options'])) {
                    foreach ($question['options'] as $option) {
                        if (!empty($option['label'])) {
                            $parts[] = '- ' . $option['label'];
                        }
                        if (!empty($option['description'])) {
                            $parts[] = '  ' . $option['description'];
                        }
                    }
                }
            }
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Moderate content using OpenAI API
     *
     * @return array{ok: bool, flagged: bool, skipped?: bool, reason?: string, flagged_categories?: array, all_scores?: array, error?: string}
     */
    public static function moderate(string $content): array
    {
        if (!self::isEnabled()) {
            return [
                'ok' => true,
                'flagged' => false,
                'skipped' => true,
                'reason' => 'Moderation not enabled',
            ];
        }

        if (empty(trim($content))) {
            return [
                'ok' => true,
                'flagged' => false,
                'skipped' => true,
                'reason' => 'Empty content',
            ];
        }

        $apiKey = SiteSetting::get('api.openai_key', '');

        try {
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => self::MODEL,
                    'input' => $content,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return self::handleApiError("cURL error: $error");
            }

            if ($httpCode !== 200) {
                return self::handleApiError("HTTP $httpCode: $response");
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return self::handleApiError('Invalid JSON response');
            }

            if (empty($result['results'][0])) {
                return self::handleApiError('Missing results in response');
            }

            return self::analyzeResults($result['results'][0]);

        } catch (\Exception $e) {
            return self::handleApiError('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Analyze moderation results against thresholds
     */
    private static function analyzeResults(array $result): array
    {
        $categoryScores = $result['category_scores'] ?? [];
        $flaggedCategories = [];
        $thresholds = self::getThresholds();

        foreach (self::CATEGORIES as $category) {
            $score = $categoryScores[$category] ?? 0;
            $threshold = $thresholds[$category] ?? 1.0;

            if ($score >= $threshold) {
                $flaggedCategories[$category] = [
                    'score' => $score,
                    'threshold' => $threshold,
                ];
            }
        }

        return [
            'ok' => true,
            'flagged' => !empty($flaggedCategories),
            'flagged_categories' => $flaggedCategories,
            'all_scores' => $categoryScores,
            'api_flagged' => $result['flagged'] ?? false,
        ];
    }

    /**
     * Get configured thresholds from settings
     */
    public static function getThresholds(): array
    {
        $thresholds = [];
        foreach (self::CATEGORIES as $category) {
            $settingKey = 'moderation.threshold.' . str_replace('/', '_', $category);
            $value = SiteSetting::get($settingKey);
            $thresholds[$category] = $value !== null
                ? (float) $value
                : (self::DEFAULT_THRESHOLDS[$category] ?? 0.5);
        }
        return $thresholds;
    }

    /**
     * Handle API errors based on fail_open setting
     */
    private static function handleApiError(string $message): array
    {
        error_log("ModerationService error: $message");

        $failOpen = SiteSetting::getBool('moderation.fail_open', true);

        if ($failOpen) {
            return [
                'ok' => true,
                'flagged' => false,
                'error' => $message,
                'skipped' => true,
                'reason' => 'API error (fail-open)',
            ];
        }

        return [
            'ok' => false,
            'flagged' => true,
            'error' => $message,
            'reason' => 'API error (fail-closed)',
        ];
    }

    /**
     * Generate human-readable error message for flagged content
     */
    public static function getFlaggedMessage(array $moderationResult): string
    {
        if (empty($moderationResult['flagged_categories'])) {
            return 'Content was flagged by moderation system.';
        }

        $categories = array_keys($moderationResult['flagged_categories']);
        $readable = array_map(function ($cat) {
            return str_replace(['/', '_', '-'], ' ', $cat);
        }, $categories);

        return 'Content was flagged for: ' . implode(', ', $readable) .
               '. Please revise your content and try again.';
    }
}
