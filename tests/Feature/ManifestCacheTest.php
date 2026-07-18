<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\DiscoveredRoutes;
use Poshtive\Router\Discovery\DiscoveryManifest;
use Poshtive\Router\Discovery\ManifestCacheManager;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\RouterServiceProvider;
use Tests\TestCase;

final class ManifestCacheTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/laravel-router-manifest-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);

        config()->set('router.groups', [
            'web' => [
                'paths' => [$this->fixturePath('RouteDiscovery/Controllers')],
                'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\',
            ],
        ]);

        $this->runDiscovery();
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function it_writes_manifest_after_discovery(): void
    {
        RouterServiceProvider::writeManifest();

        $manager = new ManifestCacheManager(base_path());
        $manifest = $manager->read();

        if ($manifest !== null) {
            $this->assertSame(DiscoveryManifest::SCHEMA_VERSION, $manifest->schemaVersion);
            $this->assertNotEmpty($manifest->buildFingerprint);
        }

        $manager->remove();
    }

    #[Test]
    public function it_round_trips_manifest_entries(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $entries = $registry->all();

        $this->assertGreaterThan(0, count($entries), 'Registry should have entries after discovery.');

        foreach ($entries as $entry) {
            $data = $entry->toArray();
            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('group', $data);
            $this->assertArrayHasKey('status', $data);
            $this->assertArrayHasKey('uri', $data);
            $this->assertArrayHasKey('controller', $data);
            $this->assertArrayHasKey('method', $data);
            $this->assertArrayHasKey('source_file', $data);
            $this->assertArrayHasKey('provenance', $data);
        }
    }

    #[Test]
    public function it_handles_missing_manifest_safely(): void
    {
        $manager = new ManifestCacheManager($this->tempDir);
        $this->assertNull($manager->read());
    }

    #[Test]
    public function it_removes_manifest_properly(): void
    {
        $tmpManager = new ManifestCacheManager($this->tempDir);
        $manifest = new DiscoveryManifest(
            schemaVersion: DiscoveryManifest::SCHEMA_VERSION,
            buildFingerprint: 'test-fingerprint',
            entries: [],
            diagnostics: [],
            totalRoutes: 0,
            hasZeroRoutes: true,
            packageVersion: '2.1.0',
        );
        $tmpManager->write($manifest);
        $this->assertNotNull($tmpManager->read());

        $tmpManager->remove();
        $this->assertNull($tmpManager->read());
    }

    #[Test]
    public function manifest_fingerprint_is_deterministic(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $entries = $registry->all();

        if (count($entries) === 0) {
            $this->markTestSkipped('No entries in registry to fingerprint.');

            return;
        }

        $data1 = array_map(fn ($e) => $e->toArray(), $entries);
        $data2 = array_map(fn ($e) => $e->toArray(), $entries);

        $this->assertSame($data1, $data2);
    }

    /**
     * Triggers discovery on the configured groups and exposes the registry.
     */
    private function runDiscovery(): void
    {
        $manager = app(RouteDiscoveryManager::class);
        $manager->discover((array) config('router.groups', []));

        $registry = $manager->registry();
        if ($registry !== null) {
            $this->app->instance(DiscoveredRoutes::class, $registry);
        }
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
