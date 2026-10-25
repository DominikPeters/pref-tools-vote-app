-- Remove identity and timing links left by older secret-ballot submission paths.
-- Ballots, eligibility tokens and participation flags are left intact.
UPDATE email_invitations
SET clicked_at = NULL
WHERE poll_id IN (SELECT id FROM polls WHERE voting_mode = 'secret_ballot');

UPDATE action_log
SET ip_address = NULL, user_id = NULL, response_id = NULL, data = NULL
WHERE poll_id IN (SELECT id FROM polls WHERE voting_mode = 'secret_ballot')
  AND action IN ('response.submitted', 'response.edited', 'email.response_notification_sent');

UPDATE polls
SET notify_on_response = 0
WHERE voting_mode = 'secret_ballot';
