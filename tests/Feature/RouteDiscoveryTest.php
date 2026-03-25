<?php

namespace Tests\Feature;

use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\Router as PackageRouter;
use Tests\Support\ArrayLogger;
use Tests\TestCase;

class RouteDiscoveryTest extends TestCase
{
    public function test_it_discovers_routes_with_nested_controllers_and_attributes(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        config()->set('router.method_extends', false);

        PackageRouter::create(basePath: $this->packageBasePath())
            ->discover($this->fixturePath('RouteDiscovery/Controllers'));

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route]);

        $this->assertSame('user', $routes['user.index']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['user.index']->methods());
        $this->assertSame(['auth', 'bindings'], $routes['user.index']->gatherMiddleware());

        $this->assertSame('user/{user}/show', $routes['user.show']->uri());
        $this->assertSame(['user' => '[0-9]+'], $routes['user.show']->wheres);

        $this->assertSame('user/update/{user}/{section}', $routes['user.update']->uri());
        $this->assertSame(['PUT'], $routes['user.update']->methods());
        $this->assertSame(['auth', 'bindings', 'verified'], $routes['user.update']->gatherMiddleware());

        $this->assertSame('user/{user}/profile/{section}/edit', $routes['user.profile.edit']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['user.profile.edit']->methods());
    }

    public function test_it_reports_skipped_routes_when_enabled(): void
    {
        $logger = new ArrayLogger;

        app()->instance('log', $logger);
        config()->set('router.report_skipped_routes', true);

        PackageRouter::create(basePath: $this->packageBasePath())
            ->discover($this->fixturePath('Diagnostics/Controllers'));

        $this->assertCount(2, $logger->infoMessages);
        $this->assertStringContainsString('DoNotDiscover', $logger->infoMessages[0]);
        $this->assertStringContainsString('LocalOnly', $logger->infoMessages[1]);
    }

    public function test_it_throws_for_duplicate_route_signatures_in_strict_mode(): void
    {
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);
        $this->expectExceptionMessage('duplicate route signature [GET conflict]');

        PackageRouter::create(basePath: $this->packageBasePath())
            ->discover($this->fixturePath('Conflicts/Controllers'));
    }

    public function test_it_reports_duplicates_when_strict_mode_is_disabled(): void
    {
        $logger = new ArrayLogger;

        app()->instance('log', $logger);
        config()->set('router.strict', false);

        PackageRouter::create(basePath: $this->packageBasePath())
            ->discover($this->fixturePath('Conflicts/Controllers'));

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringContainsString('duplicate route signature [GET conflict]', $logger->warningMessages[0]);
    }
}
