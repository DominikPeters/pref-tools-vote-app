<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\TokenService;

class VerificationTest extends TestCase
{
    public function test_set_verification_token(): void
    {
        $user = $this->createUser('token-test@example.com');
        $token = TokenService::generate(64);
        
        $user->setVerificationToken($token);
        
        $this->assertEquals($token, $user->emailVerificationToken);
        $this->assertNotNull($user->emailVerificationExpires);
        
        // Verify in DB
        $freshUser = User::find($user->id);
        $this->assertEquals($token, $freshUser->emailVerificationToken);
    }

    public function test_find_by_verification_token(): void
    {
        $user = $this->createUser('find-token@example.com');
        $token = 'some-unique-token-123';
        $user->setVerificationToken($token);
        
        $found = User::findByVerificationToken($token);
        
        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_find_by_verification_token_fails_if_expired(): void
    {
        $user = $this->createUser('expired-token@example.com');
        $token = 'expired-token-123';
        
        // Manually set an expired date in the DB
        $db = \App\Database::getInstance();
        $expired = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $db->update('users', [
            'email_verification_token' => $token,
            'email_verification_expires' => $expired
        ], 'id = :id', ['id' => $user->id]);
        
        $found = User::findByVerificationToken($token);
        
        $this->assertNull($found);
    }

    public function test_mark_email_verified(): void
    {
        $user = $this->createUser('mark-verified@example.com');
        $token = 'mark-token-123';
        $user->setVerificationToken($token);
        
        $this->assertFalse($user->isEmailVerified());
        $this->assertNotNull($user->emailVerificationToken);
        
        $user->markEmailVerified();
        
        $this->assertTrue($user->isEmailVerified());
        $this->assertNull($user->emailVerificationToken);
        $this->assertNull($user->emailVerificationExpires);
        $this->assertNotNull($user->emailVerifiedAt);
        
        // Verify in DB
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->isEmailVerified());
        $this->assertNull($freshUser->emailVerificationToken);
    }
}
