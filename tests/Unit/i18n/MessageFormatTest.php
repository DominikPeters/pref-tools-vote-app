<?php

namespace Tests\Unit\i18n;

use App\i18n\MessageFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessageFormatTest extends TestCase
{
    public static function messages(): iterable
    {
        foreach (json_decode(file_get_contents(__DIR__ . '/../../fixtures/i18n-messages.json'), true) as $case) {
            yield $case['name'] => [$case];
        }
    }

    #[DataProvider('messages')]
    public function test_shared_message_contract(array $case): void
    {
        $this->assertSame($case['expected'], MessageFormat::format($case['pattern'], $case['params'], $case['locale']));
    }

    public function test_message_values_are_plain_scalars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MessageFormat::format('Hello {name}', ['name' => ['unexpected']]);
    }
}
