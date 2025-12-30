<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UnsubscribeService;
use App\Models\SiteSetting;

class UnsubscribeServiceTest extends TestCase
{
    public function testGenerateSignatureIsConsistent(): void
    {
        $email = 'test@example.com';

        $sig1 = UnsubscribeService::generateSignature($email);
        $sig2 = UnsubscribeService::generateSignature($email);

        $this->assertEquals($sig1, $sig2);
    }

    public function testGenerateSignatureNormalizesEmail(): void
    {
        $sig1 = UnsubscribeService::generateSignature('test@example.com');
        $sig2 = UnsubscribeService::generateSignature('  TEST@EXAMPLE.COM  ');

        $this->assertEquals($sig1, $sig2);
    }

    public function testGenerateSignatureIsDifferentForDifferentEmails(): void
    {
        $sig1 = UnsubscribeService::generateSignature('user1@example.com');
        $sig2 = UnsubscribeService::generateSignature('user2@example.com');

        $this->assertNotEquals($sig1, $sig2);
    }

    public function testVerifySignatureReturnsTrueForValid(): void
    {
        $email = 'valid@example.com';
        $signature = UnsubscribeService::generateSignature($email);

        $this->assertTrue(UnsubscribeService::verifySignature($email, $signature));
    }

    public function testVerifySignatureReturnsFalseForInvalid(): void
    {
        $email = 'test@example.com';
        $invalidSignature = 'invalid_signature_12345';

        $this->assertFalse(UnsubscribeService::verifySignature($email, $invalidSignature));
    }

    public function testVerifySignatureReturnsFalseForWrongEmail(): void
    {
        $email = 'correct@example.com';
        $signature = UnsubscribeService::generateSignature($email);

        // Try to verify with different email
        $this->assertFalse(UnsubscribeService::verifySignature('wrong@example.com', $signature));
    }

    public function testVerifySignatureWorksWithNormalizedEmail(): void
    {
        $signature = UnsubscribeService::generateSignature('test@example.com');

        // Verify with differently cased/spaced email
        $this->assertTrue(UnsubscribeService::verifySignature('  TEST@EXAMPLE.COM  ', $signature));
    }

    public function testGenerateUnsubscribeUrlContainsRequiredParams(): void
    {
        $email = 'user@example.com';
        $url = UnsubscribeService::generateUnsubscribeUrl($email);

        $this->assertStringContainsString('unsubscribe?', $url);
        $this->assertStringContainsString('email=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function testGenerateResubscribeUrlContainsActionParam(): void
    {
        $email = 'user@example.com';
        $url = UnsubscribeService::generateResubscribeUrl($email);

        $this->assertStringContainsString('unsubscribe?', $url);
        $this->assertStringContainsString('action=resubscribe', $url);
    }

    public function testGenerateOneClickUrlFormat(): void
    {
        $email = 'user@example.com';
        $url = UnsubscribeService::generateOneClickUrl($email);

        $this->assertStringContainsString('unsubscribe/one-click?', $url);
        $this->assertStringContainsString('email=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function testUnsubscribeBlocksEmail(): void
    {
        $email = 'block-me@example.com';

        $this->assertFalse(UnsubscribeService::isUnsubscribed($email));

        UnsubscribeService::unsubscribe($email);

        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));
    }

    public function testResubscribeUnblocksEmail(): void
    {
        $email = 'unblock-me@example.com';

        UnsubscribeService::unsubscribe($email);
        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));

        UnsubscribeService::resubscribe($email);
        $this->assertFalse(UnsubscribeService::isUnsubscribed($email));
    }

    public function testCheckMultipleReturnsCorrectStatus(): void
    {
        UnsubscribeService::unsubscribe('blocked@example.com');

        $result = UnsubscribeService::checkMultiple([
            'blocked@example.com',
            'not-blocked@example.com',
        ]);

        $this->assertTrue($result['blocked@example.com']);
        $this->assertFalse($result['not-blocked@example.com']);
    }

    public function testSecretKeyIsGeneratedAndPersisted(): void
    {
        // First call should generate and store secret
        $sig1 = UnsubscribeService::generateSignature('test@example.com');

        // Verify secret was stored
        $secret = SiteSetting::get('security.unsubscribe_secret');
        $this->assertNotNull($secret);
        $this->assertNotEmpty($secret);

        // Second call should use same secret and produce same signature
        $sig2 = UnsubscribeService::generateSignature('test@example.com');
        $this->assertEquals($sig1, $sig2);
    }
}
