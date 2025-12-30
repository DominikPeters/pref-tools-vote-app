<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\EmailDeliverability;

class EmailDeliverabilityTest extends TestCase
{
    public function testFindByEmailReturnsNullForNonExistent(): void
    {
        $result = EmailDeliverability::findByEmail('nonexistent@example.com');
        $this->assertNull($result);
    }

    public function testFindOrCreateCreatesNewRecord(): void
    {
        $email = 'test@example.com';
        $record = EmailDeliverability::findOrCreate($email);

        $this->assertNotNull($record);
        $this->assertEquals($email, $record->email);
        $this->assertNull($record->unsubscribedAt);
    }

    public function testFindOrCreateReturnsExistingRecord(): void
    {
        $email = 'existing@example.com';

        // Create first
        $first = EmailDeliverability::findOrCreate($email);
        $first->updateData(['test' => 'value']);

        // Find again
        $second = EmailDeliverability::findOrCreate($email);

        // Should have the same data
        $this->assertEquals($first->email, $second->email);
        $this->assertEquals(['test' => 'value'], $second->data);
    }

    public function testFindOrCreateNormalizesEmail(): void
    {
        $record = EmailDeliverability::findOrCreate('  TEST@EXAMPLE.COM  ');
        $this->assertEquals('test@example.com', $record->email);
    }

    public function testUnsubscribeSetsTimestamp(): void
    {
        $email = 'unsubscribe@example.com';
        $record = EmailDeliverability::unsubscribe($email);

        $this->assertNotNull($record->unsubscribedAt);
        $this->assertTrue(EmailDeliverability::isUnsubscribed($email));
    }

    public function testUnsubscribeIsIdempotent(): void
    {
        $email = 'idempotent@example.com';

        // Unsubscribe twice
        $first = EmailDeliverability::unsubscribe($email);
        $firstTime = $first->unsubscribedAt->getTimestamp();

        // Small delay to ensure different timestamp
        usleep(10000);

        $second = EmailDeliverability::unsubscribe($email);
        $secondTime = $second->unsubscribedAt->getTimestamp();

        // Should keep the original timestamp
        $this->assertEquals($firstTime, $secondTime);
    }

    public function testResubscribeClearsTimestamp(): void
    {
        $email = 'resubscribe@example.com';

        // Unsubscribe first
        EmailDeliverability::unsubscribe($email);
        $this->assertTrue(EmailDeliverability::isUnsubscribed($email));

        // Resubscribe
        $record = EmailDeliverability::resubscribe($email);
        $this->assertNull($record->unsubscribedAt);
        $this->assertFalse(EmailDeliverability::isUnsubscribed($email));
    }

    public function testIsUnsubscribedReturnsFalseForNonExistent(): void
    {
        $this->assertFalse(EmailDeliverability::isUnsubscribed('never-existed@example.com'));
    }

    public function testCheckMultipleReturnsCorrectStatus(): void
    {
        // Set up some emails
        EmailDeliverability::unsubscribe('blocked1@example.com');
        EmailDeliverability::unsubscribe('blocked2@example.com');
        EmailDeliverability::findOrCreate('allowed@example.com'); // Not unsubscribed

        $emails = [
            'blocked1@example.com',
            'blocked2@example.com',
            'allowed@example.com',
            'unknown@example.com',
        ];

        $result = EmailDeliverability::checkMultiple($emails);

        $this->assertTrue($result['blocked1@example.com']);
        $this->assertTrue($result['blocked2@example.com']);
        $this->assertFalse($result['allowed@example.com']);
        $this->assertFalse($result['unknown@example.com']);
    }

    public function testCheckMultipleWithEmptyArray(): void
    {
        $result = EmailDeliverability::checkMultiple([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUpdateData(): void
    {
        $email = 'data@example.com';
        $record = EmailDeliverability::findOrCreate($email);

        $record->updateData(['key1' => 'value1']);
        $this->assertEquals(['key1' => 'value1'], $record->data);

        $record->updateData(['key2' => 'value2']);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $record->data);

        // Verify it persists
        $reloaded = EmailDeliverability::findByEmail($email);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $reloaded->data);
    }

    public function testToArray(): void
    {
        $email = 'toarray@example.com';
        EmailDeliverability::unsubscribe($email);

        $record = EmailDeliverability::findByEmail($email);
        $array = $record->toArray();

        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('unsubscribed_at', $array);
        $this->assertArrayHasKey('is_unsubscribed', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals($email, $array['email']);
        $this->assertTrue($array['is_unsubscribed']);
    }
}
