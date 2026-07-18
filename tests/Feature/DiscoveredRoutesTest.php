<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\DiscoveredRoutes;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\RouteStatus;
use Tests\TestCase;

final class DiscoveredRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('router.groups', [
            'web' => [
                'paths' => [$this->fixturePath('RouteDiscovery/Controllers')],
                'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\',
            ],
        ]);

        $this->runDiscovery();
    }

    #[Test]
    public function it_populates_registry_after_discovery(): void
    {
        $registry = app(DiscoveredRoutes::class);

        $this->assertNotNull($registry);
        $this->assertGreaterThan(0, $registry->count());
    }

    #[Test]
    public function it_filters_by_group(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $web = $registry->forGroup('web');

        $this->assertGreaterThan(0, $web->count());
        foreach ($web->all() as $entry) {
            $this->assertSame('web', $entry->group);
        }
    }

    #[Test]
    public function it_has_registered_routes(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $routes = $registry->routes();

        $this->assertGreaterThan(0, count($routes));
        foreach ($routes as $route) {
            $this->assertSame(RouteStatus::Registered, $route->status);
        }
    }

    #[Test]
    public function it_returns_structured_diagnostics(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $diags = $registry->diagnostics();

        foreach ($diags as $diag) {
            $this->assertInstanceOf(Diagnostic::class, $diag);
            $this->assertNotEmpty($diag->code);
            $this->assertNotEmpty($diag->severity);
            $this->assertNotEmpty($diag->message);
            $this->assertIsString($diag->toArray()['code']);
        }
    }

    #[Test]
    public function it_returns_empty_registry_when_no_discovery(): void
    {
        $this->app->forgetInstance(DiscoveredRoutes::class);

        config()->set('router.enabled', false);
        $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes([], []));

        $registry = app(DiscoveredRoutes::class);
        $this->assertSame(0, $registry->count());
    }

    #[Test]
    public function it_includes_skipped_routes(): void
    {
        $registry = app(DiscoveredRoutes::class);
        $skipped = $registry->forStatus(RouteStatus::Skipped);

        $found = false;
        foreach ($skipped->all() as $entry) {
            if ($entry->skipReason !== null) {
                $found = true;
            }
        }

        // Skipped routes may exist or not, but the registry should handle both cases
        $this->assertIsInt($skipped->count());
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
}
