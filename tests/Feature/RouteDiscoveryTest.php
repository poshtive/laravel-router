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

    public function test_method_uri_override_keeps_nested_child_binding_before_the_method_segment(): void
    {
        $this->discover('NestedBinding/Controllers', 'Tests\\Fixtures\\NestedBinding\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->keyBy(fn ($route) => $route->getName());

        $this->assertSame('user/{user}/profile/{profile}/settings', $routes['user.profile.edit-profile']->uri());
    }

    public function test_class_absolute_uri_bypasses_nested_controller_convention(): void
    {
        $this->discover('Absolute/Controllers', 'Tests\\Fixtures\\Absolute\\Controllers\\');

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'AbsoluteController'));

        $this->assertNotNull($route);
        $this->assertSame('teams/{team}/members/{member}/settings', $route->uri());
    }

    public function test_unbalanced_uri_braces_are_reported_and_skipped_in_non_strict_mode(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'invalid-uri' => [
                'paths' => [$this->fixturePath('InvalidUri/Controllers')],
                'namespace' => 'Tests\\Fixtures\\InvalidUri\\Controllers\\',
            ],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'BrokenController'));

        $this->assertNull($route);
        $this->assertStringContainsString('Invalid URI', implode("\n", $logger->warningMessages));
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
            $route = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

            $this->assertNull($route);
        }
    }

    public function test_discovery_is_skipped_when_routes_are_cached(): void
    {
        app()->instance('routes.cached', true);

        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

        $this->assertNull($route);
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

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\Invalid\\Controllers\\BrokenController'));

        $this->assertNull($route);
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

    public function test_optional_nested_bindings_are_rejected_when_they_are_not_trailing(): void
    {
        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'optional-nested' => [
                'paths' => [$this->fixturePath('OptionalNested/Controllers')],
                'namespace' => 'Tests\\Fixtures\\OptionalNested\\Controllers\\',
            ],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'OptionalNested'));

        $this->assertCount(0, $routes);
        $this->assertStringContainsString('Optional route parameters must be trailing', implode("\n", $manager->diagnostics()));
    }

    public function test_optional_nested_binding_validation_is_atomic_in_strict_mode(): void
    {
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);

        try {
            app(RouteDiscoveryManager::class)->discover([
                'valid' => [
                    'paths' => [$this->fixturePath('Prefix/Controllers')],
                    'namespace' => 'Tests\\Fixtures\\Prefix\\Controllers\\',
                ],
                'optional-nested' => [
                    'paths' => [$this->fixturePath('OptionalNested/Controllers')],
                    'namespace' => 'Tests\\Fixtures\\OptionalNested\\Controllers\\',
                ],
            ]);
        } finally {
            $this->assertNull(app('router')->getRoutes()->getByName('fallback.status'));
        }
    }

    public function test_prefix_convention_registers_unprefixed_methods_with_a_get_fallback(): void
    {
        config()->set('router.convention', 'prefix');

        app(RouteDiscoveryManager::class)->discover([
            'prefix' => [
                'paths' => [$this->fixturePath('Prefix/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Prefix\\Controllers\\',
            ],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'fallback.status');

        $this->assertNotNull($route);
        $this->assertSame('fallback/status', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_unloadable_controller_files_are_reported_in_diagnostics(): void
    {
        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
                'patterns' => ['MissingController.php'],
            ],
        ]);

        $this->assertStringContainsString('could not be loaded', implode("\n", $manager->diagnostics()));
    }

    private function discover(string $path, string $namespace): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath($path)], 'namespace' => $namespace],
        ]);
    }
}
