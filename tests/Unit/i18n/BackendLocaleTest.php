<?php

namespace Tests\Unit\i18n;

use App\Controllers\PollApiController;
use App\i18n\Translator;
use App\i18n\UiLocale;
use App\Models\Poll;
use App\Models\User;
use App\Services\EmailTemplate;
use App\Validation\Validator;
use PHPUnit\Framework\TestCase;
use App\Auth;
use App\Database;

class BackendLocaleTest extends TestCase
{
    public function test_character_limits_count_unicode_characters_instead_of_utf8_bytes(): void
    {
        $validator = new Validator(['name' => 'éééé']);
        $this->assertTrue($validator->validate(['name' => 'max:4']));
        $this->assertFalse($validator->validate(['name' => 'min:8']));
        $controller = new class extends \App\Controllers\ApiController {
            public function inspect(array $data): ?array { return $this->validate($data, ['name' => 'min:8']); }
        };
        $this->assertNotNull($controller->inspect(['name' => 'éééé']));
        $this->assertNull($controller->inspect(['name' => 'éééééééé']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Database::reset();
        Auth::reset();
        $files = glob(MIGRATIONS_PATH . '/*.sql');
        sort($files);
        foreach ($files as $file) Database::getInstance()->runMigration(file_get_contents($file));
        $_SESSION = [];
        UiLocale::initialize('en');
    }

    protected function tearDown(): void
    {
        UiLocale::initialize('en');
        Database::reset();
        Auth::reset();
        parent::tearDown();
    }

    public function test_account_locale_is_persisted_and_invalid_stored_preferences_fall_back(): void
    {
        UiLocale::initialize('de');
        $user = Auth::getInstance()->register('test@example.test', 'password123', 'Test User');
        $this->assertSame('de', User::find($user->id)->getPreferredLocale());

        $user->updatePreferredLocale('fr');
        $this->assertSame('fr', User::find($user->id)->getPreferredLocale());

        Database::getInstance()->update('users', ['preferred_locale' => 'unknown'], 'id = :id', ['id' => $user->id]);
        $this->assertSame('en', User::find($user->id)->getPreferredLocale());
    }

    public function test_voter_api_uses_poll_locale_without_changing_interface_locale(): void
    {
        UiLocale::initialize('fr');
        $poll = Poll::create(['title' => 'German ballot', 'locale' => 'de', 'status' => 'closed']);
        $result = (new PollApiController())->submitResponse(['publicId' => $poll->publicId]);

        $this->assertSame('POLL_NOT_OPEN', $result['code']);
        $this->assertSame(Translator::translateForLocale('de', 'error_poll_is_not_open_for_submissions'), $result['error']);
        $this->assertSame('fr', Translator::getLocale());
    }

    public function test_email_context_uses_recipient_language_and_escapes_user_values(): void
    {
        UiLocale::initialize('fr');
        $poll = new Poll();
        $poll->title = '<script>poll</script>';
        $poll->description = 'Example';
        $body = EmailTemplate::render('invitation', 'de', [
            'poll' => $poll,
            'inviterName' => '<script>name</script>',
            'voteUrl' => 'https://example.test/?a=1&b=2',
            'unsubscribeUrl' => 'https://example.test/unsubscribe',
        ]);

        $this->assertStringContainsString('lang="de" dir="ltr"', $body);
        $this->assertStringContainsString('&lt;script&gt;poll&lt;/script&gt;', $body);
        $this->assertStringContainsString('&lt;script&gt;name&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertSame('fr', Translator::getLocale());
        $this->assertSame('fr', UiLocale::get());
    }

    public function test_validation_translates_field_labels_and_formats_count_in_complete_message(): void
    {
        UiLocale::initialize('de');
        $validator = Validator::make(['password' => 'x'], ['password' => 'min:8']);
        $this->assertSame(Translator::translateForLocale('de', 'validation_min_characters', [
            'field' => Translator::translateForLocale('de', 'validation_field_password'),
            'count' => 8,
        ]), $validator->getError('password'));
    }
}
