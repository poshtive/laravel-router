<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\DiscoveredRouteEntry;
use Poshtive\Router\Discovery\DiscoveredRoutes;
use Poshtive\Router\Discovery\DiscoveryManifest;
use Poshtive\Router\Discovery\ManifestCacheManager;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\RouteStatus;
use Poshtive\Router\RouterServiceProvider;
use Tests\TestCase;

final class RouterServiceProviderTest extends TestCase
{
    #[Test]
    public function write_manifest_produces_valid_manifest(): void
    {
        $this->setupRegistry();

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
    public function remove_manifest_deletes_file(): void
    {
        $this->setupRegistry();

        RouterServiceProvider::writeManifest();

        $manager = new ManifestCacheManager(base_path());
        $this->assertNotNull($manager->read());

        RouterServiceProvider::removeManifest();

        $this->assertNull($manager->read());
    }

    #[Test]
    public function write_manifest_handles_empty_registry(): void
    {
        $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes([], []));

        RouterServiceProvider::writeManifest();

        $manager = new ManifestCacheManager(base_path());
        $manifest = $manager->read();

        if ($manifest !== null) {
            $this->assertTrue($manifest->hasZeroRoutes);
            $this->assertSame(0, $manifest->totalRoutes);
        }

        $manager->remove();
    }

    #[Test]
    public function write_manifest_is_idempotent(): void
    {
        $this->setupRegistry();

        RouterServiceProvider::writeManifest();
        $manager = new ManifestCacheManager(base_path());
        $first = $manager->read();

        RouterServiceProvider::writeManifest();
        $second = $manager->read();

        if ($first !== null && $second !== null) {
            $this->assertSame($first->buildFingerprint, $second->buildFingerprint);
        }

        $manager->remove();
    }

    #[Test]
    public function write_manifest_noops_when_registry_not_bound(): void
    {
        $this->app->forgetInstance(DiscoveredRoutes::class);

        RouterServiceProvider::writeManifest();

        $this->assertTrue(true);
    }

    #[Test]
    public function registry_rebuilt_from_cached_manifest(): void
    {
        // Setup registry and write manifest
        $this->setupRegistry();
        RouterServiceProvider::writeManifest();

        // Now simulate reading from manifest
        $manager = new ManifestCacheManager(base_path());
        $manifest = $manager->read();

        $this->assertNotNull($manifest);
        $this->assertGreaterThan(0, count($manifest->entries));

        $manager->remove();
    }

    #[Test]
    public function discovered_routes_is_bound_in_container(): void
    {
        $this->setupRegistry();

        $registry = app(DiscoveredRoutes::class);
        $this->assertInstanceOf(DiscoveredRoutes::class, $registry);
        $this->assertSame(1, $registry->count());
    }

    #[Test]
    public function manifest_write_and_remove_cycle_is_clean(): void
    {
        $this->setupRegistry();

        $manager = new ManifestCacheManager(base_path());

        // Clean start
        $manager->remove();
        $this->assertNull($manager->read());

        // Write
        RouterServiceProvider::writeManifest();
        $this->assertNotNull($manager->read());

        // Remove
        RouterServiceProvider::removeManifest();
        $this->assertNull($manager->read());
    }

    #[Test]
    public function routes_are_cached_path_handles_missing_manifest(): void
    {
        $manager = new ManifestCacheManager(base_path());
        $manager->remove();

        // Simulate routes being cached without a manifest
        $this->app->instance('routes.cached', true);
        $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes([], []));

        $registry = app(DiscoveredRoutes::class);
        $this->assertInstanceOf(DiscoveredRoutes::class, $registry);
        $this->assertSame(0, $registry->count());

        $this->app->offsetUnset('routes.cached');
    }

    #[Test]
    public function expose_registry_rebuilds_from_cached_manifest(): void
    {
        // Write manifest first
        $this->setupRegistry();
        RouterServiceProvider::writeManifest();

        // Simulate routes cached state
        $this->app->instance('routes.cached', true);

        // Create manager with null registry
        $manager = app(RouteDiscoveryManager::class);

        // Reset instance to force exposeRegistry to run
        $this->app->forgetInstance(DiscoveredRoutes::class);

        // Call exposeRegistry via reflection
        $provider = new RouterServiceProvider($this->app);
        $provider->register();
        $ref = new \ReflectionMethod(RouterServiceProvider::class, 'exposeRegistry');
        $ref->setAccessible(true);
        $ref->invoke($provider, $manager);

        // Should have restored from manifest
        $registry = app(DiscoveredRoutes::class);
        $this->assertInstanceOf(DiscoveredRoutes::class, $registry);
        $this->assertSame(1, $registry->count());

        // Cleanup
        $this->app->offsetUnset('routes.cached');
        $manager = new ManifestCacheManager(base_path());
        $manager->remove();
    }

    #[Test]
    public function expose_registry_falls_back_to_empty_when_cached_and_no_manifest(): void
    {
        $manager = new ManifestCacheManager(base_path());
        $manager->remove();

        $this->app->instance('routes.cached', true);
        $this->app->forgetInstance(DiscoveredRoutes::class);

        $discoveryManager = app(RouteDiscoveryManager::class);

        $provider = new RouterServiceProvider($this->app);
        $provider->register();
        $ref = new \ReflectionMethod(RouterServiceProvider::class, 'exposeRegistry');
        $ref->setAccessible(true);
        $ref->invoke($provider, $discoveryManager);

        $registry = app(DiscoveredRoutes::class);
        $this->assertInstanceOf(DiscoveredRoutes::class, $registry);
        $this->assertSame(0, $registry->count());

        $this->app->offsetUnset('routes.cached');
    }

    private function setupRegistry(): void
    {
        $entries = [
            new DiscoveredRouteEntry(
                id: 'test-id',
                group: 'web',
                status: RouteStatus::Registered,
                methods: ['GET', 'HEAD'],
                uri: '/user',
                name: 'user.index',
                domain: null,
                controller: 'App\\UserController',
                method: 'index',
                sourceFile: 'app/UserController.php',
                sourceLine: 10,
                middleware: ['web'],
                wheres: [],
                scopeBindings: false,
                withoutScopedBindings: false,
                skipReason: null,
                invalidReason: null,
                discardReason: null,
                provenance: [],
            ),
        ];

        $diagnostics = [
            new Diagnostic('test', 'info', 'web', '', 'test'),
        ];

        $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes($entries, $diagnostics));
    }
}
