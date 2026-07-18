<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\DiscoveryManifest;
use Poshtive\Router\Discovery\ManifestCacheManager;
use Tests\TestCase;

final class ManifestCacheManagerTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/laravel-router-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function it_writes_and_reads_manifest(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $manifest = $this->makeManifest();

        $manager->write($manifest);
        $read = $manager->read();

        $this->assertNotNull($read);
        $this->assertSame($manifest->buildFingerprint, $read->buildFingerprint);
        $this->assertSame($manifest->schemaVersion, $read->schemaVersion);
        $this->assertSame($manifest->totalRoutes, $read->totalRoutes);
    }

    #[Test]
    public function it_returns_null_for_missing_file(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $this->assertNull($manager->read());
    }

    #[Test]
    public function it_returns_null_for_corrupt_file(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $dir = $this->tempDir.'/bootstrap/cache';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/laravel-router-manifest.json', 'not-valid-json');

        $this->assertNull($manager->read());
    }

    #[Test]
    public function it_returns_null_for_version_mismatch(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $dir = $this->tempDir.'/bootstrap/cache';
        mkdir($dir, 0755, true);

        $data = [
            'schema_version' => 999,
            'build_fingerprint' => 'abc',
            'entries' => [],
            'diagnostics' => [],
            'total_routes' => 0,
            'has_zero_routes' => true,
            'package_version' => '2.1.0',
        ];
        file_put_contents($dir.'/laravel-router-manifest.json', json_encode($data));

        $this->assertNull($manager->read());
    }

    #[Test]
    public function it_removes_manifest(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $manifest = $this->makeManifest();

        $manager->write($manifest);
        $this->assertNotNull($manager->read());

        $manager->remove();
        $this->assertNull($manager->read());
    }

    #[Test]
    public function it_removes_temp_file_on_cleanup(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $tmpFile = $this->tempDir.'/bootstrap/cache/laravel-router-manifest.json.tmp';
        $dir = $this->tempDir.'/bootstrap/cache';
        mkdir($dir, 0755, true);
        file_put_contents($tmpFile, '{}');

        $manager->remove();

        $this->assertFileDoesNotExist($tmpFile);
    }

    #[Test]
    public function it_has_correct_path(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $expected = $this->tempDir.'/bootstrap/cache/laravel-router-manifest.json';

        $this->assertSame($expected, $manager->path());
    }

    #[Test]
    public function read_returns_null_for_empty_file(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $dir = $this->tempDir.'/bootstrap/cache';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/laravel-router-manifest.json', '');

        $this->assertNull($manager->read());
    }

    #[Test]
    public function read_handles_json_exception(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $dir = $this->tempDir.'/bootstrap/cache';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/laravel-router-manifest.json', '{invalid');

        $this->assertNull($manager->read());
    }

    #[Test]
    public function remove_handles_nonexistent_files(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $manager->remove();

        $this->assertNull($manager->read());
        $this->assertTrue(true);
    }

    #[Test]
    public function write_creates_parent_directory(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $manifest = $this->makeManifest();

        $manager->write($manifest);

        $this->assertDirectoryExists(dirname($manager->path()));
        $this->assertNotNull($manager->read());
    }

    #[Test]
    public function write_throws_when_path_component_is_a_file(): void
    {
        // Create a file where a directory would be needed
        $dir = $this->tempDir.'/bootstrap';
        file_put_contents($dir, 'block');

        $manager = new ManifestCacheManager($this->tempDir);
        $manifest = $this->makeManifest();

        try {
            $this->expectException(\RuntimeException::class);
            $manager->write($manifest);
        } finally {
            @unlink($dir);
        }
    }

    private function makeManifest(): DiscoveryManifest
    {
        return new DiscoveryManifest(
            schemaVersion: DiscoveryManifest::SCHEMA_VERSION,
            buildFingerprint: hash('sha256', 'test'),
            entries: [
                ['id' => 'test', 'uri' => '/'],
            ],
            diagnostics: [],
            totalRoutes: 1,
            hasZeroRoutes: false,
            packageVersion: '2.1.0',
        );
    }

    private function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir((string) $item) : unlink((string) $item);
        }

        rmdir($dir);
    }
}
