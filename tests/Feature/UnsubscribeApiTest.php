<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\UnsubscribeService;

class UnsubscribeApiTest extends TestCase
{
    public function testUnsubscribeApiSuccess(): void
    {
        $email = 'unsubscribe-api@example.com';
        $signature = UnsubscribeService::generateSignature($email);

        $result = $this->callApi('POST', '/api/unsubscribe', [
            'email' => $email,
            'sig' => $signature,
            'action' => 'unsubscribe',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_unsubscribed']);
        $this->assertStringContainsString('unsubscribed', strtolower($result['message']));

        // Verify it's actually unsubscribed
        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));
    }

    public function testResubscribeApiSuccess(): void
    {
        $email = 'resubscribe-api@example.com';
        $signature = UnsubscribeService::generateSignature($email);

        // First unsubscribe
        UnsubscribeService::unsubscribe($email);
        $this->assertTrue(UnsubscribeService::isUnsubscribed($email));

        // Then resubscribe via API
        $result = $this->callApi('POST', '/api/unsubscribe', [
            'email' => $email,
            'sig' => $signature,
            'action' => 'resubscribe',
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_unsubscribed']);
        $this->assertStringContainsString('resubscribed', strtolower($result['message']));

        // Verify it's actually resubscribed
        $this->assertFalse(UnsubscribeService::isUnsubscribed($email));
    }

    public function testUnsubscribeApiFailsWithMissingEmail(): void
    {
        $result = $this->callApi('POST', '/api/unsubscribe', [
            'sig' => 'some_signature',
            'action' => 'unsubscribe',
        ]);

        $this->assertError($result);
    }

    public function testUnsubscribeApiFailsWithMissingSignature(): void
    {
        $result = $this->callApi('POST', '/api/unsubscribe', [
            'email' => 'test@example.com',
            'action' => 'unsubscribe',
        ]);

        $this->assertError($result);
    }

    public function testUnsubscribeApiFailsWithInvalidSignature(): void
    {
        $result = $this->callApi('POST', '/api/unsubscribe', [
            'email' => 'test@example.com',
            'sig' => 'invalid_signature_12345',
            'action' => 'unsubscribe',
        ]);

        $this->assertError($result);
        $this->assertEquals(403, $result['status']);
    }

    public function testUnsubscribeApiDefaultsToUnsubscribeAction(): void
    {
        $email = 'default-action@example.com';
        $signature = UnsubscribeService::generateSignature($email);

        // Don't specify action - should default to unsubscribe
        $result = $this->callApi('POST', '/api/unsubscribe', [
            'email' => $email,
            'sig' => $signature,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_unsubscribed']);
    }
}
