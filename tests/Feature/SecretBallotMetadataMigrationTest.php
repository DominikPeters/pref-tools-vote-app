<?php

namespace Tests\Feature;

use App\Database;
use App\Models\AccessToken;
use App\Models\EmailInvitation;
use App\Models\Response;
use App\Services\LogService;
use Tests\TestCase;

class SecretBallotMetadataMigrationTest extends TestCase
{
    public function test_cleanup_preserves_ballots_and_eligibility_and_leaves_other_polls_untouched(): void
    {
        $db = Database::getInstance();
        $secret = $this->createPoll(['voting_mode' => 'secret_ballot', 'notify_on_response' => true]);
        $identified = $this->createPoll(['voting_mode' => 'identified', 'notify_on_response' => true]);
        $question = $this->createQuestion($secret->id);
        $ballot = Response::create($secret->id, [
            'answers' => [$question->id => $question->options[0]->id],
        ], true);
        $token = AccessToken::generate($secret->id, 1)[0];
        $token->markUsed($ballot->id, true);
        $secretInvite = EmailInvitation::create($secret->id, 'secret@example.com');
        $secretInvite->markUsed($ballot->id, true);
        $secretInvite->markClicked(); // Reproduce historical identifying metadata.
        $otherInvite = EmailInvitation::create($identified->id, 'other@example.com');
        $otherInvite->markClicked();
        LogService::getInstance()->log('email.response_notification_sent', $secret->id, null, $ballot->id, ['legacy' => true]);
        LogService::getInstance()->log('response.submitted', $identified->id, null, null, ['keep' => true]);
        $ballotBefore = $db->fetch('SELECT * FROM responses WHERE id = :id', ['id' => $ballot->id]);
        $answersBefore = $db->fetchAll('SELECT * FROM answers WHERE response_id = :id', ['id' => $ballot->id]);

        $sql = file_get_contents(MIGRATIONS_PATH . '/004_secret_ballot_metadata.sql');
        $db->runMigration($sql);
        $db->runMigration($sql); // Safe to retry.

        $this->assertSame($ballotBefore, $db->fetch('SELECT * FROM responses WHERE id = :id', ['id' => $ballot->id]));
        $this->assertSame($answersBefore, $db->fetchAll('SELECT * FROM answers WHERE response_id = :id', ['id' => $ballot->id]));
        $this->assertTrue(AccessToken::find($token->id)->isUsed());
        $this->assertTrue(EmailInvitation::find($secretInvite->id)->isUsed());
        $this->assertNull(EmailInvitation::find($secretInvite->id)->clickedAt);
        $this->assertNotNull(EmailInvitation::find($otherInvite->id)->clickedAt);
        $log = $db->fetch('SELECT * FROM action_log WHERE poll_id = :id', ['id' => $secret->id]);
        foreach (['ip_address', 'user_id', 'response_id', 'data'] as $field) {
            $this->assertNull($log[$field]);
        }
        $this->assertFalse(\App\Models\Poll::find($secret->id)->notifyOnResponse);
        $this->assertTrue(\App\Models\Poll::find($identified->id)->notifyOnResponse);
        $otherLog = $db->fetch('SELECT * FROM action_log WHERE poll_id = :id', ['id' => $identified->id]);
        $this->assertNotNull($otherLog['ip_address']);
        $this->assertSame('{"keep":true}', $otherLog['data']);
    }
}
