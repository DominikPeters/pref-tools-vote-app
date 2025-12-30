<?php

namespace App\Controllers;

use App\Services\UnsubscribeService;
use App\Services\LogService;

class UnsubscribeController
{
    /**
     * GET /unsubscribe - Display the unsubscribe page
     * The actual unsubscribe happens via JS calling the API
     */
    public function showPage(array $params): void
    {
        $email = $_GET['email'] ?? '';
        $signature = $_GET['sig'] ?? '';
        $action = $_GET['action'] ?? 'unsubscribe';

        // Validate signature
        $isValid = !empty($email) && !empty($signature) && UnsubscribeService::verifySignature($email, $signature);

        // Check current status
        $isUnsubscribed = $isValid ? UnsubscribeService::isUnsubscribed($email) : false;

        view('unsubscribe', [
            'email' => $email,
            'signature' => $signature,
            'action' => $action,
            'isValid' => $isValid,
            'isUnsubscribed' => $isUnsubscribed,
        ]);
    }

    /**
     * POST /api/unsubscribe - API endpoint for unsubscribe/resubscribe
     * Called by JS after page renders
     */
    public function handleApi(array $params): array
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $email = $input['email'] ?? '';
        $signature = $input['sig'] ?? '';
        $action = $input['action'] ?? 'unsubscribe';

        // Validate signature
        if (empty($email) || empty($signature)) {
            return ['error' => 'Missing email or signature', 'status' => 400];
        }

        if (!UnsubscribeService::verifySignature($email, $signature)) {
            return ['error' => 'Invalid signature', 'status' => 403];
        }

        if ($action === 'resubscribe') {
            UnsubscribeService::resubscribe($email);
            LogService::getInstance()->log('email_resubscribe', null, null, null, ['email' => $email]);
            return [
                'success' => true,
                'message' => 'You have been resubscribed and will receive future invitations.',
                'is_unsubscribed' => false,
            ];
        } else {
            UnsubscribeService::unsubscribe($email);
            LogService::getInstance()->log('email_unsubscribe', null, null, null, ['email' => $email]);
            return [
                'success' => true,
                'message' => 'You have been unsubscribed and will no longer receive invitation emails.',
                'is_unsubscribed' => true,
            ];
        }
    }

    /**
     * POST /unsubscribe/one-click - RFC 8058 one-click unsubscribe
     * Email clients send POST requests with body: List-Unsubscribe=One-Click
     */
    public function handleOneClick(array $params): void
    {
        $email = $_GET['email'] ?? '';
        $signature = $_GET['sig'] ?? '';

        // Validate signature
        if (empty($email) || empty($signature)) {
            http_response_code(400);
            echo 'Missing email or signature';
            return;
        }

        if (!UnsubscribeService::verifySignature($email, $signature)) {
            http_response_code(403);
            echo 'Invalid signature';
            return;
        }

        // Unsubscribe immediately
        UnsubscribeService::unsubscribe($email);
        LogService::getInstance()->log('email_unsubscribe_oneclick', null, null, null, ['email' => $email]);

        // Return success (RFC 8058 expects 200 OK)
        http_response_code(200);
        echo 'Unsubscribed successfully';
    }
}
