<?php

namespace App\Controllers;

use App\Models\Poll;
use App\Models\User;
use App\Models\EmailInvitation;
use App\Services\InvitationService;

class InvitationApiController extends ApiController
{
    private InvitationService $invitationService;

    public function __construct()
    {
        $this->invitationService = new InvitationService();
    }

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
     * Check if poll owner is email verified (if poll has an owner)
     */
    private function checkOwnerEmailVerified(Poll $poll): ?array
    {
        if ($poll->userId) {
            $owner = User::find($poll->userId);
            if ($owner && !$owner->isEmailVerified()) {
                return $this->error(
                    'You must verify your email address before sending invitations. Please check your inbox for the verification link.',
                    'EMAIL_NOT_VERIFIED',
                    403
                );
            }
        }
        return null;
    }

    /**
     * Check if the requirement for email invitations is met:
     * User must be logged in and must be the owner of the poll.
     */
    private function checkOwnerRequirement(Poll $poll): ?array
    {
        $user = $this->user();
        if (!$user) {
            return $this->error(
                'You must be logged in to use email invitations.',
                'AUTH_REQUIRED',
                401
            );
        }

        if ($poll->userId === null) {
            return $this->error(
                'This poll must be linked to your account before you can send email invitations.',
                'POLL_NOT_LINKED',
                403
            );
        }

        if ($poll->userId !== $user->id) {
            return $this->error(
                'Only the owner of this poll can manage email invitations.',
                'NOT_OWNER',
                403
            );
        }

        return null;
    }

    /**
     * List all email invitations for a poll
     * GET /api/polls/:publicId/admin/:adminToken/invitations
     */
    public function list(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        // Check owner requirement
        $requirementError = $this->checkOwnerRequirement($poll);
        if ($requirementError) {
            return $requirementError;
        }

        $invitations = EmailInvitation::findByPollId($poll->id);

        return $this->success([
            'invitations' => array_map(function ($inv) use ($poll) {
                $arr = $inv->toArray();
                $arr['url'] = url($poll->publicId . '?token=' . $inv->token);
                return $arr;
            }, $invitations),
            'mail_configured' => $this->invitationService->isMailConfigured(),
        ]);
    }

    /**
     * Send email invitations
     * POST /api/polls/:publicId/admin/:adminToken/invitations
     */
    public function send(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        // Check owner requirement
        $requirementError = $this->checkOwnerRequirement($poll);
        if ($requirementError) {
            return $requirementError;
        }

        // Check if poll owner has verified their email
        $verificationError = $this->checkOwnerEmailVerified($poll);
        if ($verificationError) {
            return $verificationError;
        }

        if (!$this->invitationService->isMailConfigured()) {
            return $this->error(
                'Email is not configured. Please configure SMTP settings in the sysadmin panel.',
                'MAIL_NOT_CONFIGURED',
                400
            );
        }

        $data = $this->getBody() ?? [];
        $emailsRaw = $data['emails'] ?? '';

        // Parse emails - support comma, semicolon, newline separators
        $emails = preg_split('/[\s,;]+/', $emailsRaw, -1, PREG_SPLIT_NO_EMPTY);
        $emails = array_filter($emails);

        if (empty($emails)) {
            return $this->error('No valid email addresses provided', 'NO_EMAILS', 400);
        }

        $results = $this->invitationService->sendInvitations($poll, $emails);

        // Get updated list
        $invitations = EmailInvitation::findByPollId($poll->id);

        return $this->success([
            'sent_count' => count($results['sent']),
            'failed_count' => count($results['failed']),
            'existing_count' => count($results['existing']),
            'blocked_count' => count($results['blocked']),
            'sent' => $results['sent'],
            'failed' => $results['failed'],
            'existing' => $results['existing'],
            'blocked' => $results['blocked'],
            'invitations' => array_map(function ($inv) use ($poll) {
                $arr = $inv->toArray();
                $arr['url'] = url($poll->publicId . '?token=' . $inv->token);
                return $arr;
            }, $invitations),
        ]);
    }

    /**
     * Resend an invitation
     * POST /api/polls/:publicId/admin/:adminToken/invitations/:invitationId/resend
     */
    public function resend(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        // Check owner requirement
        $requirementError = $this->checkOwnerRequirement($poll);
        if ($requirementError) {
            return $requirementError;
        }

        // Check if poll owner has verified their email
        $verificationError = $this->checkOwnerEmailVerified($poll);
        if ($verificationError) {
            return $verificationError;
        }

        if (!$this->invitationService->isMailConfigured()) {
            return $this->error(
                'Email is not configured. Please configure SMTP settings in the sysadmin panel.',
                'MAIL_NOT_CONFIGURED',
                400
            );
        }

        $invitation = EmailInvitation::find((int) ($params['invitationId'] ?? 0));
        if (!$invitation || $invitation->pollId !== $poll->id) {
            return $this->error('Invitation not found', 'NOT_FOUND', 404);
        }

        if ($invitation->usedAt) {
            return $this->error('Cannot resend to an invitation that has already been used', 'ALREADY_USED', 400);
        }

        try {
            $result = $this->invitationService->resendInvitation($poll, $invitation);

            if ($result['blocked']) {
                return $this->error(
                    'This email address has unsubscribed from invitation emails',
                    'EMAIL_BLOCKED',
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->error('Failed to send email: ' . $e->getMessage(), 'SEND_FAILED', 500);
        }

        $arr = $invitation->toArray();
        $arr['url'] = url($poll->publicId . '?token=' . $invitation->token);

        return $this->success(['invitation' => $arr]);
    }

    /**
     * Delete an invitation
     * DELETE /api/polls/:publicId/admin/:adminToken/invitations/:invitationId
     */
    public function delete(array $params): array
    {
        $poll = $this->getPollWithAdminAuth($params);
        if (!$poll) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 403);
        }

        // Check owner requirement
        $requirementError = $this->checkOwnerRequirement($poll);
        if ($requirementError) {
            return $requirementError;
        }

        $invitation = EmailInvitation::find((int) ($params['invitationId'] ?? 0));
        if (!$invitation || $invitation->pollId !== $poll->id) {
            return $this->error('Invitation not found', 'NOT_FOUND', 404);
        }

        if ($invitation->usedAt) {
            return $this->error('Cannot delete an invitation that has been used', 'ALREADY_USED', 400);
        }

        $invitation->delete();

        return $this->success();
    }
}
