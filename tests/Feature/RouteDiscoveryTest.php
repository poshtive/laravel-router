<?php

namespace Tests\Feature;

use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Tests\Support\ArrayLogger;
use Tests\TestCase;

class RouteDiscoveryTest extends TestCase
{
    public function test_it_discovers_routes_with_nested_controllers_and_attributes(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        config()->set('router.method_extends', false);

        $this->discover('RouteDiscovery/Controllers', 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\');

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

        $this->discover('Diagnostics/Controllers', 'Tests\\Fixtures\\Diagnostics\\Controllers\\');

        $this->assertCount(2, $logger->infoMessages);
        $messages = implode("\n", $logger->infoMessages);

        $this->assertStringContainsString('DoNotDiscover', $messages);
        $this->assertStringContainsString('LocalOnly', $messages);
        $this->assertNotEmpty(app(RouteDiscoveryManager::class)->diagnostics());
    }

    public function test_nested_uri_overrides_are_scoped_to_the_current_controller(): void
    {
        config()->set('router.strict', true);

        $this->discover('Conflicts/Controllers', 'Tests\\Fixtures\\Conflicts\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());
        $this->assertSame('conflict', $routes['conflict.index']->uri());
        $this->assertSame('admin/conflict', $routes['admin.conflict.index']->uri());
    }

    public function test_nested_uri_overrides_do_not_report_false_duplicate_signatures(): void
    {
        $logger = new ArrayLogger;

        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $this->discover('Conflicts/Controllers', 'Tests\\Fixtures\\Conflicts\\Controllers\\');

        $this->assertCount(0, $logger->warningMessages);
    }

    public function test_duplicate_routes_are_detected_across_groups_and_registered_once(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        app(RouteDiscoveryManager::class)->discover([
            'first' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
            'second' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

        $this->assertCount(4, $routes);
        $this->assertNotEmpty($logger->warningMessages);
    }

    public function test_strict_duplicate_detection_is_global_and_atomic(): void
    {
        config()->set('router.strict', true);

        try {
            app(RouteDiscoveryManager::class)->discover([
                'first' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
                'second' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
            ]);
            $this->fail('Expected duplicate route detection to throw.');
        } catch (RouteDiscoveryException) {
            $this->assertCount(0, app('router')->getRoutes()->getRoutes());
        }
    }

    public function test_discovery_is_skipped_when_routes_are_cached(): void
    {
        app()->instance('routes.cached', true);

        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
        ]);

        $this->assertCount(0, app('router')->getRoutes()->getRoutes());
    }

    public function test_invalid_parameter_mapping_is_reported_and_skipped_in_non_strict_mode(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);

        app(RouteDiscoveryManager::class)->discover([
            'invalid' => [
                'paths' => [$this->fixturePath('Invalid/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Invalid\\Controllers\\',
            ],
        ]);

        $this->assertCount(0, app('router')->getRoutes()->getRoutes());
        $this->assertNotEmpty($logger->warningMessages);
        $this->assertStringContainsString('item', implode("\n", $logger->warningMessages));
    }

    public function test_invalid_parameter_mapping_throws_atomically_in_strict_mode(): void
    {
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);

        app(RouteDiscoveryManager::class)->discover([
            'invalid' => [
                'paths' => [$this->fixturePath('Invalid/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Invalid\\Controllers\\',
            ],
        ]);
    }

    private function discover(string $path, string $namespace): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath($path)], 'namespace' => $namespace],
        ]);
    }
}
