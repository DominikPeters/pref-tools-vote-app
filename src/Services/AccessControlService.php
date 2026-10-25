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
                // Token is used if usedAt is set OR isSecretBallot is true (for secret ballots, no timestamp is stored)
                if ($accessToken->usedAt || $accessToken->isSecretBallot) {
                    return [
                        'allowed' => false,
                        'identity' => null,
                        'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_this_access_link_has_already_been_used'),
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
                // Invitation is used if usedAt is set OR isSecretBallot is true
                if ($emailInvite->usedAt || $emailInvite->isSecretBallot) {
                    return [
                        'allowed' => false,
                        'identity' => null,
                        'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_this_invitation_link_has_already_been_used'),
                    ];
                }
                // Submission timing would link a secret ballot back to its invitation.
                if ($poll->votingMode !== 'secret_ballot') {
                    $emailInvite->markClicked();
                }

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
                'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_invalid_access_token'),
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
                        'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_you_have_already_voted_in_this_poll'),
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
            'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_valid_access_token_required_to_vote_in_this_poll'),
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
            'error' => \App\i18n\Translator::translateForLocale($poll->locale, 'error_password_required_to_access_this_poll'),
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
