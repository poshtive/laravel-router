<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

final class ManifestCacheManager
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    public function write(DiscoveryManifest $manifest): void
    {
        $dir = dirname($this->path());

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmp = $this->path().'.tmp';
        $bytes = @file_put_contents($tmp, $manifest->serialize(), LOCK_EX);

        if ($bytes === false || ! file_exists($tmp)) {
            throw new \RuntimeException('Failed to write manifest temporary file.');
        }

        rename($tmp, $this->path());
    }

    public function read(): ?DiscoveryManifest
    {
        $path = $this->path();

        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        try {
            $manifest = DiscoveryManifest::deserialize($contents);
        } catch (\JsonException|\RuntimeException) {
            return null;
        }

        if ($manifest->schemaVersion !== DiscoveryManifest::SCHEMA_VERSION) {
            return null;
        }

        return $manifest;
    }

    public function remove(): void
    {
        $path = $this->path();

        if (file_exists($path)) {
            unlink($path);
        }

        $tmp = $this->path().'.tmp';

        if (file_exists($tmp)) {
            unlink($tmp);
        }
    }

    public function path(): string
    {
        return $this->basePath.'/bootstrap/cache/laravel-router-manifest.json';
    }
}
