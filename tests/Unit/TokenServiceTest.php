<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TokenService;

class TokenServiceTest extends TestCase
{
    public function test_generate_creates_hex_string(): void
    {
        $token = TokenService::generate(32);

        $this->assertEquals(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function test_generate_respects_length(): void
    {
        $this->assertEquals(16, strlen(TokenService::generate(16)));
        $this->assertEquals(64, strlen(TokenService::generate(64)));
    }

    public function test_generate_creates_unique_tokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = TokenService::generate(32);
        }

        $unique = array_unique($tokens);
        $this->assertCount(100, $unique, 'Tokens should be unique');
    }

    public function test_public_id_uses_url_safe_characters(): void
    {
        $publicId = TokenService::generatePublicId();

        // Should not contain ambiguous characters (0, O, I, l, 1)
        $this->assertDoesNotMatchRegularExpression('/[0OIl1]/', $publicId);
        $this->assertEquals(8, strlen($publicId)); // Default length
    }

    public function test_admin_token_is_32_chars(): void
    {
        $token = TokenService::generateAdminToken();
        $this->assertEquals(32, strlen($token));
    }

    public function test_voter_token_is_32_chars(): void
    {
        $token = TokenService::generateVoterToken();
        $this->assertEquals(32, strlen($token));
    }

    public function test_access_token_is_16_chars(): void
    {
        $token = TokenService::generateAccessToken();
        $this->assertEquals(16, strlen($token));
    }
}
