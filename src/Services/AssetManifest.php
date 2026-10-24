<?php

namespace App\Services;

/** Resolve frontend entry points to Vite's content-hashed build output. */
final class AssetManifest
{
    private ?array $manifest = null;

    public function __construct(private string $projectRoot)
    {
    }

    public function url(string $path, string $basePath = ''): string
    {
        $path = ltrim($path, '/');
        $basePath = rtrim($basePath, '/');

        if (!str_starts_with($path, 'assets/js/') && !str_starts_with($path, 'assets/css/')) {
            return $basePath . '/' . $path;
        }

        if ($this->manifest === null) {
            $manifestPath = $this->projectRoot . '/assets/build/.vite/manifest.json';
            if (!is_file($manifestPath)) {
                throw new \RuntimeException('Frontend assets are not built. Run npm ci && npm run build.');
            }
            $this->manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        }

        if (!isset($this->manifest[$path]['file'])) {
            throw new \RuntimeException("Frontend entry point missing from Vite manifest: {$path}. Run npm run build.");
        }

        return $basePath . '/assets/build/' . $this->manifest[$path]['file'];
    }
}
