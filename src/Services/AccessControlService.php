<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\AccessToken;
use App\Models\EmailInvitation;
use App\Models\Response;
use App\Auth;

class AccessControlService
{
    /**
     * Validate access to a poll based on its voting mode and access methods
     * Returns: ['allowed' => bool, 'identity' => ?array, 'error' => ?string]
     */
    public function validateAccess(Poll $poll, ?string $token = null): array
    {
        // Open mode - anyone can access
        if ($poll->votingMode === 'open') {
            // Check password if required
            if ($poll->accessMode === 'password') {
                return $this->validatePasswordAccess($poll);
            }
            return ['allowed' => true, 'identity' => null, 'error' => null];
        }

        // Identified or Secret Ballot mode - requires authentication
        $user = Auth::getInstance()->user();

        // Try token-based access first
        if ($token) {
            // Check access tokens
            $accessToken = AccessToken::findByToken($poll->id, $token);
            if ($accessToken) {
                if ($accessToken->usedAt) {
                    return [
                        'allowed' => false,
                        'identity' => null,
                        'error' => 'This access link has already been used',
                    ];
                }
                return [
                    'allowed' => true,
                    'identity' => [
                        'type' => 'token',
                        'token_id' => $accessToken->id,
                        'label' => $accessToken->label,
                    ],
                    'error' => null,
                ];
            }

            // Check email invitations
            $emailInvite = EmailInvitation::findByToken($poll->id, $token);
            if ($emailInvite) {
                if ($emailInvite->usedAt) {
                    return [
                        'allowed' => false,
                        'identity' => null,
                        'error' => 'This invitation link has already been used',
                    ];
                }
                // Track that the link was clicked (for deliverability tracking)
                $emailInvite->markClicked();

                return [
                    'allowed' => true,
                    'identity' => [
                        'type' => 'email',
                        'invitation_id' => $emailInvite->id,
                        'email' => $emailInvite->email,
                    ],
                    'error' => null,
                ];
            }

            // Invalid token
            return [
                'allowed' => false,
                'identity' => null,
                'error' => 'Invalid access token',
            ];
        }

        // Try login-based access (if enabled and user is logged in)
        $accessMethods = $poll->accessMethods ?? [];
        if (in_array('login', $accessMethods) && $user) {
            // Check if user already voted
            $existingResponse = Response::findByUserIdAndPollId($user->id, $poll->id);
            if ($existingResponse) {
                if ($poll->votingMode === 'secret_ballot') {
                    return [
                        'allowed' => false,
                        'identity' => null,
                        'error' => 'You have already voted in this poll',
                    ];
                }
                // For identified mode, allow returning to edit
                return [
                    'allowed' => true,
                    'identity' => [
                        'type' => 'login',
                        'user_id' => $user->id,
                        'existing_response_id' => $existingResponse->id,
                    ],
                    'error' => null,
                ];
            }
            return [
                'allowed' => true,
                'identity' => [
                    'type' => 'login',
                    'user_id' => $user->id,
                ],
                'error' => null,
            ];
        }

        // No valid authentication
        return [
            'allowed' => false,
            'identity' => null,
            'error' => 'Valid access token required to vote in this poll',
        ];
    }

    /**
     * Validate password-protected poll access via session
     */
    private function validatePasswordAccess(Poll $poll): array
    {
        $sessionKey = 'poll_access_' . $poll->publicId;
        if (!empty($_SESSION[$sessionKey])) {
            return ['allowed' => true, 'identity' => null, 'error' => null];
        }

        return [
            'allowed' => false,
            'identity' => null,
            'error' => 'Password required to access this poll',
        ];
    }

    /**
     * Mark access as used after successful vote submission
     */
    public function markAccessUsed(Poll $poll, array $identity, int $responseId): void
    {
        $isSecretBallot = $poll->votingMode === 'secret_ballot';

        switch ($identity['type'] ?? null) {
            case 'token':
                $token = AccessToken::find($identity['token_id']);
                if ($token) {
                    $token->markUsed($responseId, $isSecretBallot);
                }
                break;

            case 'email':
                $invitation = EmailInvitation::find($identity['invitation_id']);
                if ($invitation) {
                    $invitation->markUsed($responseId, $isSecretBallot);
                }
                break;

            // 'login' type doesn't need marking - tracked via response.user_id
        }
    }
}
