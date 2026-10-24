<?php

namespace Tests\Unit\Services;

use App\Services\AssetManifest;
use PHPUnit\Framework\TestCase;

class AssetManifestTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/vote-assets-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/assets/build/.vite', 0700, true);
        file_put_contents($this->root . '/assets/build/.vite/manifest.json', json_encode([
            'assets/js/builder.js' => ['file' => 'builder-abc123.js'],
            'assets/css/main.css' => ['file' => 'main-def456.css'],
        ]));
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/assets/build/.vite/manifest.json');
        rmdir($this->root . '/assets/build/.vite');
        rmdir($this->root . '/assets/build');
        rmdir($this->root . '/assets');
        rmdir($this->root);
    }

    public function test_resolves_hashed_scripts_and_styles_at_root_and_in_subfolder(): void
    {
        $assets = new AssetManifest($this->root);
        $this->assertSame('/assets/build/builder-abc123.js', $assets->url('assets/js/builder.js'));
        $this->assertSame('/vote/assets/build/builder-abc123.js', $assets->url('/assets/js/builder.js', '/vote/'));
        $this->assertSame('/vote/assets/build/main-def456.css', $assets->url('assets/css/main.css', '/vote'));
        $this->assertSame('/vote/screenshots/home.png', $assets->url('screenshots/home.png', '/vote'));
    }

    public function test_reports_an_entry_point_missing_from_the_build(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Frontend entry point missing');
        (new AssetManifest($this->root))->url('assets/js/missing.js');
    }

    public function test_reports_a_missing_build_with_the_build_command(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('npm ci && npm run build');
        (new AssetManifest($this->root . '/missing'))->url('assets/js/builder.js');
    }
}
