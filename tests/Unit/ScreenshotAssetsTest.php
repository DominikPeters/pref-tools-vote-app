<?php

namespace Tests\Unit;

use App\i18n\Languages;
use App\Services\ScreenshotAssets;
use PHPUnit\Framework\TestCase;

final class ScreenshotAssetsTest extends TestCase
{
    private const STEMS = [
        'question_single_choice', 'question_approval', 'question_ranking',
        'question_ranking_truncated', 'question_ranking_with_ties', 'question_star',
        'question_grade', 'question_grade_2', 'question_yes_no_abstain',
        'full_poll_container', 'full_results_container',
    ];

    private function pair(string $locale, string $name): array
    {
        $pair = [];
        foreach (['light', 'dark'] as $theme) {
            $fingerprint = str_repeat($theme === 'light' ? 'a' : 'b', 64);
            $pair[$theme] = [
                'path' => "screenshots/localized/{$locale}/{$name}-{$theme}-" . substr($fingerprint, 0, 12) . '.webp',
                'width' => 1200, 'height' => 400, 'fingerprint' => $fingerprint,
            ];
        }
        return $pair;
    }

    public function test_resolves_a_localized_light_and_dark_pair_with_a_subfolder(): void
    {
        $manifest = ['version' => 1, 'locales' => ['fr' => ['question_grade' => $this->pair('fr', 'question_grade')]]];
        $result = ScreenshotAssets::resolve($manifest, 'question_grade', 'fr', '/vote');
        $this->assertSame('fr', $result['locale']);
        $this->assertStringStartsWith('/vote/screenshots/localized/fr/', $result['light']['url']);
        $this->assertStringContainsString('-dark-', $result['dark']['url']);
        $this->assertSame(1200, $result['light']['width']);
    }

    public function test_missing_or_invalid_theme_falls_back_as_a_complete_english_pair(): void
    {
        $english = $this->pair('en', 'question_grade');
        $french = $this->pair('fr', 'question_grade');
        unset($french['dark']);
        $manifest = ['version' => 1, 'locales' => [
            'en' => ['question_grade' => $english], 'fr' => ['question_grade' => $french],
        ]];
        $this->assertSame('en', ScreenshotAssets::resolve($manifest, 'question_grade', 'fr')['locale']);
        $manifest['locales']['fr']['question_grade'] = $this->pair('fr', 'question_grade');
        $manifest['locales']['fr']['question_grade']['dark']['path'] = '../private.webp';
        $this->assertSame('en', ScreenshotAssets::resolve($manifest, 'question_grade', 'fr')['locale']);
        unset($manifest['locales']['en']);
        $this->assertNull(ScreenshotAssets::resolve($manifest, 'question_grade', 'fr'));
    }

    public function test_rejects_paths_that_disagree_with_the_image_name_locale_theme_or_hash(): void
    {
        $manifest = ['version' => 1, 'locales' => ['fr' => ['question_grade' => $this->pair('fr', 'question_grade')]]];
        $manifest['locales']['fr']['question_grade']['light']['path'] = 'screenshots/localized/en/question_grade-light-aaaaaaaaaaaa.webp';
        $this->assertNull(ScreenshotAssets::resolve($manifest, 'question_grade', 'fr'));
    }

    public function test_every_registered_locale_has_all_real_images_with_matching_dimensions_and_hashes(): void
    {
        $path = BASE_PATH . '/screenshots/manifest.json';
        $this->assertFileExists($path, 'Run npm run screenshots:generate after adding a language.');
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['version']);
        foreach (array_keys(Languages::getAll()) as $locale) {
            foreach (self::STEMS as $name) {
                $pair = ScreenshotAssets::resolve($manifest, $name, $locale);
                $this->assertNotNull($pair, "Missing screenshot pair {$locale}/{$name}");
                $this->assertSame($locale, $pair['locale'], "Localized screenshots must not fall back: {$locale}/{$name}");
                foreach (['light', 'dark'] as $theme) {
                    $imagePath = BASE_PATH . '/' . $manifest['locales'][$locale][$name][$theme]['path'];
                    $this->assertFileExists($imagePath);
                    $this->assertSame($pair[$theme]['fingerprint'], hash_file('sha256', $imagePath));
                    $size = getimagesize($imagePath);
                    $this->assertIsArray($size);
                    $this->assertSame(IMAGETYPE_WEBP, $size[2]);
                    $this->assertSame($pair[$theme]['width'], $size[0]);
                    $this->assertSame($pair[$theme]['height'], $size[1]);
                }
            }
        }
    }
}
