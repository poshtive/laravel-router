<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\DiscoveredRouteEntry;
use Poshtive\Router\Discovery\DiscoveredRoutes;
use Poshtive\Router\Discovery\RouteStatus;
use Tests\TestCase;

final class DiscoveredRoutesTest extends TestCase
{
    #[Test]
    public function it_returns_all_entries(): void
    {
        $registry = $this->makeRegistry();
        $this->assertCount(4, $registry->all());
    }

    #[Test]
    public function it_filters_by_group(): void
    {
        $registry = $this->makeRegistry();
        $web = $registry->forGroup('web');

        $this->assertCount(2, $web->all());
        foreach ($web->all() as $entry) {
            $this->assertSame('web', $entry->group);
        }
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $registry = $this->makeRegistry();
        $skipped = $registry->forStatus(RouteStatus::Skipped);

        $this->assertCount(1, $skipped->all());
        $this->assertSame(RouteStatus::Skipped, $skipped->all()[0]->status);
    }

    #[Test]
    public function it_chains_filters(): void
    {
        $registry = $this->makeRegistry();
        $result = $registry->forGroup('web')->forStatus(RouteStatus::Registered);

        $this->assertCount(1, $result->all());
        $this->assertSame('web', $result->all()[0]->group);
        $this->assertSame(RouteStatus::Registered, $result->all()[0]->status);
    }

    #[Test]
    public function it_returns_only_registered_routes(): void
    {
        $registry = $this->makeRegistry();
        $routes = $registry->routes();

        $this->assertCount(2, $routes);
        foreach ($routes as $route) {
            $this->assertSame(RouteStatus::Registered, $route->status);
        }
    }

    #[Test]
    public function it_returns_diagnostics(): void
    {
        $registry = $this->makeRegistry();
        $diags = $registry->diagnostics();

        $this->assertCount(2, $diags);
        $this->assertInstanceOf(Diagnostic::class, $diags[0]);
    }

    #[Test]
    public function it_is_countable(): void
    {
        $registry = $this->makeRegistry();
        $this->assertSame(4, $registry->count());
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $registry = $this->makeRegistry();
        $json = json_encode($registry);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('entries', $decoded);
        $this->assertArrayHasKey('diagnostics', $decoded);
        $this->assertArrayHasKey('total', $decoded);
        $this->assertSame(4, $decoded['total']);
    }

    #[Test]
    public function it_handles_empty_registry(): void
    {
        $registry = new DiscoveredRoutes([], []);
        $this->assertCount(0, $registry->all());
        $this->assertCount(0, $registry->routes());
        $this->assertSame(0, $registry->count());

        $filtered = $registry->forGroup('nonexistent');
        $this->assertCount(0, $filtered->all());
    }

    private function makeRegistry(): DiscoveredRoutes
    {
        $entries = [
            new DiscoveredRouteEntry('id1', 'web', RouteStatus::Registered, ['GET', 'HEAD'], '/user', 'user.index', null, 'App\\UserController', 'index', 'app/UserController.php', 10, ['web'], [], false, false, null, null, null, []),
            new DiscoveredRouteEntry('id2', 'web', RouteStatus::Skipped, [], '/', '', null, 'App\\UserController', 'helper', 'app/UserController.php', 15, [], [], false, false, 'DoNotDiscover', null, null, []),
            new DiscoveredRouteEntry('id3', 'api', RouteStatus::Registered, ['GET', 'HEAD'], '/api/post', 'api.post.index', null, 'App\\Api\\PostController', 'index', 'app/Api/PostController.php', 12, ['api'], [], false, false, null, null, null, []),
            new DiscoveredRouteEntry('id4', 'api', RouteStatus::Invalid, ['GET', 'HEAD'], '/api/invalid', 'api.invalid.index', null, 'App\\Api\\InvalidController', 'index', 'app/Api/InvalidController.php', 8, ['api'], [], false, false, null, 'Invalid URI', null, []),
        ];

        $diagnostics = [
            new Diagnostic('test_1', 'warning', 'web', 'path', 'Test diagnostic 1'),
            new Diagnostic('test_2', 'info', 'api', 'path', 'Test diagnostic 2'),
        ];

        return new DiscoveredRoutes($entries, $diagnostics);
    }
}
