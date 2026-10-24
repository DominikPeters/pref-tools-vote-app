<?php

namespace Tests\Unit\i18n;

use App\Auth;
use App\Config;
use App\Controllers\LanguageController;
use App\i18n\Languages;
use App\i18n\Translator;
use App\i18n\UiLocale;
use App\Models\User;
use App\Router;
use App\Services\MarketingLocale;
use App\Services\TokenService;
use PHPUnit\Framework\TestCase;

class MarketingLocaleTest extends TestCase
{
    private array $originalConfig;
    private array $originalServer;
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalConfig = Config::all();
        $this->originalServer = $_SERVER;
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        Auth::reset();
        $this->configure('https://configured.test/vote');
        UiLocale::initialize('en');
    }

    protected function tearDown(): void
    {
        $this->loadConfig($this->originalConfig);
        $_SERVER = $this->originalServer;
        $_SESSION = $this->originalSession;
        Auth::reset();
        UiLocale::initialize('en');
    }

    private function loadConfig(array $config): void
    {
        $file = tempnam(sys_get_temp_dir(), 'marketing-config-');
        file_put_contents($file, '<?php return ' . var_export($config, true) . ';');
        try {
            Config::load($file);
        } finally {
            unlink($file);
        }
    }

    private function configure(string $url, int $idLength = 8): void
    {
        $config = $this->originalConfig;
        $config['app']['url'] = $url;
        $config['security']['public_id_length'] = $idLength;
        $this->loadConfig($config);
    }

    public function test_paths_and_context_support_root_and_subfolder_installations(): void
    {
        $this->assertSame('/en/', MarketingLocale::path('home', 'en', ''));
        $this->assertSame('/vote/fr/about', MarketingLocale::path('about', 'fr', '/vote/'));
        $this->assertSame(['page' => 'home', 'locale' => 'fr'], MarketingLocale::context('/vote/fr/?ref=1', '/vote'));
        $this->assertSame(['page' => 'about', 'locale' => 'fr'], MarketingLocale::context('/fr/about', ''));
        $this->assertSame(['page' => 'home', 'locale' => null], MarketingLocale::context('/vote/', '/vote'));
        $this->assertSame(['page' => 'about', 'locale' => null], MarketingLocale::context('/vote/about', '/vote'));
        UiLocale::initialize('de');
        $this->assertSame('/vote/de/', MarketingLocale::path());
    }

    public function test_url_locale_overrides_preferences_for_this_request_and_sets_cookie_without_changing_account(): void
    {
        $user = new User();
        $user->id = 123;
        $user->name = 'Example';
        $user->preferredLocale = 'de';
        Auth::getInstance()->login($user);
        UiLocale::initialize('de', 'es');
        $_SERVER['HTTPS'] = 'on';
        $cookie = null;
        MarketingLocale::activate('fr', static function (...$arguments) use (&$cookie): void { $cookie = $arguments; });
        $this->assertSame('fr', UiLocale::get());
        $this->assertSame('fr', Translator::getLocale());
        $this->assertSame('de', Auth::getInstance()->user()->preferredLocale);
        $this->assertSame(['ui_locale', 'fr'], array_slice($cookie, 0, 2));
        $this->assertSame('/vote/', $cookie[2]['path']);
        $this->assertTrue($cookie[2]['httponly']);
        $this->assertTrue($cookie[2]['secure']);
        $this->assertSame('Lax', $cookie[2]['samesite']);
    }

    public function test_only_registered_exact_codes_are_marketing_routes_and_poll_paths_are_preserved(): void
    {
        foreach (['/vote/xx/', '/vote/FR/', '/vote/fr-CA/', '/vote/fr/admin/token', '/vote/fr/results', '/vote/Ab12Cd34', '/vote/api/polls', '/votes/fr/'] as $path) {
            $this->assertNull(MarketingLocale::context($path, '/vote'), $path);
        }
        $router = new Router();
        $router->setBasePath('/vote');
        foreach (MarketingLocale::routes() as $path => $page) {
            $router->get($path, static fn() => ['kind' => 'marketing', 'page' => $page]);
        }
        $router->get('/:publicId/results', static fn($params) => ['kind' => 'results', 'id' => $params['publicId']]);
        $router->get('/:publicId', static fn($params) => ['kind' => 'poll', 'id' => $params['publicId']]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        foreach (['/vote/fr/' => 'marketing', '/vote/en/about' => 'marketing', '/vote/xx' => 'poll', '/vote/fr-CA' => 'poll', '/vote/fr/results' => 'results', '/vote/Ab12Cd34' => 'poll'] as $path => $kind) {
            $_SERVER['REQUEST_URI'] = $path;
            $this->assertSame($kind, $router->dispatch()['kind'], $path);
        }
    }

    public function test_canonical_and_reciprocal_hreflang_use_configured_origin_including_english_and_x_default(): void
    {
        $_SERVER['HTTP_HOST'] = 'attacker.invalid';
        $metadata = MarketingLocale::metadata('about', 'fr');
        $this->assertSame('https://configured.test/vote/fr/about', $metadata['canonical']);
        $this->assertSame('https://configured.test/vote/about', $metadata['alternates']['x-default']);
        $this->assertSame('https://configured.test/vote/en/about', $metadata['alternates']['en']);
        $this->assertSame('fr_FR', $metadata['og_locale']);
        $this->assertCount(count(Languages::getAll()) + 1, $metadata['alternates']);
        foreach (array_keys(Languages::getAll()) as $code) {
            $other = MarketingLocale::metadata('about', $code);
            $this->assertSame($metadata['alternates'], $other['alternates']);
            $this->assertSame($metadata['alternates'][$code], $other['canonical']);
        }
        $this->configure('https://root.test');
        $this->assertSame('https://root.test/en/', MarketingLocale::metadata('home', 'en')['canonical']);
        $this->assertSame('https://root.test/', MarketingLocale::metadata('home', 'en')['alternates']['x-default']);
    }

    public function test_unprefixed_pages_keep_negotiated_locale_and_canonicalize_to_its_explicit_url(): void
    {
        UiLocale::initialize(null, 'fr-FR, en;q=0.5');
        $this->assertNull(MarketingLocale::context('/vote/', '/vote')['locale']);
        $this->assertSame('https://configured.test/vote/fr/', MarketingLocale::metadata('home')['canonical']);
        $this->assertSame('fr', UiLocale::get());
    }

    public function test_layout_escapes_seo_attributes_and_exposes_visible_equivalent_language_links(): void
    {
        $this->configure('https://configured.test/vote/"quoted');
        $_SERVER['HTTP_HOST'] = 'untrusted-host.invalid';
        $_SERVER['REQUEST_URI'] = '/vote/fr/about';
        UiLocale::initialize('fr');
        $html = render('layout', [
            'marketingPage' => 'about', 'user' => null, 'content' => '<h1>Fixture</h1>',
            'title' => 'A "title" <unsafe>', 'metaDescription' => 'A "description" & <unsafe>',
        ]);
        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertStringContainsString('rel="canonical" href="https://configured.test/vote/&quot;quoted/fr/about"', $head);
        $this->assertStringContainsString('content="A &quot;description&quot; &amp; &lt;unsafe&gt;"', $head);
        $this->assertStringContainsString('property="og:locale" content="fr_FR"', $head);
        $this->assertStringNotContainsString('untrusted-host.invalid', $head);
        $this->assertStringContainsString('class="marketing-languages"', $html);
        foreach (Languages::getAll() as $code => $name) {
            $this->assertStringContainsString('hreflang="' . $code . '" lang="' . $code . '"', $html);
        }
    }

    public function test_picker_maps_only_marketing_pages_and_keeps_query_and_fragment(): void
    {
        $this->assertSame('/vote/fr/about?ref=1#intro', LanguageController::returnPathForLocale('/vote/de/about?ref=1#intro', 'fr', '/vote'));
        $this->assertSame('/vote/fr/about', LanguageController::returnPathForLocale('/vote/about', 'fr', '/vote'));
        $this->assertSame('/en/', LanguageController::returnPathForLocale('/', 'en'));
        foreach (['/vote/create?x=1', '/vote/Ab12Cd34?token=test', '/vote/fr/admin/token/edit', '/vote/api/polls'] as $path) {
            $this->assertSame($path, LanguageController::returnPathForLocale($path, 'fr', '/vote'));
        }
        foreach (['https://evil.test', '//evil.test', '/outside', '/vote/%2e%2e/outside', '/vote/%5cevil', '/vote/%0d%0aLocation:evil', []] as $path) {
            $this->assertSame('/vote/fr/', LanguageController::returnPathForLocale($path, 'fr', '/vote'));
        }
        $this->assertSame('/fr/', LanguageController::returnPathForLocale('/%2f%2fevil.test', 'fr'));
    }

    public function test_short_public_ids_reserve_marketing_and_other_literal_route_names(): void
    {
        foreach (array_keys(Languages::getAll()) as $code) $this->assertTrue(MarketingLocale::isReservedPublicId($code));
        foreach (['create', 'demo', 'api', 'about'] as $id) $this->assertTrue(MarketingLocale::isReservedPublicId($id));
        $this->assertFalse(MarketingLocale::isReservedPublicId('Ab12Cd34'));
        $this->assertFalse(MarketingLocale::isReservedPublicId('xx'));
        $this->configure('https://configured.test/vote', 2);
        for ($index = 0; $index < 1000; $index++) {
            $id = TokenService::generatePublicId();
            $this->assertSame(2, strlen($id));
            $this->assertFalse(MarketingLocale::isReservedPublicId($id));
        }
        $this->configure('https://configured.test/vote', 8);
        $this->assertSame(8, strlen(TokenService::generatePublicId()));
    }
}
